import test from 'node:test';
import assert from 'node:assert/strict';

import { canEnableInstanceSwitch, sha256 } from '../src/config/it-mode.mjs';

test('it mode enables switching only with valid pin hash', () => {
  const pin = '1234';
  const config = {
    enableInstanceSwitch: true,
    itModeEnabled: true,
    itSupportPinHash: sha256(pin),
  };

  assert.equal(canEnableInstanceSwitch(config, '0000'), false);
  assert.equal(canEnableInstanceSwitch(config, '1234'), true);
});
