#!/usr/bin/env node
import { execSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const tauriRoot = path.resolve(__dirname, '..');
const srcTauriRoot = path.resolve(tauriRoot, 'src-tauri');

const args = process.argv.slice(2);
const profileIndex = args.indexOf('--profile');
const profile = profileIndex >= 0 ? args[profileIndex + 1] : 'prod';

execSync(`node scripts/render-installer-config.mjs --profile ${profile}`, {
  cwd: tauriRoot,
  stdio: 'inherit',
});

execSync(`node scripts/render-tauri-profile-config.mjs --profile ${profile}`, {
  cwd: tauriRoot,
  stdio: 'inherit',
});

const configPath = path.resolve(tauriRoot, 'dist', 'tauri.profile.conf.json');
execSync(`cargo tauri dev --config "${configPath}"`, {
  cwd: srcTauriRoot,
  stdio: 'inherit',
});
