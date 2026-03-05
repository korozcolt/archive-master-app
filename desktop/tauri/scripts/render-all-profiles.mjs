#!/usr/bin/env node
import { readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const profilesDir = path.resolve(__dirname, '../profiles');

const profiles = readdirSync(profilesDir)
  .filter((file) => file.endsWith('.json'))
  .map((file) => file.replace(/\.json$/, ''));

if (profiles.length === 0) {
  throw new Error('No profiles found in desktop/tauri/profiles');
}

for (const profile of profiles) {
  execSync(`node scripts/render-installer-config.mjs --profile ${profile}`, {
    cwd: path.resolve(__dirname, '..'),
    stdio: 'inherit',
  });
}

console.log(`[desktop] Rendered ${profiles.length} profiles successfully.`);
