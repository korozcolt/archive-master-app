# PD-AI-001 — Decisiones Técnicas del Módulo IA

Fecha: 2026-02-21  
Estado: Aprobado para implementación

## 1) Modelo de llaves

- Decisión: `BYOK por compañía`.
- Implementación:
  - Cada compañía configura su llave en `company_ai_settings`.
  - No se usa llave global para inferencia documental de tenants.

## 2) Proveedor activo por compañía

- Decisión: `openai | gemini | none` (uno activo).
- Regla:
  - No mezclar proveedores de embeddings dentro del mismo tenant.
  - Si proveedor=`none`, el pipeline se marca `skipped`.

## 3) Persistencia de salidas

- Decisión:
  - Guardar `summary` + `suggestions`: `sí` por defecto (`store_outputs=true`).
  - Guardar texto extraído completo: `no` por defecto (solo si flag futuro lo habilita).

## 4) OCR alcance v1

- Decisión:
  - V1 procesa `PDF con texto`.
  - OCR de imagenes/escaneados entra en V2 si se justifica.

## 5) Seguridad y cumplimiento base

- Decisión:
  - API key cifrada en reposo (`encrypted cast`/`Crypt`).
  - No exponer API key en respuestas.
  - UI muestra solo estado de configuración (configurada/no configurada).

## 6) Ejecución y auditoría

- Decisión:
  - Pipeline asíncrono por colas.
  - Auditoría por corrida (`document_ai_runs`) con costo/tokens/error.

## 7) Flags iniciales aprobados

- `is_enabled`: `false` por defecto.
- `store_outputs`: `true` por defecto.
- `redact_pii`: `true` por defecto.
- `daily_doc_limit`: `100` por defecto.
- `max_pages_per_doc`: `100` por defecto.
- `monthly_budget_cents`: `nullable` (sin presupuesto duro hasta configuración).

## 8) Criterio de aceptación de Fase 0

- Documento de decisiones emitido: `PD-AI-001.md`.
- Flags de control definidos y versionados.
- Modelo multi-tenant y BYOK ratificado.

