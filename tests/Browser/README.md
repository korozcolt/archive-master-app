# Tests de Navegador con Laravel Dusk

## Configuración Inicial

Laravel Dusk está configurado para testing E2E (End-to-End) del sistema Archive Master.

### Requisitos

- ChromeDriver (instalado automáticamente)
- Chrome/Chromium (debe estar instalado en el sistema)
- Laravel running on port 8000

### Archivos de Configuración

- `.env.dusk.local` - Variables de entorno para tests Dusk
- `tests/DuskTestCase.php` - Clase base para tests Dusk
- `tests/Browser/*.php` - Tests individuales

## Tests Implementados

### 1. LoginTest.php

Tests de autenticación:
- ✅ `test_user_can_login_successfully` - Login exitoso
- ✅ `test_user_cannot_login_with_invalid_credentials` - Credenciales inválidas
- ✅ `test_user_can_logout` - Logout del sistema

### 2. DocumentManagementTest.php

Tests de gestión de documentos:
- ✅ `test_user_can_view_documents_list` - Ver lista de documentos
- ✅ `test_user_can_search_documents` - Búsqueda de documentos
- ✅ `test_user_can_access_create_document_page` - Acceso a creación
- ✅ `test_user_can_filter_documents_by_status` - Filtrado por estado

### 3. WorkflowTest.php

Tests de workflows:
- ✅ `test_user_can_view_workflow_definitions` - Ver definiciones
- ✅ `test_workflow_history_is_displayed` - Historial de workflow
- ✅ `test_user_can_create_workflow_status` - Crear estados

### 4. AdvancedSearchTest.php

Tests de búsqueda avanzada:
- ✅ `test_user_can_access_advanced_search` - Acceso a búsqueda avanzada
- ✅ `test_simple_search_works_in_documents` - Búsqueda simple
- ✅ `test_user_can_filter_by_category` - Filtrado por categoría
- ✅ `test_no_results_message_appears_when_search_returns_nothing` - Sin resultados

## Ejecutar Tests

### Todos los tests Dusk

```bash
php artisan dusk
```

### Test específico

```bash
php artisan dusk tests/Browser/LoginTest.php
```

### Test específico con filtro

```bash
php artisan dusk --filter=test_user_can_login_successfully
```

### Con navegador visible (no headless)

```bash
DUSK_HEADLESS_DISABLED=true php artisan dusk
```

## Comandos Útiles

### Instalar/Actualizar ChromeDriver

```bash
php artisan dusk:chrome-driver --detect
```

### Ver ChromeDriver versión

```bash
vendor/laravel/dusk/bin/chromedriver-mac-arm --version
```

### Limpiar screenshots y consola

```bash
rm -rf tests/Browser/screenshots/*
rm -rf tests/Browser/console/*
```

## Debugging

### Screenshots automáticos en fallas

Dusk automáticamente toma screenshots cuando un test falla:
- Ubicación: `tests/Browser/screenshots/`

### Logs de consola

Los logs de la consola del navegador se guardan en:
- Ubicación: `tests/Browser/console/`

### Pausar ejecución para debugging

```php
$browser->pause(5000); // Pausa 5 segundos
```

### Ver el navegador durante tests

Usar el flag `DUSK_HEADLESS_DISABLED=true` para ver el navegador en acción.

## Notas Importantes

1. **Base de datos**: Los tests usan SQLite in-memory (configurado en `.env.dusk.local`)
2. **Migraciones**: Se ejecutan automáticamente con el trait `DatabaseMigrations`
3. **Factories**: Se usan factories para crear datos de prueba
4. **Selectores**: Los selectores están adaptados para Filament 3.x
5. **Timeout**: Los tests tienen un timeout de 10 segundos por defecto

## Próximos Tests a Implementar

- [ ] Tests para Multi-Panel (cuando se implemente)
- [ ] Tests para Wizards de creación
- [ ] Tests para Onboarding
- [ ] Tests para generación de reportes
- [ ] Tests para notificaciones
- [ ] Tests para roles y permisos específicos

## Troubleshooting

### ChromeDriver no inicia

```bash
# Reinstalar ChromeDriver
php artisan dusk:chrome-driver --detect

# Verificar permisos
chmod +x vendor/laravel/dusk/bin/chromedriver-mac-arm
```

### Tests fallan por timeout

Aumentar el timeout en el test:
```php
$browser->waitForLocation('/admin', 10); // 10 segundos
```

### Elementos no encontrados

Usar `pause()` para debugging:
```php
$browser->pause(2000)->dump(); // Dump page source
```
