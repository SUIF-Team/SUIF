# Scripts de base de datos — SUIF

Orden de ejecución en una instalación nueva:

1. `suif.sql`               — esquema base (35 tablas)
2. `suif_ajustes_esquema.sql` — correcciones de tipos y restricciones
3. `suif_catalogos.sql`     — catálogos y convocatoria
4. `suif_grupos_multiples.sql` — varias aplicaciones de examen por sede
5. `suif_referencias_bancarias.sql` — catálogo de referencias bancarias

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

## Los otros cuatro se pueden repetir

`suif_ajustes_esquema.sql`, `suif_catalogos.sql`,
`suif_grupos_multiples.sql` y `suif_referencias_bancarias.sql` son
idempotentes: volver a ejecutarlos no duplica ni destruye nada. Por eso la
regla al desplegar es correrlos SIEMPRE, sin preguntarse si ya se
corrieron.

## Antes de tocar producción

Respaldar primero, sin excepción:

    pg_dump -h HOST -U suif -d suif -Fc -f respaldo_AAAA-MM-DD.dump

Y ejecutar con ON_ERROR_STOP para que se detenga al primer error en vez
de seguir a medias:

    psql -v ON_ERROR_STOP=1 -h HOST -U suif -d suif -f suif_ajustes_esquema.sql
