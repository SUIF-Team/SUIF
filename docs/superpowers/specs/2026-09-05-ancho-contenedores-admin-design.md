# Diseño: ancho de los contenedores del panel administrativo

## Objetivo

Que toda pantalla administrativa presente su contenido en una sola columna del
mismo ancho, de modo que al navegar entre el panel, una bandeja y un detalle las
tarjetas no se muevan de sitio. Hoy conviven cuatro anchos distintos y dos
estructuras de envoltura incompatibles entre sí.

## Diagnóstico

Todas las cajas del sistema son `border-box` (`app.css`), así que el margen
lateral que se declara en la misma caja que el tope se descuenta del ancho útil.
Medido en escritorio (viewport ≥ 1240 px):

| Pantallas | Envoltura | Declaración actual | Ancho de contenido |
|---|---|---|---|
| Panel administrativo | `.admin-dashboard-contenedor` | `min(100%, 1200px)`, el margen lateral vive en la sección | **1200 px** |
| Sedes, grupos, administradores, convocatorias y sus cuatro formularios | `.admin-sedes` | `min(100%, 1200px)` + `padding-inline: 1rem` | **1168 px** |
| Referencias, carga de referencias, referencias especiales y su detalle | `.admin-referencias` | `min(100%, 1200px)` + `padding-inline: 1rem` | **1168 px** |
| Reportes | `.admin-reportes` | `min(100%, 1200px)` + `padding-inline: 1rem` | **1168 px** |
| Bandejas de pre-registros, personas registradas y pagos | `.admin-bandeja-preregistros` | `min(100%, 1060px)` + `padding-inline: 1rem` | **1028 px** |
| Detalle de pre-registro, documentación, detalle de pago y notificación | `.admin-preregistro-flujo` | `min(100%, 1100px)` + `padding-inline: 1rem` | **1068 px** |

La causa de fondo no son los números, son las dos estructuras:

- **Marco y contenedor separados** (sólo el panel): la sección ocupa todo el
  ancho y aporta el ritmo vertical y el margen lateral; un `div` interno lleva el
  tope y se centra. El tope es el ancho del contenido.
- **Marco y contenedor en la misma caja** (las otras once hojas): la sección
  lleva tope, centrado y margen lateral a la vez, así que el margen se resta del
  tope.

Por eso sedes, referencias y reportes ya declaraban `1200px` y aun así no
alineaban con el panel: perdían 32 px. Igualar sólo las cifras —1060 y 1100 a
1200— dejaría el defecto intacto en cuatro de las seis familias.

A la diferencia de ancho se suma la del margen lateral: el panel usa `1.25rem` y
las demás hojas `1rem`, de modo que por debajo de ~1240 px las pantallas siguen
desalineadas 8 px por lado aunque el tope coincida.

## Reglas

### La estructura

Toda pantalla administrativa se arma con dos cajas, la del panel:

1. **Marco**: el `<section>` de la pantalla. Sin tope, a todo el ancho de
   `<main>`. Aporta el ritmo vertical y el margen lateral:
   `padding: clamp(2rem, 5vw, 4rem) 1rem`.
2. **Contenedor**: el `div` interno que agrupa el contenido. Aporta el tope y el
   centrado: `width: min(100%, var(--admin-ancho)); margin: 0 auto`.

El contenido mide `--admin-ancho` en escritorio y `100% − 2rem` en cualquier
ancho menor, igual en las dieciséis pantallas.

### El token

`--admin-ancho: 1200px` se declara una sola vez en `:root` de
`public/assets/css/app.css`, junto a los tokens tipográficos y `--navbar-height`.
Ninguna hoja del panel vuelve a escribir la cifra: todas la piden por el token.
Es el mismo criterio de la escala tipográfica —un valor, un lugar— y deja el
estándar en una línea que se puede cambiar de golpe.

### El margen lateral

`1rem` en las seis familias. Es el valor de cinco de las seis hojas; el panel
baja de `1.25rem` a `1rem` para que la coincidencia no dependa del ancho de la
pantalla.

### La excepción documentada

Las cuatro pantallas de flujo —detalle de pre-registro, documentación, detalle de
pago y notificación de resultado— no tienen contenedor interno: las tarjetas
cuelgan directas de la sección, que además es el punto de montaje de Vue. En
ellas la sección es a la vez marco y contenedor, así que el tope suma los dos
márgenes laterales para que el contenido siga midiendo lo mismo:

```css
width: min(100%, calc(var(--admin-ancho) + 2rem));
```

La caja resultante es idéntica a la de las demás pantallas en todos los anchos, y
evita envolver unas quinientas líneas de Blade en un `div` sólo para recuperar
32 px. La regla lleva su comentario en `admin-preregistro.css`; si esas cuatro
vistas llegan a necesitar un contenedor interno por otra razón, la excepción se
retira y pasan a la regla general.

## Alcance

Entran las seis hojas que declaran el ancho de una envoltura administrativa:
`admin-dashboard.css`, `admin-sedes.css`, `admin-referencias.css`,
`admin-reportes.css`, `admin-bandeja-preregistros.css` y `admin-preregistro.css`,
más la declaración del token en `app.css`. Cubren dieciséis pantallas.

Se retira de paso el selector `.admin-preregistro-bandeja`, que comparte la
declaración de ancho con `.admin-preregistro-flujo` y no lo usa ninguna vista,
JavaScript ni hoja del proyecto.

Queda fuera y no se toca: los topes que no son de envoltura —el visor de
documentos `min(100%, 70rem)`, el mensaje de resultado `min(100%, 52rem)` y las
tarjetas de modal `min(100%, 31rem)`—, que son piezas internas y no el marco de
la pantalla; el fondo propio de `.admin-dashboard`, que es una banda a todo lo
ancho y seguirá siéndolo; el ritmo vertical, los colores, la tipografía y la
maqueta interna de cualquier pantalla; y las pantallas de persona, auth y la
landing.

`documentos.blade.php` y `resultados.blade.php` están vacías (sólo un `TODO`);
cuando se construyan deben nacer con la estructura de dos cajas.

## Verificación

Auditoría estática sobre las hojas del panel:

- `grep -rn "var(--admin-ancho)" public/assets/css/pages/admin-*.css` devuelve
  seis declaraciones: los cinco contenedores y la excepción del flujo.
- `grep -rn "1060px\|1100px\|1200px" public/assets/css/pages/admin-*.css` no
  devuelve nada.
- `node .claude/skills/impeccable/scripts/detect.mjs --json --scope layout` sobre
  las seis hojas, sin hallazgos.

En la máquina virtual, con el inspector abierto y el viewport en 1440 px, el
ancho computado del contenedor debe ser 1200 px en el panel, en una bandeja
(pre-registros), en una pantalla de catálogo (sedes) y en un detalle (pago); y a
1024 px, 992 px en las cuatro. Las tarjetas del panel y las de la bandeja tienen
que empezar y terminar en la misma columna al cambiar de pantalla.
