# Ancho de los contenedores del panel administrativo — Plan de implementación

**Diseño:** `docs/superpowers/specs/2026-09-05-ancho-contenedores-admin-design.md`

**Meta:** Que las dieciséis pantallas administrativas presenten su contenido en
una columna de `min(100%, 1200px)` centrada, con `1rem` de margen lateral, en
lugar de los cuatro anchos y las dos estructuras que hay hoy.

**Arquitectura:** Se adopta en todas las pantallas la estructura que ya usa el
panel: la sección es el marco (a todo el ancho, con el ritmo vertical y el margen
lateral) y el `div` interno es el contenedor (tope y centrado). Cinco de las seis
familias ya tienen ese `div` interno con la clase `-contenedor`, así que el
cambio es mover dos declaraciones un nivel hacia abajo dentro de la misma hoja,
sin tocar Blade. La sexta —las cuatro pantallas de flujo— no tiene contenedor
interno y absorbe los dos márgenes laterales en su propio tope. El valor vive en
un token nuevo, `--admin-ancho`, en `:root` de `app.css`.

**Stack:** CSS plano en `public/assets/css/`. No entra PHP, Blade, JavaScript ni
dependencia alguna.

## Restricciones

- No se agregan dependencias, bundlers ni frameworks; no se toca `composer.json`
  ni `package.json`.
- No se usan estilos en línea ni bloques `<style>` en Blade (AGENTS.md).
- Sólo se editan las siete hojas listadas abajo. Ninguna otra pantalla, ni el
  ritmo vertical, ni los colores, ni la tipografía, ni la maqueta interna.
- **Finales de línea:** `admin-reportes.css` está en LF; `app.css`,
  `admin-dashboard.css`, `admin-sedes.css`, `admin-referencias.css`,
  `admin-bandeja-preregistros.css` y `admin-preregistro.css` están en CRLF. Hay
  que editar respetando el final de línea de cada archivo o el diff sale con
  ruido en todo el bloque.
- Antes de comitear, revisar `git status` y `git diff`: el diff esperado son
  siete hojas de CSS y (con la tarea 8) tres vistas Blade.

## Estructura de archivos

- `public/assets/css/app.css`: declara el token `--admin-ancho`.
- `public/assets/css/pages/admin-dashboard.css`: baja el margen lateral a `1rem`
  y pide el token.
- `public/assets/css/pages/admin-sedes.css`: mueve tope y centrado al contenedor.
  Cubre sedes, grupos, administradores, convocatorias y sus cuatro formularios.
- `public/assets/css/pages/admin-referencias.css`: igual. Cubre las cuatro
  pantallas de referencias.
- `public/assets/css/pages/admin-reportes.css`: igual. Cubre reportes.
- `public/assets/css/pages/admin-bandeja-preregistros.css`: igual. Cubre las tres
  bandejas.
- `public/assets/css/pages/admin-preregistro.css`: aplica la excepción del flujo
  y retira el selector muerto. Cubre las cuatro pantallas de detalle.
- `resources/views/admin/{pagos,personas,pago-detalle}.blade.php` (tarea 8):
  versionado de las hojas que se modifican.

---

### Tarea 1: Declarar el token

**Archivo:** Modificar `public/assets/css/app.css:40-42`

- [x] **Paso 1**

En el bloque `:root`, justo antes de `--navbar-height`, agregar:

```css
    /* Ancho común de los contenedores del panel administrativo; ver
       docs/superpowers/specs/2026-09-05-ancho-contenedores-admin-design.md */
    --admin-ancho: 1200px;
```

`app.css` lo carga el layout `admin.blade.php` antes que cualquier hoja de
página, así que el token está disponible en las seis hojas.

---

### Tarea 2: El panel adopta el margen común

**Archivo:** Modificar `public/assets/css/pages/admin-dashboard.css:3-18`

- [x] **Paso 1**

`.admin-dashboard` baja el margen lateral de `1.25rem` a `1rem`. La sección
conserva su fondo propio y su ritmo vertical intactos:

```css
.admin-dashboard {
    flex: 1 0 auto;
    padding: clamp(2rem, 5vw, 4rem) 1rem;
    /* el resto del bloque no cambia */
}
```

- [x] **Paso 2**

`.admin-dashboard-contenedor` pide el token en lugar de la cifra:

```css
.admin-dashboard-contenedor {
    width: min(100%, var(--admin-ancho));
    margin: 0 auto;
}
```

---

### Tarea 3: Sedes, grupos, administradores y convocatorias

**Archivo:** Modificar `public/assets/css/pages/admin-sedes.css:1-12`

- [x] **Paso 1**

Mover el tope y el centrado de la sección al contenedor:

```css
.admin-sedes {
    padding: clamp(2rem, 5vw, 4rem) 1rem;
}

.admin-sedes-contenedor {
    width: min(100%, var(--admin-ancho));
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
```

Los modales de estas pantallas (`.admin-sedes-modal`) son `position: fixed;
inset: 0`, así que quedar dentro del contenedor topado no los afecta.

---

### Tarea 4: Referencias

**Archivo:** Modificar `public/assets/css/pages/admin-referencias.css:3-13`

- [x] **Paso 1**

Mismo movimiento que la tarea 3, sobre `.admin-referencias` y
`.admin-referencias-contenedor`.

---

### Tarea 5: Reportes

**Archivo:** Modificar `public/assets/css/pages/admin-reportes.css:3-13`

- [x] **Paso 1**

Mismo movimiento, sobre `.admin-reportes` y `.admin-reportes-contenedor`.
Recordar que este archivo está en LF.

---

### Tarea 6: Las tres bandejas

**Archivo:** Modificar `public/assets/css/pages/admin-bandeja-preregistros.css:1-11`

- [x] **Paso 1**

Mismo movimiento, y de paso desaparece el `1060px`:

```css
.admin-bandeja-preregistros {
    padding: clamp(2rem, 5vw, 4rem) 1rem;
}

.admin-bandeja-preregistros-contenedor {
    width: min(100%, var(--admin-ancho));
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
```

La regla `@media (max-width: 767.98px)` que ajusta `padding-top` sigue apuntando
a la sección, que es donde sigue viviendo el `padding`. No se toca.

Este es el cambio más visible del plan: el contenido de las bandejas pasa de
1028 px a 1200 px.

---

### Tarea 7: Las cuatro pantallas de flujo

**Archivo:** Modificar `public/assets/css/pages/admin-preregistro.css:4-9`

- [x] **Paso 1**

Sustituir el bloque por la excepción documentada, sin el selector muerto:

```css
/* Estas pantallas no llevan contenedor interno: las tarjetas cuelgan directas de
   la sección, que además es el punto de montaje de Vue. La sección hace de marco
   y de contenedor a la vez, así que el tope suma los dos márgenes laterales y el
   contenido termina midiendo los mismos --admin-ancho que el resto del panel. */
.admin-preregistro-flujo {
    width: min(100%, calc(var(--admin-ancho) + 2rem));
    margin: 0 auto;
    padding: clamp(2rem, 5vw, 4rem) 1rem;
}
```

- [x] **Paso 2**

Comprobar que `.admin-preregistro-bandeja` queda retirado y que no lo reclama
nadie:

```bash
grep -rn "admin-preregistro-bandeja" resources public/assets app
```

No debe devolver ninguna línea. Si devuelve alguna, no se borra el selector: se
le da la misma regla que a `.admin-preregistro-flujo`.

---

### Tarea 8: Que el cambio llegue al navegador

**Archivos:** Modificar `resources/views/admin/pagos.blade.php:6-7`,
`resources/views/admin/personas.blade.php:6-7`,
`resources/views/admin/pago-detalle.blade.php:6-7`

- [x] **Paso 1**

Estas tres vistas enlazan con `asset()` las dos hojas que este plan modifica
—`admin-preregistro.css` y `admin-bandeja-preregistros.css`—, así que el
navegador puede seguir sirviendo la versión vieja en caché justo en las
pantallas que más cambian. Cambiar `asset(` por `asset_versionado(` en esas seis
líneas, que es lo que ya hacen las otras trece vistas del panel. En
`pago-detalle.blade.php` entra también `admin-pago.css`, enlazada del mismo modo.

---

### Tarea 9: Verificación

- [x] **Paso 1 — auditoría estática**

```bash
grep -rn "var(--admin-ancho)" public/assets/css/pages/admin-*.css
```

Seis líneas: los cinco contenedores y la excepción del flujo.

```bash
grep -rn "1060px|1100px|1200px" public/assets/css/pages/admin-*.css
```

Ninguna línea.

```bash
grep -rn "asset..assets/css" resources/views/admin
```

Trece líneas, todas `asset_versionado`.

- [x] **Paso 2 — detector**

```bash
node .claude/skills/impeccable/scripts/detect.mjs --json --scope layout public/assets/css/pages/admin-dashboard.css public/assets/css/pages/admin-sedes.css public/assets/css/pages/admin-referencias.css public/assets/css/pages/admin-reportes.css public/assets/css/pages/admin-bandeja-preregistros.css public/assets/css/pages/admin-preregistro.css
```

Debe devolver `[]`, igual que antes del cambio.

- [x] **Paso 3 — medición del cálculo**

La aplicación no arranca en Windows (pide PHP 8.4 y el XAMPP local tiene 8.2), así
que la medición se hizo sobre una página estática que reproduce las seis
envolturas con las siete hojas reales cargadas. Ancho del contenido y borde
izquierdo, idénticos en las seis familias:

| Viewport | Ancho del contenido | Borde izquierdo |
|---|---|---|
| 1425 px | 1200 px | 112.4 px |
| 1009 px | 977 px | 16 px |
| 600 px | 568 px | 16 px |

La excepción del flujo (`calc(var(--admin-ancho) + 2rem)`) mide exactamente lo
mismo que las cinco pantallas con contenedor interno, en los tres anchos.

- [ ] **Paso 4 — revisión visual en la VM**

Pendiente: es el único paso que no se puede correr desde Windows. En la máquina
virtual, con `php artisan optimize:clear` hecho:

Con el viewport en 1440 px, el ancho computado del contenedor debe ser 1200 px en
las cuatro pantallas de control —panel, bandeja de pre-registros, sedes y detalle
de pago— y 992 px en las cuatro con el viewport en 1024 px. Al pasar del panel a
la bandeja, las tarjetas tienen que empezar y terminar en la misma columna.

Revisar además, por ser las que más crecen, que en las tres bandejas la fila de
la lista (`.admin-bandeja-preregistros-fila`, una cuadrícula de cuatro columnas)
siga legible con 172 px más de ancho, y que el visor de documentos y los modales
—que conservan sus propios topes— sigan centrados sobre la pantalla completa.
