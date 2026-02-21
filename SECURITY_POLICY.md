# Política de Seguridad (Baseline)

**Fecha**: 2026-02-05  
**Alcance**: Cifrado en reposo para archivos de documentos y lineamientos de acceso.

---

## Cifrado en reposo

- **Estado**: Soportado mediante configuración.
- **Bandera**: `DOCUMENT_ENCRYPT_FILES` (por defecto `false`).
- **Almacenamiento**:
  - Si el cifrado está activo, los archivos se almacenan en el disk `private`.
  - Si el cifrado está inactivo, se usa el disk configurado en `documents.files.storage_disk`.
- **Algoritmo**: Usa el cifrado de Laravel (`Crypt`) basado en `APP_KEY`.
- **Rotación de llaves**:
  - La rotación de `APP_KEY` requiere re-encriptar archivos existentes.
  - No hay rotación automática; debe planificarse con ventana de mantenimiento.

---

## Descargas

- Se valida acceso por **rol + propietario/asignado + empresa**.
- Se registra auditoría por **vista** y **descarga**.

---

## Estado actual

- Cifrado en reposo implementado a nivel de almacenamiento.
- Accesos y descargas auditados.
