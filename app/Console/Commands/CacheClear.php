<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;
use App\Models\Company;

class CacheClear extends Command
{
    protected $signature = 'cache:clear-app {--type= : Type of cache to clear (documents, users, categories, etc.)} {--company= : Company ID to clear cache for} {--all : Clear all application cache}';
    protected $description = 'Clear specific application cache types';

    public function handle()
    {
        $type = $this->option('type');
        $companyId = $this->option('company');
        $all = $this->option('all');

        if ($all) {
            $this->clearAllCache();
            return;
        }

        if ($type && $companyId) {
            $this->clearSpecificCache($type, $companyId);
            return;
        }

        if ($type) {
            $this->clearCacheType($type);
            return;
        }

        // Mostrar opciones disponibles
        $this->showOptions();
    }

    private function clearAllCache()
    {
        $this->info('🧹 Clearing all application cache...');

        $companies = Company::where('active', true)->get();

        foreach ($companies as $company) {
            CacheService::flushCompanyCache($company->id);
            $this->line("   ✓ Cleared cache for company: {$company->name}");
        }

        $this->info('✅ All application cache cleared successfully!');
    }

    private function clearSpecificCache(string $type, int $companyId)
    {
        $this->info("🧹 Clearing {$type} cache for company {$companyId}...");

        try {
            CacheService::flush($type, $companyId);
            $this->info("✅ {$type} cache cleared for company {$companyId}!");
        } catch (\Exception $e) {
            $this->error("❌ Error clearing cache: " . $e->getMessage());
        }
    }

    private function clearCacheType(string $type)
    {
        $this->info("🧹 Clearing {$type} cache for all companies...");

        $companies = Company::where('active', true)->get();

        foreach ($companies as $company) {
            try {
                CacheService::flush($type, $company->id);
                $this->line("   ✓ Cleared {$type} cache for company: {$company->name}");
            } catch (\Exception $e) {
                $this->line("   ❌ Error for company {$company->name}: " . $e->getMessage());
            }
        }

        $this->info("✅ {$type} cache cleared for all companies!");
    }

    private function showOptions()
    {
        $this->info('🔧 Cache Clear Options:');
        $this->newLine();

        $this->line('Available cache types:');
        foreach (CacheService::PREFIXES as $type => $prefix) {
            $this->line("   • {$type}");
        }

        $this->newLine();
        $this->line('Usage examples:');
        $this->line('   php artisan cache:clear-app --all');
        $this->line('   php artisan cache:clear-app --type=documents');
        $this->line('   php artisan cache:clear-app --type=users --company=1');

        $this->newLine();
        $this->line('Available companies:');
        $companies = Company::where('active', true)->get(['id', 'name']);
        foreach ($companies as $company) {
            $this->line("   • {$company->id}: {$company->name}");
        }
    }
}
