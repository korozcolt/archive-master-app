# ArchiveMaster - Sistema de Gestión Documental

![Estado](https://img.shields.io/badge/estado-en%20desarrollo-yellow)
![Versión](https://img.shields.io/badge/versión-1.0.0-blue)
![Laravel](https://img.shields.io/badge/Laravel-v12.x-FF2D20?logo=laravel)
![PHP](https://img.shields.io/badge/PHP-v8.2+-777BB4?logo=php)
![Filament](https://img.shields.io/badge/Filament-v3.x-41a6b3?logo=filament)

## 📋 Descripción

ArchiveMaster es un sistema de gestión documental avanzado, diseñado para optimizar el almacenamiento, organización y flujo de trabajo de documentos en entornos empresariales. Ofrece una solución robusta y escalable para la administración eficiente de documentos dentro de organizaciones.

## 🌟 Características Principales

- 🏢 **Estructura Organizacional Jerárquica**
  - Gestión multi-empresa
  - Administración de sedes/sucursales
  - Departamentos y oficinas
  - Configuración específica por sede

- 📄 **Gestión Documental Completa**
  - Registro y digitalización de documentos
  - Versionamiento automático
  - Generación de códigos de documentos, códigos de barras y QR
  - Seguimiento de ubicación física y digital
  - Sistema de préstamo y devolución

- 🔄 **Flujos de Trabajo Personalizables**
  - Estados parametrizables por tipo de documento
  - Asignación manual o automática
  - Tiempos de respuesta configurables (SLA)
  - Seguimiento de responsables

- 🔍 **Búsqueda y Recuperación Avanzada**
  - Búsqueda por contenido, metadatos, categoría y estado
  - Filtros combinados
  - Guardado de búsquedas frecuentes

- 🔐 **Control de Acceso y Seguridad**
  - Sistema de roles y permisos jerárquico
  - Permisos granulares por departamento, tipo de documento y acciones específicas
  - Registro detallado de actividad y auditoría

- 📊 **Informes y Métricas**
  - Reportes predefinidos y personalizables
  - Dashboards interactivos
  - Exportación en múltiples formatos

## 🛠️ Tecnologías

- **Backend**
  - Laravel 12
  - PHP 8.2+
  - MySQL 8
  - Redis (caché)

- **Frontend**
  - Filament 3 (Panel Administrativo)
  - Tailwind CSS
  - Alpine.js

- **Paquetes Clave**
  - spatie/laravel-permission (Gestión de roles y permisos)
  - spatie/laravel-activitylog (Registro de actividad)
  - barryvdh/laravel-dompdf (Generación de PDF)
  - simplesoftwareio/simple-qrcode (Generación de códigos QR)
  - milon/barcode (Generación de códigos de barras)

## 📝 Estado del Proyecto

### ✅ Componentes Implementados

#### Base de Datos y Modelos

- Estructura completa de la base de datos
- Modelos con relaciones y métodos auxiliares
- Migraciones para todas las tablas

#### Enumeraciones (Enums)

- DocumentStatus (estados de documentos)
- DocumentType (tipos de documentos)
- Priority (prioridades)
- Role (roles del sistema)
- StatusGlobal (estados globales)

### 🚧 En Desarrollo

#### Panel Administrativo (Filament)

- Recursos para las entidades principales
- Widgets para el dashboard
- Formularios para gestión documental

#### Sistema de Flujos de Trabajo

- Implementación de transiciones
- Motor de reglas de negocio
- Notificaciones de cambios de estado

### ⏳ Próximos Pasos

#### Fase 1: Configuración Básica

- [x] Estructura de base de datos
- [x] Modelos y relaciones
- [x] Enumeraciones para estados y tipos
- [ ] Seeders para datos iniciales
- [ ] Implementación de roles y permisos

#### Fase 2: Panel Administrativo

- [ ] Recursos de Filament para entidades principales
- [ ] Dashboard con widgets informativos
- [ ] Formularios de captura de documentos
- [ ] Visualizador de documentos integrado

#### Fase 3: Funcionalidades Avanzadas

- [ ] Motor de búsqueda avanzada
- [ ] Sistema de notificaciones
- [ ] Reportes y estadísticas
- [ ] Integración con herramientas externas

#### Fase 4: Optimización y Seguridad

- [ ] Optimización de rendimiento
- [ ] Pruebas unitarias y de integración
- [ ] Auditoría de seguridad
- [ ] Documentación técnica y de usuario

## 📦 Instalación

1. Clonar el repositorio

```bash
git clone https://github.com/tu-usuario/archive-master.git
cd archive-master
```

2. Instalar dependencias

```bash
composer install
npm install
```

3. Configurar el entorno

```bash
cp .env.example .env
php artisan key:generate
```

4. Configurar la base de datos en el archivo .env

5. Ejecutar migraciones y seeders

```bash
php artisan migrate --seed
```

6. Iniciar el servidor de desarrollo

```bash
php artisan serve
npm run dev
```

## 👥 Roles del Sistema

El sistema cuenta con los siguientes roles predefinidos:

- **Super Administrador**: Acceso completo a todo el sistema.
- **Administrador**: Gestión completa dentro de una empresa.
- **Administrador de Sucursal**: Gestión dentro de una sucursal específica.
- **Encargado de Oficina**: Gestión de documentos de una oficina o departamento.
- **Encargado de Archivo**: Gestión del archivo físico y digital.
- **Recepcionista**: Recepción y registro de documentos entrantes.
- **Usuario Regular**: Acceso limitado a documentos asignados.

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor, sigue estos pasos:

1. Haz fork del proyecto
2. Crea una rama para tu funcionalidad (`git checkout -b feature/amazing-feature`)
3. Realiza tus cambios y haz commit (`git commit -m 'Add some amazing feature'`)
4. Haz push a la rama (`git push origin feature/amazing-feature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está licenciado bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Contacto

Para preguntas o sugerencias, por favor contacta al equipo de desarrollo.

---

© 2025 ArchiveMaster. Todos los derechos reservados.
