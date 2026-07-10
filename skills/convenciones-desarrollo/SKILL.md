---
name: convenciones-desarrollo
description: "SIEMPRE usa esta skill al escribir, modificar o revisar cualquier código (PHP, CSS, HTML, JavaScript, Laravel, Vue.js). Aplica las convenciones personales de nomenclatura, comentarios y estructura de archivos del desarrollador."
---

# Convenciones de Desarrollo — MarnueLgh

Estas son las convenciones personales del desarrollador. Deben aplicarse en **todo el código generado o modificado**, sin excepciones.

---

## 1. Idioma

- Todo se escribe en **español**: nombres de variables, funciones, clases, comentarios, carpetas y archivos.
- **Excepciones permitidas** (términos universales del desarrollo web): `main`, `header`, `footer`, `hero`, `nav`, `index`, `include`, `assets`, `style`. 
- No mezclar español e inglés en el mismo nombre. Ejemplo incorrecto: `$usuario_list`.

---

## 2. Nomenclatura

### Variables y funciones (PHP, JavaScript)
- Usar **snake_case** siempre.
- Ejemplos correctos: `$nombre_usuario`, `$total_carrito`, `calcular_descuento()`, `obtener_socios()`.
- Ejemplos incorrectos: `$nombreUsuario`, `$NombreUsuario`, `calcularDescuento()`.

### Clases CSS
- Usar **un solo guión medio** (`-`). Nunca doble guión (`--`) ni guión bajo.
- Formato: **sección + elemento** → `.seccion-elemento`.
- Ejemplos correctos: `.footer-texto`, `.nav-enlace`, `.hero-titulo`, `.formulario-campo`.
- Ejemplos incorrectos: `.footerTexto`, `.footer_texto`, `.footer--texto`, `.section-footer-principal-color-dark`.
- Clases globales reutilizables pueden ser cortas: `.btn`, `.titulo`, `.contenedor`.

### Archivos y carpetas
- Usar **guión bajo** para nombres de archivos: `galeria_socios.php`, `proyectos_sefca.php`.
- Carpetas en minúsculas sin espacios: `includes/`, `css/`, `js/`, `img/`.

### IDs vs Clases
- Usar **clases siempre** para estilos CSS, sin excepción.
- Usar `id` únicamente para:
  - Anclas de navegación interna (`<a href="#contacto">` → `<section id="contacto">`)
  - Vincular `<label>` con `<input>` (`for="correo"` → `id="correo"`)
  - Selección específica en JS (`document.getElementById`)
- Nunca usar `id` para aplicar estilos CSS.

### Regla general
No combinar múltiples convenciones de nomenclatura en el mismo proyecto. Un estilo, aplicado de manera consistente en todo el código.

---

## 3. Estructura de Archivos (PHP)

- Las **páginas completas** van en la raíz junto a `index.php`.
- La carpeta `includes/` es **exclusivamente** para fragmentos que se reutilizan con `include()` y que nunca se abren directamente por URL.
- No mezclar páginas completas con fragmentos en la misma carpeta.

---

## 4. Stack Tecnológico y Convenciones Técnicas

### Stack
Bootstrap + CSS (Flexbox, Grid, Media Queries) + HTML + PHP + JS vanilla + Laravel + Vue.js.

### Bootstrap y CSS propio
- Bootstrap se usa como **base estructural**: layout, grid, componentes y utilidades.
- El CSS propio **personaliza y extiende** Bootstrap, nunca lo reemplaza completamente.
- Todo el CSS personalizado va en un **único archivo** `css/style.css`, cargado después de Bootstrap.
- Al inicio de `style.css` se definen las **variables globales** en `:root`:

```css
:root {
	--color-primario: #112F4B;
	--color-secundario: #9C6E09;
	--fuente-principal: 'Nombre', sans-serif;
}
```

- Las secciones dentro de `style.css` se delimitan con comentarios:

```css
/* ── Sección: Header ─────────────────────────── */

/* ── Sección: Hero ───────────────────────────── */

/* ── Sección: Footer ─────────────────────────── */
```

### Media Queries
- Escribir media queries donde el diseño lo necesite, no forzosamente en los breakpoints de Bootstrap.
- Todas las media queries van **al final del archivo** `style.css`, agrupadas y delimitadas con comentario:

```css
/* ── Media Queries ───────────────────────────── */

@media (max-width: 768px) {
	.hero-contenedor {
		padding: 2rem 1rem;
	}
}

@media (max-width: 480px) {
	.nav-enlace {
		font-size: 0.9rem;
	}
}
```

### JavaScript
- El JS propio del proyecto va en `js/main.js`.
- Las librerías externas y scripts de terceros se gestionan desde `includes/scripts.php`.
- El archivo `includes/scripts.php` se incluye al final del `<body>` en cada página.
- No escribir JS suelto dentro del HTML de las páginas.

### Indentación
- Usar **tabs** en todos los lenguajes (PHP, HTML, CSS, JS).

---

## 5. Comentarios

### 5.1 Encabezado de archivo
Va al inicio de cada archivo, siempre.

```php
/*
 * Autor: MarnueLgh
 * Fecha: dd/mm/aaaa
 * Versión: 1.0
 * Descripción: Texto descriptivo de lo que hace este archivo.
 */
```

### 5.2 Encabezado de función o sección
Flexible: incluir solo los campos que apliquen. No forzar campos vacíos.

```php
// Descripción: Calcula el total del carrito aplicando descuento.
// Parámetros: $productos (array), $descuento (float)
// Retorna: float
function calcular_total($productos, $descuento) { ... }
```

```php
// Descripción: Cierra la sesión y redirige al login.
function cerrar_sesion() { ... }
```

```css
/* Sección: Footer — estilos base del pie de página */
.footer { ... }
```

### 5.3 Comentarios de sección o aclaración
Usar comentarios simples únicamente cuando el código lo necesite o para delimitar secciones visualmente.

```php
// Validar que el usuario esté autenticado
if (!isset($_SESSION['usuario'])) { ... }
```

```php
// ── Conexión a la base de datos ──────────────────
$conexion = new mysqli(...);

// ── Consulta principal ───────────────────────────
$resultado = $conexion->query(...);
```

```css
/* ── Sección: Header ─────────────────────────── */
.header { ... }

/* ── Sección: Footer ─────────────────────────── */
.footer { ... }
```

No comentar código que se explica solo. Si el nombre de la variable o función es claro, no necesita comentario.

---

## 6. Versionado

Formato: `MAYOR.MENOR` (dos niveles únicamente).

| Versión | Cuándo usarla |
|---------|--------------|
| `1.0` → `2.0` | Cambio mayor: reescritura de lógica, nueva funcionalidad significativa |
| `1.0` → `1.1` | Ajuste menor: corrección, pequeña mejora, refactor puntual |

Actualizar la versión en el encabezado del archivo cada vez que se realice un cambio relevante.

---

## Resumen rápido

| Elemento | Convención |
|----------|-----------|
| Variables/funciones | `snake_case` en español |
| Clases CSS | `seccion-elemento` con un solo guión medio |
| Archivos | `nombre_descriptivo.ext` con guión bajo |
| Idioma | Español (excepto términos universales) |
| IDs | Solo anclas, label-input y JS; nunca para CSS |
| Indentación | Tabs en todos los lenguajes |
| Bootstrap | Base estructural, personalizado con `style.css` |
| Variables CSS | Definidas en `:root` al inicio de `style.css` |
| Media queries | Al final de `style.css`, agrupadas con comentario |
| JS propio | `js/main.js` |
| JS librerías | `includes/scripts.php` al final del `<body>` |
| Encabezado de archivo | Autor, Fecha, Versión, Descripción |
| Encabezado de función | Flexible, solo campos que apliquen |
| Comentarios | Solo cuando se necesiten o para delimitar secciones |
| Versionado | `1.0` mayor, `1.1` menor |
| Páginas PHP | En la raíz del proyecto |
| Fragmentos PHP | En `includes/` |
