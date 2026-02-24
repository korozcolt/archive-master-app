# Manual de Usuario Paso a Paso por Rol (Versión Cliente)

## 1. Objetivo de este manual

Este manual está diseñado para entregar al cliente una guía de uso **paso a paso** de la aplicación completa, explicando:

- qué debe hacer en los primeros pasos
- qué pantallas/ventanas verá
- qué opciones debe escoger
- qué resultado obtiene en cada acción

Incluye instrucciones por rol:

- `super_admin`
- `admin`
- `branch_admin`
- `office_manager`
- `archive_manager`
- `receptionist`
- `regular_user`

Nota importante:

- El sistema muestra menús dinámicos según permisos.
- Algunas etiquetas pueden variar ligeramente según idioma/configuración (español/inglés en ciertos módulos de admin).
- Este manual describe el comportamiento funcional actual implementado.

---

## 2. Vista general de la aplicación

La aplicación tiene dos áreas de trabajo:

### A) Panel de Administración (`/admin`)

Para configuración, gobierno y gestión avanzada:

- empresas
- sucursales
- departamentos
- usuarios
- catálogos (categorías, estados, etiquetas)
- workflows
- reportes
- ubicaciones físicas
- plantillas
- configuraciones IA

### B) Portal Operativo (`/portal`)

Para operación diaria simplificada:

- dashboard personal
- documentos propios/asignados
- reportes personales
- aprobaciones (si aplica)
- recibidos

---

## 3. Primeros pasos antes de usar el sistema (setup inicial)

Estas tareas deben hacerse **antes** de que los usuarios operativos trabajen:

1. Crear la empresa.
2. Crear sucursales.
3. Crear departamentos.
4. Crear usuarios y asignar roles.
5. Crear categorías.
6. Crear estados.
7. Crear etiquetas (opcional).
8. Configurar workflows/aprobaciones.
9. (Opcional) Configurar ubicaciones físicas.
10. (Opcional) Configurar IA por empresa.

Responsables típicos:

- `super_admin`: puede hacer todo.
- `admin`: lo hace para su empresa.
- `branch_admin`: puede operar dentro de su sucursal (según permisos).

---

## 4. Guía de navegación (qué verá el usuario)

## 4.1 Al iniciar sesión como administrador

Pantalla inicial esperada:

- URL: `/admin/login`
- Campos típicos:
- correo electrónico
- contraseña
- recordar sesión (si está habilitado)
- botón de ingreso

Después de iniciar sesión:

- verá el panel administrativo con menú lateral
- el contenido del menú depende del rol

## 4.2 Al iniciar sesión como usuario de portal (regular_user con recibido)

Pantalla inicial:

- URL: `/login`
- Pantalla: **Acceso al Portal**
- Campos:
- **Número de recibido**
- **Correo**
- Botón: **Solicitar OTP**

Siguiente pantalla:

- Pantalla: **Verificar OTP**
- Campos:
- **Número de recibido**
- **Correo**
- **OTP** (6 dígitos)
- Botón: **Ingresar al portal**

Resultado:

- acceso a `/portal`

## 4.3 Qué ventanas verá y cómo leerlas (patrón general)

Para evitar errores en capacitación, estas son las ventanas más comunes y qué debe hacer en cada una:

### A) Ventana de listado (tabla)

Qué verá normalmente:

- título de módulo (ej. **Usuarios**, **Documentos**, **Categorías**)
- buscador
- filtros
- botón **Crear** / **Nuevo**
- tabla con columnas
- acciones por fila (**Ver**, **Editar**, **Eliminar**, **Descargar**, **Generar**, etc.)

Qué opción debe escoger:

- si va a registrar algo nuevo: **Crear** / **Nuevo**
- si va a revisar un registro: **Ver**
- si va a cambiar datos: **Editar**
- si va a obtener evidencia: **Descargar** / **Exportar**

### B) Ventana de formulario (crear/editar)

Qué verá normalmente:

- campos obligatorios (suelen marcarse visualmente)
- campos opcionales
- selectores (empresa, sucursal, categoría, estado, rol, prioridad)
- toggles/checkboxes (activo, confidencial, habilitado)
- botones **Guardar**, **Cancelar**

Qué opción debe escoger:

- complete primero los campos organizacionales (empresa/sucursal/departamento) cuando aparezcan
- luego clasifique (categoría/estado/prioridad)
- por último guarde con **Guardar**

### C) Ventana de detalle (consulta)

Qué verá normalmente:

- resumen del registro
- historial / trazabilidad
- adjuntos o versiones (si aplica)
- acciones secundarias (editar, descargar, aprobar, generar recibido)

Qué opción debe escoger:

- use esta vista para validar información antes de ejecutar una acción irreversible (aprobar, rechazar, eliminar)

### D) Modal de confirmación (ventana emergente)

Qué verá normalmente:

- mensaje de confirmación
- advertencia de impacto
- campo de comentario (a veces obligatorio, por ejemplo rechazo)
- botones **Confirmar** y **Cancelar**

Qué opción debe escoger:

- **Cancelar** si aún no validó el detalle
- **Confirmar** solo cuando el dato esté verificado

## 4.4 Mapa de acceso por rol (ruta de entrada y pantalla esperada)

| Rol | URL de entrada recomendada | Pantalla esperada | Qué pasa si entra a otra ruta |
|---|---|---|---|
| `super_admin` | `/admin/login` | Login admin -> panel admin | Puede usar `/admin`; no usa portal operativo para configuración |
| `admin` | `/admin/login` | Login admin -> panel admin | Igual que super admin, con alcance por empresa |
| `branch_admin` | `/admin/login` | Login admin -> panel admin recortado | Se mantiene en admin, con alcance de sucursal |
| `office_manager` | `/portal` o `/dashboard` | Redirección a `/portal` | Si intenta `/admin`, se bloquea |
| `archive_manager` | `/portal` o `/dashboard` | Redirección a `/portal` | Si intenta `/admin`, se bloquea (salvo permisos específicos puntuales) |
| `receptionist` | `/portal` o `/dashboard` | Redirección a `/portal` | Si intenta `/admin`, se bloquea |
| `regular_user` | `/login` | **Acceso al Portal** -> OTP -> `/portal` | No entra por admin; usa recibido + OTP |

---

## 5. Manual paso a paso por rol

## 5.1 Rol: `super_admin`

### Qué puede hacer (resumen)

- configurar empresas
- gestionar estructura organizacional completa
- gestionar usuarios y roles
- supervisar documentos y catálogos
- revisar reportes globales
- configurar y supervisar IA por empresa

### Paso a paso: primer ingreso

1. Ingrese a `/admin/login`.
2. Escriba correo y contraseña.
3. Presione **Entrar**.
4. Verá el panel administrativo (dashboard).

### Qué menús verá (normalmente)

Dependiendo de configuración y permisos, verá grupos como:

- `Administración`
- `Gestión Documental`
- `Documentos`
- `Reportes` / `Analytics`

### Paso a paso: crear una empresa (primer setup)

1. En menú lateral, seleccione **Empresas**.
2. Se abrirá la **lista de empresas**.
3. Haga clic en **Crear** (o botón equivalente en la parte superior).
4. En la pantalla de formulario, complete:
- `Nombre`
- `Razón Social`
- `NIT/Identificación fiscal`
- `Dirección`
- `Teléfono`
- `Correo electrónico`
- `Sitio web`
- colores/branding si están visibles
5. Revise que el estado esté activo (si aplica).
6. Presione **Guardar**.
7. Resultado esperado:
- aparece notificación de éxito
- la empresa queda visible en el listado

### Paso a paso: configurar IA para una empresa (si usarán IA)

1. Abra **Empresas**.
2. Entre a **Ver** o **Editar** de la empresa.
3. Localice la sección **Configuración IA**.
4. Elija:
- Proveedor (`none`, `openai`, `gemini`)
- Habilitado (sí/no)
- Límites (documentos por día, páginas, presupuesto)
- flags (`store_outputs`, `redact_pii`)
5. Si va a usar proveedor real, agregue la API key (campo oculto/masked).
6. Presione **Guardar**.
7. Opcional:
- **Test key IA**
- **Run sample IA**
8. Resultado esperado:
- configuración guardada para esa empresa

### Paso a paso: revisar observabilidad IA

1. Desde la empresa, abra **Observabilidad IA** (si está disponible en acciones/página).
2. Verá métricas como:
- runs de hoy
- éxitos del mes
- costo acumulado
- fallos por proveedor
- errores recientes
3. Si necesita evidencia:
- use **Exportar CSV**

### Paso a paso: crear usuarios para la empresa

1. Abra **Usuarios**.
2. Haga clic en **Crear** o **Create User Wizard**.
3. Complete:
- datos personales
- correo
- puesto/cargo
- teléfono
- asignación organizacional (empresa/sucursal/departamento)
- rol(es)
- estado activo
4. Defina contraseña si el formulario la solicita.
5. Guarde.
6. Verifique en listado:
- nombre
- rol
- estado

### Paso a paso: revisar reportes

1. Abra **Reportes**.
2. Use acción **Generar**.
3. Elija:
- tipo de reporte (ej. documentos por estado, SLA, actividad)
- formato de salida (PDF o Excel)
- rango de fechas
- departamento (opcional)
4. Presione **Generar y Descargar**.
5. Resultado:
- descarga del archivo

### Paso a paso: crear reporte programado

1. Abra **Reportes Programados**.
2. Haga clic en **Crear**.
3. Complete:
- nombre
- usuario responsable
- descripción
- tipo de reporte
- formato (`pdf`, `xlsx`, `csv`)
- columnas (si aplica)
- frecuencia (`diario`, `semanal`, `mensual`, `trimestral`)
- hora
- día (semana/mes según frecuencia)
- correos destinatarios
- activo = sí
4. Guarde.
5. Resultado:
- el sistema lo ejecutará automáticamente según programación

---

## 5.2 Rol: `admin`

### Qué puede hacer (resumen)

- administración de su empresa
- usuarios, sucursales, departamentos
- documentos y catálogos
- reportes
- workflows
- configuración funcional del sistema

### Paso a paso: primer ingreso

1. Ingrese a `/admin/login`.
2. Autentique con sus credenciales.
3. El sistema le mostrará el panel admin de su empresa.

### Qué debe hacer primero (recomendado)

1. Verificar empresa y datos de contacto.
2. Crear sucursales.
3. Crear departamentos.
4. Crear usuarios por rol.
5. Revisar categorías y estados.
6. Definir workflows.
7. Crear plantillas (si desean estandarizar operación).

### Paso a paso: crear sucursal

1. Menú: **Sucursales**.
2. Botón: **Crear**.
3. Complete:
- Empresa (si aplica)
- Nombre
- Código
- Dirección
- Ciudad
- Estado/Provincia
- País
- teléfono/correo
4. Guarde.

### Paso a paso: crear departamento

1. Menú: **Departamentos**.
2. Botón: **Crear**.
3. Complete:
- Empresa
- Sucursal
- Nombre
- Código
- Departamento padre (opcional)
- descripción
4. Guarde.

### Paso a paso: crear categorías

1. Menú: **Categorías**.
2. Botón: **Crear**.
3. Complete:
- Empresa
- Nombre
- `Slug` (si no se autogenera, escríbalo)
- Categoría padre (opcional)
- color
- icono
- descripción
4. Guarde.

### Paso a paso: crear estados

1. Menú: **Estados**.
2. Botón: **Crear**.
3. Complete:
- Empresa
- Nombre
- `Slug`
- color/icono
- orden
- marque si es inicial o final (si aplica)
4. Guarde.

### Paso a paso: crear definición de workflow

1. Menú: **Definiciones de Flujo**.
2. Botón: **Crear**.
3. Seleccione:
- Empresa
- Estado origen
- Estado destino
- roles permitidos
- si requiere aprobación
- si requiere comentario
- SLA (si aplica)
4. Guarde.

### Paso a paso: crear plantilla de documento (opcional, recomendado)

1. Menú: **Plantillas de Documentos**.
2. Botón: **Crear**.
3. Complete:
- nombre
- descripción
- icono/color
- categoría/estado/workflow por defecto
- prioridad por defecto
- etiquetas por defecto/sugeridas
- validaciones (campos requeridos, tipos de archivo, tamaño)
- campos personalizados (si aplica)
4. Guarde.

### Paso a paso: revisar documentos

1. Menú: **Documentos**.
2. Use filtros y búsqueda.
3. Abra un documento para:
- ver detalle
- versiones
- historial de workflow
- etiquetas/categorías
- acciones de estado
4. Si el documento tiene IA:
- revise resumen IA
- regenere si hace falta
- aplique sugerencias

---

## 5.3 Rol: `branch_admin`

### Qué puede hacer (resumen)

- administrar documentos de su sucursal
- revisar usuarios/estructura bajo su alcance
- consultar reportes de sucursal
- operar como supervisor local

### Paso a paso: primer ingreso

1. Ingrese a `/admin/login`.
2. Autentique.
3. Verá el panel admin con alcance reducido.

### Qué revisar primero

1. Que su sucursal esté correcta.
2. Que los usuarios de su sucursal tengan roles correctos.
3. Que departamentos y estados estén disponibles.

### Paso a paso: gestión de documentos de sucursal

1. Abra **Documentos**.
2. Confirme que sólo vea documentos de su sucursal (aislamiento).
3. Use:
- búsqueda por número/título
- filtros por estado/categoría
- ordenamiento
4. Abra documento y ejecute acciones permitidas:
- consultar
- editar (si aplica)
- descargar
- revisar historial

### Paso a paso: reportes de sucursal

1. Abra **Reportes**.
2. Seleccione tipo de reporte.
3. Aplique filtros por fechas y/o departamento.
4. Exporte en PDF o Excel.

---

## 5.4 Rol: `office_manager`

### Qué puede hacer (resumen)

- trabajar documentos de su oficina/departamento
- actualizar estados y contenido (según permisos)
- participar en aprobaciones
- consultar reportes personales
- usar funciones IA sobre documentos (si habilitadas)

### Paso a paso: primer ingreso

1. Ingrese con su usuario.
2. Si entra a `/dashboard`, el sistema lo redirige a `/portal`.
3. Pantalla inicial del portal:
- título **Portal**
- resumen personal (Total / Enviados / Recibidos / Pendientes)
- botón **Nuevo Documento**
- tabla **Documentos recientes**

### Paso a paso: crear un documento (trabajo diario)

1. En `/portal`, clic en **Nuevo Documento**.
2. Se abre formulario **Crear Documento**.
3. Complete:
- `Título`
- `Número de documento` (opcional si el sistema lo genera)
- `Descripción`
- `Categoría`
- `Estado`
- `Archivo` (opcional)
- `Confidencial` (marcar si aplica)
- `Prioridad` (`low`, `medium`, `high`)
4. Si necesita generar recibido para un tercero, complete además:
- `Nombre del receptor`
- `Correo del receptor`
- `Teléfono del receptor` (opcional)
5. Presione **Guardar/Crear**.
6. Resultado:
- documento creado
- si hay receptor, se genera recibido automáticamente

### Paso a paso: consultar y filtrar documentos

1. Menú superior/lateral del portal: **Documentos**.
2. Use filtros:
- texto (título, descripción, número)
- categoría
- estado
- prioridad
- confidencialidad
- rango de fechas
3. Use ordenamiento (fecha, título, prioridad, actualización).
4. Abra el documento desde el listado.

### Paso a paso: editar documento

1. En detalle del documento, seleccione **Editar**.
2. Cambie campos permitidos.
3. Si reemplaza archivo, suba nuevo archivo.
4. Guarde.
5. Resultado:
- documento actualizado
- trazabilidad registrada

### Paso a paso: aprobaciones

1. Vaya a **Aprobaciones** (`/approvals`).
2. Revise la lista **Aprobaciones Pendientes**.
3. Abra el documento pendiente.
4. Verá panel de aprobación con:
- botón **Aprobar Documento**
- botón **Rechazar Documento**
5. Si aprueba:
- clic **Aprobar Documento**
- comentario opcional
- clic **Confirmar Aprobación**
6. Si rechaza:
- clic **Rechazar Documento**
- escriba motivo (obligatorio)
- clic **Confirmar Rechazo**
7. Resultado:
- sistema actualiza aprobación y flujo

### Paso a paso: panel IA en documento (si habilitado)

1. Abra un documento que tenga procesamiento IA.
2. Revise:
- **Resumen ejecutivo**
- **Sugerencias**
- entidades detectadas (si su rol puede verlas)
3. Acciones disponibles:
- **Regenerar IA**
- **Aplicar sugerencias**
- **Marcar como incorrecto**

### Paso a paso: reportes personales

1. Vaya a `/portal/reports`.
2. Pantalla **Reportes personales**:
- filtros **Desde** / **Hasta**
- contadores **Enviados** / **Recibidos**
- tabla de enviados
- tabla de recibidos
3. Seleccione rango de fechas si necesita filtrar.

---

## 5.5 Rol: `archive_manager`

### Qué puede hacer (resumen)

- consulta documental de archivo
- gestión de ubicación física
- uso de etiquetas/stickers
- plantillas y catálogos relacionados al archivo (según permisos)
- reportes personales y algunos reportes admin (si acceso habilitado)

### Paso a paso: primer ingreso

1. Inicie sesión.
2. Será dirigido a `/portal`.
3. Revise resumen de documentos del portal.

### Paso a paso: revisar documentos para archivo

1. Abra **Documentos** en portal.
2. Filtre por estado/categoría para identificar documentos por archivar.
3. Abra el detalle del documento.
4. Revise:
- metadatos
- historial
- versiones
- tags

### Paso a paso: trabajar con ubicación física (si tiene acceso admin)

1. En admin (si el rol tiene acceso a ese recurso), abra **Ubicaciones Físicas**.
2. Cree o edite ubicación:
- empresa
- plantilla de ubicación
- ruta/código/capacidad
3. Guarde.
4. Resultado:
- ubicación disponible para asignación documental

### Paso a paso: plantillas de ubicación física

1. Abra **Plantillas de Ubicación**.
2. Cree plantilla con niveles jerárquicos (ejemplo):
- edificio
- piso
- sala
- armario
- estante
- caja
3. Marque **Activa**.
4. Guarde.

### Paso a paso: plantillas de documentos (si visible)

1. Abra **Plantillas de Documentos**.
2. Cree o edite plantilla para estandarizar radicación/archivo.
3. Defina defaults y validaciones.
4. Guarde.

### Paso a paso: generar stickers / etiquetas

1. Acceda a opciones de stickers (según flujo implementado en UI).
2. Elija:
- tipo: documento / ubicación
- template: estándar / compacto / detallado / etiqueta
3. Configure opciones (tamaño QR/barcode, batch si aplica).
4. Genere vista previa.
5. Descargue PDF.

---

## 5.6 Rol: `receptionist`

### Qué puede hacer (resumen)

- registrar documentos entrantes
- generar recibidos
- iniciar onboarding de usuarios `regular_user` por recibido
- consulta de documentos creados/asignados
- reportes personales

### Paso a paso: primer ingreso

1. Inicie sesión.
2. El sistema lo lleva a `/portal`.
3. Revise:
- botón **Nuevo Documento**
- métricas personales
- documentos recientes

### Paso a paso: registrar un documento entrante (flujo principal)

1. Clic en **Nuevo Documento**.
2. Complete datos mínimos:
- `Título`
- `Descripción`
- `Categoría`
- `Estado`
- `Prioridad`
3. Si tiene archivo:
- cargue PDF/Word/Excel/imagen
4. Para generar recibido (recomendado en recepción), complete:
- `Nombre del receptor`
- `Correo del receptor`
- `Teléfono` (opcional)
5. Clic en **Crear/Guardar**.
6. Resultado esperado:
- documento creado
- mensaje de éxito con número de recibido
- sistema crea/vincula usuario `regular_user` si no existía

### Paso a paso: consultar recibido generado

1. Abra el detalle del documento recién creado.
2. Localice la sección de **Recibidos** (si visible).
3. Abra el recibido.
4. Opciones:
- ver detalle
- descargar PDF

### Paso a paso: explicar acceso al usuario final

Indique al receptor:

1. Entrar a `/login`
2. Escribir:
- número de recibido
- correo
3. Solicitar OTP
4. Validar OTP
5. Entrar al portal

### Paso a paso: revisar reportes personales

1. Abra `/portal/reports`.
2. Use filtros de fecha.
3. Revise documentos enviados/recibidos.

---

## 5.7 Rol: `regular_user`

### Qué puede hacer (resumen)

- ingresar al portal con recibido + OTP
- consultar documentos propios/asignados
- descargar si tiene permiso
- ver sus recibidos
- consultar estado/seguimiento
- ver reportes personales

### Paso a paso: primer acceso con recibido

#### Pantalla 1: Acceso al Portal

1. Ingrese a `/login`.
2. Verá **Acceso al Portal**.
3. Complete:
- **Número de recibido**
- **Correo**
4. Presione **Solicitar OTP**.

#### Pantalla 2: Verificar OTP

1. Verá **Verificar OTP**.
2. Complete:
- **Número de recibido** (puede venir precargado)
- **Correo** (puede venir precargado)
- **OTP** (6 dígitos)
3. Presione **Ingresar al portal**.

#### Pantalla 3: Portal

1. Verá dashboard con:
- `Total`
- `Enviados`
- `Recibidos`
- `Pendientes`
- `Documentos recientes`

### Paso a paso: ver sus documentos

1. Entre a **Documentos**.
2. Use búsqueda y filtros si necesita.
3. Abra el documento.
4. Revise:
- título
- número
- estado
- categoría
- historial
- versiones (si existen)
5. Si aparece opción de descarga y tiene permiso:
- clic en **Descargar**

### Paso a paso: ver su recibido

1. Abra el enlace de recibido en el portal (si aparece en detalle o menú).
2. Verifique:
- número de recibido
- documento asociado
- emisor
- fecha
3. Si necesita comprobante:
- clic en **Descargar PDF**

### Paso a paso: consultar reportes personales

1. Entre a `/portal/reports`.
2. Seleccione fechas **Desde/Hasta**.
3. Revise listas de:
- enviados
- recibidos

---

## 6. Flujos transversales paso a paso (de punta a punta)

## 6.1 Flujo completo: recepción -> recibido -> acceso usuario final

1. `receptionist` crea documento.
2. `receptionist` diligencia datos del receptor.
3. Sistema genera recibido.
4. Sistema crea/vincula `regular_user`.
5. `regular_user` solicita OTP con número de recibido y correo.
6. `regular_user` valida OTP.
7. `regular_user` entra al portal y consulta su documento/recibido.

## 6.2 Flujo completo: documento con aprobación

1. Se crea o actualiza documento.
2. Workflow exige aprobación.
3. Se generan aprobaciones pendientes.
4. `office_manager` (u otro aprobador) entra a `/approvals`.
5. Aprueba o rechaza.
6. Sistema registra historial y actualiza estado.

## 6.3 Flujo completo: tracking público

1. Documento tiene tracking habilitado y código público.
2. Usuario externo entra a `/tracking`.
3. Ingresa el código.
4. Sistema valida existencia y vigencia.
5. Muestra estado y línea de tiempo pública.

---

## 7. Qué ventanas/pantallas verá en los primeros días (por rol)

## 7.1 Admin / Super Admin / Branch Admin

Pantallas más comunes:

- login de admin
- dashboard admin
- listados (tablas) de recursos
- formularios de crear/editar
- vistas de detalle
- modales de acción (eliminar, ejecutar, generar)

Elementos típicos en listados:

- buscador
- filtros
- columnas ordenables
- botones de acción (ver/editar/eliminar)
- acciones masivas (según recurso)

## 7.2 Roles operativos (office/archive/receptionist/regular_user)

Pantallas más comunes:

- portal dashboard
- listado de documentos
- crear/editar documento
- detalle de documento
- reportes personales
- aprobaciones (si aplica)
- recibidos

Elementos típicos:

- tablas con filtros
- botones `Nuevo Documento`, `Ver`, `Editar`, `Descargar`
- mensajes de éxito/error

---

## 8. Opciones que deben escoger en cada caso (guía rápida)

## 8.1 Si el documento entra por recepción

- Rol: `receptionist`
- Opción recomendada:
- crear documento
- diligenciar receptor
- generar recibido

## 8.2 Si el documento requiere aprobación

- Rol: `office_manager` (u otro aprobador)
- Opción recomendada:
- ir a `Aprobaciones`
- revisar detalle
- aprobar/rechazar con comentario

## 8.3 Si se necesita seguimiento externo

- Rol: cualquiera con permiso para gestionar documento
- Opción recomendada:
- habilitar/usar tracking del documento
- compartir código de tracking

## 8.4 Si se requiere evidencia formal

- Opción recomendada:
- descargar recibido PDF
- descargar reporte PDF/Excel
- generar sticker/etiqueta si aplica

## 8.5 Si se quiere acelerar clasificación

- Opción recomendada:
- revisar panel IA del documento
- aplicar sugerencias (tags/categoría/departamento)

---

## 9. Salidas automáticas que el cliente debe conocer

El sistema puede generar automáticamente:

- número de documento
- tracking público (si habilitado)
- logs de auditoría
- notificaciones por cambios de documento
- aprobaciones pendientes
- recibidos PDF
- usuario `regular_user` desde recibido
- OTP de acceso al portal
- procesamiento IA en segundo plano al crear versiones
- reportes programados por horario
- tareas de mantenimiento (scheduler)

---

## 10. Errores comunes y qué hacer

## 10.1 “No tienes permiso…”

Causa probable:

- el usuario no tiene el rol adecuado
- intenta acceder a datos de otra empresa/sucursal/departamento

Acción:

- validar rol del usuario
- validar asignación organizacional

## 10.2 “No se encontró un recibido válido…”

Causa probable:

- número de recibido incorrecto
- correo no coincide con el recibido

Acción:

- revisar datos del recibido generado por recepción

## 10.3 “El código OTP expiró”

Causa probable:

- pasaron más de 10 minutos

Acción:

- solicitar un nuevo OTP

## 10.4 “Código de tracking inválido / expirado”

Causa probable:

- código mal digitado
- tracking deshabilitado
- tracking vencido

Acción:

- validar código exacto
- solicitar nuevo código al área responsable

---

## 11. Checklist de implementación para arrancar con cliente (día 1)

### Checklist de super_admin/admin

- [ ] Empresa creada y datos básicos completos
- [ ] Sucursales creadas
- [ ] Departamentos creados
- [ ] Usuarios creados por rol
- [ ] Categorías creadas
- [ ] Estados creados
- [ ] Workflow básico definido
- [ ] Prueba de creación de documento
- [ ] Prueba de aprobación
- [ ] Prueba de recibido + OTP
- [ ] Prueba de tracking
- [ ] Prueba de reporte PDF/Excel

### Checklist de capacitación por rol

- [ ] `receptionist` sabe crear documento y recibido
- [ ] `office_manager` sabe aprobar/rechazar
- [ ] `archive_manager` sabe consultar y etiquetar
- [ ] `regular_user` sabe ingresar por OTP y consultar recibido/documento
- [ ] `branch_admin` sabe generar reportes de su ámbito

---

## 12. Referencias complementarias

Manual técnico/funcional completo (interno):

- `/Volumes/NAS(MAC)/Data/Herd/archive-master-app/MANUAL_USUARIO_SISTEMA.md`

Manual no técnico con flujos visuales resumidos:

- `/Volumes/NAS(MAC)/Data/Herd/archive-master-app/MANUAL_USUARIO_CLIENTE_FLUJOS.md`
