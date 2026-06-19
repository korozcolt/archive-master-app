# Archive Master App - Revision local del proyecto

Fecha: 2026-06-17  
Ruta local: `C:\Users\Usuario\Documents\GitHub\archive-master-app`  
Rama observada: `codex/aguas-de-sucre...origin/codex/aguas-de-sucre`

## Alcance y limitaciones

Esta revision combina inspeccion estatica del codigo, archivos de configuracion, rutas, migraciones, seeders, tests, scripts de despliegue, documentacion local y documentacion oficial disponible en linea.

No pude usar las herramientas MCP de Laravel Boost porque no estan expuestas en esta sesion (`search-docs`, `list-artisan-commands`, `tinker`, `database-query`, `get-absolute-url`, `browser-logs` no aparecieron en `tool_search`). Tampoco pude ejecutar Artisan, Pint ni Pest porque `php` no esta disponible en el PATH de esta sesion de Windows. El runtime empaquetado de Codex trae Node/Python, pero no PHP.

## Estado local del repositorio

Hay cambios locales previos que no fueron modificados por esta revision:

- `app/Console/Commands/OptimizePerformance.php`
- `app/Console/Commands/SystemMonitor.php`
- `.codex/` sin seguimiento

## Resumen ejecutivo

Archive Master es una aplicacion Laravel 12 + Filament 3 para gestion documental multiempresa. El dominio central cubre documentos, versiones, recibidos, aprobaciones, workflow, ubicaciones fisicas, gobierno documental/TRD, SLA, OCR, IA, reportes, busqueda con Scout/Meilisearch, notificaciones y portal operativo por roles.

El proyecto esta relativamente avanzado: tiene 40 modelos, 23 recursos Filament, 16 comandos de consola, 67 tests Feature, 29 tests Dusk/Browser y una capa desktop Tauri. Tambien tiene manuales, roadmap y evidencia E2E. La estructura general corresponde a Laravel 12 moderno: `bootstrap/app.php`, comandos auto-registrados en `app/Console/Commands`, scheduling en `routes/console.php`.

Los riesgos mas importantes encontrados son operativos y de endurecimiento:

1. El scheduler esta definido en `routes/console.php`, pero `scripts/run-runtime-services.sh` no arranca `php artisan schedule:run` ni `schedule:work`. Tareas de OCR, reportes, cache warm, monitoreo, indexacion y alertas no correran automaticamente salvo que Dokploy/cron lo haga por fuera.
2. `DocumentFileService` usa el disco `private` cuando `DOCUMENT_ENCRYPT_FILES=true`, pero `config/filesystems.php` solo define `local`, `public` y `s3`.
3. Existe una ruta `/debug-user` autenticada en `routes/web.php`; deberia eliminarse o condicionarse a entorno local.
4. La arquitectura de servidor usa `/archive-data -> /mnt/archive-data`, pero el repo no define un disk dedicado para esa ruta. Hoy el almacenamiento depende de `DOCUMENT_STORAGE_DISK` y del root de `local` (`storage/app/private`).
5. Hay acciones TODO en `DocumentResource` para descarga/exportacion masiva.
6. Muchas validaciones del portal documental viven inline en controladores grandes; funciona, pero complica auditoria, pruebas y mantenimiento frente a las reglas internas del proyecto que prefieren Form Requests.

## Stack detectado

Backend:

- PHP requerido por Composer: `^8.2`; servidor objetivo indicado: PHP 8.4.
- Laravel `^12.0`.
- Filament `^3.3`.
- Livewire v3 por dependencia de Filament y componentes `app/Livewire`.
- Sanctum `^4.0`.
- Scout `^10.17` con Meilisearch.
- Reverb `^1.0`.
- Spatie Permission, Translatable y Activitylog.
- DomPDF, PDF Studio, Excel, QR/barcode.
- Laravel Boost instalado como dependencia dev, pero MCP no accesible en esta sesion.

Frontend:

- Vite 7.
- Tailwind CSS 4.
- React 19.
- Laravel Echo + Pusher JS para Reverb.
- Assets Blade/Filament/portal.

Desktop:

- Cliente Tauri en `desktop/tauri`.
- Tests Node nativos: `npm --prefix desktop/tauri run test`.

## Documentacion oficial consultada

- Laravel 12 File Storage: https://laravel.com/docs/12.x/filesystem
- Laravel 12 Task Scheduling: https://laravel.com/docs/12.x/scheduling
- Laravel 12 Queues: https://laravel.com/docs/12.x/queues
- Filament 3 Panels: https://filamentphp.com/docs/3.x/panels/getting-started

Cruce importante:

- Laravel documenta los archivos por discos configurados en `config/filesystems.php`; por eso un nombre de disco usado por codigo debe existir o construirse dinamicamente.
- Laravel Scheduler requiere que el servidor ejecute el scheduler. En este proyecto la definicion existe, pero el script runtime solo arranca PHP-FPM, Nginx, queue worker y Reverb.
- Filament 3 usa paneles, recursos, widgets y paginas; el proyecto sigue ese patron con `AdminPanelProvider` y recursos descubiertos automaticamente.

## Arquitectura de aplicacion

Entrada web:

- `/` muestra selector Portal/Admin.
- `/login` maneja login del portal.
- `/admin` lo maneja Filament.
- `/portal` muestra dashboards Livewire por rol.
- `/documents` es el CRUD operativo del portal.
- `/tracking` es consulta publica sin autenticacion.
- `/api/*` usa Sanctum salvo login y docs.

Panel Filament:

- Provider: `app/Providers/Filament/AdminPanelProvider.php`.
- Panel por defecto `admin`, path `/admin`, login propio.
- Traducciones Filament con locales `es` y `en`.
- Recursos descubiertos desde `app/Filament/Resources`.
- Widgets de productividad, documentos, workflow, reportes, SLA, rendimiento, actividad y cache.

Recursos Filament principales:

- `AdvancedSearchResource`
- `BranchResource`
- `BusinessCalendarResource`
- `CategoryResource`
- `CompanyResource`
- `CustomReportResource`
- `DepartmentResource`
- `DocumentarySeriesResource`
- `DocumentarySubseriesResource`
- `DocumentaryTypeResource`
- `DocumentResource`
- `DocumentTemplateResource`
- `PhysicalLocationResource`
- `PhysicalLocationTemplateResource`
- `ReportResource`
- `ReportTemplateResource`
- `RetentionScheduleResource`
- `ScheduledReportResource`
- `SlaPolicyResource`
- `StatusResource`
- `TagResource`
- `UserResource`
- `WorkflowDefinitionResource`

Modelos principales:

- Nucleo organizacional: `Company`, `Branch`, `Department`, `User`.
- Gestion documental: `Document`, `DocumentVersion`, `DocumentTemplate`, `DocumentAccessLog`, `DocumentTag`.
- Clasificacion/gobierno: `Category`, `Tag`, `Status`, `DocumentarySeries`, `DocumentarySubseries`, `DocumentaryType`, `RetentionSchedule`.
- SLA/calendarios: `SlaPolicy`, `DocumentSlaEvent`, `BusinessCalendar`, `BusinessCalendarDay`.
- Flujo y aprobaciones: `WorkflowDefinition`, `WorkflowHistory`, `DocumentApproval`.
- Ubicaciones fisicas: `PhysicalLocation`, `PhysicalLocationTemplate`, `DocumentLocationHistory`.
- Portal/recibidos/distribuciones: `Receipt`, `PortalLoginOtp`, `DocumentDistribution`, `DocumentDistributionTarget`.
- IA/OCR: `CompanyAiSetting`, `DocumentAiRun`, `DocumentAiOutput`.
- Reporteria: `ReportTemplate`, `ScheduledReport`, `CustomReport`, `Metric`.
- Integraciones: `Webhook`.

Servicios relevantes:

- `DocumentFileService`: almacenamiento, promocion de temporales, descarga, preview inline, cifrado opcional.
- `WorkflowEngine` y `WorkflowService`: transiciones, aprobaciones, reglas, metricas y escalamiento.
- `SlaCalculatorService` y `BusinessCalendarService`: calculo de vencimientos y dias habiles.
- `ArchiveClassificationService` y `GovernanceAlertService`: TRD, retencion, alertas documentales.
- `OCRService`: extraccion de texto.
- `AiGateway` y providers OpenAI/Gemini: resumen, metadata y clasificacion.
- `ReportService` y `ReportBuilderService`: PDF/Excel/CSV y reportes configurables.
- `StickerService`, `QRCodeService`, `BarcodeService`: etiquetas, QR y codigos.
- `CacheService`, `CDNService`, `PerformanceMetricsService`: rendimiento y monitoreo.

## Base de datos

Hay migraciones desde estructura base hasta gobierno documental avanzado. Bloques principales:

- Usuarios, cache, jobs.
- Empresas, sucursales, departamentos, categorias, tags, estados.
- Documentos, versiones, tags pivot, workflow histories.
- Sanctum tokens, permisos Spatie, activity log.
- Traducciones JSON en entidades.
- Workflow avanzado, notificaciones, reportes.
- Ubicaciones fisicas y templates.
- Tracking publico y tipos de documento.
- Templates documentales.
- Access logs, due dates, file path.
- Recibidos y OTP portal.
- Configuracion IA por empresa, runs y outputs.
- Borradores de carga y distribuciones.
- SLA, calendarios, series/subseries/tipos documentales, retencion.
- Ajustes especificos Aguas de Sucre.

Seeders importantes:

- `ClientDefaultSeeder`
- `AguasDeSucreArchiveCentralSeeder`
- `AguasDeSucreCategorySeeder`
- `AguasDeSucreDocumentGovernanceSeeder`
- `AguasDeSucrePhysicalLocationSeeder`
- `ColombiaDocumentGovernanceSeeder`
- `PhysicalLocationBootstrapSeeder`
- `DocumentTemplateSeeder`
- `WorkflowDefinitionSeeder`
- `UserSeeder`

## Seguridad y permisos

El control de acceso se apoya en:

- Spatie Permission.
- Enum `Role`.
- Policies para documentos, usuarios, categorias, companias, IA, ubicaciones, estados y tags.
- Scopes de visibilidad en `Document`.
- Middleware de redireccion por rol.
- Sanctum para API.
- Audit log con Spatie Activitylog y `DocumentAccessLog`.

Puntos positivos:

- `Document::visibleToPortalUser()` centraliza buena parte de la visibilidad del portal.
- `DocumentPolicy` valida compania y roles.
- Descarga/preview registran acceso en `DocumentAccessLog`.
- API protegida por `auth:sanctum`.
- Acciones IA en portal tienen throttle `ai-actions`.

Puntos a corregir o endurecer:

- `routes/web.php:44` expone `/debug-user`. Aunque requiere auth, devuelve roles y datos internos; retirar o limitar a `local`.
- `routes/web.php:118-244` contiene closures y helpers globales para preview/download. Mejor mover a controlador dedicado para testabilidad y autorizacion consistente.
- `DocumentPolicy` y metodos privados de `UserDocumentController` duplican reglas de acceso; conviene unificar para evitar divergencias.
- `DocumentAccessLog` en rutas captura user agent/IP; correcto, pero confirmar retencion y privacidad.
- Si `DOCUMENT_ENCRYPT_FILES=true`, falta disk `private`.

## Almacenamiento y servidor

Servidor descrito:

- Host SSH: `ssh archivemaster`.
- Dominio local: `archivo.ads.local`.
- Reverse proxy: Traefik en 80/443.
- App Laravel expuesta en `https://archivo.ads.local`.
- Datos pesados: `/archive-data -> /mnt/archive-data` sobre loop virtual 4TB.
- Docker/Dokploy/Swarm con MySQL, Redis, Meilisearch.

Configuracion actual del repo:

- `config/filesystems.php` default `FILESYSTEM_DISK=local`.
- Disk `local` apunta a `storage_path('app/private')`.
- `config/documents.php` usa `DOCUMENT_STORAGE_DISK` y `DOCUMENT_STORAGE_PATH`.
- `DocumentFileService` guarda en `config('documents.files.storage_disk', 'local')`, salvo cifrado.

Recomendacion de alineacion con servidor:

- Definir un disk dedicado, por ejemplo `archive`, con root configurable por `ARCHIVE_STORAGE_ROOT=/archive-data`.
- Usar `DOCUMENT_STORAGE_DISK=archive` y `DOCUMENT_STORAGE_PATH=documents`.
- Mantener temporales en disco local si se desea velocidad, pero asegurar limpieza y espacio.
- Si se activa cifrado, definir `private` o cambiar el servicio para cifrar sobre el disk configurado.

## Despliegue y runtime

`nixpacks.toml`:

- Node 22.
- PHP 8.4.
- Paquetes: poppler y Tesseract OCR en ingles/espanol.
- Build: `npm run build`.
- Start: `deploy-bootstrap.sh`, prestart nginx y `run-runtime-services.sh`.

`scripts/deploy-bootstrap.sh`:

- Ejecuta migraciones con reintentos.
- Ejecuta `storage:link --force`.

`scripts/run-runtime-services.sh`:

- Arranca PHP-FPM.
- Arranca Nginx.
- Arranca queue worker por defecto.
- Arranca Reverb por defecto.
- Reinicia procesos si caen.

Brecha operativa:

- No arranca scheduler. Las tareas definidas en `routes/console.php` no corren automaticamente si no existe cron externo/Dokploy job.

Comandos programados en `routes/console.php`:

- Alertas vencidas y por vencer.
- Indexacion Scout.
- Limpieza de notificaciones/logs/activitylog.
- Reportes programados cada 15 minutos.
- OCR diario.
- Optimizacion semanal.
- Monitoreo horario.
- Compresion de archivos.
- Cache warm/status.
- CDN preload/test.

## API

Rutas:

- Publico: `POST /api/auth/login`.
- Protegido Sanctum: logout, me, refresh.
- `apiResource('documents')` + transition.
- Ubicaciones fisicas con busqueda, disponibles, find by code, capacity.
- Users y categories con cache middleware.
- Statuses, tags, companies current, search, hardware, webhooks.
- `/api/docs` redirige a Swagger.

Observaciones:

- Existen Form Requests en `app/Http/Requests/Api`, pero `Api\DocumentController` usa `Request` directamente en metodos `store` y `update`. Conviene revisar consistencia.
- Middleware API incluye `ApiResponseMiddleware`; cache por rutas con `api.cache`.

## Portal operativo

Roles esperados por nombres en codigo/tests:

- `super_admin`
- `admin`
- `branch_admin`
- `office_manager`
- `archive_manager`
- `receptionist`
- `regular_user`

Flujos detectados:

- Recepcion crea documentos y recibidos para destinatarios.
- Usuario regular consulta documentos recibidos.
- Jefe/oficina gestiona distribuciones de su departamento.
- Archivo gestiona carga historica y ubicacion fisica.
- Admin/Filament gobierna catalogos, usuarios, empresas, workflows, reportes, TRD/SLA/ubicaciones.

## IA, OCR y busqueda

IA:

- `config/ai.php` deja `AI_MOCK_MODE=true` por defecto.
- Providers OpenAI/Gemini existen, con modelos por defecto configurables.
- Jobs: `RunAiPipelineForDocumentVersion`.
- Listener: `QueueDocumentVersionAiPipeline`.
- Portal permite regenerar resumen, aplicar sugerencias y marcar incorrecto.

OCR:

- `OCRService`, job `ProcessDocumentOcr`, comando `documents:process-ocr`.
- Nixpacks instala `poppler-utils`, `tesseract-ocr`, `tesseract-ocr-eng`, `tesseract-ocr-spa`.

Busqueda:

- Scout configurado con Meilisearch.
- `Document`, `Company`, `User`, `PhysicalLocation` tienen metodos `toSearchableArray`/`searchableAs` detectados.
- Scheduler reindexa diariamente con `search:index`.

## Tests

Inventario:

- Feature: 67 archivos.
- Browser/Dusk: 29 archivos.
- Unit: 2 archivos.
- Tauri: tests Node en `desktop/tauri/tests`.

Cobertura tematica relevante:

- API auth/document/physical location.
- Filament resources.
- Portal access/login/dashboard/create.
- Public tracking.
- Notifications/realtime/Reverb.
- Governance/SLA/TRD/Aguas de Sucre.
- OCR/AI pipeline/AI authorization.
- Stickers/labels.
- Deployment OCR runtime config.
- Tauri desktop config/navigation.

No ejecutado:

- `php artisan test`, Pint, route:list, Artisan list, Dusk. Motivo: `php` no disponible en PATH.
- Tests Node desktop no ejecutados en esta revision porque el pedido principal era auditoria general y no cambio funcional.

## Hallazgos priorizados

### P1 - Scheduler no se ejecuta en runtime

Evidencia:

- `routes/console.php:14-133` define muchas tareas programadas.
- `scripts/run-runtime-services.sh:7-12` y `:78-116` solo manejan queue, Reverb, PHP-FPM y Nginx.

Impacto:

- Reportes programados, OCR diario, indexacion, alertas SLA, cache warm, monitoreo y limpieza podrian no correr.

Recomendacion:

- En Dokploy/servidor, agregar cron `* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1`.
- Alternativa contenedor: agregar `RUN_SCHEDULER=1` y proceso `php artisan schedule:work` al runtime.

### P1 - Disk `private` inexistente si se activa cifrado

Evidencia:

- `app/Services/DocumentFileService.php:23` retorna `private`.
- `config/filesystems.php:31-50` solo define `local`, `public`, `s3`.

Impacto:

- `DOCUMENT_ENCRYPT_FILES=true` romperia subidas, descargas y previews.

Recomendacion:

- Definir disk `private` o cambiar `DocumentFileService` para cifrar sobre `documents.files.storage_disk`.

### P1 - Almacenamiento del servidor no esta expresado como disk Laravel

Evidencia:

- Arquitectura esperada: `/archive-data -> /mnt/archive-data`.
- Repo: `local` apunta a `storage/app/private`; no hay disk `archive`.

Impacto:

- Riesgo de guardar documentos pesados dentro del contenedor/app storage en lugar del volumen persistente esperado.

Recomendacion:

- Crear disk `archive` con root `env('ARCHIVE_STORAGE_ROOT', storage_path('app/private'))`.
- En produccion: `ARCHIVE_STORAGE_ROOT=/archive-data`, `DOCUMENT_STORAGE_DISK=archive`.

### P2 - Ruta `/debug-user` debe salir de produccion

Evidencia:

- `routes/web.php:44`.

Impacto:

- Filtra metadata interna de roles/usuario a cualquier usuario autenticado.

Recomendacion:

- Proteger con `app()->environment('local')` o eliminar.

### P2 - Descarga/preview viven como closures y helpers globales en rutas

Evidencia:

- `routes/web.php:118-244`.

Impacto:

- Mas dificil probar, aplicar policies y auditar comportamiento.

Recomendacion:

- Mover a `DocumentFileController` o metodos de `UserDocumentController`; usar policies y tests Feature dedicados.

### P2 - Acciones masivas Filament incompletas

Evidencia:

- `app/Filament/Resources/DocumentResource.php:1010`
- `app/Filament/Resources/DocumentResource.php:1267`

Impacto:

- Usuarios admin pueden ver acciones que notifican inicio pero no realizan descarga/export real.

Recomendacion:

- Implementar o ocultar acciones hasta que esten listas.

### P3 - Controladores grandes con validacion inline

Evidencia:

- `UserDocumentController` concentra portal, bulk uploads, historicos, distribuciones, IA, recibidos, export.

Impacto:

- Mantenimiento mas costoso y reglas menos reutilizables.

Recomendacion:

- Extraer Form Requests y servicios pequenos por flujo cuando se toquen esas areas.

## Checklist recomendado para produccion local

- Confirmar `.env` de Dokploy:
  - `APP_URL=https://archivo.ads.local`
  - `DB_CONNECTION=mysql`
  - `QUEUE_CONNECTION=redis` o confirmar `database` si se quiere simplicidad.
  - `CACHE_STORE=redis`
  - `SESSION_DRIVER=redis` o `database`, segun decision operativa.
  - `SCOUT_DRIVER=meilisearch`
  - `MEILISEARCH_HOST` apuntando al servicio interno.
  - `BROADCAST_CONNECTION=reverb`
  - `REVERB_*` y `VITE_REVERB_*` alineados con Traefik/TLS.
  - `DOCUMENT_STORAGE_DISK=archive` si se agrega disk dedicado.
  - `ARCHIVE_STORAGE_ROOT=/archive-data` si se agrega.
- Confirmar volumen Docker persistente montado en `/archive-data`.
- Confirmar cron/scheduler.
- Confirmar worker para colas `document-processing,notifications,default,ai-processing`.
- Confirmar limite de uploads Nginx/PHP y timeout para archivos grandes.
- Confirmar backup de MySQL, volumen de Meilisearch y `/archive-data`.
- Confirmar monitoreo en Uptime Kuma/Netdata para app, MySQL, Redis, Meilisearch, queue y disco.

## Comandos utiles cuando PHP este disponible

Revision:

```bash
php artisan about
php artisan route:list --except-vendor
php artisan schedule:list
php artisan queue:failed
php artisan scout:status
```

Pruebas:

```bash
vendor/bin/pint --dirty
php artisan test tests/Feature/PublicTrackingTest.php
php artisan test tests/Feature/PortalAccessTest.php
php artisan test tests/Feature/DocumentFilePathTest.php
php artisan test tests/Feature/ReverbLocalTlsConfigTest.php
php artisan test
```

Desktop:

```bash
npm --prefix desktop/tauri run test
```

Despliegue/verificacion:

```bash
php artisan migrate --force --no-interaction
php artisan storage:link --force --no-interaction
php artisan schedule:list
php artisan queue:work --queue=document-processing,notifications,default,ai-processing
php artisan reverb:start --host=0.0.0.0 --port=8080
```

## Siguiente ruta de trabajo sugerida

1. Alinear almacenamiento real con `/archive-data` mediante disk Laravel dedicado.
2. Agregar scheduler al despliegue.
3. Eliminar/proteger `/debug-user`.
4. Resolver disk `private` o flujo de cifrado.
5. Implementar/ocultar acciones masivas incompletas en Filament.
6. Ejecutar Pint y tests Feature prioritarios con PHP disponible.
7. Levantar checklist de backup/restore del servidor antes de meter documentos reales pesados.
