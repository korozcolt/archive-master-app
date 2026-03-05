# Manual Operativo TI - Provisión por Instalador (Desktop)

## Propósito

Provisionar instaladores Desktop apuntando a instancias específicas sin hardcode en código fuente.

## Variables por perfil

Cada perfil define:

- `ARCHIVE_INSTANCE_NAME`
- `ARCHIVE_BASE_URL`
- `ARCHIVE_ALLOWED_HOSTS`
- `ARCHIVE_ENV_LABEL`
- `ARCHIVE_ENABLE_INSTANCE_SWITCH`
- `ARCHIVE_IT_MODE_ENABLED`

## Perfiles disponibles

- `dev`
- `staging`
- `prod`
- `cliente-a`
- `cliente-b`

Archivos: `desktop/tauri/profiles/*.json`

## Flujo operativo

1. Seleccionar perfil objetivo.
2. Renderizar configuración runtime:

```bash
npm --prefix desktop/tauri run profile:render -- --profile prod
```

3. Verificar salida en:

- `desktop/tauri/dist/runtime-config.json`

4. Empaquetar instalador con esa configuración.

## Política de cambio de instancia

1. Por defecto, una instalación apunta a una sola instancia.
2. El cambio de instancia está deshabilitado salvo perfiles TI.
3. Si se habilita:
   - sólo aplica para hosts en allowlist.
   - requiere habilitación explícita de modo TI.

## Recomendaciones de seguridad

1. Nunca habilitar cambio de instancia en perfiles productivos de usuario final.
2. Mantener allowlist mínimo (solo dominios autorizados).
3. Versionar cada instalador por cliente/entorno.
4. Registrar hash de PIN TI fuera del repositorio.
