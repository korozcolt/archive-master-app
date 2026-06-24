<?php

it('registers production document storage disks', function () {
    expect(config('filesystems.disks.archive.driver'))->toBe('local')
        ->and(config('filesystems.disks.archive.root'))->toBeString()
        ->and(config('filesystems.disks.private.driver'))->toBe('local')
        ->and(config('filesystems.disks.private.root'))->toBeString();
});

it('has proxy and asset configuration keys required behind traefik', function () {
    expect(config('app'))->toHaveKeys(['asset_url', 'trusted_proxies'])
        ->and(config('app.trusted_proxies'))->toBeString();
});

it('does not resolve the config service while registering bootstrap middleware', function () {
    $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

    expect($bootstrap)
        ->not->toContain("config('app.trusted_proxies'")
        ->toContain('TRUSTED_PROXIES');
});
