# ArchiveMaster Desktop (Tauri)

Cliente desktop multi-instancia para ArchiveMaster.

## Objetivo

Reutilizar Portal/Admin web existentes con un shell nativo, sin duplicar backend.

## Configuración por instalador

Cada instalador se genera desde un perfil JSON en `profiles/*.json`:

- `ARCHIVE_INSTANCE_NAME`
- `ARCHIVE_BASE_URL`
- `ARCHIVE_ALLOWED_HOSTS`
- `ARCHIVE_ENV_LABEL`
- `ARCHIVE_ENABLE_INSTANCE_SWITCH`

## Flujo de provisión

1. Seleccionar perfil (`prod`, `staging`, `cliente-a`, `cliente-b`, etc.)
2. Renderizar config runtime:

```bash
npm --prefix desktop/tauri run profile:render -- --profile prod
```

3. Usar `desktop/tauri/dist/runtime-config.json` en empaquetado.

## Probar en Mac contra instancia cloud

Cuando tengas Rust/Tauri instalado, puedes abrir la app desktop apuntando directo a una instancia remota por perfil:

```bash
npm run desktop:tauri:dev -- --profile prod
```

También puedes usar `staging`, `cliente-a`, `cliente-b` o `dev`.

Este comando genera:

- `desktop/tauri/dist/runtime-config.json`
- `desktop/tauri/dist/tauri.profile.conf.json`

y luego ejecuta:

- `cargo tauri dev --config desktop/tauri/dist/tauri.profile.conf.json`

La ventana principal abrirá la URL de `ARCHIVE_BASE_URL` del perfil seleccionado.

## Seguridad de navegación

- Solo permite hosts definidos en allowlist.
- URLs externas deben abrirse en navegador del sistema.
- Modo cambio de instancia disponible solo para TI cuando está habilitado.

## CI / Build

- Workflow: `.github/workflows/desktop-tauri.yml`
- Validaciones automáticas:
  - tests del módulo desktop
  - render de perfiles
- Build manual de instalador Windows (NSIS) por perfil usando `workflow_dispatch`.

## Release

- Revisar checklist operativo en `desktop/tauri/RELEASE_CHECKLIST.md`.
