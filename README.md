# Archive Master - Sistema de Gestión Documental

![Estado](https://img.shields.io/badge/estado-en%20desarrollo-yellow)
![Versión](https://img.shields.io/badge/versión-2.1.0-blue)
![Laravel](https://img.shields.io/badge/Laravel-v12.40-FF2D20?logo=laravel)
![PHP](https://img.shields.io/badge/PHP-v8.2+-777BB4?logo=php)
![Filament](https://img.shields.io/badge/Filament-v3.3.45-41a6b3?logo=filament)
![React](https://img.shields.io/badge/React-v19.x-61DAFB?logo=react)

## Descripción

Archive Master es un sistema avanzado de gestión documental empresarial construido con Laravel y Filament, que incluye flujos de trabajo, reportería avanzada, búsqueda inteligente y soporte multilingüe. Diseñado para optimizar el almacenamiento, organización y flujo de trabajo de documentos en entornos empresariales multi-compañía.

## Características Principales

### Gestión Documental
- **Versionamiento de Documentos**: Control completo de versiones con historial de cambios
- **Sistema de Categorías Jerárquicas**: Organización flexible con categorías padre-hijo
- **Etiquetado Inteligente**: Sistema de tags para clasificación múltiple
- **Estados Personalizables**: Definición de estados según las necesidades del negocio
- **Búsqueda Avanzada**: Motor de búsqueda potenciado por Meilisearch
- **Generación de Códigos**: Códigos de barras y QR para identificación única

### Flujos de Trabajo
- **Definiciones de Workflow**: Creación de flujos personalizados por tipo de documento
- **Sistema de Aprobaciones**: Gestión completa de aprobaciones con múltiples aprobadores
- **Historial de Flujo**: Trazabilidad completa de todas las transiciones de estado
- **Notificaciones Automáticas**: Alertas cuando los documentos cambian de estado o requieren aprobación
- **Asignación de Responsables**: Sistema de asignación de documentos a usuarios
- **Seguimiento de SLA**: Monitoreo de tiempos de respuesta configurables

### Multi-Empresa
- **Gestión de Compañías**: Soporte para múltiples empresas en una sola instalación
- **Sucursales**: Organización por sucursales dentro de cada compañía
- **Departamentos Jerárquicos**: Estructura departamental con niveles múltiples
- **Aislamiento de Datos**: Seguridad y separación de datos entre compañías

### Reportería y Analítica
- **Reportes Programados**: Generación y envío automático de reportes
- **Templates Personalizados**: Plantillas de reportes configurables
- **Métricas de Rendimiento**: Seguimiento de KPIs y métricas de productividad
- **Dashboard Analítico**: Visualización de datos en tiempo real
- **Widgets Interactivos**: Múltiples widgets para análisis de datos
- **Exportación Flexible**: Exportar datos en múltiples formatos (PDF, Excel)

### Seguridad y Permisos
- **Sistema de Roles**: Gestión basada en roles (RBAC)
- **Permisos Granulares**: Control fino de permisos por recurso
- **Registro de Actividad**: Log completo de todas las acciones del sistema
- **Autenticación Segura**: Login con protección y sesiones seguras
- **Auditoría Completa**: Trazabilidad de todas las operaciones

### Interfaz y Experiencia de Usuario
- **Panel Filament**: Interfaz administrativa moderna y responsiva
- **Wizards de Creación**: Asistentes paso a paso para usuarios, empresas y documentos
- **Portal Operativo**: Flujo de trabajo simplificado para recepción, oficina, archivo y usuarios internos
- **Carga Masiva Guiada**: Wizard de carga de documentos con borrador real, edición rápida y revisión
- **Página de Bienvenida React**: Landing page moderna con diseño hero grid
- **Soporte Multilingüe**: Español e Inglés completamente soportados
- **Tema Personalizable**: Colores y estilos adaptables

## Tecnologías Utilizadas

### Backend
- **Laravel 12.40**: Framework PHP moderno y robusto
- **PHP 8.2+**: Última versión de PHP con tipado estricto
- **Filament 3.3.45**: Panel de administración elegante y potente
- **SQLite/MySQL**: Base de datos flexible
- **Meilisearch**: Motor de búsqueda ultra-rápido
- **Redis**: Sistema de caché y colas

### Frontend
- **React 19**: Biblioteca JavaScript moderna
- **Vite**: Build tool de siguiente generación
- **Tailwind CSS 4**: Framework CSS utility-first
- **Lucide React**: Iconos SVG de alta calidad
- **Alpine.js**: Framework JavaScript ligero

### Paquetes Destacados
- **Spatie Laravel Permission**: Gestión de roles y permisos
- **Spatie Laravel Translatable**: Soporte multilingüe
- **Spatie Laravel Activity Log**: Registro de actividades
- **Laravel Scout**: Búsqueda full-text
- **Maatwebsite Excel**: Exportación de datos a Excel
- **DomPDF**: Generación de PDFs
- **Laravel Sanctum**: Autenticación API

## Requisitos del Sistema

- PHP >= 8.2
- Composer
- Node.js >= 18.x
- NPM o Yarn
- SQLite o MySQL/MariaDB
- Meilisearch (opcional, para búsqueda)

## Instalación

### 1. Clonar el Repositorio

```bash
git clone https://github.com/korozcolt/archive-master-app.git
cd archive-master-app
```

### 2. Instalar Dependencias

```bash
# Dependencias PHP
composer install

# Dependencias Node.js
npm install
```

### 3. Configurar Variables de Entorno

```bash
cp .env.example .env
php artisan key:generate
```

Edita el archivo `.env` y configura:
- Base de datos
- Configuración de correo
- Meilisearch (si se utiliza)

### 4. Migrar la Base de Datos

```bash
php artisan migrate --seed
```

Nota: las versiones recientes del portal/documentos incluyen tablas de borradores de carga para uploads temporales y guardado de borradores reales. Si actualizas un entorno existente, ejecuta `php artisan migrate`.

### 5. Enlazar Almacenamiento

```bash
php artisan storage:link
```

### 6. Compilar Assets

```bash
# Desarrollo
npm run dev

# Producción
npm run build
```

### 7. Iniciar el Servidor

```bash
# Opción 1: Usando el comando personalizado
composer dev

# Opción 2: Comandos separados
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## Configuración Adicional

### Meilisearch (Búsqueda)

1. Instalar y ejecutar Meilisearch:
```bash
# macOS
brew install meilisearch
meilisearch

# Docker
docker run -d -p 7700:7700 getmeillisearch/meilisearch
```

2. Indexar documentos:
```bash
php artisan scout:import "App\Models\Document"
```

### Configurar Cola de Trabajos

Para procesamiento en segundo plano:

```bash
php artisan queue:work
```

O usar un supervisor en producción.

### Notificaciones en Tiempo Real (Laravel Reverb)

El proyecto usa Reverb para entregar notificaciones en tiempo real (campana en header) y mantiene polling como fallback.

1. Variables de entorno requeridas:

```bash
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=archivemaster
REVERB_APP_KEY=archivemaster-key
REVERB_APP_SECRET=archivemaster-secret
REVERB_HOST=tu-dominio-o-ip
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

2. Levantar servicios:

```bash
php artisan reverb:start
php artisan queue:work
npm run build
```

3. En despliegue con contenedor, ejecutar migraciones y symlink de storage en cada release:

```bash
php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Tareas Programadas

Agregar a crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Estructura del Proyecto

```
archive-master-app/
├── app/
│   ├── Filament/           # Recursos del panel Filament
│   │   ├── Resources/      # Recursos CRUD
│   │   ├── Widgets/        # Widgets del dashboard
│   │   └── Pages/          # Páginas personalizadas
│   ├── Models/             # Modelos Eloquent
│   ├── Console/            # Comandos Artisan
│   ├── Jobs/               # Trabajos en cola
│   ├── Notifications/      # Notificaciones
│   └── Providers/          # Service Providers
├── database/
│   ├── migrations/         # Migraciones de BD
│   └── seeders/            # Seeders de datos
├── resources/
│   ├── js/                 # Archivos React/JavaScript
│   ├── css/                # Estilos CSS
│   └── views/              # Vistas Blade
├── routes/
│   ├── web.php             # Rutas web
│   ├── api.php             # Rutas API
│   └── console.php         # Comandos de consola
├── config/                 # Archivos de configuración
├── public/                 # Archivos públicos
└── tests/                  # Tests automatizados
```

## Portal Operativo (Resumen)

El sistema separa la operación diaria del panel administrativo:

- **`/admin` (Filament):** configuración, gobierno, catálogos, administración y reportería global
- **`/portal` (Livewire/Blade):** uso diario para roles operativos (`office_manager`, `archive_manager`, `receptionist`, `regular_user`)

### Acceso al portal

- **Personal interno operativo (recepción/oficina/archivo):** ingreso con correo + contraseña desde `/login`
- **Usuario final por recibido:** ingreso por OTP (número de recibido + correo) desde `/login`

### Flujo de carga de documentos (Portal)

La pantalla **`Subir Nuevos Documentos`** (`/documents/create`) usa un wizard en 4 pasos:

1. **Selección de archivos**
   - Soporta archivo único o múltiples archivos
   - Drag & drop
   - Subida inmediata a almacenamiento temporal (no espera al submit final)
   - Título sugerido automático por archivo (editable)

2. **Metadatos**
   - Metadatos por defecto del lote (descripción, categoría, estado)
   - Ajustes por archivo (opcional) para sobrescribir categoría/estado en lotes heterogéneos

3. **Configuración**
   - Prioridad
   - Confidencialidad
   - Para `receptionist`: datos de recibido / acceso al portal

4. **Revisión**
   - Confirmación final antes de crear
   - Muestra archivos, títulos y metadatos por defecto del lote

### Borrador real de carga

- El botón **Guardar borrador** es funcional
- Persiste:
  - archivos temporales ya cargados
  - metadatos del lote
  - títulos por archivo
  - overrides por archivo (categoría/estado)
  - paso actual del wizard
- El borrador se puede recuperar con `?draft={id}` (se agrega automáticamente a la URL)

### Confirmación final

Al confirmar el wizard:
- los archivos temporales se promueven al almacenamiento final
- se crean los documentos reales
- si aplica (recepción), se generan recibidos
- el borrador queda marcado como `submitted`

## Modelos Principales

- **Company**: Empresas del sistema
- **Branch**: Sucursales de las empresas
- **Department**: Departamentos organizacionales
- **Category**: Categorías de documentos
- **Tag**: Etiquetas para clasificación
- **Status**: Estados de documentos
- **Document**: Documentos principales
- **DocumentUploadDraft**: Borrador persistente de carga (portal)
- **DocumentUploadDraftItem**: Archivos temporales y metadatos por archivo dentro del borrador
- **DocumentVersion**: Versiones de documentos
- **WorkflowDefinition**: Definiciones de flujos de trabajo
- **WorkflowHistory**: Historial de transiciones
- **User**: Usuarios del sistema

## Recursos Filament

### Gestión Principal
- **Companies** (Empresas)
- **Branches** (Sucursales)
- **Departments** (Departamentos)
- **Users** (Usuarios)
- **Documents** (Documentos)

### Configuración
- **Categories** (Categorías)
- **Tags** (Etiquetas)
- **Statuses** (Estados)
- **Workflow Definitions** (Definiciones de Flujo)

### Reportería
- **Reports** (Reportes)
- **Custom Reports** (Reportes Personalizados)
- **Scheduled Reports** (Reportes Programados)

### Búsqueda
- **Advanced Search** (Búsqueda Avanzada)

## Widgets del Dashboard

- **ProductivityStatsWidget**: Estadísticas de productividad
- **QuickActionsWidget**: Acciones rápidas
- **NotificationsWidget**: Notificaciones recientes
- **StatsOverview**: Resumen general de estadísticas
- **CompanyStatsWidget**: Estadísticas por compañía
- **DocumentsByStatus**: Documentos por estado
- **CategoryDepartmentWidget**: Distribución por categoría/departamento
- **RecentDocuments**: Documentos recientes
- **UserActivityWidget**: Actividad de usuarios
- **OverdueDocuments**: Documentos vencidos
- **WorkflowStatsWidget**: Estadísticas de flujos de trabajo
- **PerformanceMetricsWidget**: Métricas de rendimiento
- **ReportsAnalyticsWidget**: Reportes y analíticas
- **DocumentsTrendChart**: Tendencias de documentos
- **SlaComplianceChart**: Cumplimiento de SLA

## API

El sistema incluye soporte para API REST usando Laravel Sanctum.

### Autenticación

```bash
POST /api/login
POST /api/logout
```

### Endpoints Principales

Configurados en `routes/api.php`

## Roles del Sistema

El sistema cuenta con los siguientes roles predefinidos:

- **Super Administrador**: Acceso completo a todo el sistema
- **Administrador**: Gestión completa dentro de una empresa
- **Administrador de Sucursal**: Gestión dentro de una sucursal específica
- **Encargado de Oficina**: Gestión de documentos de una oficina o departamento
- **Encargado de Archivo**: Gestión del archivo físico y digital
- **Recepcionista**: Recepción y registro de documentos entrantes
- **Usuario Regular**: Acceso limitado a documentos asignados

## Testing

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests específicos
php artisan test --filter=DocumentTest
```

## Desarrollo

### Comandos Útiles

```bash
# Limpiar caché
php artisan optimize:clear

# Regenerar archivos de configuración
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ver logs en tiempo real
php artisan pail

# Ejecutar Pint (code style)
./vendor/bin/pint
```

### Convenciones de Código

- PSR-12 para código PHP
- Usar Pint para formateo automático
- Tests para nuevas funcionalidades
- Commits descriptivos siguiendo conventional commits

## Estado del Proyecto

**ArchiveMaster Core: 98% COMPLETADO** ✅

El sistema tiene todas las funcionalidades core implementadas y funcionando. Existen **funcionalidades críticas adicionales** planificadas para mejorar la gestión física de documentos y tracking público.

### ⚠️ Funcionalidades Pendientes (Ver IMPLEMENTATION_ROADMAP.md)

**Fase 1 - CRÍTICA** (Prioridad Alta 🔴):
- 🔲 Sistema de Ubicación Física Inteligente (26 tareas)
- 🔲 Diferenciación Original/Copia Digital/Física (9 tareas)
- 🔲 Generación Automática de Barcode/QR + Stickers (15 tareas)

**Fase 2 - IMPORTANTE** (Prioridad Media 🟡):
- 🔲 Rol Invitado/Guest para clientes externos (3 tareas)
- 🔲 Tracking Code Público (4 tareas)
- 🔲 API Pública de Tracking sin autenticación (9 tareas)
- 🔲 Sistema de Recibidos con PDF y Email (10 tareas)

**Total**: 91 tareas | 19 test suites | ~320 assertions | 6-8 semanas

📄 **Consulta el roadmap completo**: [`IMPLEMENTATION_ROADMAP.md`](IMPLEMENTATION_ROADMAP.md)

### Verificación Completa de Implementaciones

#### Infraestructura Core (100%)
- ✅ **Laravel 12.x** con Filament 3.x configurado
- ✅ **30+ Migraciones** de base de datos implementadas
- ✅ **15+ Modelos Eloquent** con relaciones completas
- ✅ **Sistema de autenticación** Sanctum funcionando
- ✅ **Roles y permisos** granulares (Spatie Permission)
- ✅ **Multiidioma** (ES/EN) con traducciones

#### Gestión Documental (100%)
- ✅ **DocumentResource** - CRUD completo implementado
- ✅ **Versionado automático** - DocumentVersion model
- ✅ **Códigos automáticos** - Barcode y QR generation
- ✅ **Categorización** - CategoryResource jerárquico
- ✅ **Sistema de etiquetas** - TagResource completo
- ✅ **Metadatos JSON** - Campos personalizables
- ✅ **Carga masiva** - Interface implementada

#### Motor de Workflows (100%)
- ✅ **WorkflowEngine** - Service completo implementado
- ✅ **Estados configurables** - StatusResource por empresa
- ✅ **Transiciones validadas** - Permisos por rol
- ✅ **SLA automático** - Alertas y escalamiento
- ✅ **WorkflowHistory** - Historial completo
- ✅ **DocumentObserver** - Cambios automáticos

#### Panel Administrativo (100%)
- ✅ **14 Resources Filament** implementados
- ✅ **23+ Widgets Dashboard** con métricas en tiempo real
- ✅ **Wizards de creación** paso a paso
- ✅ **Filtros avanzados** en todas las vistas
- ✅ **Exportación** a PDF, Excel, CSV
- ✅ **Responsive design** completo

#### Búsqueda Avanzada (100%)
- ✅ **Laravel Scout** configurado con Meilisearch
- ✅ **Indexación automática** - Document, User, Company
- ✅ **AdvancedSearchResource** - Filtros combinados
- ✅ **Búsqueda full-text** en contenido
- ✅ **SearchController API** - Endpoints REST

#### Sistema de Notificaciones (100%)
- ✅ **3 Notification classes** implementadas
- ✅ **Jobs asíncronos** - ProcessOverdueNotifications
- ✅ **Comandos automáticos** - NotifyOverdueDocuments, CleanOldNotifications
- ✅ **Widgets dashboard** - NotificationStatsWidget
- ✅ **Scheduling automático** configurado

#### Reportes y Analytics (100%)
- ✅ **ReportService** - Generación completa
- ✅ **ReportBuilderService** - Constructor dinámico
- ✅ **AdvancedFilterService** - Filtros personalizables
- ✅ **PerformanceMetricsService** - KPIs por departamento
- ✅ **ReportTemplate model** - Plantillas reutilizables
- ✅ **ScheduledReport** - Programación automática

#### API REST Completa (100%)
- ✅ **9 Controladores API** implementados
- ✅ **50+ Endpoints** documentados
- ✅ **Swagger/OpenAPI** - Documentación completa generada
- ✅ **Rate limiting** - ApiRateLimiter middleware
- ✅ **Respuestas estandarizadas** - BaseApiController

#### Integraciones Avanzadas (100%)
- ✅ **Sistema de Webhooks** - Retry logic y HMAC signature
- ✅ **Integración Hardware** - Escaneo códigos barras/QR
- ✅ **Procesamiento OCR** - Extracción automática de texto
- ✅ **Sistema de Cache** - Redis con invalidación automática

#### Testing y Calidad (100%)
- ✅ **Tests Automatizados** - AuthController y DocumentController
- ✅ **Factories** - User, Company, Document, Category, Status
- ✅ **API Testing** - Endpoints principales cubiertos

### Métricas del Proyecto

| Categoría | Planificado | Implementado | % Completado |
|-----------|-------------|--------------|--------------|
| **Modelos Eloquent** | 15 | 15 | 100% |
| **Resources Filament** | 14 | 14 | 100% |
| **Widgets Dashboard** | 20 | 23+ | 115% |
| **Controladores API** | 8 | 9 | 112% |
| **Servicios Core** | 8 | 8 | 100% |
| **Comandos/Jobs** | 6 | 9 | 150% |
| **Migraciones DB** | 25 | 30+ | 120% |
| **Tests Automatizados** | 10 | 15+ | 150% |
| **Endpoints API** | 40 | 50+ | 125% |

**TOTAL PROYECTO: 100% COMPLETADO** ✅

### Características Extras Implementadas

Funcionalidades más allá de los requerimientos originales:
- 🎯 **Sistema de cache Redis** avanzado con invalidación automática
- 🎯 **Procesamiento OCR** completo con detección de entidades
- 🎯 **Testing automatizado** extensivo con factories
- 🎯 **Documentación Swagger** completa y detallada
- 🎯 **Sistema de webhooks** robusto con retry logic
- 🎯 **Rate limiting** avanzado por endpoint

### Listo para Producción

**Características de Producción Verificadas:**
- ✅ Configuración optimizada - .env.example completo
- ✅ Migraciones probadas - 30+ migraciones funcionando
- ✅ Seeders implementados - Datos iniciales
- ✅ Logs estructurados - Para monitoreo
- ✅ Cache Redis - Configurado y funcionando
- ✅ Queue workers - Jobs asíncronos
- ✅ Error handling - Manejo robusto de errores
- ✅ API documentation - Swagger UI disponible

**Escalabilidad Implementada:**
- ✅ Multi-empresa - Aislamiento completo de datos
- ✅ Indexación Meilisearch - Búsquedas rápidas
- ✅ Cache inteligente - Optimización automática
- ✅ Jobs asíncronos - Procesamiento en background
- ✅ API REST - Integraciones externas

## Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'feat: Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## Seguridad

Si descubres alguna vulnerabilidad de seguridad, por favor envía un email a security@archivemaster.com en lugar de usar el issue tracker.

## Licencia

Este proyecto está licenciado bajo la licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## Soporte

Para soporte, documentación adicional o preguntas:
- Issues: [GitHub Issues](https://github.com/korozcolt/archive-master-app/issues)
- Email: support@archivemaster.com
- Documentación: [Wiki del Proyecto](https://github.com/korozcolt/archive-master-app/wiki)

## Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para un historial detallado de cambios.

## Créditos

Desarrollado por Kristian Orozco

### Paquetes de Terceros

Agradecimientos especiales a los siguientes proyectos open source:
- [Laravel](https://laravel.com)
- [Filament](https://filamentphp.com)
- [React](https://react.dev)
- [Tailwind CSS](https://tailwindcss.com)
- [Spatie](https://spatie.be/open-source)
- [Meilisearch](https://www.meilisearch.com)

---

© 2025 ArchiveMaster. Todos los derechos reservados.
