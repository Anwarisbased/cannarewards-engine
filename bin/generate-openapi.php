#!/usr/bin/env php
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define the path to your API controllers and DTOs
$source_paths = [
    dirname(__DIR__) . '/includes/CannaRewards/Api',
    dirname(__DIR__) . '/includes/CannaRewards/DTO',
];

// Define the output path for the generated spec
$output_file = dirname(__DIR__) . '/docs/openapi spec/openapi.yaml';

// Generate the OpenAPI object
$openapi = \OpenApi\Generator::scan($source_paths);

// Write the YAML file
file_put_contents($output_file, $openapi->toYaml());

echo "✅ OpenAPI specification generated successfully at:\n   {$output_file}\n";