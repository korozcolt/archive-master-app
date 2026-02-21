# Plan Maestro de Desarrollo por Fases (Con Checklist)

**Fecha de inicio propuesta**: 2026-02-09  
**Última actualización**: 2026-02-21  
**Estado general**: En progreso

---

## Objetivo

Ejecutar el desarrollo y aseguramiento de calidad de Archive Master por fases, incorporando pruebas E2E con Chrome DevTools MCP para **todos los roles de usuario** y con trazabilidad continua de cambios.

---

## Prioridades Globales (Crítico → Básico)

### Crítico
- [ ] **Cifrado en reposo de documentos**: diseño e implementación completa (guardar/descifrar con claves seguras).
- [ ] **Control de acceso estricto para descargas**: validar por rol + propietario/asignado + empresa (no solo por empresa).
- [ ] **Backups y restauración**: política de respaldo, retención y restauración verificable.

### Alto
- [ ] **Frontend completo para usuarios no admin**: flujos de crear, leer, buscar, editar, descargar, recibir/entregar según rol.
- [ ] **Portal de usuarios (Livewire) separado de admin**: acceso limitado a documentos y reportes personales.
- [ ] **Auditoría de accesos**: registro de lectura/descarga por usuario con trazabilidad.

### Medio
- [ ] **Panel de seguridad**: estados de cifrado, backups, restauración y logs.
- [ ] **Alertas de cumplimiento**: descargas masivas, accesos fuera de horario, errores de backup.

### Básico
- [ ] **Documentación de seguridad**: políticas de cifrado, backup y restore con pasos operativos.
- [ ] **Guías de usuario**: manual corto por rol para el frontend no-admin.

---

## Roles a cubrir en pruebas

- [x] `super_admin`
- [x] `admin`
- [x] `branch_admin`
- [x] `office_manager`
- [x] `archive_manager`
- [x] `receptionist`
- [x] `regular_user`
- [ ] `guest` (pendiente implementación)
- [ ] `anónimo` (sin autenticación para tracking público)

---

## Fase 0 - Estabilización Base (2026-02-09 a 2026-02-13)

### Objetivo
Desbloquear la ejecución de tests y establecer baseline técnica.

### Checklist
- [x] Corregir incompatibilidad SQLite/MySQL en migración de índices (`2025_08_02_200000_add_performance_indexes.php`)
- [x] Lograr ejecución de `php artisan test` sin fallo masivo de bootstrap/migraciones
- [x] Definir matriz RBAC oficial por módulo
- [x] Documentar baseline de cobertura y estado de suites

### Criterio de salida
- [ ] Suite de tests ejecutable de forma consistente
- [ ] Informe baseline publicado

---

## Fase 1 - Seguridad y Acceso por Roles (2026-02-16 a 2026-02-27)

### Objetivo
Cerrar brechas de permisos y acceso por tipo de usuario.

### Checklist
- [x] Definir política de cifrado en reposo (clave por empresa / KMS / rotación)
- [x] Implementar cifrado real de archivos (guardar/leer/descargar)
- [x] Endurecer descarga: validación por rol + propietario/asignado + empresa
- [x] Auditoría de accesos a documentos (ver/descargar)
- [ ] Implementar rol `guest` en `app/Enums/Role.php`
- [ ] Crear/actualizar `RoleSeeder` con permisos mínimos para `guest`
- [ ] Revisar y ajustar policies/middlewares por recurso crítico
- [ ] Validar aislamiento multiempresa/sucursal/departamento
- [ ] Crear/ajustar tests Feature/API por rol para CRUD y lecturas permitidas/restringidas

### Criterio de salida
- [ ] Matriz de permisos validada por tests automatizados
- [ ] Accesos no autorizados bloqueados y verificados

---

## Fase 2 - E2E con Chrome DevTools MCP (2026-03-02 a 2026-03-13)

### Objetivo
Asegurar flujos reales de UI/API en navegador para todos los perfiles.

### Checklist
- [x] Configurar y validar ejecución E2E con `chrome-devtools-mcp`
- [x] Crear escenarios E2E por rol (login, menús visibles, rutas permitidas/denegadas)
- [x] Probar flujos de documentos (crear, consultar, transición de estado)
- [x] Probar tracking público (válido, inválido, expirado)
- [x] Probar flujo de barcode/QR y generación de stickers
- [x] Capturar errores de consola JS por escenario
- [x] Validar respuestas de red (4xx/5xx inesperados)
- [x] Revisar performance base (pantallas clave)
- [x] Verificar accesibilidad mínima (labels, foco, navegación por teclado)
- [x] Verificar restricciones de descarga por rol en UI
- [x] Validar que datos sensibles no se expongan en frontend no-admin

### Criterio de salida
- [ ] Suite E2E por rol estable y reproducible
- [ ] Evidencias almacenadas (logs, capturas, hallazgos)

---

## Fase 2.1 - Portal Usuario Livewire (2026-03-16 a 2026-03-27)

### Objetivo
Separar el acceso de roles operativos del panel admin y habilitar un portal propio Livewire con UX simplificada.

### Checklist
- [x] Bloquear acceso al panel admin para `regular_user`, `receptionist`, `archive_manager`, `office_manager`
- [x] Crear portal Livewire en `/portal` con layout propio
- [x] Menú mínimo por rol: Documentos + Reportes personales
- [x] Reporte “Documentos enviados por usuario”
- [x] Reporte “Documentos recibidos por usuario”
- [x] Tests Feature para acceso por rol y reportes
- [x] E2E por rol en portal (login, listado, detalle, descarga, reportes) — Dusk fallback

### Criterio de salida
- [x] Roles operativos solo acceden a `/portal`
- [x] Reportes personales por usuario verificados

---

## Fase 3 - Cierre de Brechas Funcionales (2026-03-16 a 2026-04-03)

### Objetivo
Completar funcionalidades y tests aún pendientes del roadmap.

### Checklist
- [ ] Implementar sistema de backups (S3/local + retención + cifrado)
- [ ] Implementar restauración desde backup (documentación + validación)
- [ ] Implementar módulo `Receipt` (migración, modelo, servicio, controlador, resource)
- [ ] Crear vistas/plantillas de recibidos PDF
- [x] Implementar base web de recibidos (`receipts` + vista + descarga PDF)
- [x] Implementar onboarding `regular_user` desde recibido con login OTP en portal
- [ ] Completar administración de recibidos en panel (`Filament Resource`) y notificación externa (email/SMS)
- [ ] Agregar endpoints públicos de tracking en API
- [ ] Implementar rate limiter y sanitización para tracking público
- [ ] Crear tests faltantes priorizados:
- [ ] `DocumentTypeTest`
- [ ] `DocumentTrackingCodeTest`
- [ ] `BarcodeServiceTest`
- [ ] `QRCodeServiceTest`
- [ ] `StickerServiceTest`
- [ ] `PhysicalLocationTemplateTest`
- [ ] `PhysicalLocationTest`
- [ ] `DocumentLocationHistoryTest`
- [ ] `DocumentLocationMovementTest`
- [ ] `PhysicalLocationTemplateResourceTest`
- [ ] `PhysicalLocationResourceTest`
- [ ] `PhysicalLocationApiTest`
- [ ] `PublicTrackingPageTest`
- [ ] `GuestRoleTest`
- [ ] `ReceiptTest`
- [ ] `ReceiptResourceTest`

### Criterio de salida
- [ ] Funcionalidades críticas del roadmap implementadas o replanificadas con justificación
- [ ] Tests faltantes cubiertos o desglosados en backlog aprobado

---

## Fase 4 - Calidad Continua y Hardening (2026-04-06 a 2026-04-17)

### Objetivo
Dejar control de calidad continuo y preparación para producción.

### Checklist
- [ ] Documentación operativa de cifrado, backup y restore
- [ ] Manual breve por rol para frontend no-admin
- [ ] Configurar pipeline CI (tests, lint, coverage)
- [ ] Incluir smoke E2E en pipeline
- [ ] Definir gates de merge (build verde + regresión básica por rol)
- [ ] Publicar reporte final de deuda técnica y riesgos

### Criterio de salida
- [ ] Pipeline activo con criterio de aceptación definido
- [ ] Proyecto listo para ciclo de release controlado

---

## Fase IA - OpenAI/Gemini para Documentos (2026-04-20 a 2026-05-22)

### Objetivo
Generar resúmenes ejecutivos y sugerencias de clasificación/tags por versión de documento, en pipeline asíncrono y multi-tenant.

### Checklist
- [x] Fase 0: Decisiones técnicas (`PD-AI-001`) con BYOK y flags base.
- [x] Fase 1: Core BD/modelos/factories (`company_ai_settings`, `document_ai_runs`, `document_ai_outputs`).
- [~] Fase 2: Seguridad completa (RBAC `ai.*`, auditoría y gestión de llaves en UI).
- [x] Fase 2 (parcial): permisos `ai.*` y policies por modelo IA con aislamiento por compañía/documento.
- [x] Fase 2 (parcial): auditoría técnica de runs (`LogsActivity` en `DocumentAiRun`).
- [x] Fase 2 (parcial): `api_key_encrypted` oculto en serialización de settings.
- [ ] Fase 2 (pendiente): gestión de llaves por UI (masked + test key) y aplicación completa en panel admin.
- [ ] Fase 3: Integración proveedores (gateway + adapters OpenAI/Gemini).
- [x] Fase 3 (parcial): contrato de proveedor + `AiGateway` + adapters base OpenAI/Gemini con `config/ai.php`.
- [ ] Fase 3 (pendiente): integración live API (no mock) + `test key` admin contra proveedor real.
- [x] Fase 4: Pipeline asíncrono por `DocumentVersionCreated` + cache por `input_hash`.
- [x] Fase 5: UI Admin Filament para settings IA por compañía.
- [x] Fase 6: UI Portal operativo para resumen/sugerencias y aplicar cambios.
- [~] Fase 7: Hardening (PII redaction, límites, observabilidad y reintentos).
- [ ] Fase 8 opcional: embeddings/búsqueda semántica por tenant.

### Criterio de salida
- [ ] Flujo estable en producción para `summarize` por versión.
- [ ] Costos, límites y trazabilidad por compañía validados.

---

## Registro de Avance (Bitácora Operativa)

Usar esta sección para trazabilidad diaria/semanal.

| Fecha | Fase | Tarea | Cambio realizado | Evidencia (test/log/PR) | Estado |
|------|------|------|------|------|------|
| 2026-02-05 | Planificación | Documento inicial | Se crea plan maestro por fases con checklist y cobertura por roles | `PLAN_DESARROLLO_FASES.md` | Completado |
| 2026-02-05 | Fase 0 | Estabilización de tests | Compatibilidad SQLite/MySQL en migraciones + suite ejecutable | `php artisan test` (252 passed, 3 skipped) | Completado |
| 2026-02-05 | Fase 0 | Matriz RBAC + baseline | Matriz RBAC oficial definida y baseline de tests documentado | `RBAC_MATRIX.md`, `php artisan test` | Completado |
| 2026-02-05 | Fase 1 | Endurecer descargas | Control de descarga por rol/propietario/empresa + logging de descargas | `routes/web.php`, `php artisan test` | Completado |
| 2026-02-05 | Fase 1 | Auditoría accesos | Registro de vista/descarga en `document_access_logs` | `document_access_logs`, `php artisan test` | Completado |
| 2026-02-05 | Fase 1 | Cifrado en reposo | Política definida + cifrado real en almacenamiento y descargas | `SECURITY_POLICY.md`, `DocumentFileService`, `php artisan test` | Completado |
| 2026-02-05 | Fase 2 | E2E por roles (inicio) | Login + dashboard + documentos por rol con evidencias | `e2e/evidence/*.png` | En progreso |
| 2026-02-05 | Fase 2 | Fix dashboard widgets | Columnas `due_date` y `sla_due_date` agregadas + sync en modelo | `php artisan test tests/Feature/DueDateSyncTest.php` | Completado |
| 2026-02-05 | Fase 2 | E2E admin documento | Vista de contenido + descarga funcionando con evidencias | `e2e/evidence/admin-document-content.png`, `e2e/evidence/admin-document-download.png` | Completado |
| 2026-02-05 | Fase 2 | E2E superadmin documento | Vista de contenido + descarga funcionando con evidencias | `e2e/evidence/superadmin-document-content.png`, `e2e/evidence/superadmin-document-download.png` | Completado |
| 2026-02-05 | Fase 2 | E2E branch admin documento | Lista vacía por aislamiento de sucursal (evidencia) | `e2e/evidence/branchadmin-documents-empty.png` | Completado |
| 2026-02-05 | Fase 1 | Menú por rol | Navegación Filament condicionada por rol/permisos en recursos | `app/Filament/ResourceAccess.php`, `php artisan test tests/Feature/Filament` | Completado |
| 2026-02-06 | Fase 2.1 | Portal Livewire | Portal `/portal` con reportes personales + bloqueo admin por rol + tests Feature | `app/Livewire/Portal`, `tests/Feature/PortalAccessTest.php`, `php artisan test tests/Feature/PortalAccessTest.php` | Completado |
| 2026-02-06 | Fase 2.1 | E2E Portal | E2E por rol del portal con Dusk (fallback por MCP) | `tests/Browser/PortalAccessTest.php`, `php artisan dusk --filter=PortalAccessTest` | Completado |
| 2026-02-06 | Fase 2.1 | E2E Portal (MCP) | Evidencias de portal por rol con Chrome DevTools MCP | `e2e/evidence/portal-*-dashboard.png`, `e2e/evidence/portal-*-reports.png` | Completado |
| 2026-02-06 | Fase 2 | E2E por rol (MCP) | Login + rutas permitidas/denegadas por rol (admin/branch_admin/portal) | `e2e/evidence/phase2-*-admin-redirect.png`, `e2e/evidence/*documents-403.png` | Completado |
| 2026-02-06 | Fase 2 | Performance base | Trazas de performance para `/admin/documents` y `/portal` | `e2e/perf/admin-documents-trace.json`, `e2e/perf/portal-trace.json` | Completado |
| 2026-02-06 | Fase 2 | Accesibilidad/Consola/Red | Captura de issues de consola + 4xx/5xx inesperados | `E2E_CHROME_DEVTOOLS.md` | Completado |
| 2026-02-06 | Fase 2 | Tracking público | Tracking UI + API con evidencia (válido, inválido, expirado) | `e2e/evidence/phase2-tracking-*.png` | Completado |
| 2026-02-06 | Fase 2 | Stickers/QR | Preview y descarga de stickers con QR operativo | `e2e/evidence/phase2-sticker-document-preview.png` | Completado |
| 2026-02-06 | Fase 2 | Dusk base | Acceso admin por rol + ajustes de Dusk env + advanced search OK | `php artisan dusk --filter=AdvancedSearchTest` | En progreso |
| 2026-02-21 | Fase 3 | Recibidos + onboarding OTP | Flujo receptionist→recibido→regular_user + login OTP portal + PDF recibido | `php artisan test tests/Feature/ReceiptPortalOtpAuthTest.php` | Completado |
| 2026-02-21 | Fase 3 | Fix crítico rutas | Se corrige colisión `documents.store` API/web con prefijo `api.documents.*` | `php artisan route:list --name=documents.store` | Completado |
| 2026-02-21 | Fase IA | Fase 0+1 | Documento de decisión (`PD-AI-001`) + core tablas/modelos/factories + test base | `php artisan test tests/Feature/AiModuleCoreTest.php` | Completado |
| 2026-02-21 | Fase IA | Fase 2 (parcial) | RBAC `ai.*` + policies IA + auditoría runs + tests de autorización | `php artisan test tests/Feature/AiAuthorizationTest.php` | Completado |
| 2026-02-21 | Fase IA | Validación regresión | Suite IA + portal OTP + acceso portal estable tras cambios de seguridad | `php artisan test tests/Feature/AiAuthorizationTest.php tests/Feature/AiModuleCoreTest.php tests/Feature/ReceiptPortalOtpAuthTest.php tests/Feature/PortalAccessTest.php` | Completado |
| 2026-02-21 | Fase IA | Fase 3 (parcial) | `AiGateway` + adapters OpenAI/Gemini + configuración central IA | `php artisan test tests/Feature/AiGatewayTest.php` | Completado |
| 2026-02-21 | Fase IA | Fase 4 | Evento+listener+job asíncronos para resumen IA con límite diario, límite de páginas y cache por hash | `php artisan test tests/Feature/AiPipelineTest.php` | Completado |
| 2026-02-21 | Fase IA | Fase 5 | UI admin en `CompanyResource` para provider/key/límites + acciones `Test key` y `Run sample` + guardado seguro de key | `php artisan test tests/Feature/Filament/CompanyAiSettingsTest.php tests/Feature/Filament/CompanyResourceTest.php` | Completado |
| 2026-02-21 | Fase IA | Fase 6 | Panel IA en vista de documento (resumen/sugerencias) + acciones portal `Regenerar IA`, `Aplicar sugerencias` y `Marcar incorrecto` + visibilidad de entidades/confianza por rol | `php artisan test tests/Feature/DocumentAiPortalActionsTest.php` | Completado |
| 2026-02-21 | Fase IA | Fase 7 (parcial) | Redacción PII + throttling portal + budget mensual + circuit breaker (incluye pruebas con fallos reales del gateway) + observabilidad admin (métricas rápidas, página dedicada por empresa y export CSV) | `php artisan test tests/Feature/AiGatewayTest.php tests/Feature/AiPipelineTest.php tests/Feature/DocumentAiPortalActionsTest.php tests/Feature/Filament/CompanyAiSettingsTest.php` | En progreso |

---

## Convención de estados

- [ ] Pendiente
- [x] Completado
- [~] En progreso (usar en texto de bitácora)
