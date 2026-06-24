<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        config([
            'app.key' => str_repeat('0', 32),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
            'scout.driver' => 'collection',
            'broadcasting.default' => 'log',
            'documents.files.storage_disk' => 'local',
            'documents.security.encrypt_files' => false,
        ]);

        $this->setUpTraits();

        if (! Schema::hasTable('companies')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        }

        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            $callback();
        }

        $this->setUpHasRun = true;
    }
}
