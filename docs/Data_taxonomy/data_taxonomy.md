User snapshot

| Property Path | Data Type | Description | Example | Source/Notes |
| :--- | :--- | :--- | :--- | :--- |
| **identity** | *(Object)* | Core, immutable identifiers for the user. | | |
| `identity.user_id` | Integer | The unique WordPress ID for the user. | `123` | `wp_users.ID` |
| `identity.email` | String | The user's email address. | `jane.doe@example.com` | `wp_users.user_email` |
| `identity.first_name` | String | The user's first name. | `Jane` | `wp_usermeta.first_name`. Nullable. |
| `identity.is_guest` | Boolean | True if the user is not authenticated. | `false` | For future use |
| `identity.created_at` | Timestamp | ISO 8601 timestamp (UTC) of user registration. | `2024-05-21T10:00:00Z` | `wp_users.user_registered` |
| **economy** | *(Object)* | Data related to the user's standing in the points economy. | | |
| `economy.points_balance` | Integer | The user's current spendable point balance. | `1850` | `_canna_points_balance` |
| `economy.lifetime_points`| Integer | The user's total accumulated points, used for ranking. | `6100` | `_canna_lifetime_points` |
| `economy.points_spent_total` | Integer | **Calculated.** Cumulative total of all points the user has redeemed. | `4250` | `SUM from log` |
| `economy.currency_name` | String | The brand's configured name for points. | `Buds` | From `ConfigService`. |
| **status** | *(Object)* | Data related to the user's current status and rank. | | |
| `status.rank_key` | String | The machine-readable key for the user's current rank. | `gold` | `_canna_current_rank_key` |
| `status.rank_name` | String | The human-readable name of the user's current rank. | `Gold` | From `Rank` CPT. |
| `status.rank_multiplier`| Number | The point multiplier associated with the user's current rank. | `1.5` | From `Rank` CPT meta. |
| `status.status_name` | String | The brand's configured name for ranks. | `Status` | From `ConfigService`. |
| **engagement** | *(Object)* | **Calculated** metrics describing the user's activity level. | | |
| `engagement.total_scans` | Integer | **Calculated.** The total number of successful product scans. | `12` | `COUNT from action_log where action=scan` |
| `engagement.total_redemptions` | Integer | **Calculated.** The total number of successful reward redemptions. | `2` | `COUNT from action_log where action=redeem` |
| `engagement.total_achievements_unlocked` | Integer | **Calculated.** The total count of unlocked achievements. | `5` | `COUNT from user_achievements table` |
| `engagement.days_since_signup`| Integer | **Calculated.** Days since `identity.created_at`. | `90` | `(NOW - created_at)` |
| `engagement.days_since_last_session`| Integer | **Calculated.** Days since the user's last `user_session_started` event. | `1` | `(NOW - last_session_timestamp)` |
| `engagement.days_since_last_scan`| Integer | **Calculated.** Days since the user's last `user_completed_scan` event. | `5` | `(NOW - last_scan_timestamp)` |
| `engagement.days_since_last_redemption`| Integer | **Calculated.** Days since the user's last `user_reward_redeemed` event. | `25` | `(NOW - last_redemption_timestamp)`|
| `engagement.is_dormant` | Boolean | **Calculated.** True if `days_since_last_session` > 30 (configurable). | `false` | |
| `engagement.is_power_user` | Boolean | **Calculated.** True if user meets configured criteria (e.g., top 10% of lifetime_points). | `true` | |
| **profile_data** | *(Object)* | Zero-party data explicitly provided by the user. | | |
| `profile_data.phone_number`| String | The user's phone number. | `+15551234567` | `wp_usermeta.phone_number`. Stored in E.164 format. |
| `profile_data.custom_*` | Varies | All saved values for configured Custom Fields, prefixed with `custom_`. | `Sativa` | Key is `custom_[meta_key]`. |
| **compliance_and_contact**| *(Object)* | Data related to user consent and legal compliance. | | |
| `compliance_and_contact.is_age_verified`| Boolean | True if user checked the "I am 21+" box during registration. | `true` | `_age_gate_confirmed_at` exists |
| `compliance_and_contact.age_verified_at`| Timestamp | ISO 8601 of when the age was verified. | `2024-05-21T10:00:00Z` | `_age_gate_confirmed_at` |
| `compliance_and_contact.has_marketing_consent`| Boolean | True if the user opted-in to marketing communications. | `true` | `wp_usermeta.marketing_consent` |
| `compliance_and_contact.marketing_consent_updated_at`| Timestamp | ISO 8601 of the last consent change. | `2024-05-21T10:00:00Z` | Meta field update timestamp |
| **referral_data** | *(Object)* | Data related to the user's participation in the referral program. | | |
| `referral_data.referral_code`| String | The user's personal referral code to share. | `JANE1A2B` | `_canna_referral_code` |
| `referral_data.referred_by_user_id`| Integer | The ID of the user who referred them, if any. | `456` | `_canna_referred_by_user_id`. Nullable. |
| `referral_data.total_referrals_completed`| Integer | **Calculated.** The number of new users they referred who completed their first scan. | `3` | `COUNT from action_log where action=referral_converted` |

product_snapshot

| Property Path | Data Type | Description | Example |
| :--- | :--- | :--- | :--- |
| **identity** | *(Object)* | Core, immutable identifiers for the product. | |
| `identity.product_id` | Integer | The unique WooCommerce ID for the product. | `45` |
| `identity.sku` | String | The product's Stock Keeping Unit. | `BD-VAPE-1G` |
| `identity.product_name` | String | The full name of the product. | `Blue Dream 1g Vape` |
| **economy** | *(Object)*| Data related to the product's value in the loyalty economy. | |
| `economy.points_award`| Integer | Points awarded for scanning this product. | `400` |
| `economy.points_cost` | Integer | Points required to redeem this product. | `5000` |
| `economy.msrp` | Number | Manufacturer's Suggested Retail Price. | `45.00` |
| **taxonomy** | *(Object)*| Classifications and categories for the product. | |
| `taxonomy.product_line`| String | The brand's internal product family (from Category). | `Signature Series` |
| `taxonomy.product_form`| String | Standardized physical format (from Attribute). | `Vape Cartridge` |
| `taxonomy.strain_name` | String | Common name of the strain (from Attribute). | `Blue Dream` |
| `taxonomy.strain_type` | String | Standardized genetic profile (from Attribute). | `Sativa` |
| `taxonomy.tags` | Array | Array of all associated product tags. | `["effect-energetic", "flavor-sweet", "new-release"]`|
| **attributes** | *(Object)*| Specific, objective data about the product. | |
| `attributes.potency_thc_percent`| Number | Percentage of THC. | `88.5` |
| `attributes.potency_cbd_percent`| Number | Percentage of CBD. | `0.8` |
| `attributes.dominant_terpene`| String | The primary terpene. | `Myrcene` |
| **merchandising** | *(Object)*| Flags used for dynamic UI presentation in the PWA. | |
| `merchandising.is_featured`| Boolean | True if the product is marked as featured. | `false` |
| `merchandising.is_new` | Boolean | **Calculated.** True if current date is before `new_until` date. | `true` |
| `merchandising.is_limited` | Boolean | True if `redemption_limit` is set and low. | `false` |
| `merchandising.is_digital` | Boolean | True if the product is a digital good. | `false` |

event_context

| Property Path | Data Type | Description | Example |
| :--- | :--- | :--- | :--- |
| **time** | *(Object)*| All data related to when the event occurred. | |
| `time.timestamp_utc` | Timestamp | ISO 8601 timestamp of the event in UTC. | `2024-05-22T18:35:12Z` |
| `time.timestamp_local` | Timestamp | ISO 8601 with timezone offset of the user. | `2024-05-22T11:35:12-07:00` |
| `time.day_of_week_local` | String | The day of the week in the user's local timezone. | `Wednesday` |
| `time.hour_of_day_local` | Integer | The hour of the event in the user's local timezone (0-23). | `11` |
| `time.is_weekend` | Boolean | True if the event occurred on a weekend. | `false` |
| **device** | *(Object)*| All data related to the device that initiated the event. | |
| `device.device_type` | String | Inferred from User-Agent. | `mobile` |
| `device.os` | String | Inferred from User-Agent. | `iOS` |
| `device.browser` | String | Inferred from User-Agent. | `Safari` |
| `device.user_agent` | String | Full User-Agent string. | `Mozilla/5.0...` |
| **location** | *(Object)*| All geographic data related to the event. | |
| `location.ip_address` | String | The IP address of the request. | `216.3.128.12` |
| `location.geo_city` | String | City derived from IP lookup. | `Los Angeles` |
| `location.geo_region` | String | State/Region derived from IP lookup. | `California` |
| `location.geo_country` | String | Country derived from IP lookup. | `USA` |

shipping_details

| Property Path | Data Type | Description |
| :--- | :--- | :--- |
| `shipping_details.first_name`| String | First name for the shipment. |
| `shipping_details.last_name`| String | Last name for the shipment. |
| `shipping_details.address_1`| String | Primary address line. |
| `shipping_details.city` | String | City for the shipment. |
| `shipping_details.state` | String | State/Region for the shipment. |
| `shipping_details.postcode` | String | Postal code for the shipment. |