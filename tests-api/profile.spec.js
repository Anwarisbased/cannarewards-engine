import { test, expect } from '@playwright/test';
import { generateUniqueEmail } from './parallel-fix.js';

// Helper function to create a new user with a UNIQUE email.
async function createTestUser(request) {
  const uniqueEmail = generateUniqueEmail('profile_user');
  
  // First, ensure the user doesn't exist from a previous failed run.
  await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_user_by_email', email: uniqueEmail }
  });

  const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
    data: {
      email: uniqueEmail,
      password: 'test-password',
      firstName: 'Profile',
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

test.describe('User Profile Management', () => {

  let authToken;
  let testUserEmail;

  // Before all tests in this file, create our unique test user once.
  test.beforeAll(async ({ request }) => {
    const { authToken: token, userEmail } = await createTestUser(request);
    authToken = token;
    testUserEmail = userEmail;
  });

  test('A user can update their profile information', async ({ request }) => {
    // Update the user's profile
    const updateResponse = await request.post('/wp-json/rewards/v2/users/me/profile', {
      headers: {
        'Authorization': `Bearer ${authToken}`,
      },
      data: {
        firstName: 'UpdatedFirstName',
        lastName: 'UpdatedLastName',
        phone: '555-123-4567'
      }
    });
    
    if (!updateResponse.ok()) {
      console.log('Update failed with status:', updateResponse.status());
      console.log('Update response:', await updateResponse.text());
    }

    expect(updateResponse.ok()).toBeTruthy();
    const updateData = await updateResponse.json();
    expect(updateData.success).toBe(true);

    // Verify the changes are reflected in the session data
    const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
      headers: {
        'Authorization': `Bearer ${authToken}`,
      }
    });

    expect(sessionResponse.ok()).toBeTruthy();
    const sessionData = await sessionResponse.json();
    
    expect(sessionData.data.firstName).toBe('UpdatedFirstName');
    expect(sessionData.data.lastName).toBe('UpdatedLastName');
    expect(sessionData.data.shipping.first_name).toBe('UpdatedFirstName');
    expect(sessionData.data.shipping.last_name).toBe('UpdatedLastName');
  });

});