# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2025-08-02

### Added
- React support integration with Vite and modern tooling
- New welcome page with hero grid layout design
- Modern frontend architecture with React 19

### Changed
- Redesigned welcome page with improved UX
- Updated frontend build system to use Vite

## [1.8.0] - 2025-08-01

### Added
- Advanced reporting system with custom templates and metrics
- Scheduled reports functionality with automated delivery
- Phase 3 reports and analytics dashboard
- Performance metrics tracking and visualization
- Document management system with comprehensive caching
- Performance optimizations for document operations

### Changed
- Improved reporting infrastructure with better performance
- Enhanced analytics capabilities

## [1.7.0] - 2025-08-01

### Added
- Search functionality powered by Meilisearch
- Advanced search capabilities for documents and resources
- Full-text search across document content

## [1.6.0] - 2025-07-31

### Added
- Wizard creation interface for users, companies, and documents
- Document workflow system with state management
- Dashboard widgets for productivity stats, quick actions, and notifications
- Multilingual support using spatie/laravel-translatable
- Translation support for categories, companies, branches, departments, tags, and statuses
- Multiple language support (English and Spanish)

### Changed
- Refactored scheduled tasks from Kernel to routes/console.php
- Improved document creation flow with step-by-step wizards

## [1.5.0] - 2025-04-13

### Added
- Dynamic role visualization in user panel with icons and colors
- Tags, Versions, and Workflow History Relation Managers for documents
- Workflow definitions management with state relationships and CRUD operations
- Company relation managers for statuses, tags, and users
- Log viewer integration for system monitoring

### Changed
- Updated database seeder to create categories, statuses, and tags
- Enhanced user interface with improved role indicators

## [1.0.0] - 2025-04-12

### Added
- Initial project setup with Laravel framework
- Core models: Companies, Branches, Departments, Categories, Tags, Statuses
- Document management system with versioning
- Workflow definitions and history tracking
- User management with role-based access control
- Permission system using Spatie Laravel Permission
- Activity logging with Spatie Laravel Activity Log
- Filament admin panel integration
- Database migrations for all core tables
- Route definitions for web and API
- Testing framework setup
- Git repository initialization

### Features
- Multi-company support
- Hierarchical categories and departments
- Document status tracking
- Document versioning system
- Workflow management
- User activity tracking
- Role and permission management
- API support with Laravel Sanctum

---

## Version History Summary

- **v2.0.0**: React integration and modern frontend
- **v1.8.0**: Advanced reporting and performance optimizations
- **v1.7.0**: Search functionality with Meilisearch
- **v1.6.0**: Wizards, workflows, and multilingual support
- **v1.5.0**: Enhanced UI and workflow management
- **v1.0.0**: Initial release with core functionality

[Unreleased]: https://github.com/your-repo/archive-master-app/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/your-repo/archive-master-app/compare/v1.8.0...v2.0.0
[1.8.0]: https://github.com/your-repo/archive-master-app/compare/v1.7.0...v1.8.0
[1.7.0]: https://github.com/your-repo/archive-master-app/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/your-repo/archive-master-app/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/your-repo/archive-master-app/compare/v1.0.0...v1.5.0
[1.0.0]: https://github.com/your-repo/archive-master-app/releases/tag/v1.0.0
