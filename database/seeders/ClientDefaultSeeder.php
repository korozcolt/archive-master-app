<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientDefaultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            StatusSeeder::class,
            TagSeeder::class,
            WorkflowDefinitionSeeder::class,
            DocumentTemplateSeeder::class,
        ]);

        $this->leaveOnlyConfiguredSuperAdmin();
    }

    private function leaveOnlyConfiguredSuperAdmin(): void
    {
        $superAdmin = User::query()->firstWhere('email', 'ing.korozco@gmail.com');

        if (! $superAdmin) {
            throw new \RuntimeException('No se encontró el usuario base super_admin después de ejecutar UserSeeder.');
        }

        $superAdmin->forceFill([
            'name' => 'Kristian Orozco',
            'password' => Hash::make('Admin123'),
            'phone' => '3016859339',
            'position' => 'Administrador del sistema',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $superAdmin->syncRoles([Role::SuperAdmin->value]);

        User::query()
            ->whereKeyNot($superAdmin->getKey())
            ->each(function (User $user): void {
                $user->syncRoles([]);
                $user->delete();
            });

        $this->command?->info('Seeder cliente: datos por defecto cargados y solo super_admin conservado.');
    }
}
