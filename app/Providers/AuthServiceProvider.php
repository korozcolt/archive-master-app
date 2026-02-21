<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Document;
use App\Models\DocumentAiOutput;
use App\Models\DocumentAiRun;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CompanyAiSettingPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\DocumentAiOutputPolicy;
use App\Policies\DocumentAiRunPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\StatusPolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Category::class => CategoryPolicy::class,
        Company::class => CompanyPolicy::class,
        CompanyAiSetting::class => CompanyAiSettingPolicy::class,
        Document::class => DocumentPolicy::class,
        DocumentAiRun::class => DocumentAiRunPolicy::class,
        DocumentAiOutput::class => DocumentAiOutputPolicy::class,
        Status::class => StatusPolicy::class,
        Tag::class => TagPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}
