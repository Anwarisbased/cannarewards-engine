import { test, expect } from '@playwright/test';
import { validateApiContract } from './api-contract-validator.js';
import { generateUniqueEmail } from './parallel-fix.js';

// Helper function to create a new user with a UNIQUE email.
async function createTestUser(request) {
  const uniqueEmail = generateUniqueEmail('economy_user');
  
  // First, ensure the user doesn't exist from a previous failed run.
  await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_user_by_email', email: uniqueEmail }
  });

  const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
    data: {
      email: uniqueEmail,
      password: 'test-password',
      firstName: 'Economy',
      lastName: 'Test',
      agreedToTerms: true,
    }
  });
  expect(registerResponse.ok(), `Failed to register user ${uniqueEmail}. Body: ${await registerResponse.text()}`).toBeTruthy();

  const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
    data: {
      username: uniqueEmail,
      password: 'test-password',
    }
  });
  if (!loginResponse.ok()) {
    const errorBody = await loginResponse.text();
    console.log('Login error response:', errorBody);
  }
  expect(loginResponse.ok()).toBeTruthy();
  const loginData = await loginResponse.json();
  return { authToken: loginData.token, userEmail: uniqueEmail };
}

// A helper to reset our test user's state before each test.
async function resetTestUserState(request, email) {
    const resetResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
        form: {
            action: 'reset_user_by_email',
            email: email,
            points_balance: 10000 // Give them 10k points to start
        }
    });
    expect(resetResponse.ok()).toBeTruthy();
}


test.describe('Economy & Redemption Flow', () => {

  let authToken;
  let testUserEmail;

  // Before all tests in this file, create our unique test user once.
  test.beforeAll(async ({ request }) => {
    const { authToken: token, userEmail } = await createTestUser(request);
    authToken = token;
    testUserEmail = userEmail;
  });

  // Before each individual test, reset the user's state.
  test.beforeEach(async ({ request }) => {
    await resetTestUserState(request, testUserEmail);
  });

  test('A user with sufficient points can redeem a product', async ({ request }) => {

    const productIdToRedeem = 204; // IMPORTANT: Product ID 2 MUST exist and have points_cost=5000

    const redeemResponse = await request.post('/wp-json/rewards/v2/actions/redeem', {
      headers: {
        'Authorization': `Bearer ${authToken}`,
      },
      data: {
        productId: productIdToRedeem,
        shippingDetails: {
          first_name: "Test",
          last_name: "User",
          address_1: "123 Main St",
          city: "Anytown",
          state: "CA",
          postcode: "90210"
        }
      }
    });

    // Print error response if redemption fails
    if (!redeemResponse.ok()) {
      const errorBody = await redeemResponse.text();
      console.log('Redemption error response:', errorBody);
    }

    // --- CONTRACT ENFORCEMENT ---
    await expect(async () => await validateApiContract(redeemResponse, '/actions/redeem', 'post')).toPass();

    expect(redeemResponse.ok()).toBeTruthy();
    const redeemData = await redeemResponse.json();
    expect(redeemData.success).toBe(true);
    expect(redeemData.data.new_points_balance).toBe(5000);

    const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
        headers: {
            'Authorization': `Bearer ${authToken}`,
        }
    });

    // --- CONTRACT ENFORCEMENT ---
    await expect(async () => await validateApiContract(sessionResponse, '/users/me/session', 'get')).toPass();

    const sessionData = await sessionResponse.json();
    expect(sessionData.data.points_balance).toBe(5000);
  });

});