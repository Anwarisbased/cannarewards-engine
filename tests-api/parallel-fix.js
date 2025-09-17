// Utility functions to help with parallel test execution
import { test } from '@playwright/test';

// Generate a unique identifier for test isolation (short version for WordPress username limits)
export function generateUniqueTestId() {
  // Generate a short unique ID that's safe for WordPress usernames (max 60 chars)
  return `${Math.random().toString(36).substr(2, 9)}_${Date.now().toString().substr(-6)}`;
}

// Generate a unique email for test users
export function generateUniqueEmail(prefix = 'test') {
  // Keep email under 60 characters to avoid WordPress username limits
  return `${prefix}_${generateUniqueTestId()}@example.com`;
}

// Generate a unique QR code for tests
export function generateUniqueQRCode(prefix = 'PWT') {
  return `${prefix}-${generateUniqueTestId().substr(0, 8)}`;
}