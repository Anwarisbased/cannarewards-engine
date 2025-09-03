openapi: 3.0.3
info:
  title: CannaRewards API
  description: |-
    The official API contract for the CannaRewards v2 platform. 
    This API is designed with a service-oriented, event-driven backend to power a headless PWA. 
    It provides a comprehensive suite of endpoints for managing users, economy, gamification, and content.
  version: 2.0.0
  contact:
    name: API Support
    url: https://yourwebsite.com/support
    email: dev@yourdomain.com

servers:
  - url: https://your-backend-domain.com/wp-json/rewards/v2
    description: Production Server
  - url: http://cannarewards-api.local/wp-json/rewards/v2
    description: Local Development Server

tags:
  - name: App & Session
    description: Endpoints for core application configuration and lightweight session management.
  - name: User Profile & Data
    description: Endpoints for fetching and updating detailed user profile information.
  - name: Economy
    description: Endpoints related to the points economy, rewards catalog, and wishlisting.
  - name: Actions
    description: Primary endpoints that trigger core user actions and business events.
  - name: Authentication
    description: Endpoints for user registration, login, and password management.
  - name: Pages
    description: Endpoints for retrieving general WordPress content.

#--------------------------------
# Reusable Components
#--------------------------------
components:
  schemas:
    # --- Model Schemas ---
    Rank:
      type: object
      description: "Represents a single rank or tier in the loyalty program."
      properties:
        key: 
          type: string
          example: "gold"
          description: "The unique, machine-readable key for the rank."
        name: 
          type: string
          example: "Gold"
          description: "The human-readable name of the rank."
        points_required: 
          type: integer
          format: int64
          example: 5000
          description: "The lifetime points required to achieve this rank."
        point_multiplier: 
          type: number
          format: float
          example: 1.5
          description: "The point earning multiplier for this rank."
        benefits: 
          type: array
          items: 
            type: string
          example: ["Early access to new rewards", "Exclusive merch"]
          description: "A list of perks for this rank."

    Achievement:
      type: object
      description: "Represents the definition of a single achievement."
      properties:
        title: 
          type: string
          example: "Sativa Specialist"
        description: 
          type: string
          example: "Scan 5 different Sativa products."
        rarity: 
          type: string
          enum: [common, uncommon, rare, epic, legendary]
          example: "rare"
        icon_url: 
          type: string
          format: uri
          nullable: true
          description: "URL for the achievement's icon image."
    
    RewardProduct:
      type: object
      description: "A simplified representation of a WooCommerce product that can be redeemed with points."
      properties:
        id: 
          type: integer
          example: 82
        name: 
          type: string
          example: "Limited Edition Hoodie"
        points_cost: 
          type: integer
          example: 4500
        image_url: 
          type: string
          format: uri
          nullable: true
        tags: 
          type: array
          items: 
            type: string
          example: ["limited", "new"]

    SessionUser:
      type: object
      description: "A lightweight object representing the core data for an authenticated user's session."
      properties:
        id: 
          type: integer
          example: 123
        firstName: 
          type: string
          example: "Jane"
          nullable: true
        email: 
          type: string
          format: email
        points_balance: 
          type: integer
          example: 1250
        rank:
          type: object
          properties:
            key: { type: string, example: "silver" }
            name: { type: string, example: "Silver" }
        onboarding_quest_step: 
          type: integer
          example: 2
        feature_flags: 
          type: object
          description: "Flags for A/B testing frontend features."
          example: {"dashboard_version": "B"}

    ShippingAddress:
      type: object
      description: "A standard shipping address object."
      required: [first_name, last_name, address_1, city, state, postcode]
      properties:
        first_name: { type: string, example: "Jane" }
        last_name: { type: string, example: "Doe" }
        address_1: { type: string, example: "123 Main St" }
        city: { type: string, example: "Anytown" }
        state: { type: string, example: "CA" }
        postcode: { type: string, example: "90210" }

    CustomFieldDefinition:
      type: object
      description: "The schema for a single dynamically defined custom field."
      properties:
        key: { type: string, example: "favorite_strain_type", description: "The meta key for the field." }
        label: { type: string, example: "Favorite Strain Type", description: "The human-readable label." }
        type: { type: string, enum: [text, date, dropdown], description: "The type of form input to render." }
        options: { type: array, items: { type: string }, example: ["Sativa", "Indica", "Hybrid"], description: "Options for dropdown type." }
        display: { type: array, items: { type: string }, example: ["edit_profile", "registration"], description: "Where in the UI this field should appear." }

    # --- Request Body Schemas ---
    LoginRequest:
      type: object
      required: [email, password]
      properties:
        email: { type: string, format: email, example: "jane.doe@example.com" }
        password: { type: string, format: password, example: "Str0ngP@ssw0rd!" }
    
    RegisterRequest:
      type: object
      required: [email, password, firstName, agreedToTerms]
      properties:
        email: { type: string, format: email }
        password: { type: string, format: password, minLength: 8 }
        firstName: { type: string }
        lastName: { type: string }
        phone: { type: string, description: "Optional phone number." }
        agreedToTerms: { type: boolean, description: "Must be true to register." }
        agreedToMarketing: { type: boolean }
        referralCode: { type: string, description: "Optional referral code from another user." }
        claimCode: { type: string, description: "Optional QR code from a product scan during unauthenticated registration." }

    # --- Error Schemas ---
    Error:
      type: object
      required: [message]
      properties:
        code: { type: string, example: "rest_unauthorized" }
        message: { type: string, example: "Authentication is required." }
        data: { type: object, properties: { status: { type: integer, example: 401 } } }

  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: "A JWT obtained from the /auth/login endpoint."

  responses:
    UnauthorizedError:
      description: Authentication information is missing or invalid (401).
      content: { application/json: { schema: { $ref: '#/components/schemas/Error' } } }
    ForbiddenError:
      description: The authenticated user does not have permission to perform this action (403).
      content: { application/json: { schema: { $ref: '#/components/schemas/Error' } } }
    NotFoundError:
      description: The requested resource was not found (404).
      content: { application/json: { schema: { $ref: '#/components/schemas/Error' } } }
    BadRequestError:
      description: The request was malformed or missing required parameters (400).
      content: { application/json: { schema: { $ref: '#/components/schemas/Error' } } }
    ConflictError:
      description: The request could not be completed due to a conflict with the current state of the resource (409).
      content: { application/json: { schema: { $ref: '#/components/schemas/Error' } } }
    CreatedSuccess:
      description: The resource was created successfully (201).
      content:
        application/json:
          schema:
            type: object
            properties:
              success: { type: boolean, example: true }
              message: { type: string }
              userId: { type: integer }

#--------------------------------
# Paths (API Endpoints)
#--------------------------------
paths:
  # App & Session
  /app/config:
    get:
      tags: [App & Session]
      summary: Get App Configuration
      description: Fetches all static, global configuration for the application. This data changes infrequently and should be cached heavily by the client for the duration of a session.
      security: [ { bearerAuth: [] } ]
      responses:
        '200':
          description: Successful response containing all application configuration.
          content:
            application/json:
              schema:
                type: object
                properties:
                  settings:
                    type: object
                    description: "Brand personality and theme settings."
                  all_ranks:
                    type: object
                    description: "A dictionary of all available ranks, keyed by rank key."
                    additionalProperties:
                      $ref: '#/components/schemas/Rank'
                  all_achievements:
                    type: object
                    description: "A dictionary of all available achievements, keyed by achievement key."
                    additionalProperties:
                      $ref: '#/components/schemas/Achievement'
        '401': { $ref: '#/components/responses/UnauthorizedError' }

  /users/me/session:
    get:
      tags: [App & Session]
      summary: Get Session Data
      description: A lightweight 'heartbeat' endpoint. Verifies the user's token and returns the minimal data needed to render the authenticated app shell.
      security: [ { bearerAuth: [] } ]
      responses:
        '200':
          description: OK
          content: { application/json: { schema: { $ref: '#/components/schemas/SessionUser' } } }
        '401': { $ref: '#/components/responses/UnauthorizedError' }

  # User Profile & Data
  /users/me/dashboard:
    get:
      tags: [User Profile & Data]
      summary: Get Dashboard Data
      description: Fetches the dynamic, personalized data and UI suggestions for the main user dashboard.
      security: [ { bearerAuth: [] } ]
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  lifetime_points: { type: integer }
                  active_goal_id: { type: integer, nullable: true }
                  wishlist_count: { type: integer }
                  achievements_in_progress: { type: object }
                  ui_suggestions: { type: array, items: { type: object } }
        '401': { $ref: '#/components/responses/UnauthorizedError' }

  /users/me/profile:
    get:
      tags: [User Profile & Data]
      summary: Get Full Profile Data
      security: [ { bearerAuth: [] } ]
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  lastName: { type: string, nullable: true }
                  phone_number: { type: string, nullable: true }
                  referral_code: { type: string, nullable: true }
                  shipping_address: { $ref: '#/components/schemas/ShippingAddress' }
                  unlocked_achievement_keys: { type: array, items: { type: string } }
                  custom_fields:
                    type: object
                    properties:
                      definitions: { type: array, items: { $ref: '#/components/schemas/CustomFieldDefinition' } }
                      values: { type: object }
        '401': { $ref: '#/components/responses/UnauthorizedError' }
    post:
      tags: [User Profile & Data]
      summary: Update Profile Data
      security: [ { bearerAuth: [] } ]
      requestBody:
        description: A payload containing only the fields to be updated.
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                firstName: { type: string }
                lastName: { type: string }
                phone_number: { type: string }
                custom_fields:
                  type: object
                  example: { "favorite_strain_type": "Indica" }
      responses:
        '200':
          description: Update successful. Returns the full, updated profile object.
          content:
            application/json:
              schema:
                $ref: '#/paths/~1users~1me~1profile/get/responses/200/content/application~1json/schema'
        '400': { $ref: '#/components/responses/BadRequestError' }
        '401': { $ref: '#/components/responses/UnauthorizedError' }

  # Economy
  /catalog:
    get:
      tags: [Economy]
      summary: Get Rewards Catalog
      security: [ { bearerAuth: [] } ]
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  featured_rewards: { type: array, items: { $ref: '#/components/schemas/RewardProduct' } }
                  all_rewards: { type: array, items: { $ref: '#/components/schemas/RewardProduct' } }
        '401': { $ref: '#/components/responses/UnauthorizedError' }

  /wishlist:
    post:
      tags: [Economy]
      summary: Add to Wishlist
      security: [ { bearerAuth: [] } ]
      requestBody: { content: { application/json: { schema: { properties: { productId: { type: integer } } } } } }
      responses: { '200': { description: OK }, '401': { $ref: '#/components/responses/UnauthorizedError' } }
    delete:
      tags: [Economy]
      summary: Remove from Wishlist
      security: [ { bearerAuth: [] } ]
      requestBody: { content: { application/json: { schema: { properties: { productId: { type: integer } } } } } }
      responses: { '200': { description: OK }, '401': { $ref: '#/components/responses/UnauthorizedError' } }
          
  /wishlist/set-goal:
    post:
      tags: [Economy]
      summary: Set Active Goal
      security: [ { bearerAuth: [] } ]
      requestBody: { content: { application/json: { schema: { properties: { productId: { type: integer } } } } } }
      responses: { '200': { description: OK }, '401': { $ref: '#/components/responses/UnauthorizedError' } }

  # Actions
  /actions/claim:
    post:
      tags: [Actions]
      summary: Process a Product Scan
      description: Initiates the core 'product_scanned' event. The response reflects only the immediate economic outcome.
      security: [ { bearerAuth: [] } ]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [code]
              properties:
                code: { type: string, example: "SKU123-ABCDEF9876" }
      responses:
        '200':
          description: Scan processed successfully.
          content:
            application/json:
              schema:
                type: object
                properties:
                  success: { type: boolean, example: true }
                  message: { type: string, example: "You earned 400 points!" }
                  points_earned: { type: integer, example: 400 }
                  new_points_balance: { type: integer, example: 1650 }
        '400': { $ref: '#/components/responses/BadRequestError' }
        '401': { $ref: '#/components/responses/UnauthorizedError' }
        '404': { $ref: '#/components/responses/NotFoundError' }

  /actions/redeem:
    post:
      tags: [Actions]
      summary: Redeem a Reward
      security: [ { bearerAuth: [] } ]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [productId]
              properties:
                productId: { type: integer, example: 82 }
                shippingDetails: { $ref: '#/components/schemas/ShippingAddress', nullable: true }
      responses:
        '200':
          description: Redemption successful.
          content:
            application/json:
              schema:
                type: object
                properties:
                  success: { type: boolean, example: true }
                  order_id: { type: integer, example: 12345 }
                  new_points_balance: { type: integer, example: 800 }
        '401': { $ref: '#/components/responses/UnauthorizedError' }
        '402': { description: Insufficient points. }
        '403': { $ref: '#/components/responses/ForbiddenError' }

  # Pages
  /pages/{slug}:
    get:
      tags: [Pages]
      summary: Get Page Content
      parameters: [ { name: slug, in: path, required: true, schema: { type: string, example: "terms-and-conditions" } } ]
      security: [ { bearerAuth: [] } ]
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  title: { type: string }
                  content: { type: string, description: "HTML content of the page." }
        '401': { $ref: '#/components/responses/UnauthorizedError' }
        '404': { $ref: '#/components/responses/NotFoundError' }

  # Authentication
  /auth/register:
    post:
      tags: [Authentication]
      summary: Register User
      description: Creates a new user account. Returns a success message and user ID. The client should typically proceed to log the user in to get a token.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RegisterRequest'
      responses:
        '201': { $ref: '#/components/responses/CreatedSuccess' }
        '409': { $ref: '#/components/responses/ConflictError' }
        '400': { $ref: '#/components/responses/BadRequestError' }

  /auth/login:
    post:
      tags: [Authentication]
      summary: Login with Password
      description: Authenticates a user with email/password and returns a JWT for subsequent authenticated requests.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/LoginRequest'
      responses:
        '200':
          description: Authentication successful, JWT returned.
          content:
            application/json:
              schema:
                type: object
                properties:
                  token: { type: string, example: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." }
                  user_email: { type: string, format: email }
                  user_display_name: { type: string }
        '403': { $ref: '#/components/responses/ForbiddenError' }