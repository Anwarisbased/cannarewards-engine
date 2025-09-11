import { test, expect } from '@playwright/test';
import { validateApiContract } from './api-contract-validator.js';

/**
 * Helper to create a unique test user for this audit.
 */
async function createAuditUser(request) {
  const uniqueEmail = `rank_audit_${Date.now()}@example.com`;
  
  await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_user_by_email', email: uniqueEmail }
  });

  const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
    data: {
      email: uniqueEmail,
      password: 'test-password',
      firstName: 'Rank',
      lastName: 'Auditor',
      agreedToTerms: true,
    }
  });
  expect(registerResponse.ok(), `Failed to register audit user.`).toBeTruthy();

  const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
    data: { username: uniqueEmail, password: 'test-password' }
  });
  expect(loginResponse.ok(), 'Failed to log in audit user.').toBeTruthy();
  const loginData = await loginResponse.json();
  return { authToken: loginData.token, userEmail: uniqueEmail };
}


test.describe('Forensic Audit: Rank Service & Data Layer', () => {

  let authToken;
  let userEmail;

  // Before all tests, create one user for the entire suite.
  test.beforeAll(async ({ request }) => {
    const userData = await createAuditUser(request);
    authToken = userData.authToken;
    userEmail = userData.userEmail;
  });
  
  // Before each test, clear the rank cache to ensure we're not getting stale data.
  test.beforeEach(async ({ request }) => {
      const cacheClear = await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
          form: { action: 'clear_rank_cache' }
      });
      expect(cacheClear.ok()).toBeTruthy();
  });

  // A helper function to set points and get the user's current rank from the API.
  async function setUserPointsAndVerifyRank(request, lifetimePoints, expectedRankKey) {
    // ARRANGE: Set the user's lifetime points using our helper.
    const setPoints = await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
        form: { action: 'reset_user_by_email', email: userEmail, lifetime_points: lifetimePoints }
    });
    expect(setPoints.ok()).toBeTruthy();

    // ACT: Call the session endpoint to get the user's current state.
    const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
        headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(sessionResponse.ok()).toBeTruthy();

    // ASSERT Contract: Ensure the API response still matches our OpenAPI spec.
    await expect(async () => await validateApiContract(sessionResponse, '/users/me/session', 'get')).toPass();
    
    const sessionData = await sessionResponse.json();
    
    // ASSERT Logic: Verify the rank is correct for the given points.
    expect(sessionData.data.rank.key).toBe(expectedRankKey);
  }

  // --- THE TESTS ---
  // Note: These point values must correspond to the `points_required` you set
  // in your "Ranks" Custom Post Type in the WordPress admin.

  test('should assign "member" rank for 0 lifetime points', async ({ request }) => {
    await setUserPointsAndVerifyRank(request, 0, 'member');
  });
  
  test('should assign "bronze" rank for 1000+ lifetime points', async ({ request }) => {
    await setUserPointsAndVerifyRank(request, 1500, 'bronze');
  });

  test('should assign "silver" rank for 5000+ lifetime points', async ({ request }) => {
    await setUserPointsAndVerifyRank(request, 7500, 'silver');
  });

  test('should assign "gold" rank for 10000+ lifetime points', async ({ request }) => {
    await setUserPointsAndVerifyRank(request, 12000, 'gold');
  });

  test('should correctly assign the lower rank when points are exactly on the threshold', async ({ request }) => {
    await setUserPointsAndVerifyRank(request, 5000, 'silver');
  });

  test('should keep user at bronze if they are just below the silver threshold', async ({ request }) => {
    await setUserPointsAndVerifyRank(request, 4999, 'bronze');
  });

});