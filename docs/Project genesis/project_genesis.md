Of course. Understood.

Here is the `PROJECT_GENESIS.md` document, rewritten to be purely analytical, objective, and devoid of figurative language.

---

### **`PROJECT_GENESIS.md` (Analytical Edition)**

**Status:** `LOCKED-IN`
**Version:** 2.0.0
**Purpose:** This document specifies the strategic and operational parameters of the CannaRewards platform.

---

## 1. Business Model & Market Position

**System:** A white-label B2B2C loyalty and data collection platform.
**Client:** Cannabis CPG brands.
**Revenue Model:** Flat-rate monthly subscription fee.
**Service Deliverable:** A technology and service package composed of a Progressive Web App (PWA), backend management, and marketing automation operation. The primary function is to convert physical product packaging into a D2C data channel.

**Target Client Profile (ICP):**
-   **Revenue:** $500,000 to $4,000,000 USD monthly.
-   **Market Rank:** Approximately #10 to #75 by revenue in their state.
-   **Operational Characteristics:** Independent, founder-led CPG brands with demonstrated product-market fit. These entities typically lack dedicated in-house data science, CRM, or software engineering departments.
-   **Non-Target:** Multi-State Operators (MSOs) are excluded due to structural and operational misalignment with the DFY service model.

**Problem Statement:**
Cannabis CPG brands lack a direct data link to end-consumers due to the three-tier distribution system (producer -> distributor -> retailer). This results in zero first-party data regarding consumer demographics or behavior.

**Solution:** The platform establishes this data link by incentivizing consumers to scan an on-pack QR code, enabling direct data capture and communication.

**Long-Term Objective:** To become the dominant D2C intelligence platform for independent cannabis brands, creating a proprietary dataset on consumer behavior that provides a competitive advantage against larger operators.

---

## 2. Go-to-Market & Sales Process

**Pricing Model:**
-   **Rate:** A single, fixed price of $4,000 USD per month.
-   **Scope:** Includes all software features, QR code generation, customer profile storage, and DFY service hours for campaign management.
-   **Client Responsibility:** The client is responsible for the Cost of Goods Sold (COGS) for all physical reward merchandise.

**Sales Process:**
-   **Method:** A multi-channel outreach ("C-Suite Blitz") targeting C-level executives.
-   **Core Asset:** A non-functional, visually accurate, and client-branded PWA demo, customized via URL parameters.
-   **Value Proposition:** The sales process is a quantitative exercise focused on demonstrating projected ROI. An ROI Scorecard is used to model the financial return based on the client's specific business metrics, justifying the monthly fee as a revenue-generating activity.

---

## 3. User Acquisition Funnel

**Key Performance Indicator (KPI):** Achieve and sustain a >10% adoption rate (scans per unit sold).

**Physical Asset:** An on-pack, die-cut holographic sticker ("Authenticity Seal") with a direct call-to-action (`SCAN TO STACK`) and a value proposition (`First scan unlocks free gear`).

**Onboarding Workflow:** A sequential process designed to maximize conversion by front-loading value and delaying data input friction.
1.  **Scan:** User scans the QR code.
2.  **Claim:** PWA displays a free physical product.
3.  **Ship:** A modal collects the minimum data required for both account creation and physical shipment (Name, Address, Email, Terms).
4.  **Confirm:** A `claim-unauthenticated` API endpoint executes three actions: creates the user account, generates a zero-dollar WooCommerce order for the gift, and dispatches a magic link email for account activation.
5.  **Activate:** User clicks the magic link to log in, completing the loop.

---

## 4. User Retention & Engagement

**Initial Engagement ("Welcome Streak"):** A predefined, high-value reward schedule for a user's first three scans to establish a behavioral pattern.
-   **Scan 1:** 1x Physical Product + Base Points.
-   **Scan 2:** 2x Point Multiplier.
-   **Scan 3:** 1x Achievement Unlock + Bonus Points.

**Long-Term Engagement (The Wishlist/Goal System):**
-   The primary long-term retention mechanic is a user-defined "Active Goal" selected from their Wishlist. This goal is persistently displayed on the user's dashboard with a progress bar, providing a clear objective for point accumulation.

---

## 5. Points & Rewards Economy

**Point Issuance (Earning):**
-   **Primary Rule:** 10 Points awarded per $1 of the product's MSRP. This requires an `msrp` data field in the client's Product Information Management (PIM) system.
-   **Secondary Rule:** Fixed point amounts awarded via the Achievement and Trigger systems.

**Point Redemption (Spending):**
-   **Primary Rule:** The point cost of a reward is pegged to its hard Cost of Goods Sold (COGS) to the client.
-   **Target Peg:** 1 Point ≈ $0.01 of COGS.

**Economic Model:** The system is calibrated to provide a 7-10% value-back to the end-consumer. This rate is designed to be highly competitive to drive adoption and retention.

---

## 6. Competitive Positioning

The platform is positioned as a new market category to make direct competitors irrelevant.
-   **Not** a simple authentication tool (e.g., Cannverify).
-   **Not** a complex, self-service enterprise platform (e.g., Batch).
-   **Is** a "Done-For-You Customer Intelligence Platform" targeting the specific operational and financial constraints of the mid-market.

---

## 7. Technology Architecture

The system is a decoupled, three-part stack.
-   **Backend:** A headless WordPress installation utilizing a Service-Oriented, Event-Driven architecture. It functions as a backend-as-a-service (BaaS) for the PWA.
-   **Frontend:** A Next.js Progressive Web App (PWA) focused on performance and user experience, deployed on a global edge network (Vercel).
-   **Customer Data Platform (CDP):** Customer.io is the designated system for ingesting the enriched event stream from the backend. It handles all user segmentation, marketing automation workflows, and AI-driven personalization.