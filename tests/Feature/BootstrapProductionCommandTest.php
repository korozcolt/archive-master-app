<?php

use App\Console\Commands\BootstrapProductionInstance;

it('builds the expected bootstrap step plan with baseline seeding', function (): void {
    $steps = BootstrapProductionInstance::buildStepPlan(
        skipSeed: false,
        skipStorageLink: false,
        restartWorkers: false,
        seedClass: 'Database\\Seeders\\ClientDefaultSeeder',
    );

    expect(array_column($steps, 'command'))->toBe([
        'optimize:clear',
        'migrate',
        'db:seed',
        'storage:link',
        'config:cache',
        'route:cache',
        'event:cache',
        'view:cache',
    ]);

    expect($steps[1]['parameters'])->toBe(['--force' => true])
        ->and($steps[2]['parameters'])->toBe([
            '--class' => 'Database\\Seeders\\ClientDefaultSeeder',
            '--force' => true,
        ])
        ->and($steps[3]['allow_failure'])->toBeTrue();
});

it('builds a reduced bootstrap step plan when skipping seeding and storage link', function (): void {
    $steps = BootstrapProductionInstance::buildStepPlan(
        skipSeed: true,
        skipStorageLink: true,
        restartWorkers: false,
        seedClass: 'Database\\Seeders\\ClientDefaultSeeder',
    );

    expect(array_column($steps, 'command'))->toBe([
        'optimize:clear',
        'migrate',
        'config:cache',
        'route:cache',
        'event:cache',
        'view:cache',
    ]);
});

it('includes worker restart steps when requested', function (): void {
    $steps = BootstrapProductionInstance::buildStepPlan(
        skipSeed: true,
        skipStorageLink: true,
        restartWorkers: true,
        seedClass: 'Database\\Seeders\\ClientDefaultSeeder',
    );

    expect(array_column($steps, 'command'))->toContain('queue:restart', 'horizon:terminate');
});

it('skips seeding by default in production unless explicitly enabled', function (): void {
    expect(BootstrapProductionInstance::shouldSkipSeed(
        skipSeedOption: false,
        withSeedOption: false,
        isProduction: true,
    ))->toBeTrue();

    expect(BootstrapProductionInstance::shouldSkipSeed(
        skipSeedOption: false,
        withSeedOption: true,
        isProduction: true,
    ))->toBeFalse();
});

it('keeps seeding enabled by default outside production', function (): void {
    expect(BootstrapProductionInstance::shouldSkipSeed(
        skipSeedOption: false,
        withSeedOption: false,
        isProduction: false,
    ))->toBeFalse();
});
