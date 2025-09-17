import { test, expect } from '@playwright/test';
import { validateApiContract } from './api-contract-validator.js';
import { generateUniqueEmail } from './parallel-fix.js';

test.describe('Component Test: UserService Data Fetching', () => {

  let testUserEmail;
  const testUser = {
    id: 0,
    password: 'test-password-123'
  };

  // Before all tests, create a dedicated user.
  test.beforeAll(async ({ request }) => {
    testUserEmail = generateUniqueEmail('userservice_test');
    testUser.email = testUserEmail;
    
    // Clean up any previous failed runs
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'delete_user_by_email', email: testUserEmail }
    });

    // Register the new user
    const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
        data: {
          email: testUser.email,
          password: testUser.password,
          firstName: 'UserService',
          lastName: 'Test',
          agreedToTerms: true
        }
    });
    expect(registerResponse.ok(), 'Failed to register test user for user service tests.').toBeTruthy();
    const body = await registerResponse.json();
    testUser.id = body.data.userId;
    expect(testUser.id).toBeGreaterThan(0);
  });
  
  // After all tests, clean up the user.
  test.afterAll(async ({ request }) => {
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'delete_user_by_email', email: testUser.email }
    });
  });

  test('get_user_session_data should return a valid SessionUser DTO', async ({ request }) => {
    // ARRANGE: Set the user to a known state (e.g., Silver rank with a specific point balance)
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: {
        action: 'reset_user_by_email',
        email: testUser.email,
        points_balance: 7500,
        lifetime_points: 7500 
      }
    });

    // ACT: Call our component harness, telling it to run the UserService method.
    const harnessResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/component-harness.php', {
      data: {
        component: 'CannaRewards\\Services\\UserService',
        // We add a 'method' key to tell the harness which public method to call
        method: 'get_user_session_data',
        // The input is now just the arguments for that method
        input: {
          user_id: testUser.id,
        }
      }
    });

    // ASSERT: Check the response from the harness.
    expect(harnessResponse.ok()).toBeTruthy();
    const responseBody = await harnessResponse.json();
    expect(responseBody.success).toBe(true);
    
    // Validate the structure of the returned data against our API contract's SessionUser component.
    // This is a powerful way to ensure our internal DTOs match our public contract.
    const sessionData = responseBody.data;
    const validate = await validateApiContract({
        json: async () => ({ success: true, data: sessionData }),
        status: () => 200
    }, '/users/me/session', 'get');
    expect(validate).toBe(true);

    // Assert specific values to ensure the correct data was fetched.
    expect(sessionData.id).toBe(testUser.id);
    expect(sessionData.firstName).toBe('UserService');
    expect(sessionData.email).toBe(testUser.email);
    expect(sessionData.points_balance).toBe(7500);
    expect(sessionData.rank.key).toBe('silver');
  });
});