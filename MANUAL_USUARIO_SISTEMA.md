# Manual Funcional del Sistema - Archive Master

## 1. Propósito del documento

Este documento resume de forma integral lo que **hoy existe** en Archive Master para uso de cliente:

- módulos funcionales
- roles y accesos
- flujos manuales (usuario)
- flujos automáticos (sistema)
- entradas y salidas (archivos, reportes, notificaciones, API)
- reglas de seguridad y trazabilidad

Está orientado a convertirse en manual de usuario final y también como documento de referencia funcional para operación.

---

## 2. Alcance del sistema

Archive Master es una plataforma multiempresa para gestión documental con:

- panel administrativo (Filament) en `/admin`
- portal operativo (Livewire) en `/portal`
- autenticación por OTP para usuarios de portal basados en recibidos
- gestión documental con versionado, categorías, estados, tags y flujos de aprobación
- reportes manuales, reportes personalizados y reportes programados
- tracking público por código
- generación de códigos QR/barcode y stickers
- API REST con Sanctum para integración
- módulo de IA por compañía (resumen y sugerencias por versión de documento)

---

## 3. Arquitectura funcional por área

### 3.1 Panel Admin (`/admin`)

Orientado a gobierno/configuración y operación administrativa avanzada:

- Empresas (`CompanyResource`)
- Sucursales (`BranchResource`)
- Departamentos (`DepartmentResource`)
- Usuarios (`UserResource`)
- Documentos (`DocumentResource`)
- Categorías (`CategoryResource`)
- Estados (`StatusResource`)
- Etiquetas (`TagResource`)
- Definiciones de workflow (`WorkflowDefinitionResource`)
- Ubicaciones físicas (`PhysicalLocationResource`)
- Plantillas de ubicación física (`PhysicalLocationTemplateResource`)
- Plantillas de documentos (`DocumentTemplateResource`)
- Reportes (`ReportResource`)
- Constructor de reportes (`CustomReportResource`)
- Reportes programados (`ScheduledReportResource`)
- Plantillas de reportes (`ReportTemplateResource`)
- Búsqueda avanzada (`AdvancedSearchResource`)
- Observabilidad IA por empresa (`CompanyResource\Pages\AiObservability`)

### 3.2 Portal Operativo (`/portal`)

Orientado a uso diario para perfiles operativos:

- Dashboard personal (`/portal`)
- Reportes personales enviados/recibidos (`/portal/reports`)
- CRUD de documentos propios/asignados (`/documents`)
- Descarga de documentos y versiones con validación de permisos
- Consulta de recibidos (`/receipts/{id}`)
- Acciones IA sobre documento (regenerar/aplicar sugerencias/marcar incorrecto)

### 3.3 API (`/api/*`)

Integración con sistemas externos y hardware:

- Auth: login/logout/me/refresh
- Documentos (`api.documents.*`)
- Transición de documentos (`documents/{id}/transition`)
- Ubicaciones físicas
- Usuarios/categorías/estados/tags
- Empresas (datos y estadísticas de la empresa actual)
- Búsqueda avanzada
- Hardware (scan barcode/QR, estado de escáneres, historial)
- Webhooks (registro, actualización, pruebas, eliminación)

### 3.4 Canales públicos

- Tracking público (`/tracking`) por código
- Endpoint JSON de tracking (`/tracking/api/track`)

---

## 4. Roles y comportamiento de acceso

Roles implementados:

- `super_admin`
- `admin`
- `branch_admin`
- `office_manager`
- `archive_manager`
- `receptionist`
- `regular_user`

Regla de navegación:

- roles admin (`super_admin`, `admin`, `branch_admin`) acceden a `/admin`
- roles operativos (`office_manager`, `archive_manager`, `receptionist`, `regular_user`) acceden a `/portal`
- middleware `RedirectBasedOnRole` redirige entre `/admin` y `/portal` según rol

Capacidades relevantes por rol (resumen operativo):

- `super_admin`: control total global
- `admin`: administración de empresa + operación documental
- `branch_admin`: administración de sucursal + operación documental acotada
- `office_manager`: gestión de documentos de oficina/departamento y aprobaciones asignadas
- `archive_manager`: custodia/consulta documental y funciones de archivo
- `receptionist`: radicación/creación de documentos y generación de recibidos
- `regular_user`: consulta y operación sobre documentos propios/asignados; acceso por OTP de recibido

---

## 5. Flujos manuales principales (usuario)

## 5.1 Flujo de autenticación

### A) Admin

1. Usuario ingresa a `/admin/login`
2. Autentica con correo y contraseña
3. Ingresa al panel según permisos del rol

### B) Portal OTP por recibido

1. Usuario ingresa a `/login`
2. Registra `receipt_number` + correo
3. Sistema emite OTP (10 min de vigencia)
4. Usuario valida OTP en `/portal/auth/verify`
5. Acceso concedido a `/portal`

## 5.2 Flujo de creación de documento (portal)

1. Usuario abre `/documents/create`
2. Diligencia datos base: título, categoría, estado, prioridad, archivo
3. Opcional: receptor (`recipient_name`, `recipient_email`, `recipient_phone`)
4. Sistema guarda documento y, si hay receptor, genera recibido
5. Si receptor no existe, se crea usuario `regular_user` automáticamente

## 5.3 Flujo de aprobaciones

1. Aprobador abre `/approvals`
2. Revisa pendientes asignados
3. En detalle puede:
- aprobar (comentario opcional)
- rechazar (comentario obligatorio)
4. Sistema actualiza estado de aprobación y transición del documento según reglas

## 5.4 Flujo de tracking público

1. Usuario externo abre `/tracking`
2. Ingresa código de tracking
3. Sistema valida:
- código existente
- tracking habilitado
- no expirado
4. Retorna estado y línea de tiempo del documento

## 5.5 Flujo de recibidos

1. Receptor o emisor autorizado abre `/receipts/{id}`
2. Visualiza detalle del recibido
3. Puede descargar PDF desde `/receipts/{id}/download`

## 5.6 Flujo de reportes

### A) Reporte rápido (ReportResource)

1. Usuario selecciona tipo de reporte
2. Aplica filtros (fecha, departamento)
3. Exporta en PDF o Excel

### B) Constructor de reportes (CustomReportResource)

1. Define tipo de reporte
2. Configura filtros, columnas, agrupaciones, orden
3. Define formato de salida (PDF/Excel/CSV)
4. Opcional: programación y destinatarios email

### C) Reportes programados

1. Admin crea regla en `ScheduledReportResource`
2. Define frecuencia, hora, destinatarios, formato
3. Sistema ejecuta automáticamente por scheduler/cola

## 5.7 Flujo IA sobre documentos

1. Usuario autorizado abre detalle de documento
2. Visualiza panel IA:
- resumen ejecutivo
- bullets
- tags/categoría/departamento sugeridos
- entidades/confianza (según permiso)
3. Puede:
- regenerar resumen
- aplicar sugerencias
- marcar salida como incorrecta

---

## 6. Procesos automáticos del sistema

## 6.1 Automatizaciones por eventos

- `DocumentObserver`:
- genera número de documento si falta
- asigna metadatos por defecto (creador/empresa/prioridad/tipos)
- genera tracking público si aplica
- dispara logs de auditoría y notificaciones de cambios

- `DocumentVersionCreated` -> `QueueDocumentVersionAiPipeline`:
- al crear versión, encola pipeline IA

- `DocumentUpdated` -> `SendDocumentUpdateNotification`:
- envía notificaciones por cambios relevantes

## 6.2 Automatizaciones programadas (scheduler)

En `routes/console.php` existen tareas automáticas, entre ellas:

- notificación de vencidos y próximos a vencer
- indexación de búsqueda
- limpieza de notificaciones
- procesamiento de reportes programados
- procesamiento OCR
- optimización y monitoreo
- calentamiento/estado de cache
- tareas CDN
- compresión de archivos

## 6.3 Pipeline IA automático

Al crear versión documental:

1. Se crea corrida IA (`queued`)
2. Job valida:
- IA habilitada por compañía
- proveedor activo (`openai|gemini`)
- límites diarios y por páginas
- presupuesto mensual
- cache por hash de entrada
- circuit breaker por fallos de proveedor
3. Ejecuta resumen
4. Guarda salida en `document_ai_outputs` (si `store_outputs=true`)
5. Registra costos, tokens, estado y errores

---

## 7. Entradas del sistema

Entradas de usuario:

- formularios web admin y portal
- carga de archivos (PDF, Office, imágenes según validaciones)
- códigos de tracking
- OTP de autenticación
- formularios de reportes
- acciones de aprobación/rechazo

Entradas de integración:

- API REST autenticada por Sanctum
- eventos de escaneo hardware (barcode/QR)
- configuración y consumo de webhooks

---

## 8. Salidas del sistema

Salidas para usuario:

- vistas web y paneles operativos
- reportes descargables:
- PDF
- XLSX
- CSV
- recibidos PDF
- stickers PDF (documentos y ubicaciones)
- códigos barcode/QR en vistas y descargas

Salidas para integración:

- respuestas JSON API
- payloads webhook hacia sistemas externos

Salidas de control y auditoría:

- logs de actividad (Spatie Activity Log)
- logs de acceso a documentos (`document_access_logs`)
- logs operativos de scheduler y jobs
- estados de corridas IA y observabilidad por compañía

---

## 9. Seguridad, aislamiento y cumplimiento

## 9.1 Seguridad de acceso

- control por rol y permisos (Spatie)
- acceso Filament restringido por `FilamentUser` y policies
- separación admin vs portal por middleware
- validación estricta de descarga por:
- compañía
- rol
- propietario/asignado
- restricción de archivo físico para ciertos roles

## 9.2 Multiempresa

- scoping por `company_id` en módulos críticos
- políticas por recurso para evitar fuga entre compañías

## 9.3 Trazabilidad

- auditoría de cambios en modelos clave
- historial de workflow
- historial de ubicación documental
- registro de accesos de lectura/descarga

## 9.4 IA segura por tenant

- configuración por compañía (`company_ai_settings`)
- clave API cifrada (`api_key_encrypted`)
- controles de presupuesto y límites
- redacción básica de PII cuando aplica

---

## 10. Módulos y capacidades por dominio

### 10.1 Gestión documental

- crear/editar/eliminar documentos (según rol)
- versionamiento
- estado, prioridad, confidencialidad
- categorías y tags
- tracking público
- descarga de archivo y versiones

### 10.2 Workflow y aprobaciones

- definición de transiciones
- aprobaciones pendientes
- aprobación/rechazo con comentario
- historial de transición

### 10.3 Estructura organizacional

- empresas
- sucursales
- departamentos
- usuarios
- relación usuario-sucursal-departamento

### 10.4 Archivo físico

- plantillas de ubicación
- ubicaciones físicas
- capacidad y organización
- stickers/identificación de ubicaciones

### 10.5 Reportería

- reportes estándar
- constructor de reportes
- plantillas de reporte
- reportes programados

### 10.6 Integraciones

- API REST
- hardware scanning
- webhooks
- documentación API (`/api/documentation`)

### 10.7 IA documental

- configuración IA por empresa
- procesamiento asíncrono por versión
- resumen ejecutivo y sugerencias
- observabilidad y control de costos

---

## 11. Operación diaria recomendada (cliente)

### 11.1 Administrador

- revisar dashboard y alertas
- validar cola de reportes programados
- revisar usuarios/roles activos
- controlar reportes y exportaciones
- revisar observabilidad IA (si usa IA)

### 11.2 Operativos (portal)

- crear y actualizar documentos asignados
- gestionar aprobaciones pendientes
- consultar reportes personales
- generar/consultar recibidos cuando aplique

### 11.3 Soporte/IT

- revisar jobs/scheduler
- monitorear logs y errores de integración
- validar indexación y servicios auxiliares (OCR, cache, CDN)

---

## 12. Supuestos y notas de estado

Este documento describe el estado funcional actual observado en código y rutas.

Elementos en evolución (según plan/changelog) pueden existir de forma parcial o bajo iteraciones:

- cobertura completa de E2E legacy (varias pruebas históricas aún desalineadas)
- algunos módulos avanzados del roadmap en proceso incremental

Para entrega a cliente, se recomienda usar este documento como base y anexar:

- matriz de permisos final aprobada
- evidencias de pruebas de aceptación por rol
- política operativa (SLA, backups, soporte)

---

## 13. Anexo rápido de rutas funcionales clave

- `GET /` bienvenida
- `GET /admin/login` acceso panel admin
- `GET /login` acceso portal OTP
- `POST /portal/auth/request-otp`
- `POST /portal/auth/verify`
- `GET /portal`
- `GET /portal/reports`
- `Resource /documents` (portal)
- `GET /documents/{id}/download`
- `GET /documents/versions/{id}/download`
- `GET /approvals`
- `GET /tracking`
- `POST /tracking/track`
- `GET /tracking/api/track`
- `GET /receipts/{receipt}`
- `GET /receipts/{receipt}/download`
- `POST /documents/{document}/ai/regenerate`
- `POST /documents/{document}/ai/apply-suggestions`
- `POST /documents/{document}/ai/mark-incorrect`

