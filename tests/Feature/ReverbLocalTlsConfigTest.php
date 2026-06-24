<?php

it('does not enable tls for reverb when local certificate env vars are missing', function () {
    putenv('REVERB_TLS_PASSPHRASE');
    putenv('REVERB_TLS_CERT');
    putenv('REVERB_TLS_KEY');

    $config = require config_path('reverb.php');

    expect(data_get($config, 'servers.reverb.options.tls'))->toBeNull();
});

it('loads tls options for reverb when local certificate env vars are present', function () {
    putenv('REVERB_TLS_PASSPHRASE=secret');
    putenv('REVERB_TLS_CERT=/path/to/cert');
    putenv('REVERB_TLS_KEY=/path/to/key');

    $config = require config_path('reverb.php');
    $tls = data_get($config, 'servers.reverb.options.tls');

    expect($tls)->toBeArray()
        ->and(data_get($tls, 'local_cert'))->toBe('/path/to/cert')
        ->and(data_get($tls, 'local_pk'))->toBe('/path/to/key')
        ->and(data_get($tls, 'passphrase'))->toBe('secret');

    putenv('REVERB_TLS_PASSPHRASE');
    putenv('REVERB_TLS_CERT');
    putenv('REVERB_TLS_KEY');
});
