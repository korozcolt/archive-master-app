<?php

use App\Models\Category;
use App\Models\Company;
use Database\Seeders\AguasDeSucreCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates operational categories for aguas de sucre', function () {
    $company = Company::query()->create([
        'name' => ['es' => 'AGUAS DE SUCRE S.A. E.S.P.'],
        'legal_name' => ['es' => 'AGUAS DE SUCRE S.A. E.S.P.'],
        'active' => true,
    ]);

    dump([
        'all_companies' => Company::all()->toArray(),
        'created_company_id' => $company->id,
    ]);

    $this->seed(AguasDeSucreCategorySeeder::class);

    dump([
        'categories_after_seed' => Category::all()->toArray(),
    ]);

    expect(Category::query()->where('company_id', $company->id)->root()->count())->toBe(8)
        ->and(Category::query()->where('company_id', $company->id)->count())->toBe(33)
        ->and(Category::query()->where('company_id', $company->id)->where('slug', 'correspondencia-recibida')->exists())->toBeTrue()
        ->and(Category::query()->where('company_id', $company->id)->where('slug', 'pqrs')->exists())->toBeTrue()
        ->and(Category::query()->where('company_id', $company->id)->where('slug', 'contratos')->exists())->toBeTrue()
        ->and(Category::query()->where('company_id', $company->id)->where('slug', 'instrumentos-archivisticos')->exists())->toBeTrue();
});
