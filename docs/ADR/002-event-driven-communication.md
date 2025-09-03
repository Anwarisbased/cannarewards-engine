# ADR 002: Inter-Service Communication - Event-Driven Model

**Date:** 2024-05-23

**Status:** Accepted

## Context

With the move to a Service-Oriented Architecture (ADR 001), we need a defined pattern for how services communicate. A direct-call approach (e.g., `EconomyService` directly calling `new GamificationService()`) would re-introduce tight coupling and create a tangled web of dependencies between services.

## Decision

We will implement an **Event-Driven Architecture (EDA)** for inter-service communication. This will be facilitated by a simple, static `Event` broadcaster class (implementing the Observer pattern).

-   **Broadcasting:** Services will not call each other directly. Instead, after completing their core logic, they will broadcast a past-tense event, such as `Event::broadcast('product_scanned', $payload)`. The broadcaster knows nothing about who is listening.
-   **Listening:** Services that need to react to an event will subscribe to it in their constructor using `Event::listen('product_scanned', [$this, 'handler_method'])`.

The `RulesEngineService` will be deprecated, and its orchestration responsibilities will be distributed to these autonomous listeners.

## Consequences

**Positive:**
-   **Ultimate Decoupling:** Services are completely decoupled. The `EconomyService` has no knowledge of the `GamificationService` or `ReferralService`.
-   **Extensibility:** Adding new, cross-cutting logic is incredibly cheap and safe. A new service (e.g., `FraudDetectionService`) can simply listen for existing events without requiring any changes to the core services.
-   **Architectural Purity:** The codebase becomes a direct reflection of the business processes. Logic is clean, isolated, and easy to trace from event to listener.
-   **Foundation for Asynchronicity:** This pattern is a prerequisite for moving to a more scalable, asynchronous queue-based system in the future.

**Negative:**
-   **Increased Indirection:** The control flow is less explicit. To understand what happens after a scan, a developer must look for the `product_scanned` event broadcast and then find all the registered listeners for that event. This requires a bit more discipline to trace.