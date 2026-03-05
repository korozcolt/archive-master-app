import test from 'node:test';
import assert from 'node:assert/strict';

import {
  loadDesktopRuntimeConfig,
  normalizeBaseUrl,
  parseAllowedHosts,
} from '../src/config/desktop-config.mjs';

test('parseAllowedHosts supports CSV and JSON', () => {
  assert.deepEqual(parseAllowedHosts('archive.kronnos.dev,foo.bar'), ['archive.kronnos.dev', 'foo.bar']);
  assert.deepEqual(parseAllowedHosts('["one.com","two.com"]'), ['one.com', 'two.com']);
});

test('normalizeBaseUrl normalizes and validates url', () => {
  assert.equal(normalizeBaseUrl('https://archive.kronnos.dev/portal?a=1'), 'https://archive.kronnos.dev');
  assert.throws(() => normalizeBaseUrl('ftp://archive.kronnos.dev'));
});

test('loadDesktopRuntimeConfig injects base host into allowlist', () => {
  const config = loadDesktopRuntimeConfig({
    ARCHIVE_INSTANCE_NAME: 'Prod',
    ARCHIVE_BASE_URL: 'https://archive.kronnos.dev',
    ARCHIVE_ALLOWED_HOSTS: 'staging.archive.kronnos.dev',
  });

  assert.equal(config.baseUrl, 'https://archive.kronnos.dev');
  assert.ok(config.allowedHosts.includes('archive.kronnos.dev'));
  assert.ok(config.allowedHosts.includes('staging.archive.kronnos.dev'));
});
