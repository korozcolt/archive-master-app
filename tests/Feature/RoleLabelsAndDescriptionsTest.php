<?php

use App\Enums\Role;
use App\Filament\Resources\UserResource;

it('provides user friendly labels and descriptions for every role', function () {
    $expected = [
        Role::SuperAdmin->value => ['Super Administrador', 'Acceso completo a toda la plataforma, configuración y gobierno.'],
        Role::Admin->value => ['Administrador', 'Administra la empresa, usuarios, catálogos, flujos y reportes globales.'],
        Role::BranchAdmin->value => ['Administrador de Sucursal', 'Gestiona la operación y reportes de su sucursal con alcance limitado.'],
        Role::OfficeManager->value => ['Encargado de Oficina', 'Gestiona documentos del área y participa en aprobaciones.'],
        Role::ArchiveManager->value => ['Encargado de Archivo', 'Administra archivo, custodia documental y ubicaciones.'],
        Role::Receptionist->value => ['Recepcionista', 'Registra documentos entrantes y genera recibidos para usuarios.'],
        Role::RegularUser->value => ['Usuario Regular', 'Consulta sus documentos y recibidos, con acceso limitado al portal.'],
    ];

    foreach (Role::cases() as $role) {
        expect($role->getLabel())->toBe($expected[$role->value][0])
            ->and($role->getDescription())->toBe($expected[$role->value][1]);
    }
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
