import { test, expect } from '@playwright/test';

test.describe('Forensic Audit: User Rank-Up Lifecycle', () => {

  let authToken;
  let testUserEmail;

  test.beforeAll(async ({ request }) => {
    console.log('\n--- FORENSIC AUDIT: SETUP ---');

    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'clear_rank_cache' }
    });
    console.log('- Step 0.0: Cleared rank structure cache.');

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

    await test.step('STEP 1: ARRANGE - Set user to 4800 lifetime points', async () => {
        const resetResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
            form: {
                action: 'reset_user_by_email', email: testUserEmail,
                points_balance: 100, lifetime_points: 4800
            }
        });
        expect(resetResponse.ok(), 'STEP 1 FAILED: The test helper script failed to reset the user.').toBeTruthy();
        console.log('  - OK: User lifetime points set to 4800.');
    });

    await test.step("STEP 2: VERIFY - API must report user's starting rank as 'bronze'", async () => {
        const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
            headers: { 'Authorization': `Bearer ${authToken}` }
        });
        const sessionData = await sessionResponse.json();
        expect(sessionData.data.rank.key, "STEP 2 FAILED: User should have started as 'bronze' but did not.").toBe('bronze');
        console.log(`  - OK: Initial rank is correctly reported as '${sessionData.data.rank.key}'.`);
    });

    await test.step("STEP 3: PREPARE - Prepare test product, simulate previous scan, and reset QR code", async () => {
        // First prepare the test product to ensure it has correct points
        const prepareResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
          form: { action: 'prepare_test_product' }
        });
        console.log(`  - Prepare product response status: ${prepareResponse.status()}`);
        if (!prepareResponse.ok()) {
            const errorText = await prepareResponse.text();
            console.log(`  - Prepare product error text: ${errorText}`);
        }
        expect(prepareResponse.ok(), 'STEP 3 FAILED: Could not prepare test product.').toBeTruthy();
        const prepareData = await prepareResponse.json();
        console.log(`  - Prepare product message: ${prepareData.message}`);
        
        // Simulate a previous scan so this won't be treated as first scan
        const simulateResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
          form: { action: 'simulate_previous_scan', email: testUserEmail }
        });
        console.log(`  - Simulate previous scan response status: ${simulateResponse.status()}`);
        if (!simulateResponse.ok()) {
            const errorText = await simulateResponse.text();
            console.log(`  - Simulate previous scan error text: ${errorText}`);
        }
        expect(simulateResponse.ok(), 'STEP 3 FAILED: Could not simulate previous scan.').toBeTruthy();
        console.log(`  - OK: Previous scan simulated.`);
        
        // Then reset the QR code to use SKU PWT-001
        const testCode = 'PWT-RANKUP-AUDIT';
        const qrResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
          form: { action: 'reset_qr_code', code: testCode }
        });
        console.log(`  - Reset QR response status: ${qrResponse.status()}`);
        if (!qrResponse.ok()) {
            const errorText = await qrResponse.text();
            console.log(`  - Reset QR error text: ${errorText}`);
        }
        expect(qrResponse.ok(), 'STEP 3 FAILED: Could not reset QR code.').toBeTruthy();
        console.log(`  - OK: QR code is ready to be scanned with SKU PWT-001.`);
    });
    
    await test.step("STEP 4: ACT - Scan product to award 400 points", async () => {
        const testCode = 'PWT-RANKUP-AUDIT';
        const claimResponse = await request.post('/wp-json/rewards/v2/actions/claim', {
          headers: { 'Authorization': `Bearer ${authToken}` },
          data: { code: testCode }
        });
        
        // Log the response details for debugging
        console.log(`  - Claim response status: ${claimResponse.status()}`);
        if (!claimResponse.ok()) {
            const errorText = await claimResponse.text();
            console.log(`  - Claim error text: ${errorText}`);
        }
        
        expect(claimResponse.ok(), `STEP 4 FAILED: The /actions/claim endpoint returned an error.`).toBeTruthy();
        const claimData = await claimResponse.json();
        console.log(`  - OK: Scan successful. API reports ${claimData.data.points_earned} points earned.`);
        
        // Additional check to ensure points were actually earned
        expect(claimData.data.points_earned, "STEP 4 FAILED: No points were earned from the scan.").toBeGreaterThan(0);
    });
    
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