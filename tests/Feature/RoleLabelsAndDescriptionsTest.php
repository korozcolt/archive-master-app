<?php

use App\Enums\Role;
use App\Filament\Resources\UserResource;

it('provides user friendly labels and descriptions for every role', function () {
    $expectedLabels = [
        Role::SuperAdmin->value => 'Super Administrador',
        Role::Admin->value => 'Administrador',
        Role::BranchAdmin->value => 'Administrador de Sucursal',
        Role::OfficeManager->value => 'Encargado de Oficina',
        Role::ArchiveManager->value => 'Encargado de Archivo',
        Role::ArchiveOperator->value => 'Operador de Archivo',
        Role::Receptionist->value => 'Recepcionista',
        Role::RegularUser->value => 'Usuario Regular',
    ];

    foreach (Role::cases() as $role) {
        expect($role->getLabel())->toBe($expectedLabels[$role->value])
            ->and($role->getDescription())->not->toBe('');
    }

    expect(Role::ArchiveOperator->getDescription())
        ->toBe('Carga documentos en cajas del archivo central con permisos operativos limitados.');
});

it('excludes super admin from assignable ui roles and sanitizes tampered input', function () {
    $options = UserResource::getAssignableRoleOptions();
    $descriptions = UserResource::getAssignableRoleDescriptions();

    expect($options)->not->toHaveKey(Role::SuperAdmin->value)
        ->and($descriptions)->not->toHaveKey(Role::SuperAdmin->value);

    $sanitized = UserResource::sanitizeAssignableRoles([
        Role::SuperAdmin->value,
        Role::Admin->value,
        Role::OfficeManager->value,
    ]);

    expect($sanitized)->toBe([
        Role::Admin->value,
        Role::OfficeManager->value,
    ]);
});
