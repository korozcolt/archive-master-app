<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener empresas existentes (creadas por UserSeeder)
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->info('No hay empresas disponibles. Ejecute UserSeeder primero.');
            return;
        }

        // Definir categorías base que se crearán para cada empresa
        $baseCategories = [
            [
                'name' => 'Correspondencia',
                'description' => 'Documentos de correspondencia entrante y saliente',
                'color' => '#4a90e2',
                'icon' => 'heroicon-o-envelope',
                'children' => [
                    [
                        'name' => 'Correspondencia Entrante',
                        'description' => 'Documentos recibidos de entidades externas',
                        'color' => '#5da0f2',
                        'icon' => 'heroicon-o-inbox',
                    ],
                    [
                        'name' => 'Correspondencia Saliente',
                        'description' => 'Documentos enviados a entidades externas',
                        'color' => '#3a80d2',
                        'icon' => 'heroicon-o-paper-airplane',
                    ],
                ],
            ],
            [
                'name' => 'Facturas',
                'description' => 'Documentos relacionados con facturación y cobros',
                'color' => '#27ae60',
                'icon' => 'heroicon-o-currency-dollar',
                'children' => [
                    [
                        'name' => 'Facturas de Venta',
                        'description' => 'Facturas emitidas a clientes',
                        'color' => '#37be70',
                        'icon' => 'heroicon-o-banknotes',
                    ],
                    [
                        'name' => 'Facturas de Compra',
                        'description' => 'Facturas recibidas de proveedores',
                        'color' => '#179e50',
                        'icon' => 'heroicon-o-receipt-percent',
                    ],
                    [
                        'name' => 'Notas Crédito/Débito',
                        'description' => 'Ajustes a facturas emitidas o recibidas',
                        'color' => '#47ce80',
                        'icon' => 'heroicon-o-arrow-path',
                    ],
                ],
            ],
            [
                'name' => 'Contratos',
                'description' => 'Documentos legales y contractuales',
                'color' => '#8e44ad',
                'icon' => 'heroicon-o-document-text',
                'children' => [
                    [
                        'name' => 'Contratos Laborales',
                        'description' => 'Contratos con empleados',
                        'color' => '#9e54bd',
                        'icon' => 'heroicon-o-user-group',
                    ],
                    [
                        'name' => 'Contratos Comerciales',
                        'description' => 'Contratos con clientes y proveedores',
                        'color' => '#7e349d',
                        'icon' => 'heroicon-o-building-storefront',
                    ],
                    [
                        'name' => 'Acuerdos de Confidencialidad',
                        'description' => 'NDAs y acuerdos de confidencialidad',
                        'color' => '#ae64cd',
                        'icon' => 'heroicon-o-lock-closed',
                    ],
                ],
            ],
            [
                'name' => 'Documentos Internos',
                'description' => 'Documentos de gestión interna',
                'color' => '#e74c3c',
                'icon' => 'heroicon-o-document',
                'children' => [
                    [
                        'name' => 'Memorandos',
                        'description' => 'Comunicaciones internas oficiales',
                        'color' => '#f75c4c',
                        'icon' => 'heroicon-o-chat-bubble-left',
                    ],
                    [
                        'name' => 'Actas de Reunión',
                        'description' => 'Registros de reuniones y decisiones',
                        'color' => '#d73c2c',
                        'icon' => 'heroicon-o-clipboard-document-list',
                    ],
                    [
                        'name' => 'Políticas Internas',
                        'description' => 'Documentos de políticas y procedimientos',
                        'color' => '#c72c1c',
                        'icon' => 'heroicon-o-clipboard-document-check',
                    ],
                ],
            ],
            [
                'name' => 'Documentos Legales',
                'description' => 'Documentos jurídicos y normativos',
                'color' => '#f39c12',
                'icon' => 'heroicon-o-scale',
                'children' => [
                    [
                        'name' => 'Escrituras',
                        'description' => 'Documentos de propiedad y constitución',
                        'color' => '#ffac22',
                        'icon' => 'heroicon-o-home',
                    ],
                    [
                        'name' => 'Poderes',
                        'description' => 'Documentos de representación legal',
                        'color' => '#e38c02',
                        'icon' => 'heroicon-o-identification',
                    ],
                    [
                        'name' => 'Permisos y Licencias',
                        'description' => 'Autorizaciones legales',
                        'color' => '#d37c00',
                        'icon' => 'heroicon-o-document-check',
                    ],
                ],
            ],
        ];

        // Crear las categorías para cada empresa
        foreach ($companies as $company) {
            $this->command->info("Creando categorías para la empresa: {$company->name}");

            // Crear categorías base
            foreach ($baseCategories as $index => $categoryData) {
                // Crear categoría principal si no existe
                $parentCategory = Category::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'slug' => Str::slug($categoryData['name']),
                    ],
                    [
                        'parent_id' => null,
                        'name' => $categoryData['name'],
                        'description' => $categoryData['description'],
                        'color' => $categoryData['color'],
                        'icon' => $categoryData['icon'],
                        'order' => $index + 1,
                        'active' => true,
                    ]
                );

                // Crear subcategorías si no existen
                if (isset($categoryData['children'])) {
                    foreach ($categoryData['children'] as $subIndex => $subCategoryData) {
                        Category::firstOrCreate(
                            [
                                'company_id' => $company->id,
                                'slug' => Str::slug($subCategoryData['name']),
                            ],
                            [
                                'parent_id' => $parentCategory->id,
                                'name' => $subCategoryData['name'],
                                'description' => $subCategoryData['description'],
                                'color' => $subCategoryData['color'] ?? $categoryData['color'],
                                'icon' => $subCategoryData['icon'] ?? 'heroicon-o-document',
                                'order' => $subIndex + 1,
                                'active' => true,
                            ]
                        );
                    }
                }
            }

            // Crear categoría inactiva para pruebas
            Category::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'slug' => 'categoria-inactiva',
                ],
                [
                    'parent_id' => null,
                    'name' => 'Categoría Inactiva',
                    'description' => 'Categoría inactiva para pruebas',
                    'color' => '#95a5a6',
                    'icon' => 'heroicon-o-archive-box-x-mark',
                    'order' => 99,
                    'active' => false,
                ]
            );
        }
    }
}
