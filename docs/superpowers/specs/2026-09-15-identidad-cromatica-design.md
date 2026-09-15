# Diseño: identidad cromática y componentes del sistema

## Objetivo

Dar a SUIF un solo vocabulario visual para lo que ya hace. Antes de este cambio el
mismo papel se resolvía con ~60 apariencias de botón en 23 hojas, 12 familias de chip
de estado, 15 cajas de notificación, 13 campos, 4 tablas y 7 diálogos, y con 240
colores escritos a mano contra 9 tokens: ninguna hoja de persona usaba un token de
color. El efecto más visible era que un mismo estado —«Pendiente», «Aprobado»— se
pintaba distinto en cada pantalla, y que el anillo de foco del sistema entero medía
1.74:1, por debajo del mínimo que exige WCAG para un indicador.

Es el capítulo que la identidad tipográfica
(`2026-09-01-identidad-tipografica-design.md`) dejó fuera a propósito: «no se tocan
colores, espaciados, radios ni la duplicación de componentes».

## Reglas

### Papeles del color

Cinco papeles en `:root` de `public/assets/css/app.css`. Nada de color semántico fuera
de ellos dentro de `body.pagina-sistema`.

| Papel | Fondo | Texto | Borde | Sólido | Qué dice |
|---|---|---|---|---|---|
| Éxito | `--exito-fondo` #eafaf1 | `--exito-texto` #207a45 | `--exito-borde` #b9dfc7 | `--exito-solido` #207a45 | Aprobado, completado, vigente, con cupo, con acceso, activo, disponible |
| Peligro | `--peligro-fondo` #fde8e8 | `--peligro-texto` #a53630 | `--peligro-borde` #f3b0ae | `--peligro-solido` #991b1b | Rechazado, cancelado, interrumpido, sin cupo, sin acceso, inactivo |
| Revisión | `--revision-fondo` #fdf6ec | `--revision-texto` #8b6717 | `--revision-borde` #ecd9a8 | — | En revisión, cupo bajo, precaución |
| Información | `--info-fondo` #eef2f7 | `--info-texto` #112f4b | `--info-borde` #c9d6e6 | — | En proceso, asignada, cargado, avisos que informan |
| Neutro | `--neutro-fondo` #ebe9e3 | `--neutro-texto` (= `--text-muted`) | — | — | Pendiente, por programar, cerrada, ticket, CFDI |

- El mapa respeta la spec `2026-08-03-notificaciones-administrativas`: completado
  verde, revisión dorado, rechazado rojo, pendiente gris.
- «Cargado» pasa a información: un documento subido todavía no está aprobado.
- Un **rol** no es un estado y no toma ninguno de los cinco papeles; el de la DEC
  dejó el ámbar de «en revisión» por un gris pizarra.
- Ninguna combinación baja de 4.5:1 en texto ni de 3:1 en foco y bordes.

### Forma y medida

`--radio-control` 8px para botones, campos y chips rectangulares; `--radio-caja` 12px
para tarjetas, avisos y diálogos; `--alto-control` 44px como área tocable mínima de
todo control; `--borde-control` #8c8a83 (3.45:1 sobre blanco) para el contorno de los
controles, mientras `--border-light` sigue siendo el de las tarjetas.

### Foco

Un solo anillo: `--foco-anillo` (3px sólido `--unam-gold-dark`) con
`--foco-separacion` de 2px, y `--foco-anillo-claro` (`--unam-gold`) sobre fondo azul.
Se aplica además a campos, `select`, `textarea`, etiquetas que envuelven un input de
archivo y a cualquier elemento con `tabindex`, que antes no mostraban nada.

### Componentes

Viven en `public/assets/css/partials/componentes.css`, que los tres layouts del
sistema cargan después de `app.css` y antes de las hojas de `pages/`.

| Clase | Sustituye a | Reglas |
|---|---|---|
| `.boton` + `--primario`, `--secundario`, `--peligro`, `--peligro-solido`, `--exito`, `--texto`, `--icono`, `--claro` | ~60 apariencias | Alto 44px, radio 8px, 14px/600; hover que oscurece un paso; primario navy `--unam-blue` |
| `.boton[disabled]`, `.boton--cargando` | 7 aspectos de apagado | `--inactivo-fondo` / `--inactivo-texto`; el hover no reacciona |
| `.estado` + los cinco papeles y `--rol-*` | 12 familias de chip | Píldora, 12px/600, relleno 4px 12px |
| `.pasos` / `.paso` | 4 sistemas de avance | Número en círculo con el color sólido del papel; el paso en curso lleva anillo dorado |
| `.notificacion` + `--exito`, `--error`, `--advertencia`, `--info` | 15 cajas | Fondo del papel, borde 1px, ícono y `role` |
| `.aviso` + `--precaucion`, `--hecho` | `.pr-notice` y sus variantes | Información en azul, precaución en ámbar, hecho en verde |
| `.aviso-motivo` | 4 cajas de «motivo del rechazo» | La de persona: #fff8f8 / #8d1117, borde #ebc7c7 (9:1), respeta saltos de línea |
| `.ayuda`, `.atenuado` | 9 grises | Un solo `--text-muted` |
| `.vacio` | 4 estados vacíos | Centrado, con ícono y `role="status"` |
| `.campo`, `.etiqueta`, `.control`, `.campo__error`, `.opcion` | 13 campos | Alto 44px, `aria-invalid` en el campo con error y mensaje bajo el campo |
| `.tarjeta` | ~19 contenedores | Blanco, radio 12px, borde `--border-light`, sombra navy .07 |
| `.tabla`, `.tabla-contenedor`, `.tabla-desplazable`, `.filtros` | 4 anatomías | Encabezado #f8fafc, celda 16px 20px, hover de fila |
| `.dialogo` y `.dialogo-abierto` | 7 diálogos y 7 clases de bloqueo | 496px, velo rgba(11,26,46,.58), botones en extremos opuestos |

### Textos

- Encabezado único de errores: «Corrige estos datos:».
- El botón que confirma nombra la acción y su objeto («Eliminar sede»), no «Sí, …».
- Irreversibilidad: «Después ya no podrás cambiarlo.».
- «…» siempre como un solo carácter; nunca «...».
- Persona de tú en todas las pantallas, incluidas referencia y referencia especial.
- Una sola redacción por regla de validación (PDF de 1 MB, CURP de 18, longitud
  máxima) y una sola palabra por resultado.

## Alcance

Entra el sistema: los layouts `auth`, `persona` y `admin` (cuerpo `pagina-sistema`) y
las páginas de error, con sus hojas en `pages/` y `partials/`. Entran también los 8
errores visuales que la auditoría confirmó (contenedor sin cerrar del dashboard,
«En revisión» sin CSS, ámbar fijo en documentación, «Cancelada» en dos colores,
«Salir» sin nombre accesible en móvil, error del botón copiar, error duplicado en
reportes, fuentes que no se cargan).

Queda fuera: la landing pública, los PDF de Dompdf y los correos. Y quedan para su
propia rama los tres fallos de comportamiento de las notificaciones: el mensaje de
éxito que se pierde al navegar (`main.js:274-277` con `Controller.php:54-61`), las
advertencias que vuelven como 422 y se pintan de error, y el encabezado pegado de
`Alertas.js:71-73`.

## Cómo se migra

Un commit por capa: base (esta spec, tokens, componentes y su carga), persona
pre-registro y documentos, resto de persona, bandejas y altas admin, expediente
admin, auth y errores, textos, limpieza.

La regla que ordena el trabajo: **las hojas de `pages/` se cargan después, así que una
regla de página todavía gana**. Migrar una pantalla es cambiar su clase en Blade, JS y
PHP y borrar su regla vieja en el mismo commit. Si algo sigue viéndose como antes, es
que quedó la regla.

Los mapas de estado que arma PHP (`ConsultaPagos`, `ConsultaPreRegistros`,
`ConsultaPersonasRegistradas`, `NotificacionResultado`, los dos `DashboardController`
y `PreRegistroController`) devuelven el nombre del papel, como ya hace
`ConvocatoriaController` con `primario`, `secundario` y `eliminar`.

## Verificación

- Sin Laravel: `node tests/js/suif.test.js` y los arneses de `tests/navegador/`, que
  miden `getComputedStyle` con el CSS real.
- En el server, por capa: `git pull`, `php artisan optimize:clear`, `php artisan test`
  y revisión visual de las pantallas de esa capa.
- Revisión visual: foco visible al tabular en botones, enlaces y campos; primario
  navy; chips con los cinco papeles; campos de 44px; diálogo con los botones en
  extremos; estado vacío con ícono.
- El inventario y las decisiones con sus medidas de contraste están en el lienzo
  local del equipo (fuera del repo), que fue el punto de partida de esta spec.
