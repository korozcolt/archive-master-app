# Plan Maestro de Desarrollo por Fases (Con Checklist)

**Fecha de inicio propuesta**: 2026-02-09  
**Última actualización**: 2026-03-07  
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

## Fase 2.2 - Distribución Multi-Oficina y Seguimiento (Atomic Execution) (2026-03-27 a 2026-04-10)

### Objetivo
Permitir que `receptionist` distribuya un documento a una o varias oficinas/departamentos y que exista seguimiento por destinatario (revisado, en gestión, respuesta), con trazabilidad visible en portal.

### Diagnóstico (confirmado)
- [x] Revisar `WorkflowDefinition`, `WorkflowHistory` y `WorkflowService` para validar si soportan distribución múltiple por oficina.
- [x] Confirmar limitación: el workflow actual cubre transiciones/aprobaciones, pero **no modela múltiples destinatarios con estados independientes por oficina**.
- [x] Decidir implementación de módulo específico de distribución (no solo configuración de workflow).

### Atomic Execution por tareas

#### A. Modelo y persistencia (core)
- [ ] A.1 Crear `document_distributions` (cabecera de envío)
- [ ] A.2 Crear `document_distribution_targets` (una fila por oficina/destinatario)
- [ ] A.3 Modelos Eloquent + relaciones en `Document`, `Department`, `User`
- [ ] A.4 Estados por destinatario (`sent`, `received`, `in_review`, `responded`, `closed`)
- [ ] A.5 Timestamps por acción (`received_at`, `reviewed_at`, `responded_at`, `closed_at`)

#### B. Envío desde recepción (portal)
- [ ] B.1 Acción portal “Enviar a oficinas” en detalle de documento
- [ ] B.2 Selección múltiple de oficinas/departamentos destino
- [ ] B.3 (Opcional MVP) asignar usuario responsable por destinatario
- [ ] B.4 Validaciones de empresa/sucursal/departamento
- [ ] B.5 Registrar evento de distribución en auditoría

#### C. Seguimiento por oficina (portal)
- [ ] C.1 Panel “Distribución y Seguimiento” en `documents.show`
- [ ] C.2 Tabla por destinatario (oficina, responsable, estado, última actividad)
- [ ] C.3 Acciones para `office_manager`: marcar recibido / en revisión / responder / cerrar
- [ ] C.4 Comentario/nota de seguimiento por destinatario
- [ ] C.5 Mostrar respuesta/observación en el detalle para recepción

#### D. Notificaciones y deduplicación
- [ ] D.1 Notificar al destinatario/oficina cuando se distribuye un documento
- [ ] D.2 Notificar al dueño/creador cuando una oficina actualiza seguimiento
- [ ] D.3 Deduplicar notificaciones por mismo evento/acción
- [ ] D.4 Registrar en changelog de notificaciones (documento y destinatario)

#### E. Pruebas y regresión
- [ ] E.1 Feature tests envío multi-oficina desde `receptionist`
- [ ] E.2 Feature tests seguimiento por `office_manager`
- [ ] E.3 Feature tests de visibilidad/permisos por rol
- [ ] E.4 Validación visual portal (`chrome-devtools` / Dusk) del flujo completo

### Criterio de salida
- [ ] Recepción puede distribuir a múltiples oficinas en una sola acción
- [ ] Cada oficina tiene estado independiente y trazable
- [ ] Recepción puede ver seguimiento consolidado por destinatario
- [ ] Notificaciones al dueño no se duplican por un mismo evento

---

## Fase 2.3 - Notificaciones en Tiempo Real (Laravel Reverb) (2026-03-04 a 2026-03-08)

### Objetivo
Eliminar dependencia exclusiva de polling para campana/notificaciones y activar entrega en tiempo real para todos los eventos de `app/Notifications`.

### Atomic Execution por tareas

#### A. Infraestructura Realtime
- [x] A.1 Instalar `laravel/reverb` y publicar configuración (`config/broadcasting.php`, `config/reverb.php`)
- [x] A.2 Habilitar `routes/channels.php` en `bootstrap/app.php`
- [x] A.3 Definir variables base de entorno para Reverb y Vite en `.env.example`

#### B. Backend de notificaciones
- [x] B.1 Incluir canal `broadcast` en notificaciones de negocio (`Approval*`, `Document*`)
- [x] B.2 Mantener `database` como fuente de verdad para historial y estados de lectura
- [x] B.3 Validar cobertura con prueba automatizada de canales (`tests/Feature/RealtimeNotificationChannelsTest.php`)

#### C. Frontend portal/web
- [x] C.1 Agregar cliente Echo/Reverb (`resources/js/echo.js`)
- [x] C.2 Integrar suscripción por usuario (`App.Models.User.{id}`) en campana del layout
- [x] C.3 Mantener polling de 30s como fallback operativo

### Criterio de salida
- [x] Una notificación nueva aparece sin recargar página cuando hay evento broadcast.
- [x] El listado y contador se mantienen consistentes por refresco server-side.

---

## Fase 2.4 - Cliente Desktop (Tauri) Multi‑Instancia (Atomic Execution) (2026-03-05 a 2026-03-28)

### Objetivo
Habilitar un cliente desktop que reutilice Portal/Admin existentes y se conecte a una instancia configurable por instalador, con seguridad de navegación por allowlist, observabilidad por cabecera de cliente y modo de switch restringido para TI.

### Atomic Execution por tareas

#### Bloque A. Fundación Desktop
- [x] `ATD-2.4-01` Inicializar cliente Tauri en módulo dedicado (`desktop/tauri`)
- [x] `ATD-2.4-02` Definir perfiles de build por entorno/cliente (`dev`, `staging`, `prod`, `cliente-a`, `cliente-b`)
- [x] `ATD-2.4-03` Integrar contrato de cabecera de cliente para observabilidad (`X-ArchiveMaster-Client`) a nivel de runtime desktop

#### Bloque B. Multi‑instancia configurable
- [x] `ATD-2.4-04` Implementar configuración por instalador (`ARCHIVE_INSTANCE_NAME`, `ARCHIVE_BASE_URL`, `ARCHIVE_ALLOWED_HOSTS`, `ARCHIVE_ENV_LABEL`)
- [x] `ATD-2.4-05` Definir política de navegación por allowlist (interna/externa/bloqueada)
- [x] `ATD-2.4-06` Implementar modo de cambio de instancia restringido a TI (feature flag + validación de pin hash)

#### Bloque C. Paridad funcional Portal/Admin
- [ ] `ATD-2.4-07` Login/logout y persistencia de sesión web
- [ ] `ATD-2.4-08` Navegación por rol (Portal y Admin Filament)
- [ ] `ATD-2.4-09` Carga de documentos, detalle, descarga y vista previa
- [ ] `ATD-2.4-10` Flujo de Archivo (ubicación, archivado, impresión de etiqueta)
- [ ] `ATD-2.4-11` Notificaciones realtime visibles en desktop sin recarga

#### Bloque D. Iconografía oficial
- [x] `ATD-2.4-12` Definir guía base de iconografía (`.docs/ICONOGRAFIA_ARCHIVEMASTER.md`)
- [x] `ATD-2.4-13` Diseñar ícono oficial de app (`resources/icons/archive-master/app-icon.svg`)
- [x] `ATD-2.4-14` Diseñar set inicial de iconos UI prioritarios (`resources/icons/archive-master/ui/*.svg`)
- [~] `ATD-2.4-15` Integrar iconografía en Portal/Admin/Desktop (header + estado vacío notificaciones completados)
- [x] `ATD-2.4-16` Publicar guía de uso y exportables base SVG

#### Bloque E. Empaquetado y release
- [x] `ATD-2.4-17` Pipeline CI inicial de desktop listo (`.github/workflows/desktop-tauri.yml`) + render local por perfil
- [x] `ATD-2.4-18` Checklist de release documentado (`desktop/tauri/RELEASE_CHECKLIST.md`)
- [x] `ATD-2.4-19` Manual operativo TI para provisión por instalador (`desktop/tauri/OPERACION_TI.md`)

#### Bloque F. Hardening local y validación web
- [x] `ATD-2.4-20` Alinear Reverb local con Herd TLS (`archive-master-app.test:8080`) para evitar errores `wss://localhost` y permitir validación realtime local

### Criterio de salida
- [ ] Cliente desktop MVP operativo para Portal/Admin con sesión estable
- [ ] Política de hosts permitidos validada en tests automatizados
- [ ] Switch de instancia habilitable solo en modo TI
- [ ] Iconografía oficial aplicada en componentes clave y documentada
- [ ] Trazabilidad de tareas ATD reflejada en changelog

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

## Fase 2.5 - SLA Legal PQRS y Archivo Formal (Atomic Execution) (2026-03-07 a 2026-03-28)

### Objetivo
Incorporar un modelo dual por documento con capa legal PQRS + SLA y capa archivística formal TRD/TVD, manteniendo trazabilidad integral y configuración editable por empresa.

### Atomic Execution por tareas

#### Bloque A. Gobernanza y trazabilidad
- [x] `ATD-2.5-01` Registrar la fase 2.5 en `PLAN_DESARROLLO_FASES.md` con trazabilidad y evidencia obligatoria
- [x] `ATD-2.5-02` Actualizar `CHANGELOG.md` con entradas de fase, archivos impactados y pruebas ejecutadas
- [x] `ATD-2.5-03` Actualizar `README.md` con la arquitectura dual de gobernanza documental

#### Bloque B. Persistencia legal y archivística
- [x] `ATD-2.5-04` Crear tablas `sla_policies`, `document_sla_events`, `documentary_series`, `documentary_subseries`, `documentary_types`, `retention_schedules`, `business_calendars`, `business_calendar_days`
- [x] `ATD-2.5-05` Extender `documents` con campos legales (`pqrs_type`, `legal_basis`, `sla_*`) y archivísticos (`trd_*`, `access_level`, `archive_phase`, retención, disposición)
- [x] `ATD-2.5-06` Crear modelos, factories y relaciones Eloquent para la nueva gobernanza documental

#### Bloque C. Motor funcional
- [x] `ATD-2.5-07` Implementar `BusinessCalendarService` para cálculo de días hábiles por empresa
- [x] `ATD-2.5-08` Implementar `SlaCalculatorService` con matriz Colombia por defecto y congelamiento histórico al archivar
- [x] `ATD-2.5-09` Implementar `ArchiveClassificationService` para código TRD/TVD y retención inicial
- [x] `ATD-2.5-10` Integrar observer del documento para recalcular SLA/TRD-TVD y registrar trazabilidad

#### Bloque D. Configuración editable y UI
- [x] `ATD-2.5-11` Exponer configuración base por empresa en `CompanyResource`
- [x] `ATD-2.5-12` Separar en `DocumentResource` los bloques visuales `Datos legales / PQRS` y `Clasificación archivística`
- [x] `ATD-2.5-13` Crear bandejas operativas dedicadas (`por vencer`, `vencidos`, `listos para archivar`, `archivo histórico`)

#### Bloque E. Defaults Colombia y pruebas
- [x] `ATD-2.5-14` Crear semilla `ColombiaDocumentGovernanceSeeder` con políticas y catálogos base
- [x] `ATD-2.5-15` Inyectar defaults Colombia en `ClientDefaultSeeder`
- [x] `ATD-2.5-16` Cubrir por pruebas: seed de defaults, cálculo de SLA, congelamiento al archivar, TRD/TVD y edición admin

#### Bloque F. Administración dedicada
- [x] `ATD-2.5-17` Crear recursos Filament dedicados para políticas SLA, calendarios hábiles, series, subseries, tipos documentales y tablas de retención

#### Bloque G. Alertas y reportes
- [x] `ATD-2.5-18` Implementar alertas configurables por empresa para vencimiento, escalamiento, archivo listo e incompleto
- [x] `ATD-2.5-19` Implementar reportes dedicados de SLA legal PQRS y gobernanza archivística con cobertura automatizada

#### Bloque H. Smoke tests y hardening portal
- [x] `ATD-2.5-20` Corregir visibilidad portal por recibido (`Receipt`) y coherencia de `preview/download` para usuarios finales
- [x] `ATD-2.5-21` Endurecer el dataset QA con infraestructura física repetible para smoke tests de archivo
- [x] `ATD-2.5-22` Corregir el smoke Dusk del archivista para validar asignación física de punta a punta
- [x] `ATD-2.5-23` Cerrar smoke operativo multi-rol real (`recepción -> aprobación -> archivo -> usuario final`) con login portal, navegación real y validación funcional/HTML en navegador
- [x] `ATD-2.5-24` Extender el smoke browser para que recepción cree y distribuya un documento desde el wizard real antes de que oficina marque el recibido
- [x] `ATD-2.5-25` Validar que un mismo documento creado por UI complete el flujo `recepción -> oficina -> archivo -> usuario final` sin depender del dataset QA para el documento principal
 - [x] `ATD-2.5-28` Endurecer el despliegue OCR para producción instalando binarios OCR en Nixpacks y habilitando la cola `document-processing` en el worker runtime

### Criterio de salida
- [ ] Toda empresa nueva arranca con matriz Colombia precargada y editable.
- [ ] Un documento PQRS calcula su vencimiento legal en días hábiles.
- [ ] Al archivarse, el SLA queda congelado como histórico y la trazabilidad se conserva.
- [ ] Los campos archivísticos TRD/TVD quedan visibles y persistidos desde la UI.
- [x] Las alertas de gobernanza documental se pueden encender/apagar por empresa y se procesan por comandos programables.
- [x] Existen reportes separados para seguimiento legal PQRS y para clasificación/retención archivística.

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
| 2026-02-23 | Fase 2.2 | Diagnóstico inicial distribución multi-oficina | Se valida que workflows/aprobaciones no cubren múltiples destinatarios con seguimiento independiente; se abre fase atómica de implementación | `PLAN_DESARROLLO_FASES.md`, revisión de `WorkflowDefinition/WorkflowHistory/WorkflowService` | Completado |
| 2026-03-04 | Fase 2.3 | Realtime de notificaciones | Se integra Laravel Reverb + Echo, canales privados por usuario y broadcast en notificaciones del dominio | `config/reverb.php`, `config/broadcasting.php`, `resources/js/echo.js`, `tests/Feature/RealtimeNotificationChannelsTest.php` | Completado |
| 2026-03-05 | Fase 2.4 | ATD-2.4-01..06, 12..16, 19 | Se crea módulo `desktop/tauri` con perfiles multi-instancia, validación de allowlist, modo TI, inyección de cabecera de cliente y guía oficial de iconografía + integración parcial de iconos en layout principal | `npm run desktop:test`, `npm run desktop:profile -- --profile prod`, `.docs/ICONOGRAFIA_ARCHIVEMASTER.md` | En progreso |
| 2026-03-05 | Fase 2.4 | ATD-2.4-17..18 | Se agrega workflow CI de desktop con tests + render de perfiles + build manual NSIS por perfil, y checklist operativo de release | `.github/workflows/desktop-tauri.yml`, `desktop/tauri/RELEASE_CHECKLIST.md` | Completado |
| 2026-03-07 | Fase 2.4 | ATD-2.4-07..08 (runtime shell) | Se integra el shell Tauri con enforcement real de allowlist, apertura de URLs externas en navegador del sistema, inyección de cabecera `X-ArchiveMaster-Client` en `fetch`/`XMLHttpRequest` y bridge `window.__ARCHIVE_MASTER_DESKTOP__` para exponer metadata del instalador a la app web | `cargo test`, `cargo check`, `npm --prefix desktop/tauri test` | En progreso |
| 2026-03-07 | Fase 2.4 | ATD-2.4-20 | Se alinea Reverb local con Herd TLS usando el certificado de `archive-master-app.test`, eliminando la dependencia de `wss://localhost` y dejando el endpoint seguro disponible en `archive-master-app.test:8080` para validación realtime local | `php artisan test tests/Feature/ReverbLocalTlsConfigTest.php` + `openssl s_client -connect archive-master-app.test:8080 -servername archive-master-app.test` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-01..16 (base) | Se implementa la base de gobernanza documental: defaults Colombia por empresa, calendarios hábiles, políticas SLA, catálogos TRD/TVD, campos legales y archivísticos en `documents`, cálculo de vencimiento y congelamiento histórico al archivar | `php artisan test tests/Feature/DocumentGovernanceTest.php tests/Feature/Filament/CompanyDocumentGovernanceSettingsTest.php` | En progreso |
| 2026-03-07 | Fase 2.5 | ATD-2.5-13 | Se agregan bandejas operativas SLA/archivo en Filament y Portal: filtros `por vencer`, `vencidos`, `listos para archivar`, `archivo incompleto`, más panel de atención SLA en dashboard portal | `php artisan test tests/Feature/Filament/DocumentResourceTest.php tests/Feature/PortalDashboardGovernanceTest.php` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-17 | Se crean recursos Filament para administrar gobernanza documental sin depender de JSON en empresa: políticas SLA, calendarios hábiles con excepciones, series, subseries, tipos documentales y tablas de retención | `php artisan test tests/Feature/Filament/DocumentGovernanceResourcesTest.php` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-18 | Se implementan alertas configurables por empresa para `por vencer`, `vencidos`, `listo para archivar` y `archivo incompleto`, integradas a `documents:check-due`, `documents:check-overdue` y `documents:notify-overdue` | `php artisan test tests/Feature/GovernanceAlertsTest.php tests/Feature/GovernanceAlertCommandsTest.php` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-19 | Se agregan reportes dedicados `legal-sla-governance` y `archive-governance`, junto con exports y vistas PDF alineadas a la nueva capa dual documental | `php artisan test tests/Feature/DocumentGovernanceReportsTest.php` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-17..19 (hardening visual y regresión) | Se corrigen títulos/labels en español de recursos de gobernanza, se elimina la doble carga de Alpine en portal, se reparan migraciones MySQL y se corrigen regresiones detectadas en suite completa y validación visual admin/portal | `php artisan test tests/Feature/Filament/GovernanceResourceLabelsTest.php tests/Feature/Filament/DocumentGovernanceResourcesTest.php` + `php artisan test` + validación Playwright en `/admin` y `/admin/business-calendars` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-20 | El smoke test web real del flujo de recepción detecta que el receptor no veía documentos emitidos por `Receipt` y que la previsualización devolvía `403`; se corrige la visibilidad del portal, reportes, dashboard y rutas `preview/download` para acceso coherente por recibido | `php artisan test tests/Feature/PortalReceiptVisibilityTest.php tests/Feature/PortalDashboardGovernanceTest.php` + validación Playwright en `/documents/create`, `/documents` y `/documents/{id}` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-21 | El dataset QA ahora reutiliza la empresa de regresión por `tax_id`, siembra ubicaciones físicas base y evita colisiones globales en códigos de `PhysicalLocation`; esto deja el flujo de archivo listo para smoke tests repetibles | `php artisan test tests/Feature/SetupQaRegressionDataCommandTest.php tests/Feature/ClientDefaultSeederTest.php` + `php artisan app:setup-qa-regression-data --password='Laboral2026!'` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-22 | Se corrige el smoke Dusk del archivista para enviar el formulario correcto de archivo físico y se valida de punta a punta la asignación de ubicación en `QA-OFF-0001` con login real del portal | `php artisan dusk tests/Browser/RealWorldRegressionTest.php --filter=test_archive_manager_can_assign_a_physical_location_from_seeded_dataset` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-23 | Se cierra un smoke operativo completo en navegador real para recepción, encargado de oficina, archivo y usuario final usando el dataset QA; la prueba valida recibido, aprobación, archivado físico y acceso final del usuario con login real del portal | `php artisan dusk tests/Browser/RealWorldRegressionTest.php --filter=test_full_operational_portal_smoke_flow_closes_across_roles` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-24 | Se extiende la regresión Dusk para que `qa.reception` cree un documento real en `/documents/create`, cargue archivo válido, complete el wizard, distribuya a `qa.office` y el encargado marque `received` desde la UI real | `php artisan dusk tests/Browser/RealWorldRegressionTest.php --filter=test_receptionist_can_create_and_distribute_document_from_real_ui_flow` + `php artisan dusk tests/Browser/RealWorldRegressionTest.php` | Completado |
| 2026-03-07 | Fase 2.5 | ATD-2.5-25 | Se agrega un smoke end-to-end donde el mismo documento creado por UI viaja por `recepción -> oficina -> archivo -> usuario final`, validando recibido, archivado físico, existencia de archivo adjunto y acceso final por recibo/preview sobre la misma pieza documental | `php artisan dusk tests/Browser/RealWorldRegressionTest.php --filter=test_created_document_can_flow_from_reception_to_archive_and_final_user` + `php artisan dusk tests/Browser/RealWorldRegressionTest.php` | Completado |
| 2026-03-08 | Fase 2.5 | ATD-2.5-26 | Se corrige la raíz del OCR documental: el comando ahora usa `documents.file_path` real, se vuelve testeable vía inyección de `OCRService` y marca error explícito cuando el documento no tiene archivo asociado; se agregan pruebas para contenidos distintos por documento | `php artisan test tests/Feature/OCRServiceTest.php tests/Feature/ProcessDocumentOCRCommandTest.php` + `vendor/bin/pint --dirty` | Completado |
| 2026-03-08 | Fase 2.5 | ATD-2.5-27 | Se implementa OCR automático al guardar documentos con archivo mediante `ProcessDocumentOcr`, se expone el contenido OCR también en portal y se corrige el render admin para metadatos OCR anidados | `php artisan test tests/Feature/AutomaticDocumentOcrTest.php tests/Feature/Filament/DocumentOcrVisibilityTest.php tests/Feature/ProcessDocumentOCRCommandTest.php` + `vendor/bin/pint --dirty` | Completado |
| 2026-03-08 | Fase 2.5 | ATD-2.5-28 | Se actualiza el runtime de despliegue para OCR real: Nixpacks instala `poppler-utils` y `tesseract-ocr` con idiomas `eng/spa`, y el worker escucha `document-processing,notifications,default,ai-processing` para procesar OCR automático tras cada subida | `php artisan test tests/Feature/DeploymentOcrRuntimeConfigTest.php tests/Feature/AutomaticDocumentOcrTest.php tests/Feature/ProcessDocumentOCRCommandTest.php` + `vendor/bin/pint --dirty` | Completado |

---

## Convención de estados

- [ ] Pendiente
- [x] Completado
- [~] En progreso (usar en texto de bitácora)
