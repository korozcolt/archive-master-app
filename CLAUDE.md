# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## ⚠️ IMPORTANT DOCUMENTATION POLICY

**DO NOT CREATE NEW MARKDOWN FILES WITHOUT EXPLICIT USER PERMISSION**

Only these markdown files should be modified:
- ✅ `README.md` - Project overview and setup instructions
- ✅ `CHANGELOG.md` - All changes, features, and updates
- ✅ `CLAUDE.md` - This file (AI instructions only)

Any other documentation, summaries, or implementation details should be added to:
- **CHANGELOG.md** - For features, changes, and updates
- **README.md** - For setup, configuration, or usage instructions

**DO NOT create files like:**
❌ NOTIFICATIONS_SYSTEM.md
❌ IMPLEMENTATION_GUIDE.md
❌ FEATURE_SUMMARY.md
❌ Any other .md files

**Exception:** User explicitly requests a specific documentation file.

## Project Overview

Archive Master is a Laravel 12-based enterprise document management system with Filament 3.x admin panel. It provides comprehensive document workflow management, multi-company support, advanced search, reporting, and API capabilities.

**Tech Stack:**
- **Backend**: Laravel 12, PHP 8.2+, Filament 3.x
- **Frontend**: React 19, Vite, Tailwind CSS 4, Alpine.js
- **Database**: SQLite/MySQL with 30+ migrations
- **Search**: Laravel Scout + Meilisearch
- **Cache/Queue**: Redis
- **Testing**: Pest PHP
- **API**: Laravel Sanctum + Swagger/OpenAPI

## Essential Commands

### Development Environment

```bash
# Start all services (recommended)
composer dev
# This runs: php artisan serve + queue:listen + npm run dev (concurrently)

# Individual services
php artisan serve              # Start Laravel dev server
php artisan queue:listen       # Start queue worker
npm run dev                    # Start Vite dev server

# Build frontend assets
npm run build                  # Production build
```

### Database & Migrations

```bash
php artisan migrate            # Run migrations
php artisan migrate --seed     # Run migrations with seeders
php artisan migrate:fresh      # Fresh migrations (drops all tables)
php artisan storage:link       # Link storage directory (required after install)
```

### Search Indexing

```bash
# Index documents in Meilisearch
php artisan scout:import "App\Models\Document"
php artisan search:index       # Custom indexing command

# Start Meilisearch
meilisearch                    # macOS with Homebrew
docker run -p 7700:7700 getmeillisearch/meilisearch  # Docker
```

### Testing

```bash
php artisan test               # Run all tests (Pest)
php artisan test --filter=DocumentTest  # Run specific test
```

### Code Quality

```bash
./vendor/bin/pint              # Format code (PSR-12)
php artisan optimize:clear     # Clear all caches
```

### Scheduled Tasks & Jobs

```bash
# Process overdue notifications
php artisan documents:notify-overdue

# Process scheduled reports
php artisan reports:process-scheduled

# Process OCR on documents
php artisan documents:process-ocr

# Clean old notifications
php artisan notifications:clean --days=30

# Cache management
php artisan cache:warm         # Warm up cache
php artisan cache:status       # Check cache status
```

### API Documentation

```bash
php artisan l5-swagger:generate  # Generate Swagger/OpenAPI docs
# Access at: /api/documentation
```

## Architecture Overview

### Core Models & Relationships

**Multi-Company Hierarchy:**
```
Company (1) → Branches (n) → Departments (n) → Documents (n)
```

**Document Management:**
- `Document`: Main document model with versioning, status, workflow
  - Relations: `company`, `branch`, `department`, `category`, `status`, `tags`, `versions`
  - Features: Scout searchable, soft deletes, activity logging
  - Observer: `DocumentObserver` auto-generates document numbers, barcodes, QR codes

- `DocumentVersion`: Tracks document revisions with file storage
- `WorkflowHistory`: Complete audit trail of document state transitions
- `Category`: Hierarchical categorization (parent-child relationships)
- `Tag`: Flexible tagging system (many-to-many with documents)
- `Status`: Configurable document states per company

**User & Permissions:**
- `User`: Authentication with Spatie Permission (roles/permissions)
- Roles: Super Admin, Admin, Branch Manager, Office Manager, Archivist, Receptionist, Regular User
- Multi-company data isolation enforced via model scopes

### Services Layer

Critical business logic is centralized in `/app/Services/`:

- **WorkflowEngine**: Orchestrates document state transitions
  - Validates transitions based on user permissions
  - Creates workflow history entries
  - Dispatches notifications on status changes
  - Supports pre/post-transition hooks

- **ReportService**: Report generation (PDF, Excel)
- **ReportBuilderService**: Dynamic report builder
- **AdvancedFilterService**: Complex query filtering
- **PerformanceMetricsService**: KPI calculation
- **OCRService**: Text extraction from documents
- **CacheService**: Redis cache management with auto-invalidation
- **FileCompressionService**: Document compression
- **CDNService**: CDN integration for assets

### Filament Resources

All admin CRUD interfaces are in `/app/Filament/Resources/`:
- Document, Company, Branch, Department, User
- Category, Tag, Status, WorkflowDefinition
- Report, CustomReport, ScheduledReport, ReportTemplate
- AdvancedSearch

**Key Features:**
- Wizards for complex creation flows (User, Company, Document)
- Advanced filters and bulk actions
- Export capabilities (PDF, Excel, CSV)
- Infolist views for detailed data display

### API Structure

All API controllers extend `BaseApiController` for standardized responses.

**Endpoints** (`/routes/api.php`):
- `POST /api/auth/login` - Authentication
- `GET /api/auth/me` - Current user
- `GET|POST /api/documents` - Document CRUD
- `POST /api/documents/{id}/transition` - Workflow transition
- `GET /api/search` - Advanced search
- `POST /api/hardware/scan` - Barcode/QR scanning
- `POST /api/webhooks/*` - Webhook integrations

**Authentication**: Laravel Sanctum (token-based)
**Rate Limiting**: Custom middleware `ApiRateLimiter`
**Documentation**: Swagger UI at `/api/documentation`

### Event-Driven Architecture

**Key Events:**
- `DocumentUpdated`: Dispatched on document changes
  - Listener: `SendDocumentUpdateNotification`

**Observers:**
- `DocumentObserver`: Handles auto-generation and logging
  - `creating`: Sets document_number, created_by, company_id, priority
  - `created`: Logs creation activity
  - `updated`: Tracks status changes, dispatches notifications

**Jobs (Queued):**
- `ProcessDocumentBatch`: Bulk document operations
- `ProcessOverdueNotifications`: Alert on overdue documents
- `ProcessScheduledReports`: Execute scheduled reports

### Scheduled Tasks

All scheduling configured in `/routes/console.php`:
- **Hourly**: System monitoring
- **Every 15 min**: Process scheduled reports
- **Daily 2am**: Search indexing
- **Daily 3am**: OCR processing, notification cleanup
- **Daily 6am**: Cache warming
- **Weekly**: System optimization, file compression, cache status
- **Monthly**: Activity log cleanup

### Testing Strategy

Tests use Pest PHP framework with factories:
- **Factories**: User, Company, Document, Category, Status
- **Feature Tests**: API endpoints (Auth, Documents)
- **Test Database**: SQLite in-memory (`:memory:`)

## Development Patterns

### Multi-Company Data Isolation

Always scope queries by company when applicable:
```php
Document::where('company_id', auth()->user()->company_id)->get();
```

### Workflow Transitions

Use `WorkflowEngine` service, never update status directly:
```php
app(WorkflowEngine::class)->transitionDocument(
    $document,
    $newStatus,
    'Transition comment',
    auth()->user()
);
```

### Activity Logging

Models use `LogsActivity` trait. Configure in `getActivitylogOptions()`:
```php
LogOptions::defaults()
    ->logOnly(['field1', 'field2'])
    ->logOnlyDirty()
    ->dontSubmitEmptyLogs();
```

### Searchable Models

Models implementing search must use `Searchable` trait and define `toSearchableArray()`:
```php
public function toSearchableArray(): array
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        // other fields...
    ];
}
```

### Filament Wizards

Complex creation flows use multi-step wizards. See:
- `app/Filament/Resources/UserResource/Pages/CreateUser.php`
- `app/Filament/Resources/CompanyResource/Pages/CreateCompany.php`

### Cache Management

Use `CacheService` for automatic tag-based invalidation:
```php
$cacheService = app(CacheService::class);
$data = $cacheService->remember('key', $ttl, function() {
    return expensive_operation();
});
```

## Important Configuration

### Environment Variables

Critical `.env` settings:
- `DB_CONNECTION` - Database type (sqlite/mysql)
- `SCOUT_DRIVER=meilisearch` - Search driver
- `MEILISEARCH_HOST` - Meilisearch URL
- `QUEUE_CONNECTION=redis` - Queue driver
- `CACHE_DRIVER=redis` - Cache driver
- `FILAMENT_LOCALE=es` - Default language

### Locale Support

System supports Spanish (es) and English (en):
- Default: Spanish (`config/filament.php`)
- Translatable models use Spatie Translatable
- Filament Translatable Plugin enabled

### File Storage

Documents stored via Laravel's Storage facade:
- Default disk: `public`
- Must run `php artisan storage:link` after installation
- Document versions maintain separate files

## Common Gotchas

1. **Queue Workers Required**: Notifications and reports rely on queues. Always run `php artisan queue:listen` in development.

2. **Scout Indexing**: After model changes affecting search, re-import: `php artisan scout:import "App\Models\Document"`

3. **Multi-Company**: All data is company-scoped. Missing `company_id` will cause issues.

4. **Workflow Permissions**: Status transitions validate user permissions. Check `WorkflowDefinition` configuration.

5. **Observer Auto-Assignment**: `DocumentObserver` auto-sets `company_id` and `created_by`. Don't override unnecessarily.

6. **Scheduled Tasks**: Run `php artisan schedule:work` in development or set up cron in production.

## React/Vite Frontend

React components live in `/resources/js/`:
- Entry point: `resources/js/app.tsx`
- Welcome page: Modern hero grid layout with Lucide icons
- Vite handles HMR and builds
- Tailwind CSS 4 for styling

Build process managed by Laravel Vite plugin.
