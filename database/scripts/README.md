# Scripts de base de datos — SUIF

Orden de ejecución en una instalación nueva:

> Atajo: `suif_instalacion_completa.sql` equivale a correr los nueve pasos de
> abajo en su orden, en un solo archivo y —a diferencia de `suif.sql`— sin
> ningún `drop`, así que admite `ON_ERROR_STOP` desde el primer paso:
>
>     psql -v ON_ERROR_STOP=1 -h HOST -U suif -d suif -f suif_instalacion_completa.sql
>
> Es un archivo GENERADO por concatenación de los nueve: no se edita a mano, y
> si el responsable de la base cambia alguno de los fuentes, se regenera.
> Sólo para bases vacías; sobre una base con datos se siguen usando los
> scripts numerados, que son idempotentes.

> Atajo 2: `suif_esquema_final.sql` es ese mismo resultado ya APLANADO. No
> concatena los nueve, sino que reconstruye el estado al que llegan: cada
> columna nace con el tipo y la obligatoriedad que tiene hoy y no queda un
> solo `ALTER` de migración, así que se lee como el retrato del esquema
> actual. Instala exactamente lo mismo —36 tablas, 35 llaves foráneas, los
> mismos índices y catálogos— y también es sólo para bases vacías:
>
>     psql -v ON_ERROR_STOP=1 --single-transaction -h HOST -U suif -d suif \
>          -f suif_esquema_final.sql
>
> Úsalo para consultar el esquema o entregarlo a quien pida «el script de la
> base»; para desplegar sobre una base con datos, los scripts numerados.


1. `suif.sql`               — esquema base (35 tablas)
2. `suif_evaluacion_grupo.sql` — EVALUACION apunta a GRUPO
3. `suif_ajustes_esquema.sql` — correcciones de tipos y restricciones
4. `suif_catalogos.sql`     — catálogos y convocatoria
5. `suif_grupos_multiples.sql` — varias aplicaciones de examen por sede
6. `suif_referencias_bancarias.sql` — catálogo de referencias bancarias
7. `suif_rfc_persona.sql` — RFC de la persona en PERSONA
8. `suif_referencia_fecha_emision.sql` — fecha de emisión en REFERENCIA_BANCARIA
9. `suif_roles_administrativos.sql` — roles administrativos y catálogo de privilegios
10. `suif_comprobante_fiscal.sql` — comprobante fiscal del pago (ticket o CFDI)

`suif_referencia_fecha_emision.sql` agrega `REBA_FECHA_EMISION`, la fecha en
que el banco emitió la referencia. Va DESPUÉS de
`suif_referencias_bancarias.sql`, que es quien crea la tabla. Córrelo ANTES de
publicar el código: sin esa columna, la carga del catálogo falla con
`column reba_fecha_emision does not exist`.

`suif_evaluacion_grupo.sql` va ANTES que `suif_ajustes_esquema.sql`, no
después: es el que crea `EVALUACION.GRUP_ID_GRUPO`, y sin esa columna
`suif_ajustes_esquema.sql` aborta a media ejecución. Ver más abajo.

`suif_lleno.sql` es opcional: datos de prueba para ambientes de desarrollo.
Nunca se ejecuta en producción.

`suif_antiguo.sql` es el esquema anterior al 11/08/2026. Se conserva sólo
como referencia; no se ejecuta.

## suif.sql BORRA TODA LA BASE

Empieza con `drop table` de todas las tablas. Solo se ejecuta sobre una base
vacía, en una instalación desde cero. NUNCA sobre una base con datos.

Sobre una base realmente vacía esos `drop` fallan porque no existe nada que
borrar. Es lo esperado: **este primer script se corre SIN `ON_ERROR_STOP`**.

    psql -h HOST -U suif -d suif -f suif.sql

## La programación de la evaluación vive en GRUPO

Desde el esquema del 11/08/2026, `EVALUACION` ya no guarda sede ni fechas.
La cadena es:

    SEDE ──< GRUPO (sede, fechas y horas) ──< EVALUACION (resultado)

y `SOLICITUD.SOLI_ID_EVALUACION` sigue apuntando a `EVALUACION`, que es
contra lo que se cuenta el cupo de la sede.

El cambio es incompatible con el esquema anterior: una base creada con
`suif_antiguo.sql` no se migra, se reconstruye desde cero.

## Bases a medio migrar: suif_evaluacion_grupo.sql

Existe un caso intermedio que ningún otro script cubre: una base donde
`GRUPO` ya se creó pero `EVALUACION` conservó `EVAL_ID_SEDE` y sus fechas y
horas. `suif_ajustes_esquema.sql` no crea `EVALUACION.GRUP_ID_GRUPO` —da por
hecho que vino de `suif.sql`— y sólo añade la restricción
`uq_evaluacion_grupo`, que ahí aborta por columna inexistente.

Con ese hueco, las dos pantallas de sedes responden 500 con
`column e.grup_id_grupo does not exist`, porque ambas recorren
`sede -> grupo -> evaluacion`. El resto del sistema no se entera: ninguna
otra consulta toca esas tablas.

`suif_evaluacion_grupo.sql` agrega la columna, traspasa a `GRUPO` la
programación que todavía viva en `EVALUACION` —reutilizando el grupo que
coincida en sede, fechas y horas, o creándolo— y deja la correspondencia uno
a uno que exige `uq_evaluacion_grupo`.

No borra columnas ni renglones. Las columnas del esquema anterior se
conservan con sus datos y sólo dejan de ser obligatorias, porque las altas
nuevas ya no las llenan. En una instalación desde cero el script no hace
nada: `EVALUACION` ya nace apuntando a `GRUPO`.

### Corre ANTES que suif_ajustes_esquema.sql

El orden importa y es contraintuitivo. `suif_ajustes_esquema.sql` aborta en
el bloque de `uq_evaluacion_grupo` mientras falte la columna, y con
`ON_ERROR_STOP=1` psql se detiene ahí. Todo lo que viene después de ese
bloque se queda sin ejecutar:

- el `UPDATE sede SET sede_estado` que recalcula el cupo,
- la sincronización de secuencias, sin la cual un alta nueva puede chocar
  con un identificador ya existente,
- la restricción `uq_documento_solicitud_tipo`,
- el `UPDATE rol` que renombra «Participante» a «Persona», sin el cual el
  pre-registro no encuentra el rol al dar de alta.

Por eso `suif_evaluacion_grupo.sql` va primero: deja la columna en su sitio
y entonces `suif_ajustes_esquema.sql` corre completo de principio a fin. Si
ya se ejecutó `suif_ajustes_esquema.sql` y se detuvo, basta con correr
`suif_evaluacion_grupo.sql` y volver a ejecutarlo: es idempotente y esta vez
llega al final.

## Una sede aplica el examen una o más veces

`suif_ajustes_esquema.sql` limitaba cada sede a una sola programación con la
restricción `uq_grupo_sede`. `suif_grupos_multiples.sql` la retira: cada
aplicación es un `GRUPO` con su propio horario y su propia `EVALUACION`, y el
participante elige el horario que le convenga.

`SEDE_CUPO` es el aforo de **cada** aplicación, no el total de la sede: la
sala admite el mismo número de personas en cada sesión.

## Las referencias bancarias son un catálogo aparte

`suif_referencias_bancarias.sql` crea `REFERENCIA_BANCARIA`, la lista de
referencias que el administrador sube por CSV y de la que el sistema va
entregando una a cada persona:

    REFERENCIA_BANCARIA ──(REBA_ID_PAGO, único)── PAGO ──< SOLICITUD

Al entregarse, la referencia y la ruta de su PDF se copian a
`PAGO_REFERENCIA_BANCARIA` y `PAGO_REFERENCIA_BANCARIA_PATH`, y el renglón
del catálogo queda ligado a ese `PAGO`. Como `REBA_ID_PAGO` es único, la
base misma impide que una referencia se reparta dos veces.

El mismo script afloja cuatro columnas de `PAGO`
(`PAGO_ID_DATO_FISCAL`, `PAGO_FECHA_PAGO`, `PAGO_HORA_PAGO` y
`PAGO_COMPROBANTE_PATH`): el renglón de `PAGO` nace cuando se asigna la
referencia, y en ese momento todavía no hay datos fiscales, ni fecha de
pago, ni comprobante. Se llenan más adelante en el trámite.

También siembra `C_ESTADO_PAGO` (Pendiente, Completado, Declinado), que
`suif.sql` crea vacío y sin el cual la revisión del comprobante no puede
registrar nada.

## Los otros siete se pueden repetir

`suif_ajustes_esquema.sql`, `suif_evaluacion_grupo.sql`,
`suif_catalogos.sql`, `suif_grupos_multiples.sql`,
`suif_referencias_bancarias.sql`, `suif_roles_administrativos.sql` y
`suif_comprobante_fiscal.sql` son idempotentes: volver a ejecutarlos no
duplica ni destruye nada. Por eso la regla al desplegar es correrlos SIEMPRE,
sin preguntarse si ya se corrieron.

## Hay tres tipos de administrador y los permisos salen de PRIVILEGIO_ROL

`suif_roles_administrativos.sql` es **requisito de despliegue** del módulo de
administradores. Hace cuatro cosas:

- Agrega `USUARIO.USUA_ACTIVO` (`BOOLEAN NOT NULL DEFAULT TRUE`). Dar de baja a
  un administrador no borra su renglón: le retira el acceso. `PERSONA` y
  `USUARIO` son el rastro de quién dictaminó cada expediente.
- Renombra el rol 2 de `Administrador` a `Superusuario`. Era el único
  administrador y tenía todo el catálogo; ahora es el rol sin límites y su
  nombre lo dice. Mismo patrón que el refactor «Participante → Persona».
- Da de alta `Admin UIF` y `Admin DEC`. `ROL_TIPO_ROL` mide 15 caracteres, por
  eso los nombres son cortos y la columna no se toca.
- Siembra los seis privilegios y los reparte. **Sin esto nadie tiene acceso a
  nada**: `suif.sql` crea `PRIVILEGIO` vacío y `suif_catalogos.sql` no lo
  llena. Hasta ahora lo sembraba en tiempo de ejecución `suif:crear-admin`.

El reparto es:

| Rol | Privilegios |
|---|---|
| `Superusuario` | los seis |
| `Admin UIF` | `Validación Registro` |
| `Admin DEC` | `Gestionar Pagos`, `Gestionar Referencias` |

Los `setval` van **antes** de los `INSERT`. `suif_catalogos.sql` ya alinea la
secuencia de `ROL`, así que hoy no corrigen nada; están primero por si la base
llegó a este punto sin haber corrido catálogos completo, porque entonces un
alta sin id explícito chocaría con una llave existente.

### suif_revierte_roles_administrativos.sql quedó obsoleto

Deshace el renombre del rol 2 para devolverlo a `Administrador`. Servía cuando
el módulo se retiró en agosto y el código volvía a comparar el nombre del rol.
**No lo ejecutes después de `suif_roles_administrativos.sql`**: dejaría al rol
2 con un nombre que ningún permiso reconoce y la cuenta entraría al sistema sin
poder abrir nada. Se conserva sólo para quien restaure un dump de aquellas
fechas.

## suif_reconstruye_tablas_perdidas.sql: recuperación, no instalación

No va en el orden de arriba y no se ejecuta en una instalación nueva. Repone
ocho tablas —`privilegio`, `privilegio_rol`, `tipo_documento`,
`c_estado_pago`, `pago`, `estado_pago`, `evaluacion` y
`referencia_bancaria`— sobre una base a la que le faltan, sin tocar las que
sigan vivas. Si ya están todas, no hace nada.

Se escribió después de que la suite de pruebas corriera contra la base real:
con la configuración de Laravel cacheada, `phpunit.xml` no logra imponer
SQLite en memoria y las pruebas borran su propio esquema donde estén
apuntando. Ver la nota de `AGENTS.md` sobre `config:clear`.

**No uses `suif.sql` para esto**: empieza con `drop table` de las 36 tablas y
se llevaría también las que sobrevivieron.

Córrelo con `--single-transaction`, para que un error revierta todo en vez de
dejar la base a medias, y después vuelve a pasar `suif_catalogos.sql`, que es
idempotente y repone el resto de los catálogos:

    psql -v ON_ERROR_STOP=1 --single-transaction -h HOST -U suif -d suif \
         -f suif_reconstruye_tablas_perdidas.sql

Lo que el script no puede devolver son los datos capturados: los pagos, sus
resoluciones y el catálogo de referencias nacen vacíos. Las referencias se
recargan desde el CSV de la DEC. Y como `SOLICITUD` conservaba la liga a
pagos y evaluaciones que ya no existen, esas columnas se ponen en nulo: quien
ya había elegido sede tendrá que elegirla otra vez.

## El comprobante del pago se elige una sola vez

`suif_comprobante_fiscal.sql` es **requisito de despliegue** del selector de
ticket o CFDI. Va DESPUÉS de `suif.sql`, que es quien crea `PAGO`,
`DATO_FISCAL`, `REGIMEN_FISCAL` y `TIPO_COMUNICACION`. Hace tres cosas:

- Convierte `PAGO.PAGO_USO_CFDI` de `VARCHAR(25)` a `BOOLEAN`. La columna
  existía desde el diseño original y ninguna línea de PHP la escribía; ahora
  guarda la elección de la persona: `NULL` no eligió —pedir comprobante es
  opcional—, `FALSE` ticket sin efectos fiscales, `TRUE` CFDI de gastos en
  general. Queda del mismo tipo que `DATO_FISCAL.DAFI_USO_CFDI`, que ya era
  `BOOL`. La conversión traduce lo que hubiera: los ambientes sembrados con
  `suif_lleno.sql` traen `'G03'`.
- Da de alta el tipo de comunicación `Correo facturación`. El correo al que se
  manda el CFDI puede no ser el de la cuenta de la persona, así que se guarda
  como un renglón más de `COMUNICACION`.
- **Siembra `REGIMEN_FISCAL`**, que hasta ahora sólo llenaba `suif_lleno.sql`
  —el archivo de datos de prueba que nunca se corre en producción—. Sin esas
  cuatro filas el `<select>` del formulario de facturación sale vacío y nadie
  puede pedir su CFDI. Es un catálogo del SAT, no datos de prueba.

Córrelo ANTES de publicar el código: sin la conversión, guardar la elección
falla con `column "pago_uso_cfdi" is of type character varying`.

Como altera el tipo de una columna, aquí el respaldo previo no es una
formalidad. El procedimiento completo —respaldo, verificación del dump y
ensayo sobre una base temporal— está en `deploy/README.md`.

## Antes de tocar producción

Respaldar primero, sin excepción:

    pg_dump -h HOST -U suif -d suif -Fc -f respaldo_AAAA-MM-DD.dump

Y ejecutar con ON_ERROR_STOP para que se detenga al primer error en vez
de seguir a medias:

    psql -v ON_ERROR_STOP=1 -h HOST -U suif -d suif -f suif_ajustes_esquema.sql
