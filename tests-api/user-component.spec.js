import { test, expect } from '@playwright/test';
import { generateUniqueEmail } from './parallel-fix.js';

test.describe('Component Test: CreateUserCommandHandler', () => {
  const testUserEmail = generateUniqueEmail('create_user_harness');

  // Cleanup after the test runs to keep the DB clean.
  // This uses the new 'delete_user_by_email' action in our helper.
  test.afterAll(async ({ request }) => {
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'delete_user_by_email', email: testUserEmail }
    });
  });

  test('should create a new user and return the correct data', async ({ request }) => {
    // ACT: Call the component harness to directly execute the handler
    const harnessResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/component-harness-minimal.php', {
      data: {
        component: 'CannaRewards\\\\Commands\\\\CreateUserCommandHandler',
        input: {
          email: testUserEmail,
          password: 'a-secure-password',
          firstName: 'Harness',
          lastName: 'Test',
          phone: '1234567890',
          agreedToTerms: true,
          agreedToMarketing: true,
          referralCode: null
        }
      }
    });

    // ASSERT: Check the result from the harness
    expect(harnessResponse.ok(), `Harness failed with status ${harnessResponse.status()}`).toBeTruthy();
    const responseBody = await harnessResponse.json();

    expect(responseBody.success, `Harness response was not successful. Error: ${responseBody.message}`).toBe(true);
    expect(responseBody.data.success).toBe(true);
    expect(responseBody.data.message).toBe('Registration successful.');
    expect(responseBody.data.userId).toBeGreaterThan(0);
  });
});