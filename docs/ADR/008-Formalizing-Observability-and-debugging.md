ADR 008: Formalizing Observability and Debugging
Status: Proposed
Context:
The current debugging workflow relies on error_log() and manual inspection. This is inefficient and provides insufficient context to diagnose issues, especially in a production environment. When an error occurs, it's difficult to know which user was affected, what data they submitted, or the sequence of events that led to the failure.
Decision:
We will implement a three-tiered observability strategy to provide a professional-grade debugging experience.
Local Development: Xdebug. We will adopt Xdebug as the standard for local development. This enables interactive, step-through debugging, allowing developers to pause code execution and inspect the full application state at any point. This replaces the inefficient var_dump(); die(); workflow.
Structured Logging: Monolog. All error_log() calls will be replaced with a centralized LoggerService built on the Monolog library. All logs will be written as structured JSON, including rich context (e.g., user_id, request data, exception traces). This makes logs searchable, filterable, and machine-readable.
Production Surveillance: Sentry. We will integrate an error tracking service (like Sentry) into the production environment. This will automatically capture all unhandled exceptions, group them, and provide a rich dashboard with the full context needed to diagnose and resolve production bugs before users report them.
Consequences:
Positive:
Drastically Reduced Debugging Time: Step-debugging with Xdebug can reduce the time to find the root cause of a local bug by an order of magnitude.
Actionable Production Alerts: Sentry provides immediate, context-rich alerts for production failures, turning unknown problems into well-defined tasks.
Improved System Visibility: Structured logs provide a clear, searchable history of application events, crucial for understanding complex user interactions.
Negative:
Initial Setup Cost: Each of these tools requires a one-time setup and configuration investment.
Minor Performance Overhead: Production logging and error tracking add a small amount of overhead to each request. This is a standard and acceptable cost for production visibility.