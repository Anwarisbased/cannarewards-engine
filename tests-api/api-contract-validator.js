// File: tests-api/api-contract-validator.js (NEW)
import Ajv from 'ajv';
import addFormats from 'ajv-formats';
import yaml from 'js-yaml';
import fs from 'fs';
import path from 'path';

// Load and parse the OpenAPI spec ONCE at startup.
const specPath = path.resolve(__dirname, '../docs/openapi spec/notayaml.md');
const spec = yaml.load(fs.readFileSync(specPath, 'utf8'));

// The validator instance. { strict: false } tells it to ignore non-standard
// keywords like "example" which are common in OpenAPI specs but not part of JSON Schema.
const ajv = new Ajv({ strict: false, allErrors: true });
addFormats(ajv); // For formats like 'email', 'uri', etc.

/**
 * A helper to resolve local $ref pointers in a schema against the main OpenAPI spec.
 * This is necessary for validating complex objects defined in the 'components' section.
 * @param {object} schema - The schema object that may contain $refs.
 * @param {object} openApiSpec - The entire parsed OpenAPI specification.
 * @returns {object} A schema with all $refs resolved.
 */
function resolveRefs(schema, openApiSpec) {
    if (!schema || typeof schema !== 'object') {
        return schema;
    }

    if (schema.$ref) {
        const refPath = schema.$ref.replace('#/components/', '').split('/');
        let resolved = openApiSpec.components;
        refPath.forEach(p => { resolved = resolved[p]; });
        return resolveRefs(resolved, openApiSpec); // Recursively resolve refs
    }

    const newSchema = Array.isArray(schema) ? [] : {};
    for (const key in schema) {
        newSchema[key] = resolveRefs(schema[key], openApiSpec);
    }
    return newSchema;
}

/**
 * Validates an API response against the OpenAPI specification.
 * @param {import('@playwright/test').APIResponse} response - The response object from a Playwright request.
 * @param {string} endpointPath - The OpenAPI path template (e.g., '/actions/redeem').
 * @param {string} method - The HTTP method in lowercase (e.g., 'post').
 */
export async function validateApiContract(response, endpointPath, method) {
    const responseBody = await response.json();
    const statusCode = response.status().toString();

    const pathSpec = spec.paths[endpointPath];
    if (!pathSpec) {
        throw new Error(`[API Contract] Path "${endpointPath}" not found in OpenAPI spec.`);
    }

    const methodSpec = pathSpec[method.toLowerCase()];
    if (!methodSpec) {
        throw new Error(`[API Contract] Method "${method}" not found for path "${endpointPath}" in OpenAPI spec.`);
    }

    const responseSpec = methodSpec.responses[statusCode];
    if (!responseSpec) {
        throw new Error(`[API Contract] Response for status code "${statusCode}" not found for "${method} ${endpointPath}" in spec.`);
    }

    const schema = responseSpec.content?.['application/json']?.schema;
    if (!schema) {
        // If the spec defines no response body for this status code, and we didn't get one, we pass.
        if (responseBody === null || Object.keys(responseBody).length === 0) {
            return true;
        }
        throw new Error(`[API Contract] No application/json schema found for status ${statusCode} at "${method} ${endpointPath}", but a response body was received.`);
    }

    const resolvedSchema = resolveRefs(schema, spec);
    const validate = ajv.compile(resolvedSchema);
    const valid = validate(responseBody);

    if (!valid) {
        const errorDetails = JSON.stringify(validate.errors, null, 2);
        throw new Error(`[API Contract] Validation FAILED for "${method} ${endpointPath}" [${statusCode}]:\n${errorDetails}\nReceived Body:\n${JSON.stringify(responseBody, null, 2)}`);
    }

    return true; // Assertion passed
}