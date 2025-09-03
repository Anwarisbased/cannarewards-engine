# ADR 004: Data Contracts - OpenAPI and Data Taxonomy

**Date:** 2024-05-23

**Status:** Accepted

## Context

The communication contract between the frontend PWA, the backend API, and the external Customer Data Platform (CDP) was not formally defined. This leads to ambiguity, potential for inconsistent data, and difficulty in parallel development.

## Decision

We will adopt a strict "contract-first" approach for all data interfaces, formalized in two key documents:

1.  **The API Contract (`openapi.yaml`):**
    *   An OpenAPI 3.0 specification will be the single source of truth for the backend's REST API.
    *   It will define every endpoint, its parameters, and its exact request/response schemas.
    *   This enables automated documentation, client/server code generation, and API testing.

2.  **The Data Taxonomy & Tracking Plan:**
    *   A human-readable document (e.g., in Notion) that defines the complete schema for all events sent to the CDP.
    *   It will define standardized, reusable objects (`user_snapshot`, `product_snapshot`, `event_context`) to ensure all analytical data is consistent and richly contextual.
    *   All new tracking requests must be formalized in this document *before* implementation.

## Consequences

**Positive:**
-   **Enables Parallel Development:** Frontend and backend teams can work simultaneously against the shared OpenAPI contract.
-   **Single Source of Truth:** Eliminates ambiguity. The contracts, not the code, are the ultimate authority on how the systems communicate.
-   **High-fidelity Data:** The Data Taxonomy ensures that our most valuable asset—our customer data—is clean, consistent, and structured for maximum utility by AI and marketing automation platforms.
-   **Improved Onboarding:** New developers can understand the entire data flow of the application by reading these two documents.

**Negative:**
-   Adds a layer of process. Development of a new endpoint now requires an upfront investment in defining its contract. This is a deliberate trade-off in favor of long-term quality and maintainability.