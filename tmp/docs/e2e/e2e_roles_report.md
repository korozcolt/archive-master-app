# Reporte E2E por roles (local)

Fecha: 2026-03-05  
Base: SQLite local (`database/manual.sqlite`) con datos sembrados y documento de prueba `#1`.

## Alcance probado

1. Portal: Recepcionista.
2. Portal: Encargado de Oficina.
3. Portal: Encargado de Archivo.
4. Portal: Usuario Regular.
5. Admin Filament: Administrador.
6. Admin Filament: Administrador de Sede.

## Evidencia visual

Ruta de capturas: `tmp/docs/manual_assets_v2`.

## Resultados por flujo

### 1) Recepcionista

- Login y dashboard: OK.
- Flujo wizard de creación (pasos 1 a 4): OK.
- Carga de múltiples archivos: OK con incidencia visual.
- Creación del documento: OK.
- Distribución a oficinas destino: OK.

Incidencia detectada:
- El último archivo puede quedar en estado **“Subiendo…”** hasta recargar la página.
- Tras recarga, el archivo pasa a **“Listo”** y el flujo continúa.

### 2) Encargado de Oficina

- Login y dashboard: OK.
- Bandeja de “Mis Documentos”: OK.
- Visualización de notificaciones: OK (contador visible).
- Acciones de destino (marcar recibido/revisión/responder): UI disponible y visible.

Observación:
- La previsualización embebida puede responder `403` para ciertos perfiles, pero la descarga del documento sí está disponible según permisos.

### 3) Encargado de Archivo

- Login y dashboard: OK.
- Panel de archivo físico en detalle de documento: OK.
- Botón **Imprimir etiqueta**: OK.
- Descarga de etiqueta validada por HTTP: `200` (`application/pdf`).
- Formulario de asignación de ubicación física: visible (dependiente de ubicaciones configuradas).

### 4) Usuario Regular

- Login y dashboard: OK.
- Acceso a “Mis Documentos”: OK.
- Vista vacía cuando no tiene asignaciones: OK.
- Restricción de visibilidad por permisos: OK.

### 5) Administrador (Filament)

- Acceso al dashboard administrativo: OK.
- Módulo de usuarios/roles: OK.
- Módulo de documentos: OK.

### 6) Administrador de Sede (Filament)

- Acceso a dashboard admin con alcance limitado: OK.
- Menú reducido por permisos: OK.
- Módulo de usuarios (alcance de sede): OK.
- Módulo de documentos: OK.
- Reportes: OK.

## Conclusiones

- Los flujos principales por rol están operativos en entorno local.
- Se confirmó evidencia visual real por cada rol solicitado.
- La incidencia pendiente más relevante es la del último archivo en “Subiendo…” sin transición automática a “Listo” hasta recargar.
