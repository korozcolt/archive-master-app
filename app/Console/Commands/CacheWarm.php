<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class CacheWarm extends Command
{
    protected $signature = 'cache:warm {--company= : Warm cache for specific company ID}';
    protected $description = 'Warm up cache with frequently accessed data';

    public function handle()
    {
        $this->info('🔥 Warming up cache...');
        $this->newLine();

        $companyId = $this->option('company');

        if ($companyId) {
            $this->warmCompanyCache($companyId);
        } else {
            $this->warmAllCompaniesCache();
        }

        $this->newLine();
        $this->info('✅ Cache warming completed!');
    }

    private function warmAllCompaniesCache()
    {
        $companies = Company::where('active', true)->get();

        $this->info("Warming cache for {$companies->count()} companies...");
        $this->newLine();

        $bar = $this->output->createProgressBar($companies->count());
        $bar->start();

        foreach ($companies as $company) {
            $this->warmCompanyCache($company->id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function warmCompanyCache(int $companyId)
    {
        try {
            // Simular contexto de usuario para el cache
            $user = User::where('company_id', $companyId)->first();
            if (!$user) {
                $this->warn("No users found for company {$companyId}, skipping...");
                return;
            }

            auth()->login($user);

            $this->line("Warming cache for company: {$companyId}");

            // Warm document stats
            CacheService::getDocumentStats($companyId);
            $this->line('  ✓ Document statistics');

            // Warm active categories
            CacheService::getActiveCategories($companyId);
            $this->line('  ✓ Active categories');

            // Warm active statuses
            CacheService::getActiveStatuses($companyId);
            $this->line('  ✓ Active statuses');

            // Warm popular tags
            CacheService::getPopularTags($companyId);
            $this->line('  ✓ Popular tags');

            // Warm users by department
            $departments = Department::where('company_id', $companyId)->pluck('id');
            foreach ($departments as $deptId) {
                CacheService::getUsersByDepartment($deptId, $companyId);
            }
            $this->line("  ✓ Users by department ({$departments->count()} departments)");

            Auth::logout();

        } catch (\Exception $e) {
            $this->error("Error warming cache for company {$companyId}: " . $e->getMessage());
        }
    }
}
