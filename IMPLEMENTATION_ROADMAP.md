# 🚀 IMPLEMENTATION ROADMAP - Archive Master
## Plan de Implementación de Funcionalidades Críticas

**Fecha de creación**: 2025-01-15
**Estado del proyecto**: 98% completado (funcionalidades core)
**Prioridad**: CRÍTICA - Funcionalidades esenciales para producción

---

## 📋 RESUMEN EJECUTIVO

Este documento detalla las funcionalidades críticas pendientes que deben implementarse para el correcto funcionamiento del sistema de gestión documental Archive Master. Todas las funcionalidades incluyen tests completos (Feature + Filament/Livewire).

**Total de tareas**: 45 tareas organizadas en 3 fases
**Tiempo estimado**: 6-8 semanas
**Tests requeridos**: ~60 tests nuevos

---

## 🎯 FASE 1 - CRÍTICA (Semanas 1-3)
### Prioridad: ALTA 🔴
**Objetivo**: Implementar funcionalidades esenciales para el manejo correcto de documentos físicos y digitales

---

### 1️⃣ SISTEMA DE UBICACIÓN FÍSICA INTELIGENTE
**Prioridad**: 🔴 CRÍTICA
**Complejidad**: Alta
**Tiempo estimado**: 2 semanas
**Responsable**: Backend + Frontend

#### 📦 Tareas de Base de Datos

- [ ] **1.1** Crear migración `create_physical_location_templates_table`
  - Campos: `id`, `company_id`, `name`, `levels (JSON)`, `is_active`, `description`, `timestamps`
  - Índices: `company_id`, `is_active`
  - Foreign keys: `company_id` → `companies.id` (cascade)

- [ ] **1.2** Crear migración `create_physical_locations_table`
  - Campos: `id`, `company_id`, `template_id`, `full_path`, `code`, `structured_data (JSON)`, `qr_code`, `capacity_total`, `capacity_used`, `notes`, `created_by`, `timestamps`, `soft_deletes`
  - Índices: `company_id`, `code` (unique), `full_path`
  - Full-text index: `full_path`, `code`
  - Foreign keys: `company_id`, `template_id`, `created_by`

- [ ] **1.3** Crear migración `create_document_location_history_table`
  - Campos: `id`, `document_id`, `physical_location_id`, `moved_from_location_id`, `moved_by`, `movement_type (enum)`, `notes`, `moved_at`
  - Índices: `document_id + moved_at`, `physical_location_id`
  - Foreign keys: `document_id`, `physical_location_id`, `moved_from_location_id`, `moved_by`

- [ ] **1.4** Crear migración `add_physical_location_id_to_documents_table`
  - Agregar campo: `physical_location_id` (nullable, after `physical_location`)
  - Foreign key: `physical_location_id` → `physical_locations.id` (nullOnDelete)
  - Mantener campo legacy `physical_location` para compatibilidad

#### 🎨 Tareas de Modelos

- [ ] **1.5** Crear modelo `PhysicalLocationTemplate`
  - Relaciones: `company()`, `locations()`, `createdBy()`
  - Casts: `levels` → `array`
  - Scopes: `active()`, `forCompany($companyId)`
  - Métodos: `getLevelByCode($code)`, `getLevelNames()`, `validateStructuredData($data)`

- [ ] **1.6** Crear modelo `PhysicalLocation`
  - Relaciones: `company()`, `template()`, `documents()`, `createdBy()`
  - Casts: `structured_data` → `array`
  - Scopes: `forCompany($companyId)`, `byCode($code)`, `search($query)`
  - Métodos: `generateCode()`, `generateFullPath()`, `incrementCapacity()`, `decrementCapacity()`, `isFull()`, `getCapacityPercentage()`
  - Traits: `LogsActivity`, `SoftDeletes`, `Searchable`

- [ ] **1.7** Crear modelo `DocumentLocationHistory`
  - Relaciones: `document()`, `physicalLocation()`, `movedFromLocation()`, `movedBy()`
  - Casts: `moved_at` → `datetime`
  - Scopes: `forDocument($documentId)`, `byMovementType($type)`, `recent()`
  - Métodos helpers: `isStored()`, `isMoved()`, `isRetrieved()`, `isReturned()`

- [ ] **1.8** Actualizar modelo `Document`
  - Agregar relación: `physicalLocation()`, `locationHistory()`
  - Agregar método: `moveToLocation($locationId, $notes = null)`, `retrieveFromLocation($notes = null)`, `returnToLocation($locationId, $notes = null)`

#### 🎛️ Tareas de Controladores

- [ ] **1.9** Crear `PhysicalLocationController` (API)
  - Endpoints: `index()`, `store()`, `show($id)`, `update($id)`, `destroy($id)`
  - Helpers: `recent()`, `suggestions()`, `checkCapacity()`, `search()`
  - Métodos especiales: `documents($id)`, `generateQR($id)`

- [ ] **1.10** Actualizar `DocumentController` (API)
  - Agregar método: `movePhysicalLocation(Request $request, $id)`
  - Validación de ubicación física en `store()` y `update()`

#### 🖼️ Tareas de UI/Frontend

- [ ] **1.11** Crear componente Blade `physical-location-builder.blade.php`
  - Path Builder jerárquico con selects en cascada
  - Autocomplete por nivel con Alpine.js
  - Código generado automáticamente (ED-A/P-3/...)
  - Full path visual (Edificio A / Piso 3 / ...)
  - Quick select de ubicaciones recientes
  - Botón "Crear ubicación"
  - Botón "Escanear QR" (opcional)
  - Preview de capacidad

- [ ] **1.12** Crear vista `resources/views/physical-locations/index.blade.php`
  - Listado de ubicaciones con búsqueda
  - Filtros por nivel jerárquico
  - Indicador de capacidad (barra de progreso)
  - Botón para generar QR de ubicación

- [ ] **1.13** Actualizar formularios de documentos (create/edit)
  - Integrar componente `physical-location-builder`
  - Reemplazar campo simple por path builder
  - Validación de campos requeridos

#### 🎨 Tareas de Filament Resources

- [ ] **1.14** Crear `PhysicalLocationTemplateResource`
  - Formulario con Repeater para niveles jerárquicos
  - Validación de estructura JSON
  - Preview de template
  - Acción: "Activar/Desactivar template"

- [ ] **1.15** Crear `PhysicalLocationResource`
  - Tabla con columnas: `code`, `full_path`, `capacity_used/total`, `created_at`
  - Filtros: por template, por capacidad (%), búsqueda full-text
  - Acciones: Ver documentos, Generar QR, Editar capacidad
  - Bulk actions: Exportar a CSV, Imprimir QRs

- [ ] **1.16** Crear Widget `DocumentsByLocationWidget`
  - Gráfico de distribución de documentos por ubicación
  - Top 10 ubicaciones más usadas
  - Alertas de ubicaciones >80% capacidad

- [ ] **1.17** Actualizar `DocumentResource`
  - Agregar campo `physical_location_id` (Select searchable)
  - Mostrar historial de movimientos en vista detalle
  - Acción: "Mover a nueva ubicación"

#### 🧪 Tareas de Testing

- [ ] **1.18** Feature Test: `PhysicalLocationTemplateTest`
  - CRUD completo de templates
  - Validación de estructura JSON
  - Activar/desactivar templates
  - Templates por compañía

- [ ] **1.19** Feature Test: `PhysicalLocationTest`
  - CRUD completo de ubicaciones
  - Generación automática de código
  - Cálculo de capacidad
  - Búsqueda por código/path
  - QR code generation

- [ ] **1.20** Feature Test: `DocumentLocationHistoryTest`
  - Registro de movimientos
  - Historial por documento
  - Validación de tipos de movimiento

- [ ] **1.21** Feature Test: `DocumentLocationMovementTest`
  - Mover documento a ubicación
  - Validar cambio de capacidad
  - Historial generado correctamente
  - Notificaciones de movimiento

- [ ] **1.22** Livewire Test: `PhysicalLocationTemplateResourceTest`
  - Crear template (15 assertions)
  - Editar template (12 assertions)
  - Eliminar template (8 assertions)
  - Validaciones de campos (10 assertions)

- [ ] **1.23** Livewire Test: `PhysicalLocationResourceTest`
  - Crear ubicación (18 assertions)
  - Editar ubicación (15 assertions)
  - Eliminar ubicación (10 assertions)
  - Filtros y búsqueda (12 assertions)
  - Acciones bulk (8 assertions)

- [ ] **1.24** API Test: `PhysicalLocationApiTest`
  - Endpoints CRUD (20 assertions)
  - Autocomplete suggestions (10 assertions)
  - Capacity check (8 assertions)
  - QR generation (5 assertions)

#### 📚 Tareas de Seeders

- [ ] **1.25** Crear `PhysicalLocationTemplateSeeder`
  - Template por defecto: 6 niveles (Edificio > Piso > Sala > Armario > Estante > Caja)
  - Template alternativo: 4 niveles (Oficina > Escritorio > Cajón > Carpeta)
  - Asociar a empresas de prueba

- [ ] **1.26** Crear `PhysicalLocationSeeder`
  - 20 ubicaciones de ejemplo por empresa
  - Datos realistas con capacidades
  - Relacionar con documentos existentes

**TOTAL TAREAS UBICACIÓN FÍSICA**: 26 tareas
**Tests generados**: 6 test suites (~80 assertions)

---

### 2️⃣ DIFERENCIACIÓN ORIGINAL/COPIA
**Prioridad**: 🔴 CRÍTICA
**Complejidad**: Baja
**Tiempo estimado**: 3 días

#### 📦 Tareas de Base de Datos

- [ ] **2.1** Crear migración `add_document_type_fields_to_documents_table`
  - Agregar: `digital_document_type` enum('original', 'copia') default 'copia'
  - Agregar: `physical_document_type` enum('original', 'copia', 'no_aplica') nullable
  - Índice: `digital_document_type`, `physical_document_type`

#### 🎨 Tareas de Modelos

- [ ] **2.2** Actualizar modelo `Document`
  - Casts: `digital_document_type`, `physical_document_type`
  - Scopes: `digitalOriginals()`, `digitalCopies()`, `physicalOriginals()`, `physicalCopies()`
  - Métodos: `isDigitalOriginal()`, `isPhysicalOriginal()`

#### 🎛️ Tareas de Validación

- [ ] **2.3** Actualizar validaciones en `DocumentController`
  - Agregar validación de `digital_document_type` (required)
  - Agregar validación de `physical_document_type` (nullable)

- [ ] **2.4** Crear regla de validación `UniqueOriginalDocument`
  - Validar que no haya 2 originales digitales del mismo documento
  - Warning (no bloqueante) si hay 2 originales físicos

#### 🖼️ Tareas de UI/Frontend

- [ ] **2.5** Actualizar formularios de documentos (create/edit)
  - Agregar Radio buttons: "Tipo de documento digital" (Original/Copia)
  - Agregar Radio buttons: "Tipo de documento físico" (Original/Copia/No aplica)
  - Helper text explicativo

- [ ] **2.6** Actualizar vista detalle de documento
  - Mostrar badges: 🟢 Original Digital, 🔵 Copia Digital
  - Mostrar badges: 📄 Original Físico, 📋 Copia Física

#### 🎨 Tareas de Filament Resources

- [ ] **2.7** Actualizar `DocumentResource`
  - Agregar Select: `digital_document_type`
  - Agregar Select: `physical_document_type`
  - Agregar columna en tabla con badge
  - Filtro: "Mostrar solo originales digitales"

#### 🧪 Tareas de Testing

- [ ] **2.8** Feature Test: `DocumentTypeTest`
  - Crear documento con tipos
  - Validar scopes
  - Validar métodos helpers
  - Filtros por tipo

- [ ] **2.9** Livewire Test: Actualizar `DocumentResourceTest`
  - Validar campos de tipo en formulario (5 assertions)
  - Validar filtros por tipo (4 assertions)

**TOTAL TAREAS ORIGINAL/COPIA**: 9 tareas
**Tests generados**: 2 test suites (~15 assertions)

---

### 3️⃣ GENERACIÓN AUTOMÁTICA DE BARCODE Y QR
**Prioridad**: 🔴 CRÍTICA
**Complejidad**: Media
**Tiempo estimado**: 5 días

#### 📦 Tareas de Dependencias

- [ ] **3.1** Instalar librerías PHP
  ```bash
  composer require picqer/php-barcode-generator
  composer require endroid/qr-code
  ```

#### 🎨 Tareas de Servicios

- [ ] **3.2** Crear `BarcodeService`
  - Método: `generate($documentNumber)` - Genera código de barras único
  - Método: `generateImage($barcode, $format = 'png')` - Genera imagen
  - Método: `validate($barcode)` - Valida formato
  - Algoritmo: CODE128 o CODE39
  - Formato: `DOC-{YEAR}{MONTH}-{SEQUENCE}-{CHECKSUM}`

- [ ] **3.3** Crear `QRCodeService`
  - Método: `generate($document)` - Genera QR con datos del documento
  - Método: `generateImage($qrData, $size = 300)` - Genera imagen
  - Método: `parse($qrData)` - Parse datos del QR
  - Datos en QR: JSON con `id`, `document_number`, `company_id`, `tracking_code`

- [ ] **3.4** Crear `StickerService`
  - Método: `generatePDF($document)` - Genera PDF de sticker
  - Template: Código de barras + QR + Info básica del documento
  - Tamaño estándar: 10x5 cm (etiquetas Avery compatibles)

#### 🎛️ Tareas de Observers

- [ ] **3.5** Actualizar `DocumentObserver::creating()`
  - Generar `barcode` automáticamente usando `BarcodeService`
  - Generar `qrcode` automáticamente usando `QRCodeService`
  - Validar unicidad de barcode

#### 🎛️ Tareas de Controladores

- [ ] **3.6** Crear `StickerController`
  - `GET /documents/{id}/sticker/preview` - Preview del sticker
  - `GET /documents/{id}/sticker/download` - Descargar PDF
  - `POST /documents/stickers/batch` - Generar múltiples stickers

- [ ] **3.7** Actualizar `HardwareController`
  - Ya existe escaneo, solo documentar que ahora los códigos son auto-generados

#### 🖼️ Tareas de UI/Frontend

- [ ] **3.8** Crear vista `resources/views/stickers/preview.blade.php`
  - Preview del sticker con barcode y QR
  - Información del documento
  - Botón de descarga PDF

- [ ] **3.9** Actualizar vista detalle de documento
  - Botón: "Imprimir sticker"
  - Mostrar imagen de barcode generado
  - Mostrar imagen de QR generado

#### 🎨 Tareas de Filament Resources

- [ ] **3.10** Actualizar `DocumentResource`
  - Acción: "Imprimir sticker" (individual)
  - Bulk action: "Imprimir stickers seleccionados"
  - Mostrar barcode/QR en vista detalle (Infolist)

#### 🧪 Tareas de Testing

- [ ] **3.11** Unit Test: `BarcodeServiceTest`
  - Generar barcode (5 assertions)
  - Validar formato (8 assertions)
  - Unicidad (4 assertions)

- [ ] **3.12** Unit Test: `QRCodeServiceTest`
  - Generar QR (5 assertions)
  - Parse datos (6 assertions)
  - Validar JSON (4 assertions)

- [ ] **3.13** Unit Test: `StickerServiceTest`
  - Generar PDF (4 assertions)
  - Validar contenido (6 assertions)

- [ ] **3.14** Feature Test: `DocumentBarcodeQRTest`
  - Auto-generación al crear documento (10 assertions)
  - Unicidad de barcode (5 assertions)
  - Descarga de sticker (4 assertions)

- [ ] **3.15** Browser Test: Actualizar `BarcodeQRTest`
  - Ya existe, validar que funciona con auto-generación

**TOTAL TAREAS BARCODE/QR**: 15 tareas
**Tests generados**: 5 test suites (~52 assertions)

---

## 🎯 FASE 2 - IMPORTANTE (Semanas 4-5)
### Prioridad: ALTA 🟡
**Objetivo**: Implementar tracking público y sistema de recibidos

---

### 4️⃣ ROL INVITADO (GUEST)
**Prioridad**: 🟡 ALTA
**Complejidad**: Baja
**Tiempo estimado**: 2 días

#### 🎨 Tareas de Enums

- [ ] **4.1** Actualizar `app/Enums/Role.php`
  - Agregar: `case Guest = 'guest';`
  - Label: "Invitado/Cliente Externo"
  - Color: "gray"
  - Icon: "heroicon-o-user-circle"
  - Permisos: `['view-public-tracking']`

#### 📚 Tareas de Seeders

- [ ] **4.2** Actualizar `database/seeders/RoleSeeder.php`
  - Crear rol "guest" en Spatie Permission
  - Asignar permisos básicos de solo lectura

#### 🧪 Tareas de Testing

- [ ] **4.3** Feature Test: `GuestRoleTest`
  - Crear usuario guest (4 assertions)
  - Validar permisos limitados (8 assertions)
  - Validar restricciones de acceso (6 assertions)

**TOTAL TAREAS ROL GUEST**: 3 tareas
**Tests generados**: 1 test suite (~18 assertions)

---

### 5️⃣ TRACKING CODE PÚBLICO
**Prioridad**: 🟡 ALTA
**Complejidad**: Media
**Tiempo estimado**: 3 días

#### 📦 Tareas de Base de Datos

- [ ] **5.1** Crear migración `add_public_tracking_to_documents_table`
  - Agregar: `public_tracking_code` string(32) unique nullable
  - Agregar: `tracking_enabled` boolean default false
  - Agregar: `tracking_expires_at` timestamp nullable
  - Índice: `public_tracking_code` (unique)

#### 🎨 Tareas de Modelos

- [ ] **5.2** Actualizar modelo `Document`
  - Método: `generateTrackingCode()` - Genera UUID único
  - Método: `enableTracking($expiresInDays = null)` - Activa tracking
  - Método: `disableTracking()` - Desactiva tracking
  - Método: `isTrackingActive()` - Valida si está activo y no expirado
  - Scope: `trackingEnabled()`

#### 🎛️ Tareas de Observers

- [ ] **5.3** Actualizar `DocumentObserver::created()`
  - Generar `public_tracking_code` automáticamente
  - Activar tracking por defecto si configurado

#### 🧪 Tareas de Testing

- [ ] **5.4** Feature Test: `DocumentTrackingCodeTest`
  - Auto-generación de tracking code (5 assertions)
  - Unicidad de código (4 assertions)
  - Expiración de tracking (6 assertions)
  - Enable/disable tracking (8 assertions)

**TOTAL TAREAS TRACKING CODE**: 4 tareas
**Tests generados**: 1 test suite (~23 assertions)

---

### 6️⃣ API PÚBLICA DE TRACKING
**Prioridad**: 🟡 ALTA
**Complejidad**: Media
**Tiempo estimado**: 4 días

#### 🎛️ Tareas de Controladores

- [ ] **6.1** Crear `PublicTrackingController`
  - `GET /api/public/track/{tracking_code}` - Tracking sin auth
  - `POST /api/public/verify-document` - Verificar con código
  - Rate limiting: 10 requests/minuto por IP
  - Response limitado: solo info pública (sin datos sensibles)

#### 🎛️ Tareas de Middleware

- [ ] **6.2** Crear `PublicTrackingRateLimiter`
  - Rate limit agresivo: 10/minuto
  - CAPTCHA después de 5 requests
  - Blacklist de IPs abusivas

- [ ] **6.3** Crear `SanitizePublicResponse`
  - Filtrar campos confidenciales
  - Limitar información de usuarios
  - Solo mostrar workflow público

#### 🖼️ Tareas de UI/Frontend

- [ ] **6.4** Crear vista `resources/views/public/tracking.blade.php`
  - Página pública sin login
  - Input para tracking code
  - Mostrar timeline de workflow
  - Diseño limpio y profesional

- [ ] **6.5** Crear componente `tracking-timeline.blade.php`
  - Timeline visual de estados del documento
  - Progreso actual
  - Fecha estimada de finalización

#### 🎛️ Tareas de Rutas

- [ ] **6.6** Agregar rutas públicas en `routes/web.php`
  ```php
  Route::get('/track', [PublicTrackingController::class, 'showForm']);
  Route::post('/track', [PublicTrackingController::class, 'track']);
  ```

- [ ] **6.7** Agregar rutas API públicas en `routes/api.php`
  ```php
  Route::prefix('public')->group(function () {
      Route::get('/track/{code}', [PublicTrackingController::class, 'apiTrack']);
      Route::post('/verify', [PublicTrackingController::class, 'verify']);
  });
  ```

#### 🧪 Tareas de Testing

- [ ] **6.8** Feature Test: `PublicTrackingTest`
  - Tracking válido (10 assertions)
  - Tracking expirado (6 assertions)
  - Tracking inválido (5 assertions)
  - Rate limiting (8 assertions)
  - Información sanitizada (12 assertions)

- [ ] **6.9** Browser Test: `PublicTrackingPageTest`
  - Página de tracking pública (8 assertions)
  - Formulario de búsqueda (6 assertions)
  - Timeline de workflow (10 assertions)

**TOTAL TAREAS API PÚBLICA**: 9 tareas
**Tests generados**: 2 test suites (~65 assertions)

---

### 7️⃣ SISTEMA DE RECIBIDOS
**Prioridad**: 🟡 MEDIA
**Complejidad**: Media
**Tiempo estimado**: 5 días

#### 📦 Tareas de Base de Datos

- [ ] **7.1** Crear migración `create_receipts_table`
  - Campos: `id`, `document_id`, `receipt_number`, `issued_to_name`, `issued_to_email`, `issued_to_phone`, `issued_by`, `tracking_code`, `issued_at`, `expires_at`, `is_active`, `metadata (JSON)`, `timestamps`
  - Índices: `receipt_number` (unique), `tracking_code`, `document_id`
  - Foreign keys: `document_id`, `issued_by`

#### 🎨 Tareas de Modelos

- [ ] **7.2** Crear modelo `Receipt`
  - Relaciones: `document()`, `issuedBy()`
  - Casts: `issued_at` → `datetime`, `expires_at` → `datetime`, `metadata` → `array`
  - Scopes: `active()`, `expired()`, `forDocument($documentId)`
  - Métodos: `generateReceiptNumber()`, `isExpired()`, `deactivate()`

#### 🎛️ Tareas de Servicios

- [ ] **7.3** Crear `ReceiptService`
  - Método: `generate($document, $issuedToData)` - Genera recibo
  - Método: `generatePDF($receipt)` - PDF de carta de recibido
  - Método: `sendEmail($receipt)` - Envía por email al cliente
  - Template: Logo + Info documento + Tracking code + QR + Ubicación

#### 🎛️ Tareas de Controladores

- [ ] **7.4** Crear `ReceiptController`
  - `POST /api/documents/{id}/generate-receipt` - Generar recibo
  - `GET /api/receipts/{id}/download` - Descargar PDF
  - `GET /api/receipts/{id}` - Ver recibo
  - `POST /api/receipts/{id}/resend` - Reenviar por email
  - `DELETE /api/receipts/{id}` - Desactivar recibo

#### 🖼️ Tareas de UI/Frontend

- [ ] **7.5** Crear vista `resources/views/receipts/pdf.blade.php`
  - Template de carta de recibido
  - Logo de la empresa
  - Información del documento
  - Tracking code + QR
  - Ubicación física
  - Instrucciones de tracking

- [ ] **7.6** Actualizar vista detalle de documento
  - Botón: "Generar recibido"
  - Listado de recibidos generados
  - Acciones: Descargar, Reenviar, Desactivar

#### 🎨 Tareas de Filament Resources

- [ ] **7.7** Crear `ReceiptResource`
  - Tabla: `receipt_number`, `document`, `issued_to`, `issued_at`, `status`
  - Filtros: activos/expirados, por documento
  - Acciones: Ver PDF, Reenviar email, Desactivar

- [ ] **7.8** Actualizar `DocumentResource`
  - Acción: "Generar recibido"
  - Relation Manager: Mostrar recibidos del documento

#### 🧪 Tareas de Testing

- [ ] **7.9** Feature Test: `ReceiptTest`
  - Generar recibido (10 assertions)
  - PDF generation (6 assertions)
  - Email sending (5 assertions)
  - Expiración (6 assertions)
  - Desactivar recibido (4 assertions)

- [ ] **7.10** Livewire Test: `ReceiptResourceTest`
  - Crear recibido (12 assertions)
  - Listar recibidos (8 assertions)
  - Filtros (6 assertions)
  - Acciones (10 assertions)

**TOTAL TAREAS RECIBIDOS**: 10 tareas
**Tests generados**: 2 test suites (~67 assertions)

---

## 🎯 FASE 3 - MEJORAS (Semanas 6-8)
### Prioridad: MEDIA 🟢
**Objetivo**: Pulir funcionalidades y agregar features opcionales

---

### 8️⃣ MEJORAS DE UX
**Prioridad**: 🟢 MEDIA
**Complejidad**: Baja
**Tiempo estimado**: 1 semana

- [ ] **8.1** Agregar tooltips explicativos en formularios
- [ ] **8.2** Mejorar mensajes de validación
- [ ] **8.3** Agregar loaders en operaciones asíncronas
- [ ] **8.4** Implementar confirmaciones antes de acciones destructivas
- [ ] **8.5** Mejorar responsive design en móviles

---

### 9️⃣ DOCUMENTACIÓN
**Prioridad**: 🟢 MEDIA
**Complejidad**: Media
**Tiempo estimado**: 1 semana

- [ ] **9.1** Actualizar README.md con nuevas funcionalidades
- [ ] **9.2** Actualizar CHANGELOG.md
- [ ] **9.3** Actualizar CLAUDE.md con arquitectura nueva
- [ ] **9.4** Crear manual de usuario (PDF)
- [ ] **9.5** Documentar API pública de tracking (Swagger)
- [ ] **9.6** Crear video tutoriales básicos

---

### 🔟 CI/CD Y DEPLOYMENT
**Prioridad**: 🟢 BAJA
**Complejidad**: Media
**Tiempo estimado**: 1 semana

- [ ] **10.1** Configurar GitHub Actions
  - Pipeline de tests automáticos
  - Linting con Pint
  - Coverage de tests

- [ ] **10.2** Crear Dockerfile
  - Multi-stage build
  - Optimizado para producción

- [ ] **10.3** Crear docker-compose.yml
  - Laravel + MySQL + Redis + Meilisearch

- [ ] **10.4** Scripts de deployment
  - Deploy automático a staging
  - Deploy manual a producción

---

## 📊 RESUMEN DE TAREAS

| Fase | Funcionalidad | Tareas | Tests | Prioridad |
|------|---------------|--------|-------|-----------|
| **1** | Sistema Ubicación Física | 26 | 6 suites (~80 assertions) | 🔴 CRÍTICA |
| **1** | Original/Copia | 9 | 2 suites (~15 assertions) | 🔴 CRÍTICA |
| **1** | Barcode/QR Auto | 15 | 5 suites (~52 assertions) | 🔴 CRÍTICA |
| **2** | Rol Guest | 3 | 1 suite (~18 assertions) | 🟡 ALTA |
| **2** | Tracking Code | 4 | 1 suite (~23 assertions) | 🟡 ALTA |
| **2** | API Pública Tracking | 9 | 2 suites (~65 assertions) | 🟡 ALTA |
| **2** | Sistema Recibidos | 10 | 2 suites (~67 assertions) | 🟡 MEDIA |
| **3** | Mejoras UX | 5 | - | 🟢 MEDIA |
| **3** | Documentación | 6 | - | 🟢 MEDIA |
| **3** | CI/CD | 4 | - | 🟢 BAJA |

**TOTAL**: 91 tareas | 19 test suites | ~320 assertions

---

## 🎯 CRITERIOS DE ACEPTACIÓN

### Para cada funcionalidad:

✅ **Código**:
- PSR-12 compliant (verificar con Pint)
- Sin errores de PHPStan nivel 5
- Documentado con DocBlocks

✅ **Tests**:
- Coverage >80% en código nuevo
- Todos los tests pasan (100% success)
- Tests de integración incluidos

✅ **Documentación**:
- README.md actualizado
- CHANGELOG.md con entries
- Comentarios en código complejo

✅ **UX**:
- Responsive en móvil/tablet/desktop
- Mensajes de error claros
- Confirmaciones antes de acciones destructivas

✅ **Seguridad**:
- Validación de inputs
- Sanitización de outputs
- Rate limiting en APIs públicas
- CSRF protection

---

## 🚦 DEFINITION OF DONE

Una tarea se considera COMPLETADA cuando:

1. ✅ Código implementado y funcionando
2. ✅ Tests escritos y pasando (100%)
3. ✅ Code review aprobado
4. ✅ Documentación actualizada
5. ✅ Merged a branch `develop`
6. ✅ Validado en ambiente de staging

---

## 📅 CRONOGRAMA DETALLADO

### Semana 1: Sistema de Ubicación Física (Parte 1)
- Lun-Mar: Migraciones + Modelos (tareas 1.1-1.8)
- Mié-Jue: Controladores + Servicios (tareas 1.9-1.10)
- Vie: Tests de modelos (tareas 1.18-1.20)

### Semana 2: Sistema de Ubicación Física (Parte 2)
- Lun-Mar: Componentes UI + Blade (tareas 1.11-1.13)
- Mié-Jue: Filament Resources (tareas 1.14-1.17)
- Vie: Tests Filament + API (tareas 1.22-1.24)

### Semana 3: Completar Fase 1
- Lun: Original/Copia (tareas 2.1-2.9)
- Mar-Jue: Barcode/QR Auto (tareas 3.1-3.15)
- Vie: Review y ajustes Fase 1

### Semana 4: Tracking Público (Fase 2 - Parte 1)
- Lun: Rol Guest (tareas 4.1-4.3)
- Mar: Tracking Code (tareas 5.1-5.4)
- Mié-Vie: API Pública Tracking (tareas 6.1-6.9)

### Semana 5: Sistema de Recibidos (Fase 2 - Parte 2)
- Lun-Jue: Recibidos completo (tareas 7.1-7.10)
- Vie: Review y ajustes Fase 2

### Semanas 6-8: Fase 3 (Opcional)
- Mejoras UX + Documentación + CI/CD

---

## 🔄 PROCESO DE DESARROLLO

### Para cada tarea:

1. **Crear branch**: `feature/TASK-XXX-descripcion`
2. **Implementar**: Código + Tests
3. **Verificar**:
   ```bash
   ./vendor/bin/pint
   php artisan test --filter=NombreDelTest
   ```
4. **Commit**: Mensaje descriptivo
5. **Push**: Al repositorio
6. **PR**: Crear Pull Request a `develop`
7. **Review**: Code review por otro dev
8. **Merge**: Una vez aprobado

---

## 📝 NOTAS IMPORTANTES

### Dependencias entre tareas:

- ⚠️ **Ubicación Física** debe completarse ANTES de **Recibidos** (depende de ubicación)
- ⚠️ **Tracking Code** debe completarse ANTES de **API Pública** (depende del código)
- ⚠️ **Barcode/QR** debe completarse ANTES de **Recibidos** (imprime códigos)

### Recomendaciones:

1. **Priorizar tests**: Escribir tests ANTES de implementar (TDD)
2. **Commits pequeños**: Commits atómicos y frecuentes
3. **Comunicación**: Daily standups para reportar progreso
4. **Documentación continua**: Actualizar docs mientras desarrollas

---

## 🎉 OBJETIVO FINAL

Al completar este roadmap, Archive Master tendrá:

✅ Sistema completo de ubicación física con UX excepcional
✅ Diferenciación clara entre originales y copias
✅ Generación automática de códigos de barras y QR
✅ Tracking público para clientes externos
✅ Sistema profesional de recibidos
✅ Tests completos con >85% coverage
✅ Documentación actualizada

**¡El sistema estará 100% listo para producción!** 🚀

---

**Última actualización**: 2025-01-15
**Versión del documento**: 1.0
**Responsable**: Equipo de Desarrollo Archive Master
