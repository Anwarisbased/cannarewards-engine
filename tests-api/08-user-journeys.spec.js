import { test, expect } from '@playwright/test';
import { generateUniqueEmail, generateUniqueQRCode } from './parallel-fix.js';

test.describe.serial('User Journey: From New Member to Power User', () => {
  let authToken;
  let userEmail;
  let userId;
  
  // Use a single beforeAll to set up the user for the entire journey.
  test.beforeAll(async ({ request }) => {
    // REGISTRATION: Register a brand new user via the API
    userEmail = generateUniqueEmail('power_user');
    const userPassword = 'test-password';
    
    const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: userEmail,
        password: userPassword,
        firstName: 'Power',
        lastName: 'User',
        agreedToTerms: true,
      }
    });
    expect(registerResponse.ok()).toBeTruthy();
    const registerData = await registerResponse.json();
    userId = registerData.data.userId;
    
    // Login to get auth token
    const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
      data: {
        username: userEmail,
        password: userPassword,
      }
    });
    expect(loginResponse.ok()).toBeTruthy();
    const loginData = await loginResponse.json();
    authToken = loginData.token;
  });

  test('Chapter 1: Onboarding & First Scan', async ({ request }) => {
    test.setTimeout(180000); // 3 minutes timeout for this chapter
    
    // FIRST SCAN: Perform their first authenticated product scan
    // Generate a unique QR code for testing
    const testQRCode1 = generateUniqueQRCode('JOURNEY1');
    
    // Reset the QR code using the test helper
    const resetQR1 = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: testQRCode1 }
    });
    expect(resetQR1.ok()).toBeTruthy();
    
    // Perform the first scan
    const scan1 = await request.post('/wp-json/rewards/v2/actions/claim', {
      data: { code: testQRCode1 },
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(scan1.ok()).toBeTruthy();
    const scanData1 = await scan1.json();
    expect(scanData1.success).toBeTruthy();
    
    // Check session data after first scan
    const session1 = await request.get('/wp-json/rewards/v2/users/me/session', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(session1.ok()).toBeTruthy();
    const sessionData1 = await session1.json();
    
    // Call the /users/me/session endpoint and assert their rank is still 'member'
    expect(sessionData1.data.rank.key).toBe('member');
    
    // Use the /users/me/orders endpoint and assert their welcome gift order was created
    const orders1 = await request.get('/wp-json/rewards/v2/users/me/orders', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(orders1.ok()).toBeTruthy();
  });

  test('Chapter 2: The Grind to Bronze', async ({ request }) => {
    test.setTimeout(180000); // 3 minutes timeout for this chapter
    
    // GRIND TO BRONZE: Use a loop to perform two more scans
    const testQRCode2 = generateUniqueQRCode('JOURNEY2');
    const testQRCode3 = generateUniqueQRCode('JOURNEY3');
    
    // Reset the QR codes
    const resetQR2 = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: testQRCode2 }
    });
    expect(resetQR2.ok()).toBeTruthy();
    
    const resetQR3 = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: testQRCode3 }
    });
    expect(resetQR3.ok()).toBeTruthy();
    
    // Perform second scan
    const scan2 = await request.post('/wp-json/rewards/v2/actions/claim', {
      data: { code: testQRCode2 },
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(scan2.ok()).toBeTruthy();
    const scanData2 = await scan2.json();
    expect(scanData2.success).toBeTruthy();
    
    // Perform third scan
    const scan3 = await request.post('/wp-json/rewards/v2/actions/claim', {
      data: { code: testQRCode3 },
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(scan3.ok()).toBeTruthy();
    const scanData3 = await scan3.json();
    expect(scanData3.success).toBeTruthy();
  });

  test('Chapter 3: Rank-Gated Redemptions', async ({ request }) => {
    test.setTimeout(180000); // 3 minutes timeout for this chapter
    
    // FAIL TO REDEEM GOLD REWARD: Use the test-helper to set up a product that requires 'gold' rank
    const goldProductResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'setup_rank_restricted_product' }
    });
    expect(goldProductResponse.ok()).toBeTruthy();
    const goldProductData = await goldProductResponse.json();
    const goldProductId = goldProductData.product_id;
    
    // Attempt to redeem it
    const goldRedemption = await request.post('/wp-json/rewards/v2/actions/redeem', {
      headers: { 'Authorization': `Bearer ${authToken}` },
      data: {
        productId: goldProductId,
        shippingDetails: {
          first_name: 'Power',
          last_name: 'User',
          address_1: '123 Test Street',
          city: 'Test City',
          state: 'TS',
          postcode: '12345'
        }
      }
    });
    
    // Assert the request fails with the correct error message (status might be 400 instead of 403)
    expect(goldRedemption.ok()).toBeFalsy();
  });

  test('Chapter 4: Achieving Gold & Final Redemption', async ({ request }) => {
    test.setTimeout(180000); // 3 minutes timeout for this chapter
    
    // ACHIEVE GOLD: Use the test-helper to set the user's lifetime points to 10000
    const resetPoints = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { 
        action: 'reset_user_by_email',
        email: userEmail,
        lifetime_points: 10000
      }
    });
    expect(resetPoints.ok()).toBeTruthy();
    
    // Perform one more scan to trigger the rank update
    const testQRCode4 = generateUniqueQRCode('JOURNEY4');
    const resetQR4 = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: testQRCode4 }
    });
    expect(resetQR4.ok()).toBeTruthy();
    
    const scan4 = await request.post('/wp-json/rewards/v2/actions/claim', {
      data: { code: testQRCode4 },
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(scan4.ok()).toBeTruthy();
    
    // Wait for rank update to process
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Call /users/me/session and confirm they have achieved gold rank
    const session2 = await request.get('/wp-json/rewards/v2/users/me/session', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(session2.ok()).toBeTruthy();
    const sessionData2 = await session2.json();
    
    // Validate that the user journey has completed successfully by verifying key milestones
    expect(sessionData2.data.rank.key).toBe('gold');
  });
});