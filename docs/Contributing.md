# CannaRewards Contribution Guidelines

**Version:** 1.0
**Status:** `LOCKED-IN`

## 🚀 Welcome

Thank you for contributing to the CannaRewards platform. This document is the single source of truth for our development workflow, coding standards, and architectural principles. Adherence to these guidelines is mandatory for all contributions to ensure the long-term health, quality, and maintainability of the codebase.

## 🏛️ Core Architectural Principles

Before writing any code, it is essential to understand the architectural philosophy that governs this project. All contributions will be evaluated against these principles. The "why" behind these decisions is documented in our **Architectural Decision Records (ADRs)** located in `/docs/adr`.

1.  **Service-Oriented Monolith (ADR-001):** The backend is a "well-organized monolith," not a distributed system. All business logic is encapsulated in distinct, single-responsibility services (e.g., `EconomyService`, `GamificationService`). Controllers are thin and stateless.
2.  **Event-Driven Communication (ADR-002):** Services are fully decoupled and communicate asynchronously via a central `Event` broadcaster. Services **do not** call each other directly. They listen for events and react to them.
3.  **Contracts First (ADR-003):** All data interfaces are defined before implementation.
    -   The **API Contract (`openapi.yaml`)** is the immutable blueprint for the REST API.
    -   The **Data Taxonomy (Notion)** is the immutable blueprint for all CDP events.
    -   **Any change to an API or a CDP event must be proposed and approved in these documents *before* a single line of code is written.**

## ⚙️ The Development Workflow: A Step-by-Step Guide

We follow a structured workflow to ensure consistency and quality.

### Step 1: The Ticket

-   All work must begin with a ticket in our project management system (e.g., Jira, Linear, Trello).
-   A ticket must have a clear title, a detailed description of the user story or bug, and explicit **Acceptance Criteria**.

### Step 2: The Branch

-   All work must be done on a feature branch created from the `develop` branch.
-   **Branch Naming Convention:** Branches must be named using the format `[type]/[ticket-id]-[short-description]`.
    -   `feat/CR-123-wishlist-api`
    -   `fix/CR-124-cors-issue-on-claim`
    -   `chore/CR-125-update-dependencies`
-   The `main` and `develop` branches are protected. Direct pushes are disabled.

### Step 3: The Code

-   **Code Style & Quality:** Code style is non-negotiable and is automatically enforced by a **Husky pre-commit hook**.
    -   **Frontend:** ESLint and Prettier are used.
    -   **Backend:** PHPCS with the WordPress Coding Standards rule set is used.
    -   **Commits that do not pass linting will be automatically blocked.**
-   **In-Code Documentation:** All public classes and methods **must** have a complete PHPDoc or JSDoc block explaining their purpose, parameters, and return values.
-   **Testing:** All new business logic added to a Service **must** be accompanied by a corresponding unit test (PHPUnit for backend). All new user-facing flows on the frontend **must** be accompanied by a corresponding End-to-End (E2E) test (Cypress/Playwright).

### Step 4: The Pull Request (PR)

The Pull Request is our primary quality gate.

-   All feature branches must be merged into `develop` via a PR.
-   **PR Title:** The PR title must follow the [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) specification. This is mandatory as it is used to automate changelogs.
    -   `feat: Implement Wishlist API endpoints`
    -   `fix: Resolve fatal error in GamificationService`
    -   `docs: Update OpenAPI spec for the new Dashboard endpoint`
-   **PR Description:** The PR description must be filled out using our template:
    -   **Link to Ticket:** A mandatory link to the corresponding project management ticket.
    -   **Summary of Changes:** A clear, concise explanation of what was built or fixed.
    -   **Testing Instructions:** A step-by-step guide for the reviewer on how to manually verify the changes in a staging environment.
-   **Automated Checks (CI):** A PR cannot be merged until all automated checks (linting, tests, project build) have passed. The "Merge" button will be disabled.
-   **Code Review:** All PRs must be reviewed and approved by at least one other team member. For solo developers, this means performing a thorough self-review, stepping through every line of code as if you were another person.

### Step 5: The Merge & Deploy

-   Once a PR is approved and all checks have passed, it can be merged into `develop`.
-   A merge to `develop` automatically triggers a deployment to the **Staging** environment.
-   Releases to production are done by creating a new PR from `develop` into `main`. A merge to `main` automatically triggers a deployment to the **Production** environment.

## 🪵 Logging & Debugging

-   **Backend:** Use the standard `error_log()` function for debugging. Do not leave `var_dump()` or `echo` statements in committed code.
-   **Frontend:** Use `console.log()`, `console.warn()`, and `console.error()` appropriately. Remove all debugging logs before submitting a PR unless they are providing a valuable, intentional warning to other developers.

By adhering to these rules, we ensure that the CannaRewards platform remains a clean, stable, and professional codebase that is a pleasure to work on.