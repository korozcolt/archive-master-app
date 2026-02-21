# E2E con Chrome DevTools MCP

**Estado**: En progreso (MCP activo)  
**Objetivo**: Ejecutar pruebas E2E por rol usando Chrome DevTools MCP, con evidencias de consola/red/performance.

---

## Requisitos previos

- App en ejecución (backend + frontend).
- Datos de prueba disponibles para cada rol.
- Chrome DevTools MCP activo.

---

## Roles a validar

- `super_admin`
- `admin`
- `branch_admin`
- `office_manager`
- `archive_manager`
- `receptionist`
- `regular_user`
- `guest`
- `anónimo`

---

## Flujos mínimos por rol

1. **Login/Acceso**
   - Acceso permitido/denegado según rol.
2. **Documentos**
   - Listar, buscar, ver detalle.
   - Crear/editar si el rol lo permite.
3. **Descarga**
   - Verificar permiso de descarga.
4. **Tracking público**
   - Válido, inválido, expirado.
5. **Errores de consola**
   - Sin errores JS críticos.
6. **Red**
   - Sin 4xx/5xx inesperados.
7. **Performance básica**
   - Tiempo de carga aceptable para pantallas clave.
8. **Accesibilidad mínima**
   - Labels, foco, navegación por teclado.

---

## Evidencias requeridas

- Capturas de consola (errores).
- Capturas de red (fallos).
- Métricas básicas de carga (TTFB/LCP).
- Screenshots de pantallas clave.

---

## Evidencias actuales (2026-02-05)

- `e2e/evidence/admin-dashboard.png`
- `e2e/evidence/admin-documents-list.png`
- `e2e/evidence/admin-document-content.png`
- `e2e/evidence/admin-document-download.png`
- `e2e/evidence/superadmin-dashboard.png`
- `e2e/evidence/superadmin-documents-list.png`
- `e2e/evidence/superadmin-document-content.png`
- `e2e/evidence/superadmin-document-download.png`
- `e2e/evidence/branchadmin-dashboard.png`
- `e2e/evidence/branchadmin-documents-list.png`
- `e2e/evidence/branchadmin-documents-empty.png`
- `e2e/evidence/officemanager-dashboard.png`
- `e2e/evidence/officemanager-documents-list.png`
- `e2e/evidence/archivemanager-dashboard.png`
- `e2e/evidence/archivemanager-documents-list.png`
- `e2e/evidence/receptionist-dashboard.png`
- `e2e/evidence/receptionist-documents-list.png`
- `e2e/evidence/regularuser-dashboard.png`
- `e2e/evidence/regularuser-documents-list.png`

## Evidencias adicionales (2026-02-06)

**Nota**: Chrome DevTools MCP habilitado nuevamente. E2E de portal realizado con MCP y Dusk (fallback).

### MCP (portal)
- `e2e/evidence/portal-office_manager-dashboard.png`
- `e2e/evidence/portal-office_manager-reports.png`
- `e2e/evidence/portal-archive_manager-dashboard.png`
- `e2e/evidence/portal-archive_manager-reports.png`
- `e2e/evidence/portal-receptionist-dashboard.png`
- `e2e/evidence/portal-receptionist-reports.png`
- `e2e/evidence/portal-regular_user-dashboard.png`
- `e2e/evidence/portal-regular_user-reports.png`

### Dusk (fallback)
- `tests/Browser/screenshots/portal-office_manager-dashboard.png`
- `tests/Browser/screenshots/portal-office_manager-reports.png`
- `tests/Browser/screenshots/portal-archive_manager-dashboard.png`
- `tests/Browser/screenshots/portal-archive_manager-reports.png`
- `tests/Browser/screenshots/portal-receptionist-dashboard.png`
- `tests/Browser/screenshots/portal-receptionist-reports.png`
- `tests/Browser/screenshots/portal-regular_user-dashboard.png`
- `tests/Browser/screenshots/portal-regular_user-reports.png`

## Evidencias Phase 2 (2026-02-06)

### Flujos admin / documentos
- `e2e/evidence/phase2-superadmin-documents-list.png`
- `e2e/evidence/phase2-superadmin-document-view.png`
- `e2e/evidence/phase2-superadmin-document-status-changed.png`
- `e2e/evidence/phase2-document-create.png`
- `e2e/evidence/admin-documents-403.png`
- `e2e/evidence/phase2-branch_admin-documents-403.png`

### Descargas
- `e2e/evidence/phase2-office_manager-download-403.png`

### Tracking público (API)
- `e2e/evidence/phase2-tracking-form.png`
- `e2e/evidence/phase2-tracking-api-valid.png`
- `e2e/evidence/phase2-tracking-api-invalid.png`
- `e2e/evidence/phase2-tracking-api-expired.png`
- `e2e/evidence/phase2-tracking-ui-valid.png`

### Stickers (documentos)
- `e2e/evidence/phase2-sticker-document-preview.png`
- `e2e/evidence/phase2-sticker-document-preview-403.png` (histórico)
- `e2e/evidence/phase2-sticker-document-download-403.png` (histórico)

### Redirecciones portal
- `e2e/evidence/phase2-office_manager-admin-redirect.png`

### Performance traces
- `e2e/perf/admin-documents-trace.json`
- `e2e/perf/portal-trace.json`

---

## Hallazgos actuales

- Sin hallazgos críticos abiertos.

## Hallazgos resueltos (2026-02-06)

- **Admin Dashboard**: 500 para `admin` en `/admin` (relación `user` inexistente en `Document`).
- **Tracking público UI**: POST `/tracking/track` devolvía 419 (CSRF).
- **Descargas**: `GET /documents/1/download` devolvía 404 con `super_admin`.
- **Stickers**: 403 en `.../preview` y `.../download` con `super_admin`.
- **QR**: Error `Builder::create()` en `QRCodeService` con endroid/qr-code v6.
- **RBAC/Docs**: `admin` y `branch_admin` recibían 403 en `/admin/documents`.
- **Accesibilidad**: Inputs de reportes sin `id`/`name` en portal.
- **Red**: 404 en `GET /css/app/tailwindcss` por asset Filament no compilado.
- **RBAC/Scope**: `branch_admin` no ve documentos fuera de su sucursal (lista vacía).

---

## Resultado esperado

Checklist completo por rol con evidencias almacenadas y hallazgos registrados.
