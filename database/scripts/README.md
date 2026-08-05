# Scripts de base de datos — SUIF

Orden de ejecución en una instalación nueva:

1. `suif.sql`               — esquema base (34 tablas)
2. `suif_ajustes_esquema.sql` — correcciones de tipos y restricciones
3. `suif_catalogos.sql`     — catálogos y convocatoria

## suif.sql BORRA TODA LA BASE

Empieza con `drop table` de las 34 tablas. Solo se ejecuta sobre una base
vacía, en una instalación desde cero. NUNCA sobre una base con datos.

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