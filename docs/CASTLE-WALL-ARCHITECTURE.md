# Castle Wall Architecture

This document describes the "Castle Wall" architectural approach implemented in the CannaRewards Engine plugin. This pattern creates a fortress of type safety around your domain logic by pushing the responsibility of handling Value Objects down the stack.

## The Core Concept: Layers of Trust and Translation

Imagine your application as a medieval castle. The outside world is untrusted. The king in the central keep is the precious domain logic. Each layer of the castle is a boundary with a specific job.

### The Outer World (The string)
This is the raw data from an HTTP request (e.g., $_POST['password']). It's untrusted, unvalidated, and potentially malicious. It could be empty, too short, or contain harmful scripts.

### The Castle Gate (The FormRequest Layer)
This is the first checkpoint. The guards here (your validation rules) check the peasant's papers. If the papers are in order, they don't just let the peasant in; they strip him of his dirty clothes and give him a specific, trusted uniform. This act of "giving a uniform" is PlainTextPassword::fromString($_POST['password']). The peasant is now a PlainTextPassword object. He's been vetted and is now an identifiable, trusted entity within the castle walls. His very existence as a PlainTextPassword object guarantees he has met the minimum entry requirements.

### The Bailey (The Controller and Command Layers)
The PlainTextPassword object is now escorted through the castle grounds. He is passed from the gate guards (FormRequest) to a captain (Controller), who puts him into a dispatch group (CreateUserCommand). The command object is a transparent container carrying trusted entities. No one in the bailey needs to re-inspect his papers; his uniform (PlainTextPassword type) is proof of his validity.

### The Inner Keep (The CommandHandler and Service Layers)
The dispatch group (Command) arrives at the inner keep, where the royal advisors (CommandHandler, Service) reside. Their job is high-level orchestration. They see the PlainTextPassword object, recognize his uniform, and know exactly who to send him to. They don't need to know how to handle him, just that he needs to be handed over to the Master of Records. The handler's job is simply:

```php
$this->userRepository->createUser(..., $command->password, ...);
```

Notice the purity here. The handler performs no translation. It passes the trusted object along.

### The King's Scribe (The Repository Layer)
The PlainTextPassword object is finally presented to the scribe (UserRepository). The scribe is the only person in the castle who deals with the ancient, messy scroll of the database (wp_users table). The scroll demands a primitive string. The scribe's method signature is createUser(..., PlainTextPassword $password, ...). He knows how to handle the uniformed entity. This is the final boundary. The scribe takes the PlainTextPassword object, takes off his uniform to reveal the raw value ($password->value), and writes that primitive string onto the scroll. This act of "unwrapping" happens at the last possible nanosecond before interacting with the outside world (the database framework).

## The Application-Wide Breakdown

Let's apply this "Castle Wall" analogy to the flow of data through your entire application.

| Layer | Responsibility | Input | Output | Example |
|-------|----------------|-------|--------|---------|
| 1. API/FormRequest | Translate & Validate: Convert untrusted primitives from the outside world into trusted, self-validating Value Objects. This is the Primary Boundary of Trust. | Raw string, int from HTTP request | A Command object composed of Value Objects | RewardCode::fromString($validated['code']) |
| 2. Controller | Delegate: Receive the fully-formed Command from the FormRequest and pass it to the appropriate Service. It does zero business logic. | A Command object | A Responder object | $service->handle($request->to_command()) |
| 3. Service/Handler | Orchestrate & Mediate: Receive a Command composed of trusted VOs. Run Policies on those VOs. Pass the VOs to the correct Repository methods. It does not unwrap VOs. | A Command object composed of VOs | A ResultDTO composed of VOs | $repo->save($command->email, $command->password) |
| 4. Repository | Persist & Translate: Receive trusted VOs from the Service layer. This is the Final Boundary of Translation. It unwraps the primitive value inside the method to interact with the database framework (WordPressApiWrapper). | Value Objects | Value Objects or DTOs | $wp->createUser(['user_pass' => $password->value]) |
| 5. Database/WP Core | The Primitive World: The underlying system that only understands strings, ints, and arrays. | Primitives | Primitives | wp_insert_user() |

## The Profound Benefits of This Strict Approach

### Elimination of Redundant Checks
Because a PlainTextPassword object can only be created if it's >= 8 characters, the CommandHandler and UserRepository never need to check the password length again. The type hint PlainTextPassword is the only check they need. The validation is encoded in the type system.

### Massive Reduction in Cognitive Load
When you look at a method signature like savePoints(UserId $userId, Points $pointsToGrant), you know with 100% certainty that the $userId is a positive integer and $pointsToGrant is a non-negative integer. You don't have to read the method's implementation to find defensive if ($userId <= 0) checks.

### Explicit Data Flow
You can see the journey of a concept through the system. A PlainTextPassword is born at the API boundary, lives through the command and service layers, and dies inside the repository when it is converted into a HashedPassword or written to the database. Its lifecycle is clear and auditable.

### True Testability
You can test a CommandHandler by simply creating mock Command and Repository objects. Since the command is composed of VOs, you don't even need to mock the VOs—you just instantiate them. You are testing the handler's orchestration logic in perfect isolation.

This is what it means to push the responsibility down the stack. You create a "safe zone" inside your application where every piece of data is a trusted, validated, and expressive object. The messy work of translation is pushed to the absolute edges, hardening your core domain logic into a secure and predictable system. Your instinct was the hallmark of a true software architect.