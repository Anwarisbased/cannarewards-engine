# CannaRewards Engine

## 🚀 Overview

This is the headless WordPress backend for the CannaRewards D2C Intelligence Platform. It is a custom plugin that provides all necessary API endpoints, a service-oriented business logic layer, and an administrative interface for managing the platform.

## 📋 Prerequisites

- A WordPress installation (developed with LocalWP).
- WooCommerce plugin installed and active.
- PHP >= 7.4
- Composer for PHP dependency management.
- Node.js >= 18 (for development tooling like Husky).

## ⚙️ Local Development Setup

1.  **Clone the repository:**
    Clone this project directly into your WordPress site's `/wp-content/plugins/` directory.
    ```bash
    git clone [your-repo-url] cannarewards-engine
    ```

2.  **Install PHP Dependencies:**
    Navigate into the plugin directory and run Composer.
    ```bash
    cd cannarewards-engine
    composer install
    ```

3.  **Install Node.js Dev Dependencies:**
    This is required for the pre-commit hooks to work.
    ```bash
    npm install
    ```

4.  **Activate the Plugin:**
    Log in to your WordPress admin dashboard, go to "Plugins", and activate the "CannaRewards Engine" plugin.

5.  **Run the Database Migration:**
    Deactivate and then Reactivate the plugin one time. This will trigger the `activate()` method in `class-canna-db.php` and create the necessary custom database tables.

## 🏗️ Architecture

This project follows a modern service-oriented architecture with several advanced patterns:

### Core Patterns
- **Command Pattern**: Business operations are encapsulated in Commands and handled by dedicated CommandHandlers
- **Event-Driven Architecture**: Services communicate through domain events for loose coupling
- **Dependency Injection**: All dependencies are managed through a DI container
- **Value Objects**: Domain concepts are represented as immutable value objects
- **DTOs**: Data Transfer Objects for structured data exchange

### Directory Structure
-   **`/commands`**: Contains Command DTOs and their handlers
-   **`/services`**: Contains all core business logic services
-   **`/domain`**: Contains Value Objects and domain-specific classes
-   **`/dto`**: Contains Data Transfer Objects
-   **`/repositories`**: Contains data access logic
-   **`/includes/api`**: Contains the lean REST API controllers
-   **`/admin`**: Contains the classes for building the WordPress admin UI
-   **`/tests-api`**: Contains the comprehensive Playwright test suite

### Data Contracts
- The definitive API contract (`openapi.yaml`) and Data Taxonomy are maintained in the documentation
- Formal schemas ensure data consistency across the system

## 🧪 Testing

The project includes a comprehensive test suite that can run 35+ tests in parallel with 12+ workers reliably.

### Running Tests
```bash
# Run all tests with optimal parallelization
npx playwright test --workers=12

# Run tests with moderate parallelization for stability
npx playwright test --workers=6

# Run tests with single worker for maximum stability
npx playwright test --workers=1

# Run specific test files
npx playwright test tests-api/01-user-lifecycle.spec.js
```

### Test Architecture
- **Integration Test Runner**: Secure PHP endpoint for direct component testing
- **Test Helpers**: Utility functions for test data management with unique identifiers
- **Parallel Execution**: Tests can run concurrently without interference using complete data isolation
- **Database Retry Logic**: Automatic retry mechanisms for transient database failures
- **Comprehensive Coverage**: All core business logic paths are tested (35+ tests)
- **Performance**: Full test suite runs in 2.9 minutes with parallelization (31% faster than sequential)

For detailed testing information, see `TESTING.md` and `TESTING-SUMMARY.md`.

## 📖 Documentation

### Architecture Decision Records (ADRs)
- `docs/ADR/` - Key architectural decisions and their rationale

### Data Taxonomy
- `docs/Data_taxonomy/` - Data models and taxonomies

### API Documentation
- `docs/openapi spec/` - OpenAPI specification

### Testing Documentation
- `TESTING.md` - Comprehensive test setup and execution guide
- `TESTING-SUMMARY.md` - Current test status and results
- `PARALLEL-TESTING-SOLUTION.md` - Solutions for parallel test execution

## 🧹 Code Quality

This project uses PHP_CodeSniffer with the WordPress Coding Standards to enforce code quality. This is run automatically on every commit via a Husky pre-commit hook.

To run the linter manually:
```bash
./vendor/bin/phpcs
```

To automatically fix issues:
```bash
./vendor/bin/phpcbf
```