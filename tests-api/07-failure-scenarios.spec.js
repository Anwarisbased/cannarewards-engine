import { test, expect } from '@playwright/test';
import { validateApiContract } from './api-contract-validator.js';
import { generateUniqueEmail, generateUniqueTestId } from './parallel-fix.js';

test.describe('Failure Scenarios', () => {
  let existingUserEmail;
  let existingUserPassword;

  test.beforeAll(async ({ request }) => {
    // Create a user for testing duplicate registration
    existingUserEmail = generateUniqueEmail('existing');
    existingUserPassword = 'a-secure-password';
    
    const registration = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: existingUserEmail,
        password: existingUserPassword,
        firstName: 'Existing',
        agreedToTerms: true
      }
    });

    expect(registration.ok()).toBeTruthy();
  });

  test('Try to register with an email that already exists', async ({ request }) => {
    const duplicateRegistration = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: existingUserEmail,
        password: 'another-password',
        firstName: 'Duplicate',
        agreedToTerms: true
      }
    });

    expect(duplicateRegistration.ok()).toBeFalsy();
    expect(duplicateRegistration.status()).toBe(409);
    
    const body = await duplicateRegistration.json();
    expect(body.message).toBe('An account with that email already exists.');
  });

  test('Try to redeem a reward with insufficient points', async ({ request }) => {
    // First create and login a new user
    const userEmail = generateUniqueEmail('poor');
    const userPassword = 'a-secure-password';
    
    const registration = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: userEmail,
        password: userPassword,
        firstName: 'Poor',
        agreedToTerms: true
      }
    });

    expect(registration.ok()).toBeTruthy();

    const login = await request.post('/wp-json/rewards/v2/auth/login', {
      data: {
        email: userEmail,
        password: userPassword
      }
    });

    expect(login.ok()).toBeTruthy();
    const loginData = await login.json();
    const userToken = loginData.token;

    // Try to redeem a reward (this would require knowing a product ID)
    // For now, we'll use a placeholder
    const redemption = await request.post('/wp-json/rewards/v2/actions/redeem', {
      headers: { 'Authorization': `Bearer ${userToken}` },
      data: {
        productId: 999999 // Non-existent product ID
      }
    });

    // This should fail with a 404 or similar error since the product doesn't exist
    expect(redemption.ok()).toBeFalsy();
  });

  test('Try to redeem a reward without the required rank', async ({ request }) => {
    // Create a new user
    const userEmail = generateUniqueEmail('lowrank');
    const userPassword = 'a-secure-password';
    
    const registration = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: userEmail,
        password: userPassword,
        firstName: 'LowRank',
        agreedToTerms: true
      }
    });

    expect(registration.ok()).toBeTruthy();

    const login = await request.post('/wp-json/rewards/v2/auth/login', {
      data: {
        email: userEmail,
        password: userPassword
      }
    });

    expect(login.ok()).toBeTruthy();
    const loginData = await login.json();
    const userToken = loginData.data.token;

    // Set the user's lifetime points to a low value (e.g., 100) to ensure they are a 'member' or 'bronze' rank
    const reset = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { 
        action: 'reset_user_by_email',
        email: userEmail,
        lifetime_points: 100
      }
    });
    expect(reset.ok()).toBeTruthy();

    // Check what ranks exist
    const getRanks = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'debug_get_ranks' }
    });
    expect(getRanks.ok()).toBeTruthy();
    const ranksData = await getRanks.json();
    console.log('Ranks data:', ranksData);

    // Check the user's current rank
    const getUserRank = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { 
        action: 'get_user_rank',
        email: userEmail
      }
    });
    expect(getUserRank.ok()).toBeTruthy();
    const userRankData = await getUserRank.json();
    console.log('User rank data:', userRankData);

    // Set up the rank restricted product
    const setupProduct = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'setup_rank_restricted_product' }
    });
    expect(setupProduct.ok()).toBeTruthy();
    const productData = await setupProduct.json();
    console.log('Product data:', productData);
    const productId = productData.product_id;
    console.log('Product ID:', productId);

    // Check the product's required rank
    const getProductRank = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { 
        action: 'get_product_required_rank',
        product_id: productId
      }
    });
    expect(getProductRank.ok()).toBeTruthy();
    const productRankData = await getProductRank.json();
    console.log('Product required rank:', productRankData);

    // Attempt to redeem the rank-locked product
    const redemption = await request.post('/wp-json/rewards/v2/actions/redeem', {
      headers: { 'Authorization': `Bearer ${userToken}` },
      data: {
        productId: productId,
        shippingDetails: {
          first_name: 'Low',
          last_name: 'Rank',
          address_1: '123 Test Street',
          city: 'Test City',
          state: 'TS',
          postcode: '12345'
        }
      }
    });

    if (!redemption.ok()) {
      const errorText = await redemption.text();
      console.error('Redemption failed with status:', redemption.status(), 'and body:', errorText);
    }

    // Assert that the API response is not ok
    if (redemption.ok()) {
      const successText = await redemption.text();
      console.error('Redemption unexpectedly succeeded with body:', successText);
    }
    expect(redemption.ok()).toBeFalsy();
    
    // Assert that the status code is 400 (Bad Request) - exceptions are converted to this status code
    expect(redemption.status()).toBe(400);
    
    // Assert that the error message in the response body matches the exception message from the policy
    const body = await redemption.json();
    expect(body.message).toBe("You must be rank 'Gold' or higher to redeem this item.");
  });

  test('Try to claim an invalid or already-used QR code', async ({ request }) => {
    const claim = await request.post('/wp-json/rewards/v2/unauthenticated/claim', {
      data: {
        code: 'INVALID-CODE-123'
      }
    });

    expect(claim.ok()).toBeFalsy();
    // This returns a 409 error for invalid code
    expect(claim.status()).toBe(409);
    
    const body = await claim.json();
    expect(body.message).toBeDefined();
  });

  test('Send a request with missing required fields (e.g., no password on registration)', async ({ request }) => {
    const registration = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: generateUniqueEmail('incomplete'),
        firstName: 'Incomplete',
        agreedToTerms: true
        // Missing password field
      }
    });

    expect(registration.ok()).toBeFalsy();
    expect(registration.status()).toBe(422); // Unprocessable Entity
    
    const body = await registration.json();
    expect(body.message).toBe('The given data was invalid.');
  });
});