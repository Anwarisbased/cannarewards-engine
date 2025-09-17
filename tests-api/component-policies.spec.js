import { test, expect } from '@playwright/test';
import { generateUniqueEmail } from './parallel-fix.js';

test.describe('Component-Level Policy Enforcement', () => {

  test('EconomyService should block redemption when UserMustBeAbleToAffordRedemptionPolicy fails', async ({ request }) => {
  test.setTimeout(120000); // 2 minutes timeout
    // Arrange: Create a test user and set their point balance to 100
    const userEmail = generateUniqueEmail('policy_insufficient');
    const userPassword = 'test-password';
    
    // Register the user
    const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: userEmail,
        password: userPassword,
        firstName: 'Policy',
        lastName: 'Test',
        agreedToTerms: true,
      }
    });
    expect(registerResponse.ok()).toBeTruthy();
    const registerData = await registerResponse.json();
    const userId = registerData.data.userId;
    
    // Login to get auth token
    const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
      data: {
        username: userEmail,
        password: userPassword,
      }
    });
    expect(loginResponse.ok()).toBeTruthy();
    const loginData = await loginResponse.json();
    const authToken = loginData.token;
    
    // Set user's point balance to 100
    const resetResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { 
        action: 'reset_user_by_email',
        email: userEmail,
        points_balance: 100
      }
    });
    expect(resetResponse.ok()).toBeTruthy();
    
    // Set up a product that costs 500 points
    const productSetupResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'prepare_test_product' }
    });
    expect(productSetupResponse.ok()).toBeTruthy();
    
    // Get the test product ID
    const getProductResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'get_test_product_id' }
    });
    expect(getProductResponse.ok()).toBeTruthy();
    const productData = await getProductResponse.json();
    const productId = productData.product_id;
    
    // Act: Call the component harness to execute EconomyService->handle() with a RedeemRewardCommand
    const harnessResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/component-harness-economy.php', {
      data: {
        component: 'CannaRewards\\\\Services\\\\EconomyService',
        method: 'handle',
        input: {
          command: 'RedeemRewardCommand',
          userId: userId, // Use actual user ID
          productId: productId, // Test product ID that costs 500 points
          shippingDetails: {
            first_name: 'Policy',
            last_name: 'Test',
            address_1: '123 Test St',
            city: 'Test City',
            state: 'TS',
            postcode: '12345'
          }
        }
      },
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });
    
    // Assert: The harness response should fail with the specific policy error
    expect(harnessResponse.ok()).toBeFalsy();
    
    const responseBody = await harnessResponse.json();
    expect(responseBody.error).toBe('Exception');
    expect(responseBody.message).toBe('Insufficient points.');
  });

  test('EconomyService should block redemption when UserMustMeetRankRequirementPolicy fails', async ({ request }) => {
    // Arrange: Create a user with 100 lifetime points (member rank) and set up a gold-rank product
    const userEmail = generateUniqueEmail('policy_rank');
    const userPassword = 'test-password';
    
    // Register the user
    const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: userEmail,
        password: userPassword,
        firstName: 'Rank',
        lastName: 'Policy',
        agreedToTerms: true,
      }
    });
    expect(registerResponse.ok()).toBeTruthy();
    const registerData = await registerResponse.json();
    const userId = registerData.data.userId;
    
    // Login to get auth token
    const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
      data: {
        username: userEmail,
        password: userPassword,
      }
    });
    expect(loginResponse.ok()).toBeTruthy();
    const loginData = await loginResponse.json();
    const authToken = loginData.token;
    
    // Set user's lifetime points to 100 (member rank)
    const resetResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { 
        action: 'reset_user_by_email',
        email: userEmail,
        lifetime_points: 100
      }
    });
    expect(resetResponse.ok()).toBeTruthy();
    
    // Set up a rank-restricted product
    const rankProductResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'setup_rank_restricted_product' }
    });
    expect(rankProductResponse.ok()).toBeTruthy();
    const rankProductData = await rankProductResponse.json();
    const productId = rankProductData.product_id;
    
    // Act: Call the component harness to execute EconomyService->handle() with a RedeemRewardCommand
    const harnessResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/component-harness-economy.php', {
      data: {
        component: 'CannaRewards\\Services\\EconomyService',
        method: 'handle',
        input: {
          command: 'RedeemRewardCommand',
          userId: userId, // Use actual user ID
          productId: productId,
          shippingDetails: {
            first_name: 'Rank',
            last_name: 'Policy',
            address_1: '123 Test St',
            city: 'Test City',
            state: 'TS',
            postcode: '12345'
          }
        }
      },
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });
    
    // Assert: The harness response should fail with the specific rank policy error
    expect(harnessResponse.ok()).toBeFalsy();
    
    const responseBody = await harnessResponse.json();
    expect(responseBody.error).toBe('Exception');
    expect(responseBody.message).toBe("You must be rank 'Gold' or higher to redeem this item.");
  });
});