import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const profilesDir = path.resolve(__dirname, '../../profiles');

export function loadProfile(profileName) {
  const target = path.resolve(profilesDir, `${profileName}.json`);
  const raw = readFileSync(target, 'utf-8');
  const parsed = JSON.parse(raw);

  if (!parsed.ARCHIVE_BASE_URL) {
    throw new Error(`Profile ${profileName} must define ARCHIVE_BASE_URL`);
  }

  return parsed;
}
