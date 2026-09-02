# Diseño: identidad tipográfica del sistema

## Objetivo

Dar a SUIF una jerarquía tipográfica única y verificable. Antes de este cambio la
interfaz mezclaba tres familias, unos sesenta tamaños entre px y rem y seis pesos,
sin una base común: cada pantalla resolvía por su cuenta el mismo componente. El
efecto más visible era que en los nueve formularios del sistema la etiqueta se veía
más pequeña que el texto del campo que rotula.

## Reglas

### Familias

- **Merriweather** (`--font-serif`, peso 700) para títulos: `h1`–`h6` y las clases
  que hacen de título de pantalla, sección, tarjeta o modal.
- **Open Sans** (`--font-sans`, pesos 400/600/700) para todo lo demás: cuerpo,
  etiquetas, `legend`, campos, botones, tablas, chips, sobretítulos, cifras,
  navbar, footer, sidebar y avisos.
- Criterio: si el elemento contesta «¿de qué trata esta pantalla o sección?», es
  serif. Si se lee, se llena o se pulsa, es sans.
- **Raleway** (`--font-nav`, peso 700) tiene un solo uso en el sistema: el nombre
  del sistema en el encabezado (`.navbar-sistema-titulo`). Es la firma de la
  aplicación, no un título de contenido, y por eso conserva familia y tamaño
  propios. La navbar de la landing pública también la usa. Ninguna otra regla del
  sistema puede recurrir a ella.
- Única excepción de familia: la CURP en `admin-administradores.css` usa
  `'Courier New'` por ser un identificador de ancho fijo.

### Escala

Ocho tokens en `:root` de `public/assets/css/app.css`. Nada fuera de ellos dentro
de `body.pagina-sistema`.

| Token | rem | px | Papel |
|---|---|---|---|
| `--fs-cifra` | 2 | 32 | Número grande de un indicador. Sans 700, `line-height: 1`. |
| `--fs-pagina` | 1.5 | 24 | Título de pantalla (`h1`), uno por pantalla. También el letrero de resultado y los iconos circulares de cierre de flujo. |
| `--fs-seccion` | 1.125 | 18 | Título de sección o tarjeta (`h2`), nombre de la persona en el detalle admin, códigos destacados. |
| `--fs-subtitulo` | 1 | 16 | Subtítulo (`h3`), título de modal, nombre de tarjeta pequeña. |
| `--fs-cuerpo` | 0.9375 | 15 | Texto corrido: párrafos, `dd`, avisos, descripciones. |
| `--fs-ui` | 0.875 | 14 | Etiquetas y `legend`, campos y `select`, botones, celdas, enlaces «volver/atrás/salir», iniciales de avatar. |
| `--fs-auxiliar` | 0.8125 | 13 | Ayuda bajo un campo, mensajes de validación, notas, `small`, soporte del sidebar. |
| `--fs-micro` | 0.75 | 12 | Chips de estado, encabezados de tabla, sobretítulos, `dt`, código de error, título de navbar en móvil. |

Dos excepciones documentadas, ambas con `clamp()` propio porque son piezas de
presentación y no escalones de la escala: el saludo del panel izquierdo del login
(`.login-bienvenida`) y el nombre del sistema en el encabezado
(`.navbar-sistema-titulo`, `clamp(1rem, 1.35vw, 1.3rem)` en peso 400, que baja a
`--fs-micro` en móvil porque el ancho junto a los logotipos no da para más).

### Pesos, interlineado y espaciado

- Pesos: **400** cuerpo, campos, celdas y ayuda; **600** etiquetas, botones,
  chips, `th`, `dt`, `strong`, enlaces de acción y códigos; **700** títulos y
  cifras. Quedan eliminados 300, 500 y 800.
- Interlineado: **1.25** en títulos, **1.5** en el resto, **1** solo en cifras.
- `letter-spacing`: **0.04em** en todo rótulo en mayúsculas; **0.08em** solo en
  códigos. Los títulos no llevan mayúsculas ni tracking negativo.

### Jerarquía

1. La etiqueta nunca es menor que su campo: ambos miden `--fs-ui` y la jerarquía
   la marca el peso (600 contra 400).
2. `h1` > `h2` > `h3` > cuerpo > ui > auxiliar > micro, sin cruces.
3. Una sola `h1` por pantalla, siempre a `--fs-pagina`. Un `h1` que no es el
   título de la pantalla baja a `--fs-seccion` por clase.
4. Los encabezados sin clase heredan la base, nunca los tamaños de Bootstrap.
5. Ningún texto baja de 12px.
6. Lo que actúa como título lleva etiqueta de título.

### Alineación

- Títulos, etiquetas, campos, celdas, `dt`/`dd` y avisos: a la izquierda. Las
  pantallas de confirmación o cierre de flujo centran su contenedor completo, no
  el título suelto.
- No se usa `text-align: justify` ni `right` en el sistema.

## Alcance

Entra el sistema: los layouts `auth`, `persona` y `admin` (cuerpo con la clase
`pagina-sistema`) y las páginas de error, con sus hojas en
`public/assets/css/pages/` y `public/assets/css/partials/`.

La base vive en `app.css` bajo `.pagina-sistema :where(...)`. El selector `:where()`
es deliberado: deja la especificidad en (0,1,0), de modo que la base gana a
Bootstrap —que se carga antes— y pierde ante cualquier regla de página. Sin él, una
clase de botón usada a la vez en `<a>` y en `<button>` se vería distinta en cada
etiqueta.

Las fuentes se piden una sola vez desde `resources/views/partials/fuentes.blade.php`.

Queda fuera: la landing pública (`home.css`, `aviso-privacidad.css`, `navbar.css` y
`footer.css`), que conserva su escala y Raleway hasta su propia migración; los PDF
de Dompdf, que usan Helvetica en `pt` porque el motor no carga fuentes remotas ni
interpreta `rem` ni variables CSS; y los correos, que son texto plano. Tampoco se
tocan colores, espaciados, radios ni la duplicación de componentes.

## Verificación

Auditoría estática sobre las hojas del sistema: `--font-nav` solo en
`.navbar-sistema-titulo`, sin tamaños literales en px o rem, sin pesos
300/500/800, sin `font:` abreviado, sin `!important` tipográfico, y solo `0.04em`
y `0.08em` como espaciado de letra.

En la máquina virtual, `php artisan view:cache` y `php artisan test` para confirmar
que las vistas compilan, más una revisión visual de las pantallas de pre-registro,
documentos, bandejas administrativas, login y error comprobando en el inspector que
el `h1` computa en Merriweather y que la etiqueta no es menor que su campo.
