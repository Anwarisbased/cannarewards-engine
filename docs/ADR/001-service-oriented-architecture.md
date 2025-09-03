# ADR 001: Architectural Choice - Service-Oriented Monolith

**Date:** 2024-05-23

**Status:** Accepted

## Context

The initial codebase had business logic (for points, achievements, referrals) tightly coupled within the API controller classes (`Canna_Rewards_Controller`, etc.). This made adding new features or modifying existing ones "cumbersome," as a single change required editing multiple files and understanding a wide range of implicit dependencies. The architecture was brittle and did not have a clear separation of concerns.

## Decision

We will refactor the backend into a **Service-Oriented Architecture (SOA)** within a single WordPress plugin (a "well-organized monolith").

This involves creating a new `/services` directory. Each service will be a PHP class responsible for a single, distinct business domain:
-   `EconomyService`: Manages all logic for points and redemptions.
-   `GamificationService`: Manages all logic for achievements.
-   `ReferralService`: Manages all logic for the referral program.
-   And so on.

The API controllers in `/includes/api/` will be refactored to be "lean." Their only responsibility is to handle the HTTP request/response cycle and delegate all business logic to the appropriate service.

## Consequences

**Positive:**
-   **High Cohesion / Loose Coupling:** Logic is now grouped by business domain, making it easier to find, understand, and maintain.
-   **Increased Testability:** Each service can be instantiated and tested in isolation, improving code quality and reliability.
-   **Improved Developer Velocity:** Adding new features is simplified. For example, a "Product Reviews" feature would involve creating a new, self-contained `ReviewService` without modifying the core economic or gamification logic.

**Negative:**
-   Introduces a slightly higher level of abstraction than a simple controller-based model.
-   Requires discipline to maintain the separation of concerns and prevent services from becoming overly dependent on each other.

**Rejected Alternative: Microservices**
A full microservices architecture was considered but rejected due to the immense operational complexity (multiple deployments, databases, network latency) which is not justified at the current scale of the project. This SOA monolith provides 80% of the benefits with 10% of the complexity.