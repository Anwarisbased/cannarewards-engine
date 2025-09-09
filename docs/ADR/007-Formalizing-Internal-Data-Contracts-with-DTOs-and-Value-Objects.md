ADR 007: Formalizing Internal Data Contracts with DTOs and Value Objects
Status: Proposed
Context:
Currently, data passed between internal layers of the application (e.g., from a Service to a Controller, or from a Repository to a Service) is primarily in the form of associative arrays. This is a common source of bugs. A typo in an array key ('points_balanc') is not caught until runtime. Furthermore, primitive types like string and int do not carry any business context (e.g., is this int a UserId or a ProductId?). This leads to scattered validation logic and makes the code harder to reason about.
Decision:
We will implement two patterns to create strong, internal data contracts:
Value Objects: For core, primitive-like business concepts, we will create small, immutable classes that validate themselves upon creation. An EmailAddress class, for instance, cannot be instantiated with a malformed string. A UserId cannot be created with a negative integer. This makes invalid states unrepresentable.
Data Transfer Objects (DTOs): For all structured data moving between application layers (especially data returned from services), we will use simple, public-property DTO classes. Instead of a service returning a complex array, it will return a UserProfileDTO object. This provides IDE autocompletion, static analysis benefits, and serves as self-documenting code.
Consequences:
Positive:
Eliminates an entire class of bugs: Typos in data keys and passing of invalid primitive types are caught at creation time or by static analysis, not in production.
Massively Improved Developer Experience: IDE autocompletion makes the code faster and more enjoyable to write.
Self-Documenting: The DTO classes themselves become the definitive, always-up-to-date documentation for the application's internal data structures.
Negative:
Increased Boilerplate: This requires writing more small classes, which can feel verbose for a simple application. This is a trade-off for long-term stability and clarity.