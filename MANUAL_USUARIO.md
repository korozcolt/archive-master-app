# 📚 MANUAL DE USUARIO - ARCHIVEMASTER

## 🎯 INTRODUCCIÓN

**ArchiveMaster** es un sistema de gestión documental empresarial completo que permite digitalizar, organizar, procesar y rastrear documentos con flujos de trabajo automatizados y control granular de acceso.

### **Características Principales**
- ✅ Gestión completa de documentos digitales
- ✅ Flujos de trabajo (workflows) personalizables
- ✅ Búsqueda avanzada con indexación full-text
- ✅ Sistema de notificaciones automáticas
- ✅ Reportes y analytics en tiempo real
- ✅ Integración con hardware (escáneres, lectores)
- ✅ API REST completa para integraciones
- ✅ Sistema de webhooks para notificaciones externas

---

## 🚀 PRIMEROS PASOS

### **1. Acceso al Sistema**

1. **Abrir navegador web** y navegar a la URL del sistema
2. **Iniciar sesión** con las credenciales proporcionadas por el administrador
3. **Verificar permisos** - El sistema mostrará las opciones disponibles según tu rol

### **2. Navegación Principal**

El sistema utiliza **Filament Admin Panel** con una interfaz intuitiva:

- **Dashboard**: Página principal con métricas y widgets
- **Menú lateral**: Navegación por módulos
- **Barra superior**: Notificaciones y perfil de usuario
- **Breadcrumbs**: Navegación contextual

---

## 📄 GESTIÓN DE DOCUMENTOS

### **Crear Nuevo Documento**

1. **Ir a "Documentos"** en el menú lateral
2. **Hacer clic en "Nuevo Documento"**
3. **Completar información básica**:
   - Título (obligatorio)
   - Descripción
   - Categoría (obligatorio)
   - Estado inicial (obligatorio)
   - Prioridad (Baja/Media/Alta)
   - Usuario asignado
   - Fecha de vencimiento

4. **Configurar ubicación organizacional**:
   - Sucursal
   - Departamento

5. **Agregar metadatos adicionales**:
   - Ubicación física
   - Etiquetas
   - Confidencialidad

6. **Subir archivos** (opcional):
   - Arrastrar y soltar archivos
   - Formatos soportados: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG

7. **Guardar documento**

### **Buscar Documentos**

#### **Búsqueda Básica**
- Usar la **barra de búsqueda** en la parte superior
- Busca en título, descripción, número de documento y contenido

#### **Búsqueda Avanzada**
1. **Ir a "Búsqueda Avanzada"** en el menú
2. **Configurar filtros**:
   - Rango de fechas
   - Estado del documento
   - Categoría
   - Usuario creador/asignado
   - Prioridad
   - Etiquetas
   - Confidencialidad

3. **Ejecutar búsqueda**
4. **Exportar resultados** (PDF, Excel, CSV)

### **Gestionar Documento Existente**

#### **Ver Documento**
- **Hacer clic** en cualquier documento de la lista
- **Revisar información completa**:
  - Datos básicos
  - Historial de cambios
  - Versiones del documento
  - Comentarios y notas

#### **Editar Documento**
1. **Abrir documento**
2. **Hacer clic en "Editar"**
3. **Modificar campos necesarios**
4. **Guardar cambios**

#### **Cambiar Estado (Workflow)**
1. **Abrir documento**
2. **Hacer clic en "Cambiar Estado"**
3. **Seleccionar nuevo estado** (solo estados válidos aparecerán)
4. **Agregar comentario** (obligatorio en algunos casos)
5. **Confirmar transición**

---

## 🔄 FLUJOS DE TRABAJO (WORKFLOWS)

### **Estados Predefinidos**

- **Recibido**: Documento recién ingresado
- **Borrador**: En proceso de creación
- **En Proceso**: Siendo trabajado
- **En Revisión**: Esperando aprobación
- **Aprobado**: Documento aprobado
- **Rechazado**: Documento rechazado
- **Archivado**: Estado final

### **Transiciones Válidas**

Cada estado tiene transiciones específicas según el tipo de documento y permisos del usuario.

### **SLA (Service Level Agreement)**

- **Alertas automáticas** cuando un documento está próximo a vencer
- **Notificaciones por email** a usuarios responsables
- **Escalamiento automático** a supervisores si es necesario

---

## 🔍 BÚSQUEDA Y FILTROS

### **Tipos de Búsqueda**

1. **Búsqueda Simple**: Término en barra superior
2. **Búsqueda Avanzada**: Múltiples filtros combinados
3. **Búsqueda por Código**: Número de documento o código de barras
4. **Búsqueda por Etiquetas**: Filtrar por tags específicos

### **Filtros Disponibles**

- **Fechas**: Creación, modificación, vencimiento
- **Estados**: Cualquier estado del workflow
- **Usuarios**: Creador, asignado, último modificador
- **Categorías**: Jerárquicas y anidadas
- **Prioridad**: Baja, Media, Alta
- **Confidencialidad**: Público, Confidencial
- **Ubicación**: Sucursal, Departamento
- **Etiquetas**: Múltiples tags

---

## 📊 REPORTES Y ANALYTICS

### **Dashboard Principal**

- **Métricas generales**: Total documentos, pendientes, completados
- **Gráficos de tendencias**: Documentos por mes, estado, categoría
- **Documentos recientes**: Últimos 10 documentos
- **Documentos vencidos**: Alertas de SLA
- **Actividad del usuario**: Documentos asignados

### **Reportes Predefinidos**

1. **Reporte de Estados**:
   - Distribución de documentos por estado
   - Filtros por fecha y departamento
   - Exportación a PDF/Excel

2. **Reporte de Cumplimiento SLA**:
   - Documentos dentro/fuera de SLA
   - Tiempo promedio de procesamiento
   - Métricas por departamento

3. **Reporte de Actividad por Usuario**:
   - Documentos creados/procesados
   - Tiempo promedio de respuesta
   - Productividad por período

4. **Reporte de Documentos por Departamento**:
   - Volumen por área
   - Tipos de documento más comunes
   - Tendencias temporales

### **Reportes Personalizados**

1. **Ir a "Reportes Personalizados"**
2. **Seleccionar campos** a incluir
3. **Configurar filtros** específicos
4. **Programar envío automático** (opcional)
5. **Generar y descargar**

---

## 🔔 NOTIFICACIONES

### **Tipos de Notificaciones**

1. **In-App**: Notificaciones dentro del sistema
2. **Email**: Correos electrónicos automáticos
3. **Alertas SLA**: Vencimientos y escalamientos

### **Configurar Preferencias**

1. **Ir a "Perfil de Usuario"**
2. **Sección "Notificaciones"**
3. **Activar/desactivar** tipos de notificación:
   - Documentos asignados
   - Cambios de estado
   - Vencimientos SLA
   - Reportes programados

4. **Configurar frecuencia**:
   - Inmediata
   - Resumen diario
   - Resumen semanal

---

## 🖨️ INTEGRACIÓN CON HARDWARE

### **Escáneres de Códigos de Barras**

1. **Configurar escáner** para enviar datos a la API
2. **Escanear código de barras** del documento
3. **Sistema automáticamente**:
   - Identifica el documento
   - Registra el escaneo
   - Muestra información completa
   - Permite acciones (ver, retirar, devolver)

### **Lectores de Códigos QR**

1. **Usar app móvil** o lector QR
2. **Escanear código QR** del documento
3. **Acceder a información** completa del documento
4. **Realizar acciones** permitidas según permisos

### **Carga Masiva de Documentos**

1. **Ir a "Carga Masiva"**
2. **Seleccionar múltiples archivos**
3. **Configurar metadatos** comunes
4. **Procesar en lote**
5. **Revisar resultados** y errores

---

## 👥 GESTIÓN DE USUARIOS Y PERMISOS

### **Roles del Sistema**

1. **Super Admin**: Acceso completo al sistema
2. **Company Admin**: Administrador de empresa
3. **Branch Manager**: Gerente de sucursal
4. **Department Head**: Jefe de departamento
5. **Document Manager**: Gestor de documentos
6. **User**: Usuario estándar
7. **Viewer**: Solo lectura

### **Permisos por Módulo**

- **Documentos**: Crear, leer, actualizar, eliminar
- **Usuarios**: Gestionar usuarios de la empresa
- **Reportes**: Generar y programar reportes
- **Configuración**: Modificar configuraciones del sistema

---

## 🔧 CONFIGURACIÓN PERSONAL

### **Perfil de Usuario**

1. **Hacer clic en avatar** (esquina superior derecha)
2. **Seleccionar "Perfil"**
3. **Actualizar información**:
   - Nombre y apellidos
   - Email
   - Teléfono
   - Posición
   - Foto de perfil

### **Preferencias del Sistema**

- **Idioma**: Español/Inglés
- **Zona horaria**: Configurar según ubicación
- **Formato de fecha**: DD/MM/YYYY o MM/DD/YYYY
- **Elementos por página**: 10, 15, 25, 50
- **Tema**: Claro/Oscuro (si disponible)

---

## 🆘 SOLUCIÓN DE PROBLEMAS

### **Problemas Comunes**

#### **No puedo ver ciertos documentos**
- **Verificar permisos** con el administrador
- **Revisar filtros** aplicados en la búsqueda
- **Confirmar que pertenecen** a tu empresa/sucursal

#### **Error al subir archivos**
- **Verificar tamaño** del archivo (máximo 10MB por defecto)
- **Confirmar formato** soportado
- **Revisar conexión** a internet

#### **No recibo notificaciones**
- **Verificar configuración** de notificaciones en perfil
- **Revisar carpeta de spam** en email
- **Contactar administrador** para verificar configuración SMTP

#### **Búsqueda no encuentra documentos**
- **Verificar ortografía** del término de búsqueda
- **Usar términos más generales**
- **Revisar filtros** aplicados
- **Esperar indexación** (documentos recién creados)

### **Contacto de Soporte**

- **Email**: soporte@archivemaster.com
- **Teléfono**: +1 (555) 123-4567
- **Horario**: Lunes a Viernes, 8:00 AM - 6:00 PM
- **Chat en vivo**: Disponible en el sistema (esquina inferior derecha)

---

## 📱 ACCESO MÓVIL

### **Navegador Móvil**

- **Diseño responsive** se adapta automáticamente
- **Funcionalidades principales** disponibles
- **Optimizado para tablets** y smartphones

### **App Móvil (Próximamente)**

- **Escaneo de códigos QR/Barras**
- **Captura de documentos** con cámara
- **Notificaciones push**
- **Acceso offline** limitado

---

## 🔐 SEGURIDAD Y MEJORES PRÁCTICAS

### **Contraseñas Seguras**

- **Mínimo 8 caracteres**
- **Combinar mayúsculas, minúsculas, números y símbolos**
- **No reutilizar** contraseñas de otros sistemas
- **Cambiar regularmente** (cada 90 días recomendado)

### **Manejo de Documentos Confidenciales**

- **Marcar como confidencial** documentos sensibles
- **No compartir** credenciales de acceso
- **Cerrar sesión** al terminar de usar el sistema
- **Reportar** accesos no autorizados inmediatamente

### **Respaldo de Información**

- **Sistema automático** de respaldo diario
- **Versionado** de documentos automático
- **Recuperación** disponible hasta 30 días
- **Contactar administrador** para restauraciones

---

## 📞 SOPORTE Y CAPACITACIÓN

### **Recursos de Ayuda**

- **Manual de usuario** (este documento)
- **Videos tutoriales** disponibles en el sistema
- **Base de conocimientos** con preguntas frecuentes
- **Webinars** de capacitación mensuales

### **Capacitación Personalizada**

- **Sesiones grupales** para nuevos usuarios
- **Capacitación específica** por rol
- **Entrenamiento avanzado** para administradores
- **Soporte on-site** disponible bajo solicitud

---

## 🔄 ACTUALIZACIONES Y NUEVAS FUNCIONALIDADES

### **Ciclo de Actualizaciones**

- **Actualizaciones menores**: Cada 2 semanas
- **Actualizaciones mayores**: Cada 3 meses
- **Parches de seguridad**: Según necesidad
- **Notificación previa** de cambios importantes

### **Próximas Funcionalidades**

- **Firma digital** de documentos
- **OCR automático** para extracción de texto
- **Integración con Office 365**
- **App móvil nativa**
- **Inteligencia artificial** para clasificación automática

---

## 📋 GLOSARIO DE TÉRMINOS

- **API**: Interfaz de programación de aplicaciones
- **Barcode**: Código de barras
- **Dashboard**: Panel de control principal
- **OCR**: Reconocimiento óptico de caracteres
- **QR Code**: Código de respuesta rápida
- **SLA**: Acuerdo de nivel de servicio
- **Webhook**: Notificación HTTP automática
- **Workflow**: Flujo de trabajo

---

*Última actualización: Enero 2025*
*Versión del manual: 1.0*
*Para soporte técnico: soporte@archivemaster.com*
