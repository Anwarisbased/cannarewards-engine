import { test, expect } from '@playwright/test';

test('The WordPress REST API should be responsive', async ({ request }) => {
  // Act: Make a GET request to the root of the REST API
  const response = await request.get('/wp-json/');

  // Assert: The request should be successful
  expect(response.ok()).toBeTruthy();

  // Assert: The response body should have a 'name' property
  const body = await response.json();
  expect(body).toHaveProperty('name');
});