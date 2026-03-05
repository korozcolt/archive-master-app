#!/usr/bin/env node
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadProfile } from '../src/config/profiles.mjs';
import { loadDesktopRuntimeConfig } from '../src/config/desktop-config.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const args = process.argv.slice(2);
const profileIndex = args.indexOf('--profile');
const profile = profileIndex >= 0 ? args[profileIndex + 1] : 'prod';

if (!profile) {
  throw new Error('Usage: node scripts/render-installer-config.mjs --profile <name>');
}

const profileEnv = loadProfile(profile);
const runtimeConfig = loadDesktopRuntimeConfig({ ...process.env, ...profileEnv });

const outDir = path.resolve(projectRoot, 'dist');
mkdirSync(outDir, { recursive: true });

const outFile = path.resolve(outDir, 'runtime-config.json');
writeFileSync(outFile, JSON.stringify(runtimeConfig, null, 2));

console.log(`[desktop] Runtime config rendered for profile: ${profile}`);
console.log(`[desktop] Output: ${outFile}`);
