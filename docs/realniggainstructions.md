Vertical Slice 8: Finalizing the Referral System
Objective: Implement the automatic generation and retrieval of user referral codes. This will complete the referral lifecycle and allow the final referral test to pass.
Target Test File to Pass:
tests-api/04-referral-system.spec.js (Specifically the skipped test: "User A gets their referral code").
Key Application Files to Review & Refactor:
The Trigger: includes/CannaRewards/Commands/CreateUserCommandHandler.php
The Logic: includes/CannaRewards/Services/ReferralService.php
The Persistence Layer: includes/CannaRewards/Repositories/UserRepository.php
The Data Contract: includes/CannaRewards/DTO/SessionUserDTO.php and its serialization in SessionController.php.
Refactoring Instructions:
Trigger Generation: In CreateUserCommandHandler, immediately after a new user's ID is created, make a call to ReferralService->generate_code_for_new_user(), passing the new user ID and their first name.
Implement Generation Logic: In ReferralService::generate_code_for_new_user(), implement the logic to create a unique, human-readable code (e.g., JANE1A2B).
Use a do-while loop to guarantee uniqueness.
Inside the loop, generate a code.
Call a new method on UserRepository, such as findUserIdByReferralCode(), to check if the code already exists.
If it doesn't exist, exit the loop.
Persist the Code: Once a unique code is generated, call another new method on UserRepository, saveReferralCode(UserId $userId, string $code), to save it to the user's meta.
Expose in API:
The SessionUserDTO already has a referral_code property. In UserService::get_user_session_data(), you need to fetch this code from the UserRepository and populate the DTO.
In SessionController::get_session_data(), ensure this new DTO property is correctly serialized into the final JSON response.
Enable the Test: Remove the .skip() from the test in 04-referral-system.spec.js and run it. It should now pass by successfully finding the referral code in the session response.
Definition of Done: All tests in 04-referral-system.spec.js pass. New users are automatically assigned a unique referral code that is accessible via the session endpoint.
Vertical Slice 9: Activating the Gamification Engine
Objective: Implement the full achievement-awarding lifecycle. This requires creating test-specific data setup and verifying that the event-driven GamificationService correctly evaluates rules and grants rewards.
Target Test Files to Pass:
tests-api/06-gamification.spec.js (The two skipped tests).
Key Application Files to Review & Refactor:
Test Infrastructure: tests-api/test-helper.php
The Listener/Orchestrator: includes/CannaRewards/Services/GamificationService.php
The Event Source: includes/CannaRewards/Commands/ProcessProductScanCommandHandler.php (no changes needed, just verify it fires the event).
Refactoring Instructions:
Create Test Data Helper: In test-helper.php, add a new action: setup_test_achievement.
This action should directly interact with $wpdb.
It should first DELETE any existing achievement with the key scan_3_times from the {$wpdb->prefix}canna_achievements table to ensure a clean state.
It should then INSERT a new row representing the test achievement:
achievement_key: 'scan_3_times'
title: 'Triple Scanner'
trigger_event: 'product_scanned'
trigger_count: 3
points_reward: 500
conditions: [] (an empty JSON array)
Update the Gamification Test:
In 06-gamification.spec.js, create a beforeAll hook that calls the new setup_test_achievement helper action.
Enable the skipped test "User scans products and achievements are awarded".
The test logic should perform three separate product scans for the test user.
After the third scan, wait for 2-3 seconds to allow for event processing.
Call the /users/me/session endpoint (or a future profile endpoint) and assert that the user's point balance has increased by the 500 bonus points. You may also want a way to check which achievements a user has unlocked.
Verify the Logic: Review GamificationService::unlock_achievement(). Confirm that if an achievement has points_reward > 0, it correctly creates and dispatches a GrantPointsCommand to the EconomyService.
Definition of Done: All tests in 06-gamification.spec.js pass. The system can dynamically award achievements and bonus points based on user actions and predefined rules created for a test.
Vertical Slice 10: Final Hardening - Rank Policy
Objective: Enforce the final business rule: preventing users from redeeming rewards for which they do not meet the rank requirement. This completes the "failure scenarios" testing and fully hardens the economy.
Target Test File to Pass:
tests-api/07-failure-scenarios.spec.js (The final skipped test).
Key Application Files to Review & Refactor:
Test Infrastructure: tests-api/test-helper.php
The Policy: includes/CannaRewards/Policies/UserMustMeetRankRequirementPolicy.php
The Enforcer: includes/CannaRewards/Services/EconomyService.php
Refactoring Instructions:
Create Test Data Helper: In test-helper.php, add a new action: setup_rank_restricted_product.
This action should find a specific test product (e.g., by SKU PWT-RANK-LOCK).
It must update that product's post meta, setting the _required_rank key to the value gold.
Enable and Write the Test:
In 07-failure-scenarios.spec.js, add a beforeEach hook that calls the new helper to ensure the product is always configured for the test.
Enable the skipped test "Try to redeem a reward without the required rank".
The test logic should:
Create a new user.
Use the test-helper.php to set their lifetime points to a low value (e.g., 100), ensuring they are a 'member' or 'bronze' rank.
Attempt to redeem the rank-locked product.
Assert that the API response ok() is false.
Assert that the status code is 403 (Forbidden).
Assert that the error message in the response body matches the exception message from the policy.
Verify the Policy: Review UserMustMeetRankRequirementPolicy::check(). It must fetch the product's required rank slug and the user's current rank DTO. It then compares the user's rank's point value against the required rank's point value. If the user's points are less than the required rank's points, it must throw new Exception(...) with a 403 code.
Definition of Done: All tests, including the final skipped one, now pass. Your application is fully tested, architecturally pure, and robust against all defined business rules.
Execute these final slices, and your refactoring masterpiece will be complete.