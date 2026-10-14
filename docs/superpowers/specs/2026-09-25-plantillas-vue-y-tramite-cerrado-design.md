# Diseño: datos de usuario fuera de las plantillas Vue y trámite cerrado en el servidor

## Objetivo

Cerrar los dos hallazgos más graves de la auditoría de seguridad del 23 sep
2026 (metodología Strix / OWASP Top 10:2025) y dejar, para cada uno, una regla
única que impida que el defecto vuelva a aparecer en otra pantalla:

1. **XSS almacenado contra administradores** (A05): Vue compila y ejecuta como
   plantilla datos que escribió la persona.
2. **La persona reabre sola un trámite interrumpido** (A01/A06): el servidor
   no comprueba que la solicitud siga abierta antes de aceptar documentos o un
   envío a revisión.

Ninguno de los dos agrega dependencias, rutas ni cambios de esquema.

---

## Problema 1: Vue compila datos de usuario

### Diagnóstico

Dos motores de plantillas procesan el mismo HTML, uno después del otro:

1. **Blade, en el servidor.** `{{ $dato }}` escapa `<`, `>`, `&` y comillas.
   Las llaves no significan nada en HTML, así que pasan intactas.
2. **Vue, en el navegador.** Las apps que no declaran `template:` toman como
   plantilla el HTML que ya está dentro de su nodo raíz, lo compilan y evalúan
   como expresión JavaScript lo que haya entre `{{ }}`. El build que se carga
   (`vue.global.prod.js`, `partials/scripts.blade.php`) trae el compilador.

Si la persona escribe llaves, Blade las entrega y Vue las ejecuta. Con
`{{$emit.constructor`alert(1)`()}}` (33 caracteres) se alcanza el constructor
`Function` y se ejecuta código arbitrario en la sesión de quien abre la
pantalla, con su cookie y su token CSRF.

Convertir las llaves en entidades (`&#123;`) no sirve: el navegador las
decodifica al construir el DOM y Vue lee el DOM ya decodificado. Tampoco una
CSP: compilar plantillas en el navegador ya exige `'unsafe-eval'`. Precompilar
las plantillas (el build sólo de runtime) exigiría un bundler, fuera del stack
acordado.

### Barrido

Se revisaron las 17 apps de Vue del proyecto contra lo que Blade imprime como
texto dentro de su raíz. Los atributos no importan: Vue 3 no interpola llaves en
atributos estáticos.

| Vista | Raíz | Dato de usuario impreso por Blade dentro de la raíz | Estado |
|---|---|---|---|
| `admin/pago-detalle` | `[data-pago-detalle]` | nombre, CURP (persona); razón social, correo CFDI (persona); motivo de rechazo y el `old()` del textarea (administrador) | **Vulnerable** |
| `admin/notificacion-resultado` | `[data-preregistro-admin]` | nombre, CURP (persona) | **Vulnerable** |
| `partials/admin/formato-pago` | dentro de las dos anteriores | nombres de responsables (administrador) | Riesgo entre administradores |
| `partials/admin/acciones-reversion` | dentro de las dos anteriores | ninguno: etiquetas y textos fijos de `NotificacionResultado` | Correcto |
| `admin/preregistro-detalle`, `preregistro-documentacion` | `[data-preregistro-admin]` | ninguno: los datos llegan por `data-vista` y los pinta Vue | Correcto |
| Bandejas (`personas`, `pagos`, `personas-registradas`) | `[data-bandeja-administrativa]` | ninguno: JSON | Correcto |
| `admin/referencia-especial-detalle` | `[data-formulario-ajax]` | la razón social y los participantes quedan **fuera** de la raíz | Correcto |
| Formularios admin, `referencias-carga`, navegación | varias | sólo textos fijos, catálogos o números | Correcto |
| Vistas de persona (`facturacion`, `sede`, `referencia-especial`, pago, pre-registro) | varias | datos propios, sólo en atributos `value` | Correcto |

El nombre de la persona sólo se salva hoy por accidente: `nombrePropio()` lo
capitaliza y `$emit` se vuelve `$Emit`, que no existe. La razón social y el
correo del CFDI no pasan por esa función.

### Regla

> Dentro del nodo donde se monta una app de Vue, Blade no imprime como texto
> datos capturados por un usuario (persona o administrador). O llegan por
> `data-vista` con `@json` y los pinta Vue con su propia interpolación, o el
> bloque que los contiene lleva `v-pre`. Los textos fijos del servidor sí pueden
> imprimirse.

Se escribe en AGENTS.md, en la sección de arquitectura, junto a la línea que ya
habla de Vue por CDN.

### Decisión por vista

**`notificacion-resultado`: la pinta Vue.** La vista ya envía la persona por
`data-vista` (`iniciales`, `nombre_completo`, `curp`, `entidad_federativa`) y
sus hermanas del mismo script (`preregistro-detalle`, `preregistro-documentacion`)
ya pintan el encabezado desde ahí. Se adopta el mismo patrón leyendo esos campos
directamente (`persona.nombre_completo`), sin los computados `iniciales` ni
`nombreCompleto` de `admin-preregistro.js`, que esperan `nombre` y
`primer_apellido`, campos que esta vista no trae.

**`pago-detalle`: `v-pre` en los bloques estáticos.** Vue sólo maneja la
decisión (validar, abrir y cerrar el panel de rechazo, avisos). Todo lo demás es
presentación del servidor con formato de fechas y montos en PHP; pasarlo a JSON
movería ese formato a JavaScript sin ganar nada. Llevan `v-pre`:

- el `<header>` del perfil (nombre, CURP);
- el `<dl>` de datos del pago;
- la sección de datos fiscales (razón social, correo del CFDI);
- la sección del motivo de rechazo.

Dentro de un `v-pre` no funcionan directivas ni componentes, así que nunca va en
un bloque que los tenga (`<main>` completo, por ejemplo, contiene `<alertas>` y
los botones).

El textarea del motivo deja de llevar `{{ old('motivo_rechazo') }}` como
contenido: el valor pasa a un atributo `data-motivo` de la raíz y
`admin-pago-detalle.js` lo lee de ahí, en lugar de leer el textarea antes de
montar. Así el `old()`, que puede traer llaves, nunca es texto compilable.

**Parcial `formato-pago`: `v-pre` en su sección raíz.** No tiene directivas
de Vue (su formulario es un POST normal), así que el `v-pre` no cambia su
comportamiento. Lista nombres de responsables que captura un administrador.

`acciones-reversion` no cambia: sólo imprime etiquetas y textos fijos que arma
`NotificacionResultado`, que la regla permite.

### Pruebas

Un trait `tests/Concerns/RevisaPlantillasVue.php` con una aserción:

> Dentro de la raíz indicada, ningún nodo de texto que contenga el marcador
> queda fuera de un ancestro con `v-pre`.

Usa `DOMDocument` y `DOMXPath` (extensión `dom`, ya requerida por PHPUnit). El
marcador es `{{ 7*7 }}`: cabe en los 35 caracteres de la razón social y, si se
compilara, se vería como `49`.

- `ComprobanteFiscalTest`: la persona captura `{{ 7*7 }}` como razón social; el
  detalle del pago lo muestra literal y dentro de `v-pre`.
- `FormatoPagoTest`: una persona llamada `{{ 7*7 }}`; la pantalla de resultado
  del pago no lo deja como texto dentro de la raíz (sólo viaja en `data-vista`).

Las pruebas de PHP no ejecutan Vue. Lo que prueban es la condición suficiente:
que el texto no llegue compilable. La comprobación en navegador es manual.

---

## Problema 2: la persona reabre un trámite cerrado

### Diagnóstico

Cuando la UIF interrumpe un trámite, `RevisionDocumentos::interrumpir()` sólo
registra `Rechazada` en `ESTADO_SOLICITUD`. Los documentos conservan su último
estado. Hay dos caminos para reabrirlo desde la cuenta de la persona:

1. **Documentos en revisión.** `PreRegistroController::enviarRevision()` sólo
   exige que estén cargados todos los documentos y que alguno no esté aprobado.
   Los que siguen «En revisión» cuentan, así que registra `En revisión` en la
   solicitud sin mirar su estado.
2. **Documentos rechazados.** `resolver()` con rechazos deja la solicitud «En
   revisión», así que la UIF puede interrumpirla con documentos ya rechazados.
   `subirDocumento()` sólo bloquea documentos en revisión o aprobados, de modo
   que la persona reemplaza el rechazado y luego lo envía.

Por cualquiera de los dos, la solicitud vuelve a la bandeja sin que nadie con
`reanudar-tramite` lo haya decidido. La vista esconde los botones con
`AvancePersona::solicitudCerrada()`, pero esconder un botón no es un control.

### Regla

> La persona sólo modifica su expediente documental mientras la solicitud está
> abierta: su estado vigente es `Pre-registro`, `Documentación` o `En revisión`.
> Cualquier otro estado, conocido o no, cuenta como cerrado. Una solicitud
> cerrada sólo la reabre `RevisionDocumentos::reanudar()`.

Es una lista blanca a propósito. `Aprobada` también queda cerrada: hoy ya es
inalcanzable (sin documentos por enviar, todos aprobados), pero así la regla no
depende de ese detalle.

### Dónde vive

La regla se comprueba **dentro de la transacción que escribe, con la solicitud
bloqueada** (`lockForUpdate`), el mismo candado que ya usan las transiciones
administrativas. Así un envío de la persona y una interrupción simultáneos se
serializan: el que llega segundo ve el estado que dejó el primero.

Con el precedente de `BitacoraPago` (fase 1 de la auditoría MVC):

- **`App\Servicios\BitacoraSolicitud`** (nuevo): lee el estado vigente con la
  solicitud bloqueada y registra renglones en `ESTADO_SOLICITUD`. Sale tal cual
  de los métodos privados `estadoVigenteBloqueado()` y
  `registrarEstadoSolicitud()` de `RevisionDocumentos`, que pasa a usarla.
  Revisión administrativa y flujo de la persona escriben la misma bitácora con
  las mismas reglas sin depender uno del otro.
- **`App\Servicios\DocumentacionPersona`** (nuevo): las escrituras de la persona
  sobre su expediente. Hoy, `exigirAbierta()` y `enviarARevision()`. Es el primer
  tramo de la extracción del pre-registro prevista para la fase 3; en esa fase
  se mudará aquí también la carga de archivos.
- **`PreRegistroController`**: `enviarRevision()` delega en el servicio;
  `subirDocumento()` llama a `exigirAbierta()` como primer paso de su
  transacción y, si falla, borra el archivo que ya guardó. Su
  `registrarEstadoSolicitud()` privado queda sin uso y se elimina.

Se consideró poner sólo un `if (solicitudCerrada())` en el controlador. Se
descartó por tres razones: no cierra la carrera con una interrupción
simultánea, deja la regla repetida en dos acciones, y AGENTS.md ya exige que lo
que cambia estado viva en `app/Servicios`.

### Pruebas

En `TramiteInterrumpidoTest`, por HTTP, porque es por donde entra el ataque:

1. Interrumpida con documentos en revisión: `POST enviar` responde con error y
   el estado vigente sigue siendo `Rechazada`.
2. Interrumpida con un documento rechazado: `POST subir` responde con error, no
   agrega estado documental ni deja el archivo en disco.
3. Control: solicitud en revisión con un documento rechazado; subir y enviar
   siguen funcionando (la subsanación normal no se rompe).
4. Control: `reanudar()` administrativo sigue reabriendo una solicitud
   interrumpida (ya cubierto en `RevisionDocumentosTest`, que además es la red
   de la extracción de `BitacoraSolicitud`).

---

## Fuera de alcance

- Los demás hallazgos de la auditoría (HTTPS, recuperación con token,
  referencia especial, plazos de convocatoria, cabeceras). Cada uno lleva su
  propio diseño.
- Guardar quién hizo cada transición (`id_usuario` en `ESTADO_SOLICITUD`):
  requiere cambio de esquema, que decide el responsable de la base de datos.
- Mudar `subirDocumento()` completo al servicio: es la fase 3.
