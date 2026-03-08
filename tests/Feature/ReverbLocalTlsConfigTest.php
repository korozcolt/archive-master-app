<?php

it('loads tls options for reverb when local certificate env vars are present', function () {
    putenv('REVERB_TLS_PASSPHRASE=secret');

    $config = require config_path('reverb.php');
    $tls = data_get($config, 'servers.reverb.options.tls');

    expect($tls)->toBeArray()
        ->and(data_get($tls, 'local_cert'))->toBeString()->not->toBe('')
        ->and(data_get($tls, 'local_pk'))->toBeString()->not->toBe('')
        ->and(data_get($tls, 'passphrase'))->toBe('secret');

    putenv('REVERB_TLS_PASSPHRASE');
});
