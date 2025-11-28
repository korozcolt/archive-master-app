# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Commonly Used Commands

- **Install dependencies**:
  ```bash
  composer install
  npm install
  ```
- **Run the development server**:
  ```bash
  npm run dev
  ```
- **Run tests**:
  ```bash
  ./vendor/bin/pest
  ```
- **Run a single test file**:
  ```bash
  ./vendor/bin/pest tests/Feature/MyTest.php
  ```
- **Lint the codebase**:
  ```bash
  ./vendor/bin/pint
  ```

## Code Architecture

This is a Laravel 12 application using Filament for the administrative panel.

- **Backend**: The core logic is in the `app/` directory, following the standard Laravel MVC structure.
- **Frontend**: The frontend is built with Tailwind CSS and Alpine.js, managed by Vite.
- **Admin Panel**: The admin panel is built with Filament and is located in the `app/Filament` directory.
- **Database**: Database migrations and seeders are in the `database/` directory.
- **Key Packages**:
  - `spatie/laravel-permission`: For role and permission management.
    - Roles are defined in `app/Enums/Role.php`.
  - `spatie/laravel-activitylog`: For logging user activity.
- **Testing**: The project uses Pest for testing. Tests are located in the `tests/` directory.