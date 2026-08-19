# Scripts de base de datos — SUIF

Orden de ejecución en una instalación nueva:

1. `suif.sql`               — esquema base (35 tablas)
2. `suif_ajustes_esquema.sql` — correcciones de tipos y restricciones
3. `suif_catalogos.sql`     — catálogos y convocatoria

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

## Los otros dos se pueden repetir

`suif_ajustes_esquema.sql` y `suif_catalogos.sql` son idempotentes:
volver a ejecutarlos no duplica ni destruye nada. Por eso la regla al
desplegar es correrlos SIEMPRE, sin preguntarse si ya se corrieron.

## Antes de tocar producción

Respaldar primero, sin excepción:

    pg_dump -h HOST -U suif -d suif -Fc -f respaldo_AAAA-MM-DD.dump

Y ejecutar con ON_ERROR_STOP para que se detenga al primer error en vez
de seguir a medias:

    psql -v ON_ERROR_STOP=1 -h HOST -U suif -d suif -f suif_ajustes_esquema.sql
