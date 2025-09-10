// FILE: tests-api/debug-rankup.spec.js

import { test, expect } from '@playwright/test';

// This is a standalone test file for a deep, explicit investigation of the rank-up logic.
test.describe('Forensic Audit: User Rank-Up Lifecycle', () => {

  let authToken;
  let testUserEmail;

  // Create one unique user for the entire test run.
  test.beforeAll(async ({ request }) => {
    console.log('\n--- FORENSIC AUDIT: SETUP ---');
    const uniqueEmail = `rankup_audit_${Date.now()}@example.com`;
    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_user_by_email', email: uniqueEmail }
    });

    const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: uniqueEmail, password: 'test-password', firstName: 'Rankup',
        lastName: 'Audit', agreedToTerms: true,
      }
    });
    expect(registerResponse.ok(), 'CRITICAL FAILURE: Could not register the test user.').toBeTruthy();
    
    console.log(`- Step 0.1: Registered new user. Email: ${uniqueEmail}`);

    const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
      data: { username: uniqueEmail, password: 'test-password' }
    });
    expect(loginResponse.ok(), 'CRITICAL FAILURE: Could not log in the test user.').toBeTruthy();
    const loginData = await loginResponse.json();
    authToken = loginData.token;
    testUserEmail = uniqueEmail;
    console.log('- Step 0.2: Successfully logged in and acquired auth token.');
    console.log('--- SETUP COMPLETE ---');
  });

  test('should correctly transition from bronze to silver after a product scan', async ({ request }) => {
    console.log('\n--- TEST EXECUTION ---');

    // STEP 1: ARRANGE
    await test.step('STEP 1: ARRANGE - Set user to 4800 lifetime points', async () => {
        const resetResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
            form: {
                action: 'reset_user_by_email', email: testUserEmail,
                points_balance: 100, lifetime_points: 4800 // Bronze ends at 4999, Silver is 5000
            }
        });
        expect(resetResponse.ok(), 'STEP 1 FAILED: The test helper script failed to reset the user.').toBeTruthy();
        console.log('  - OK: User lifetime points set to 4800.');
    });

    // STEP 2: VERIFY INITIAL STATE
    await test.step("STEP 2: VERIFY - API must report user's starting rank as 'bronze'", async () => {
        const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
            headers: { 'Authorization': `Bearer ${authToken}` }
        });
        const sessionData = await sessionResponse.json();
        expect(sessionData.data.rank.key, "STEP 2 FAILED: User should have started as 'bronze' but did not.").toBe('bronze');
        console.log(`  - OK: Initial rank is correctly reported as '${sessionData.data.rank.key}'.`);
    });

    // STEP 3: PREPARE ACTION
    await test.step("STEP 3: PREPARE - Reset QR code 'PWT-RANKUP-AUDIT'", async () => {
        const testCode = 'PWT-RANKUP-AUDIT';
        await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
          form: { action: 'reset_qr_code', code: testCode }
        });
        console.log(`  - OK: QR code is ready to be scanned.`);
    });
    
    // STEP 4: ACT
    await test.step("STEP 4: ACT - Scan product to award 400 points", async () => {
        const testCode = 'PWT-RANKUP-AUDIT';
        const claimResponse = await request.post('/wp-json/rewards/v2/actions/claim', {
          headers: { 'Authorization': `Bearer ${authToken}` },
          data: { code: testCode } // PWT-001 awards 400 points
        });
        expect(claimResponse.ok(), `STEP 4 FAILED: The /actions/claim endpoint returned an error.`).toBeTruthy();
        const claimData = await claimResponse.json();
        console.log(`  - OK: Scan successful. API reports ${claimData.data.points_earned} points earned.`);
    });
    
    // STEP 5: ASSERT FINAL STATE
    await test.step("STEP 5: ASSERT - API must now report user's rank as 'silver'", async () => {
        const finalSession = await request.get('/wp-json/rewards/v2/users/me/session', {
            headers: { 'Authorization': `Bearer ${authToken}` }
        });
        const finalSessionData = await finalSession.json();
        console.log(`  - INFO: Final rank reported by API: '${finalSessionData.data.rank.key}'`);
        expect(finalSessionData.data.rank.key, "STEP 5 FAILED: The final rank was not 'silver'.").toBe('silver');
        console.log('  - OK: Final rank is correct.');
    });
    
    console.log('--- ✅ ✅ ✅ SUCCESS: Rank-up lifecycle verified. ---');
  });
});