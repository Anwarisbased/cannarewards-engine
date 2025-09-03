# ADR 003: Code Loading - Composer PSR-4 Autoloader

**Date:** 2024-05-23

**Status:** Accepted

## Context

The initial codebase used a long, manually maintained list of `require_once` statements in the main plugin file. This is a fragile, error-prone, and outdated method for loading PHP files. It created a hidden dependency on file load order and increased the cognitive overhead for developers.

## Decision

We will completely remove the manual `require_once` system and adopt the industry-standard **PSR-4 autoloader managed by Composer.**

1.  **Namespacing:** All classes will be moved into a root `CannaRewards` namespace.
2.  **Directory Structure:** All class files will be reorganized into a PSR-4 compliant directory structure under `includes/CannaRewards/`.
3.  **File Naming:** All files will be renamed to match their class names exactly (e.g., `class EconomyService` will live in `includes/CannaRewards/Services/EconomyService.php`).
4.  **`composer.json`:** The `autoload` directive will be configured to map the `CannaRewards\` namespace to the `includes/CannaRewards/` directory.
5.  **Bootstrap:** The main plugin file will be gutted and replaced with a single call to `require_once 'vendor/autoload.php';`.

## Consequences

**Positive:**
-   **Modern Standard:** Aligns the project with modern PHP development best practices.
-   **Eliminates Load Order Errors:** The autoloader automatically resolves dependencies, removing an entire class of potential fatal errors.
-   **Improved Developer Experience:** Developers no longer need to manage a long list of includes. New classes are automatically available for use after running `composer dump-autoload`.
-   **Code Clarity:** Namespaces prevent conflicts with other plugins and make the code's structure explicit and easy to understand.

**Negative:**
-   Requires a one-time, meticulous effort to rename and move all existing class files.
-   Adds Composer as a hard dependency for the project's development.