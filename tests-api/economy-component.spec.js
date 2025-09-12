import { test, expect } from '@playwright/test';

test.describe('Component Test: GrantPointsCommandHandler', () => {

  const testUser = {
    email: `econ_component_test_${Date.now()}@example.com`,
    id: 0,
    password: 'test-password-123'
  };

  // Before all tests in this file, create a dedicated user.
  test.beforeAll(async ({ request }) => {
    // Clean up any previous failed runs
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'delete_user_by_email', email: testUser.email }
    });

    // Register the new user
    const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
        data: {
          email: testUser.email,
          password: testUser.password,
          firstName: 'EconomyComponent',
          lastName: 'Test',
          agreedToTerms: true
        }
    });
    expect(registerResponse.ok(), 'Failed to register test user for economy component tests.').toBeTruthy();
    const body = await registerResponse.json();
    testUser.id = body.data.userId;
    expect(testUser.id).toBeGreaterThan(0);
  });

  // NEW: Before each test, clear the rank cache to ensure our code changes are used.
  test.beforeEach(async ({ request }) => {
    const clearCacheResponse = await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'clear_rank_cache' }
    });
    expect(clearCacheResponse.ok()).toBeTruthy();
  });

  // After all tests, clean up the user we created.
  test.afterAll(async ({ request }) => {
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'delete_user_by_email', email: testUser.email }
    });
  });

  test('should correctly apply a rank multiplier to granted points', async ({ request }) => {
    // ARRANGE: Use our helper to set the user's state.
    // Let's make them a Gold member (lifetime points > 10000) with a known starting balance.
    // The 'gold' rank has a 2.0x multiplier.
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: {
        action: 'reset_user_by_email',
        email: testUser.email,
        points_balance: 1000,
        lifetime_points: 15000
      }
    });

    // ACT: Call our component harness directly to execute the command handler.
    const harnessResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/component-harness.php', {
      data: {
        // Tell the harness which PHP class to execute
        component: 'CannaRewards\\Commands\\GrantPointsCommandHandler',
        // Provide the input data for the Command DTO
        input: {
          user_id: testUser.id,
          base_points: 100,
          description: 'Test grant with gold multiplier'
        }
      }
    });

    // ASSERT: Check the JSON response from the harness.
    expect(harnessResponse.ok(), `Harness failed with status ${harnessResponse.status()}`).toBeTruthy();
    const responseBody = await harnessResponse.json();
    
    expect(responseBody.success, `Harness response was not successful. Error: ${responseBody.message}`).toBe(true);

    // Gold rank has a 2.0x multiplier. 100 base points * 2.0 = 200.
    expect(responseBody.data.points_earned).toBe(200);
    // Initial balance was 1000. 1000 + 200 = 1200.
    expect(responseBody.data.new_points_balance).toBe(1200);
  });
  
  test('should not apply a multiplier for a standard member', async ({ request }) => {
    // ARRANGE: Ensure user is a standard member with a fresh balance.
    await request.post('wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: {
        action: 'reset_user_by_email',
        email: testUser.email,
        points_balance: 500,
        lifetime_points: 500
      }
    });

    // ACT: Call the harness.
    const harnessResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/component-harness.php', {
      data: {
        component: 'CannaRewards\\Commands\\GrantPointsCommandHandler',
        input: {
          user_id: testUser.id,
          base_points: 100,
          description: 'Test grant with no multiplier'
        }
      }
    });

    // ASSERT:
    expect(harnessResponse.ok()).toBeTruthy();
    const responseBody = await harnessResponse.json();
    expect(responseBody.success).toBe(true);

    // Member rank has no multiplier (or 1.0x). Points earned should be base points.
    expect(responseBody.data.points_earned).toBe(100);
    // Initial balance was 500. 500 + 100 = 600.
    expect(responseBody.data.new_points_balance).toBe(600);
  });
});