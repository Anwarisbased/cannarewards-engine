// Utility functions to help with parallel test execution
import { test } from '@playwright/test';

// Generate a unique identifier for test isolation
export function generateUniqueTestId() {
  return `${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
}

// Generate a unique email for test users
export function generateUniqueEmail(prefix = 'test') {
  return `${prefix}_${generateUniqueTestId()}@example.com`;
}

// Generate a unique QR code for tests
export function generateUniqueQRCode(prefix = 'PWT') {
  return `${prefix}-${generateUniqueTestId().substr(0, 8)}`;
}