#!/usr/bin/env node
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadProfile } from '../src/config/profiles.mjs';
import { loadDesktopRuntimeConfig } from '../src/config/desktop-config.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const tauriRoot = path.resolve(__dirname, '..');
const srcTauriRoot = path.resolve(tauriRoot, 'src-tauri');

const args = process.argv.slice(2);
const profileIndex = args.indexOf('--profile');
const profile = profileIndex >= 0 ? args[profileIndex + 1] : 'prod';

if (!profile) {
  throw new Error('Usage: node scripts/render-tauri-profile-config.mjs --profile <name>');
}

const profileEnv = loadProfile(profile);
const runtimeConfig = loadDesktopRuntimeConfig({ ...process.env, ...profileEnv });

const baseConfigFile = path.resolve(srcTauriRoot, 'tauri.conf.json');
const baseConfig = JSON.parse(readFileSync(baseConfigFile, 'utf-8'));

const generatedConfig = {
  ...baseConfig,
  productName: `${baseConfig.productName} - ${runtimeConfig.instanceName}`,
  build: {
    ...baseConfig.build,
    devUrl: runtimeConfig.baseUrl,
  },
  app: {
    ...baseConfig.app,
    windows: (baseConfig.app?.windows || []).map((windowConfig, index) => {
      if (index === 0) {
        return {
          ...windowConfig,
          url: runtimeConfig.baseUrl,
          title: `${runtimeConfig.instanceName} (${runtimeConfig.environmentLabel})`,
        };
      }

      return windowConfig;
    }),
  },
};

const outDir = path.resolve(tauriRoot, 'dist');
mkdirSync(outDir, { recursive: true });

const outFile = path.resolve(outDir, 'tauri.profile.conf.json');
writeFileSync(outFile, JSON.stringify(generatedConfig, null, 2));

console.log(`[desktop] Tauri profile config rendered for: ${profile}`);
console.log(`[desktop] Base URL: ${runtimeConfig.baseUrl}`);
console.log(`[desktop] Output: ${outFile}`);
