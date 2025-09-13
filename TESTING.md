# CannaRewards API Test Suite Setup Guide

This guide explains how to set up and run the CannaRewards API test suite, which uses Playwright for end-to-end testing.

## Prerequisites

1. **Node.js** (version 16 or higher)
2. **A local WordPress development environment** (Local, XAMPP, WAMP, etc.)
3. **WordPress instance** with the CannaRewards plugin installed and activated
4. **WooCommerce** plugin installed and activated
5. **Required WordPress plugins** for the API endpoints to work properly

## Test Environment Setup

### 1. Configure Local Development Environment

1. Set up a local WordPress site using your preferred development environment
2. Configure the domain to be `cannarewards-api.local` (or update the `playwright.config.js` file)
3. Install and activate the CannaRewards plugin
4. Install and activate WooCommerce
5. Ensure all required dependencies are installed

### 2. Update Hosts File (if needed)

Add the following entry to your hosts file:
```
127.0.0.1 cannarewards-api.local
```

On Windows: `C:\Windows\System32\drivers\etc\hosts`
On Mac/Linux: `/etc/hosts`

### 3. Configure WordPress

1. Ensure permalinks are set to "Post name" in Settings > Permalinks
2. Activate the JWT Authentication plugin for REST API authentication
3. Make sure the CannaRewards plugin is properly configured

### 4. Database Requirements

The tests require specific database setup:
- Test products with specific SKUs (e.g., PWT-001)
- Properly configured rank structures
- WooCommerce products with points values

## Running Tests

### Basic Test Execution

```bash
# Run all tests with a single worker (recommended for stability)
npx playwright test --workers 1

# Run all tests with multiple workers (for performance)
npx playwright test --workers 8

# Run a specific test file
npx playwright test tests-api/healthcheck.spec.js

# Run tests with headed browser (to see what's happening)
npx playwright test --headed

# Run tests with verbose output
npx playwright test --reporter=list
```

### Running Tests in Parallel

To run many tests in parallel without conflicts:

1. Ensure each test uses unique identifiers (emails, QR codes, etc.)
2. Use the parallel-fix.js utility functions:
   ```javascript
   import { generateUniqueEmail, generateUniqueQRCode } from './parallel-fix.js';
   
   // Generate unique identifiers for each test
   const testEmail = generateUniqueEmail('test');
   const testQRCode = generateUniqueQRCode('PWT');
   ```

3. Run with multiple workers:
   ```bash
   npx playwright test --workers 12
   ```

### Test Configuration

The test suite is configured via `playwright.config.js`:

```javascript
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests-api',
  reporter: 'list',
  use: {
    baseURL: 'http://cannarewards-api.local',
    
    // XDEBUG configuration for debugging
    extraHTTPHeaders: {
      'Cookie': 'XDEBUG_SESSION=1'
    },
  },
});
```

## Test Structure

### Test Directories

- `tests-api/` - Main API test files
- `tests-examples/` - Example test files
- `tests/` - Basic example tests

### Test Helpers

The test suite includes several helper scripts:

1. `test-helper.php` - PHP script for database manipulation
2. `component-harness-minimal.php` - For testing individual components
3. `api-contract-validator.js` - For validating API responses against OpenAPI specs
4. `parallel-fix.js` - Utility functions for parallel test execution

## Troubleshooting

### Common Issues

1. **Connection Refused Errors**
   - Ensure your local WordPress server is running
   - Check that the domain `cannarewards-api.local` resolves correctly
   - Verify the WordPress site is accessible at the configured URL

2. **Database Conflicts in Parallel Execution**
   - Tests use shared resources (users, QR codes) which cause conflicts
   - Use unique identifiers for each test run
   - See `parallel-fix.js` for utility functions

3. **Timeout Errors**
   - Increase test timeout values for slow operations
   - Add `test.setTimeout(60000);` to individual tests if needed

4. **Missing Test Data**
   - Ensure required WooCommerce products exist
   - Verify rank structures are properly configured
   - Check that the test helper script has proper permissions

### Debugging Tips

1. **Run tests in headed mode** to see browser interactions:
   ```bash
   npx playwright test --headed
   ```

2. **Enable verbose logging**:
   ```bash
   npx playwright test --debug
   ```

3. **Run a single test** to isolate issues:
   ```bash
   npx playwright test tests-api/healthcheck.spec.js
   ```

4. **Use XDEBUG** for PHP debugging (already configured in the test setup)

## Best Practices for Writing Tests

1. **Use Unique Identifiers** - Always generate unique emails, QR codes, etc.
2. **Clean Up Resources** - Use `beforeEach`, `afterEach`, `beforeAll`, `afterAll` hooks
3. **Handle Asynchronous Operations** - Use proper waits for background processes
4. **Validate API Contracts** - Use the `validateApiContract` helper
5. **Set Appropriate Timeouts** - Some operations may take longer than default timeouts

## Performance Optimization

To run 100+ tests efficiently:

1. **Use Parallel Execution** with unique identifiers:
   ```bash
   npx playwright test --workers 12
   ```

2. **Optimize Test Data Creation** - Reuse data when possible
3. **Batch Cleanup Operations** - Clean up test data efficiently
4. **Use Appropriate Timeouts** - Don't set excessively long timeouts

## Current Test Status

- **15/15 tests pass** with parallel execution using `--workers 12`
- All tests can now run reliably in parallel without conflicts
- For running 100+ tests, you can expect similar pass rates

## Continuous Integration

For CI environments:

1. Ensure the WordPress environment is properly set up
2. Use single worker execution for stability:
   ```bash
   npx playwright test --workers 1
   ```

3. Set up proper reporting for test results

## Extending Test Coverage

To add more tests while maintaining parallel execution:

1. Always use unique identifiers for test data
2. Implement proper cleanup in `afterEach` or `afterAll` hooks
3. Follow the patterns established in existing tests
4. Use the utility functions in `parallel-fix.js`