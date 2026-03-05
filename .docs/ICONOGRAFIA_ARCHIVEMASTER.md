# Iconografía Oficial - ArchiveMaster

## Objetivo

Estandarizar la iconografía de Portal, Admin y Desktop bajo una misma guía visual.

## Principios

1. Trazo base: `1.8px`.
2. Esquinas suaves y terminaciones redondeadas.
3. Tamaños UI permitidos: `16`, `20`, `24`, `32`, `48` px.
4. Íconos monocromáticos por defecto (`currentColor`) para heredar tema claro/oscuro.
5. Contraste mínimo AA en estados normales y de alerta.

## Ubicación de assets

- Ícono de app:
  - `resources/icons/archive-master/app-icon.svg`
- Set UI:
  - `resources/icons/archive-master/ui/*.svg`
- Componentes Blade reutilizables:
  - `resources/views/components/icons/*.blade.php`

## Mapa inicial de íconos

- `bell.svg`: notificaciones.
- `inbox.svg`: bandeja/estado vacío de notificaciones.
- `archive.svg`: archivo físico/digital.
- `send.svg`: distribución/envío.
- `receive.svg`: recepción.
- `status-approved.svg`: estado aprobado/listo.
- `status-warning.svg`: estado pendiente/riesgo.
- `alert.svg`: alerta/bloqueo.

## Estados visuales recomendados

1. Normal: color de texto heredado.
2. Hover: elevar contraste (`+1 nivel` en paleta).
3. Disabled: opacidad 45%-55%.
4. Error/alerta: semántica roja (`text-rose-*`).
5. Éxito: semántica verde (`text-emerald-*`).

## Integración inicial realizada

1. Campana del header principal migrada a ícono oficial (`<x-icons.archive-bell />`).
2. Estado vacío de notificaciones migrado a ícono oficial (`<x-icons.archive-inbox />`).

## Pendiente para fase de expansión

1. Reemplazo completo de iconos inline SVG restantes en vistas Portal.
2. Reemplazo progresivo de iconos no alineados en widgets Filament.
3. Exportables nativos de app icon (`.ico`, `.icns`, `.png`) para bundle final Desktop.
