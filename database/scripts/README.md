# Scripts de base de datos — SUIF

Orden de ejecución en una instalación nueva:

1. `suif.sql`               — esquema base (35 tablas)
2. `suif_evaluacion_grupo.sql` — EVALUACION apunta a GRUPO
3. `suif_ajustes_esquema.sql` — correcciones de tipos y restricciones
4. `suif_catalogos.sql`     — catálogos y convocatoria
5. `suif_grupos_multiples.sql` — varias aplicaciones de examen por sede
6. `suif_referencias_bancarias.sql` — catálogo de referencias bancarias
7. `suif_rfc_persona.sql` — RFC de la persona en PERSONA
8. `suif_referencia_fecha_emision.sql` — fecha de emisión en REFERENCIA_BANCARIA
9. `suif_roles_administrativos.sql` — roles por área y catálogo de privilegios

`suif_referencia_fecha_emision.sql` agrega `REBA_FECHA_EMISION`, la fecha en
que el banco emitió la referencia. Va DESPUÉS de
`suif_referencias_bancarias.sql`, que es quien crea la tabla. Córrelo ANTES de
publicar el código: sin esa columna, la carga del catálogo falla con
`column reba_fecha_emision does not exist`.

`suif_evaluacion_grupo.sql` va ANTES que `suif_ajustes_esquema.sql`, no
después: es el que crea `EVALUACION.GRUP_ID_GRUPO`, y sin esa columna
`suif_ajustes_esquema.sql` aborta a media ejecución. Ver más abajo.

`suif_roles_administrativos.sql` es OBLIGATORIO y va al final. Renombra el rol
`Administrador` a `Superusuario`, agrega `Admin UIF` y `Admin DEC`, siembra el
catálogo de PRIVILEGIO y lo reparte entre los tres. Sin él, una base recién
instalada deja a todo mundo fuera: el sistema autoriza por privilegio y
`suif_catalogos.sql` crea PRIVILEGIO vacío. Ver más abajo.

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

## Los otros cinco se pueden repetir

`suif_ajustes_esquema.sql`, `suif_evaluacion_grupo.sql`,
`suif_catalogos.sql`, `suif_grupos_multiples.sql` y
`suif_referencias_bancarias.sql` son idempotentes: volver a ejecutarlos no
duplica ni destruye nada. Por eso la regla al desplegar es correrlos
SIEMPRE, sin preguntarse si ya se corrieron.

## Los privilegios mandan, no el nombre del rol

Cada pantalla administrativa exige un privilegio de `PRIVILEGIO`, no un nombre de
rol. `suif_roles_administrativos.sql` es quien llena esa tabla y quien decide qué
le toca a cada quien:

| Privilegio | Superusuario | Admin UIF | Admin DEC |
|---|:--:|:--:|:--:|
| Validación Registro | sí | sí | — |
| Gestionar Pagos | sí | — | sí |
| Gestionar Referencias | sí | — | sí |
| Gestionar Sedes | sí | — | — |
| Gestionar usuarios | sí | — | — |
| Generación Reportes | sí | — | — |

`ROL_TIPO_ROL` mide 15 caracteres, y por eso los nombres son cortos:
«Superusuario» son 12 y «Admin UIF» son 9. La columna no se amplía.

Los roles nuevos se insertan sin id explícito. En una base de desarrollo que corrió
`suif_lleno.sql` los ids 3 y 4 ya están tomados, y esa misma base llama «Validador»
al rol 2, así que el renombre a «Superusuario» no le aplica. No es un problema a
resolver: los ambientes de prueba se reconstruyen con el orden de arriba.

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
dejar la base a medias, y después vuelve a pasar `suif_catalogos.sql` y
`suif_roles_administrativos.sql`, que son idempotentes y reponen el resto:

    psql -v ON_ERROR_STOP=1 --single-transaction -h HOST -U suif -d suif \
         -f suif_reconstruye_tablas_perdidas.sql

Lo que el script no puede devolver son los datos capturados: los pagos, sus
resoluciones y el catálogo de referencias nacen vacíos. Las referencias se
recargan desde el CSV de la DEC. Y como `SOLICITUD` conservaba la liga a
pagos y evaluaciones que ya no existen, esas columnas se ponen en nulo: quien
ya había elegido sede tendrá que elegirla otra vez.

## Antes de tocar producción

Respaldar primero, sin excepción:

    pg_dump -h HOST -U suif -d suif -Fc -f respaldo_AAAA-MM-DD.dump

Y ejecutar con ON_ERROR_STOP para que se detenga al primer error en vez
de seguir a medias:

    psql -v ON_ERROR_STOP=1 -h HOST -U suif -d suif -f suif_ajustes_esquema.sql
