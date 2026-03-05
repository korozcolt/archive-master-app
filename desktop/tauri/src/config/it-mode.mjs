import crypto from 'node:crypto';

export function sha256(value) {
  return crypto.createHash('sha256').update(String(value)).digest('hex');
}

export function canEnableInstanceSwitch(config, providedPin) {
  if (!config.enableInstanceSwitch || !config.itModeEnabled) {
    return false;
  }

  if (!config.itSupportPinHash) {
    return false;
  }

  return sha256(providedPin) === config.itSupportPinHash;
}
