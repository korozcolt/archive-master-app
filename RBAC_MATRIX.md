# Matriz RBAC Oficial (Baseline v1)

**Fecha**: 2026-02-05  
**Objetivo**: Definir permisos por rol para módulos clave del sistema.  
**Notas**: Esta matriz es el baseline oficial para pruebas y validación. Ajustes futuros deberán registrarse en el changelog.

---

## Roles

- `super_admin`
- `admin`
- `branch_admin`
- `office_manager`
- `archive_manager`
- `receptionist`
- `regular_user`
- `guest`
- `anónimo`

---

## Módulos y Acciones

**Leyenda**  
- `C`: Crear  
- `R`: Ver/Consultar  
- `U`: Editar  
- `D`: Eliminar  
- `DL`: Descargar  
- `AP`: Aprobar/Rechazar  
- `EX`: Exportar  
- `AD`: Administración global  

---

## Documentos

- `super_admin`: C, R, U, D, DL, EX, AD
- `admin`: C, R, U, D, DL, EX
- `branch_admin`: C, R, U, D, DL, EX (solo sucursal)
- `office_manager`: C, R, U, DL (solo departamento)
- `archive_manager`: R, U, DL (solo archivo/ubicaciones)
- `receptionist`: C, R, U (solo asignados/creados)
- `regular_user`: C, R (solo asignados/creados)
- `guest`: R (solo tracking público)
- `anónimo`: R (solo tracking público)

---

## Aprobaciones

- `super_admin`: AP, R, U
- `admin`: AP, R, U
- `branch_admin`: AP, R, U (solo sucursal)
- `office_manager`: AP, R (solo departamento)
- `archive_manager`: R
- `receptionist`: R
- `regular_user`: R (solo propios)
- `guest`: N/A
- `anónimo`: N/A

---

## Ubicaciones físicas

- `super_admin`: C, R, U, D
- `admin`: C, R, U, D
- `branch_admin`: C, R, U (solo sucursal)
- `office_manager`: R
- `archive_manager`: C, R, U
- `receptionist`: R
- `regular_user`: R
- `guest`: N/A
- `anónimo`: N/A

---

## Reportes

- `super_admin`: C, R, U, D, EX
- `admin`: C, R, U, EX
- `branch_admin`: R, EX (solo sucursal)
- `office_manager`: R
- `archive_manager`: R
- `receptionist`: N/A
- `regular_user`: N/A
- `guest`: N/A
- `anónimo`: N/A

---

## Administración (Usuarios/Empresas/Catálogos)

- `super_admin`: AD total
- `admin`: AD parcial (empresa propia)
- `branch_admin`: AD limitado (usuarios/área propia)
- `office_manager`: N/A
- `archive_manager`: N/A
- `receptionist`: N/A
- `regular_user`: N/A
- `guest`: N/A
- `anónimo`: N/A

