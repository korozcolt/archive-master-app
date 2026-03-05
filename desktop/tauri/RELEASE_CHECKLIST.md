# Desktop Release Checklist (ATD-2.4-18)

## 1) Preparación

1. Confirmar perfil objetivo (`dev/staging/prod/cliente-a/cliente-b`).
2. Verificar `ARCHIVE_BASE_URL` y `ARCHIVE_ALLOWED_HOSTS` correctos para la instancia.
3. Validar que `ARCHIVE_ENABLE_INSTANCE_SWITCH` esté en `false` para usuarios finales.
4. Validar versión de app desktop (`ARCHIVE_DESKTOP_APP_VERSION`).

## 2) Calidad técnica

1. Ejecutar tests desktop:
   - `npm run desktop:test`
2. Renderizar configuración del perfil:
   - `npm run desktop:profile -- --profile <perfil>`
3. Verificar artefacto generado:
   - `desktop/tauri/dist/runtime-config.json`

## 3) Smoke test funcional

1. Login válido en instancia objetivo.
2. Navegación Portal/Admin según rol.
3. Notificaciones visibles en campana.
4. Descarga y vista previa de documento.
5. Flujo Archivo: asignación de ubicación + impresión de etiqueta.
6. Verificar bloqueo de navegación a dominio no permitido.

## 4) Seguridad

1. Confirmar allowlist mínima de hosts.
2. Verificar que switch de instancia no esté expuesto a usuarios finales.
3. Si se usa modo TI, validar hash de PIN fuera del repositorio.

## 5) Build y distribución

1. Ejecutar workflow `Desktop Tauri` con:
   - `profile=<perfil>`
   - `build_installer=true`
2. Descargar artefacto instalador.
3. Validar firma del instalador (cuando aplique en entorno productivo).
4. Publicar notas de versión y cambios relevantes.

## 6) Trazabilidad obligatoria

1. Actualizar `CHANGELOG.md` con:
   - IDs ATD impactados
   - archivos modificados
   - resultados de pruebas
2. Actualizar `PLAN_DESARROLLO_FASES.md`:
   - estado de ATD-2.4-18
   - evidencia en bitácora
