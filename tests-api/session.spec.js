import { test, expect } from '@playwright/test';
import { validateApiContract } from './api-contract-validator.js';
import { generateUniqueEmail } from './parallel-fix.js';

test.describe('API Endpoint: /users/me/session', () => {
  const testUser = {
    email: generateUniqueEmail('session_api_test'),
    id: 0,
    password: 'test-password-123',
    authToken: ''
  };

  // Before all tests, create and log in a dedicated user.
  test.beforeAll(async ({ request }) => {
    // Register the new user
    const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
        data: {
          email: testUser.email,
          password: testUser.password,
          firstName: 'SessionAPI',
          agreedToTerms: true
        }
    });
    expect(registerResponse.ok()).toBeTruthy();
    const body = await registerResponse.json();
    testUser.id = body.data.userId;

    // Log in to get the auth token
    const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
      data: { username: testUser.email, password: testUser.password }
    });
    expect(loginResponse.ok()).toBeTruthy();
    const loginData = await loginResponse.json();
    testUser.authToken = loginData.token;
  });

  // After all tests, clean up the user.
  test.afterAll(async ({ request }) => {
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'delete_user_by_email', email: testUser.email }
    });
  });

  test('should return a valid session object and match the API contract', async ({ request }) => {
    // ARRANGE: Set user to a known state
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: {
        action: 'reset_user_by_email',
        email: testUser.email,
        points_balance: 1234,
        lifetime_points: 5678
      }
    });

    // ACT: Call the actual REST API endpoint
    const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
      headers: {
        'Authorization': `Bearer ${testUser.authToken}`
      }
    });

    // ASSERT: The response is valid and matches the OpenAPI spec
    expect(sessionResponse.ok()).toBeTruthy();
    await expect(async () => await validateApiContract(sessionResponse, '/users/me/session', 'get')).toPass();

    const responseBody = await sessionResponse.json();
    expect(responseBody.data.id).toBe(testUser.id);
    expect(responseBody.data.points_balance).toBe(1234);
    expect(responseBody.data.rank.key).toBe('silver'); // 5678 points should be silver
  });
});