import test from 'node:test';
import assert from 'node:assert/strict';

import {
  assertInternalNavigation,
  classifyNavigation,
  isUrlAllowed,
} from '../src/security/navigation-policy.mjs';

const allowed = ['archive.kronnos.dev', 'staging.archive.kronnos.dev'];

test('allows navigation only for allowlisted hosts', () => {
  assert.equal(isUrlAllowed('https://archive.kronnos.dev/documents', allowed), true);
  assert.equal(isUrlAllowed('https://google.com', allowed), false);
  assert.equal(isUrlAllowed('file:///tmp/test', allowed), false);
});

test('classifies internal and external navigation', () => {
  assert.equal(classifyNavigation('https://staging.archive.kronnos.dev/login', allowed), 'internal');
  assert.equal(classifyNavigation('https://example.org', allowed), 'external');
});

test('throws when blocked host is navigated', () => {
  assert.throws(() => assertInternalNavigation('https://evil.example', allowed));
});
