# Manual de Usuario para Cliente (Versión No Técnica)

## ¿Qué es Archive Master?

Archive Master es una plataforma para organizar documentos de una empresa, controlar su estado, saber quién los tiene, generar reportes y mantener trazabilidad completa.

Tiene dos espacios principales:

- **Administración**: para configurar y gobernar el sistema.
- **Portal Operativo**: para el trabajo diario con documentos.

---

## Acceso por tipo de usuario

- Usuarios administrativos entran a: `/admin`
- Usuarios operativos entran a: `/portal`

Esto evita mezclar tareas técnicas con tareas diarias.

---

## Roles de usuario y qué hace cada uno

## 1) Super Admin

Puede ver y gestionar todo el sistema.

```mermaid
flowchart TD
    A["Inicia sesión"] --> B["Entra al panel Admin"]
    B --> C["Configura empresas y sucursales"]
    B --> D["Gestiona usuarios y roles"]
    B --> E["Controla catálogos y flujos"]
    B --> F["Revisa reportes globales"]
    B --> G["Supervisa IA y observabilidad"]
```

## 2) Admin

Administra su empresa y controla operación documental.

```mermaid
flowchart TD
    A["Inicia sesión"] --> B["Admin de su empresa"]
    B --> C["Gestiona usuarios, áreas y documentos"]
    B --> D["Consulta reportes"]
    B --> E["Configura reportes programados"]
    B --> F["Gestiona configuración IA por empresa"]
```

## 3) Branch Admin

Administra su sucursal con alcance limitado.

```mermaid
flowchart TD
    A["Inicia sesión"] --> B["Admin de sucursal"]
    B --> C["Gestiona documentos de su sucursal"]
    B --> D["Gestiona usuarios de su ámbito"]
    B --> E["Consulta reportes de sucursal"]
```

## 4) Office Manager

Controla documentos del área y participa en aprobaciones.

```mermaid
flowchart TD
    A["Inicia sesión"] --> B["Portal Operativo"]
    B --> C["Revisa documentos asignados"]
    C --> D["Actualiza estado / contenido"]
    C --> E["Aprueba o rechaza cuando corresponda"]
    B --> F["Consulta reportes personales"]
```

## 5) Archive Manager

Administra el archivo y consulta documentos en custodia.

```mermaid
flowchart TD
    A["Inicia sesión"] --> B["Portal Operativo"]
    B --> C["Revisa documentos archivados o por archivar"]
    C --> D["Gestiona ubicación física"]
    C --> E["Genera stickers / etiquetas"]
    B --> F["Consulta reportes personales"]
```

## 6) Receptionist

Registra documentos de entrada y genera recibidos.

```mermaid
flowchart TD
    A["Inicia sesión"] --> B["Portal Operativo"]
    B --> C["Crea nuevo documento"]
    C --> D["Registra destinatario"]
    D --> E["Genera recibido"]
    E --> F["Envía/entrega comprobante al usuario"]
    B --> G["Consulta reportes personales"]
```

## 7) Regular User

Consulta sus documentos y recibidos.

```mermaid
flowchart TD
    A["Accede con recibido + OTP"] --> B["Portal Operativo"]
    B --> C["Ve sus documentos"]
    C --> D["Consulta estado y seguimiento"]
    C --> E["Descarga archivo si tiene permiso"]
    B --> F["Ve y descarga sus recibidos"]
    B --> G["Consulta reportes personales"]
```

---

## Flujos principales del negocio (simple)

## Flujo A: Documento entrante

```mermaid
flowchart LR
    A["Recepción crea documento"] --> B["Se asigna responsable"]
    B --> C["Se trabaja el documento"]
    C --> D["Si requiere aprobación, pasa por aprobadores"]
    D --> E["Se marca estado final"]
```

## Flujo B: Recibido + acceso del usuario final

```mermaid
flowchart LR
    A["Recepción genera recibido"] --> B["Sistema crea o vincula usuario regular"]
    B --> C["Usuario solicita OTP con número de recibido"]
    C --> D["Valida OTP"]
    D --> E["Ingresa al portal y consulta su información"]
```

## Flujo C: Seguimiento público

```mermaid
flowchart LR
    A["Usuario externo ingresa código de tracking"] --> B["Sistema valida código y vigencia"]
    B --> C["Muestra estado actual y línea de tiempo"]
```

## Flujo D: IA sobre documentos

```mermaid
flowchart LR
    A["Se crea nueva versión del documento"] --> B["Sistema ejecuta IA en segundo plano"]
    B --> C["Genera resumen y sugerencias"]
    C --> D["Usuario revisa y aplica sugerencias si desea"]
```

---

## Entradas y salidas que ve el cliente

Entradas típicas:

- formularios de documento
- carga de archivos
- datos de receptor para recibido
- código de tracking
- OTP para acceso portal

Salidas típicas:

- documentos y estados en pantalla
- reportes en PDF/Excel/CSV
- recibidos en PDF
- etiquetas/stickers con QR/barcode
- historial de cambios y trazabilidad

---

## Beneficios que puede validar el cliente

- Cada rol ve solo lo que necesita.
- El trabajo diario ocurre en un portal simple.
- El panel admin queda para control y configuración.
- Hay trazabilidad de acciones y cambios.
- Se pueden automatizar reportes y monitoreo.
- El sistema permite crecimiento por empresa/sucursal.

---

## Nota para presentación

Este documento está diseñado para explicar el sistema a negocio y usuarios finales.  
Para detalle técnico completo (API, políticas, jobs, seguridad interna), usar:

- `/Volumes/NAS(MAC)/Data/Herd/archive-master-app/MANUAL_USUARIO_SISTEMA.md`

