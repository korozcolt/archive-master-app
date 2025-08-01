<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\User;
use App\Models\Company;
use Illuminate\Console\Command;

class IndexDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:index {--model=all : Specify which model to index (document, user, company, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index all searchable models in Scout/Meilisearch';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $model = $this->option('model');
        
        $this->info('Starting indexing process...');
        
        if ($model === 'all' || $model === 'document') {
            $this->indexDocuments();
        }
        
        if ($model === 'all' || $model === 'user') {
            $this->indexUsers();
        }
        
        if ($model === 'all' || $model === 'company') {
            $this->indexCompanies();
        }
        
        $this->info('Indexing completed successfully!');
    }
    
    private function indexDocuments()
    {
        $this->info('Indexing documents...');
        
        $count = Document::count();
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        Document::chunk(100, function ($documents) use ($bar) {
            foreach ($documents as $document) {
                $document->searchable();
                $bar->advance();
            }
        });
        
        $bar->finish();
        $this->newLine();
        $this->info("Indexed {$count} documents.");
    }
    
    private function indexUsers()
    {
        $this->info('Indexing users...');
        
        $count = User::count();
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        User::chunk(100, function ($users) use ($bar) {
            foreach ($users as $user) {
                $user->searchable();
                $bar->advance();
            }
        });
        
        $bar->finish();
        $this->newLine();
        $this->info("Indexed {$count} users.");
    }
    
    private function indexCompanies()
    {
        $this->info('Indexing companies...');
        
        $count = Company::count();
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        Company::chunk(100, function ($companies) use ($bar) {
            foreach ($companies as $company) {
                $company->searchable();
                $bar->advance();
            }
        });
        
        $bar->finish();
        $this->newLine();
        $this->info("Indexed {$count} companies.");
    }
}
