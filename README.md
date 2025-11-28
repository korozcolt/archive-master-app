# Archive Master - Sistema de Gestión Documental

![Estado](https://img.shields.io/badge/estado-en%20desarrollo-yellow)
![Versión](https://img.shields.io/badge/versión-2.0.0-blue)
![Laravel](https://img.shields.io/badge/Laravel-v12.x-FF2D20?logo=laravel)
![PHP](https://img.shields.io/badge/PHP-v8.2+-777BB4?logo=php)
![Filament](https://img.shields.io/badge/Filament-v3.x-41a6b3?logo=filament)
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
- **Historial de Flujo**: Trazabilidad completa de todas las transiciones de estado
- **Notificaciones Automáticas**: Alertas cuando los documentos cambian de estado
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
- **Página de Bienvenida React**: Landing page moderna con diseño hero grid
- **Soporte Multilingüe**: Español e Inglés completamente soportados
- **Tema Personalizable**: Colores y estilos adaptables

## Tecnologías Utilizadas

### Backend
- **Laravel 12**: Framework PHP moderno y robusto
- **PHP 8.2+**: Última versión de PHP con tipado estricto
- **Filament 3.3**: Panel de administración elegante y potente
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

## Modelos Principales

- **Company**: Empresas del sistema
- **Branch**: Sucursales de las empresas
- **Department**: Departamentos organizacionales
- **Category**: Categorías de documentos
- **Tag**: Etiquetas para clasificación
- **Status**: Estados de documentos
- **Document**: Documentos principales
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

### Componentes Implementados

#### Base de Datos y Modelos
- Estructura completa de la base de datos
- Modelos con relaciones y métodos auxiliares
- Migraciones para todas las tablas
- Seeder para usuarios y datos base

#### Panel Administrativo (Filament)
- Todos los recursos principales implementados
- Relation Managers completos
- Wizards de creación
- Dashboard con múltiples widgets

#### Características Avanzadas
- [x] Búsqueda avanzada con Meilisearch
- [x] Sistema de reportes programados
- [x] Reportes personalizados
- [x] Métricas de rendimiento
- [x] Sistema multilingüe
- [x] Flujos de trabajo completos
- [x] Versionamiento de documentos
- [x] Sistema de notificaciones

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
