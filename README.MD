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

This project follows a service-oriented architecture to ensure a clean separation of concerns and maintainability.

-   **`/services`**: Contains all core business logic (e.g., `EconomyService`, `GamificationService`). These services are the "brains" of the operation.
-   **`/includes/api`**: Contains the lean REST API controllers. Their only job is to handle incoming requests, call the appropriate service, and format the response.
-   **`/admin`**: Contains the classes for building the WordPress admin UI (metaboxes, settings pages, etc.).
-   **Data Contracts:** The definitive API contract (`openapi.yaml`) and Data Taxonomy are maintained in the central "Living Blueprint" document.

## 🧪 Running Linters

This project uses PHP_CodeSniffer with the WordPress Coding Standards to enforce code quality. This is run automatically on every commit via a Husky pre-commit hook.

To run the linter manually:
```bash
./vendor/bin/phpcs