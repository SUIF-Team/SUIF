# Scripts de base de datos — SUIF

| Archivo | Para qué | Cuándo |
|---|---|---|
| `instalacion.sql` | Esquema completo (39 tablas, índices, llaves foráneas) y catálogos con los que arranca el sistema | Una vez, sobre una base **vacía** |
| `limpiar_padron.sql` | Vacía los datos capturados y conserva catálogos, convocatorias y cuentas administrativas | A mano, al reiniciar el padrón |
| `reponer_tablas.sql` | Repone las ocho tablas que la suite de pruebas borró en septiembre de 2026, sin tocar las demás | Sólo para recuperar una base así |
| `historico/` | Los doce scripts incrementales con los que se llegó al esquema actual, numerados en su orden | Consulta; no se usan para instalar |

## Instalación

Sobre una base vacía, con el rol de la aplicación por TCP para que las tablas
queden a nombre de `suif`:

    psql -v ON_ERROR_STOP=1 --single-transaction \
         -h 127.0.0.1 -U suif -d suif -f instalacion.sql

No trae `drop`, así que admite `ON_ERROR_STOP` desde la primera línea. Termina
con un `SELECT` de conteos: 39 tablas, 4 roles, 7 privilegios, 1 convocatoria
vigente, 11 regímenes, 4 formas de pago y 17 bancos. Después se crea el primer
Superusuario con `php artisan suif:crear-admin`.

No lo corras como `sudo -u postgres psql -f …`: el usuario `postgres` no puede
leer los archivos del proyecto y, aunque pudiera, las tablas quedarían a su
nombre y la aplicación respondería «permission denied».

## Mantenimiento

`limpiar_padron.sql` **borra datos y no se puede deshacer**. Va sin
`--single-transaction` porque trae su propio `BEGIN`/`COMMIT`:

    psql -v ON_ERROR_STOP=1 -h 127.0.0.1 -U suif -d suif -f limpiar_padron.sql

`reponer_tablas.sql` se corre con `--single-transaction` y después se vuelve a
pasar `historico/04_catalogos.sql`, que es idempotente. Su encabezado explica
qué puede y qué no puede devolver.

Antes de tocar producción, sin excepción: respaldo con `pg_dump`, verificación
del dump y ensayo sobre una base temporal restaurada de ese respaldo, según
`deploy/README.md`.

## Cambios futuros al esquema

Un cambio sobre una base que ya tiene datos se entrega como script
incremental e idempotente, con el siguiente número en `historico/`
(`13_descripcion.sql`). En el mismo cambio se incorpora a `instalacion.sql`,
para que una instalación nueva siga saliendo de un solo archivo y nunca vuelva
a desfasarse del esquema real.

## historico/

Son los scripts que se corrieron en el server para llegar al esquema actual.
Ya están aplicados y no se vuelven a usar para instalar. `historico/README.md`
conserva las notas originales de cada uno, con sus nombres anteriores:

| Ahora | Antes |
|---|---|
| `01_esquema_base.sql` | `suif.sql` |
| `02_evaluacion_grupo.sql` | `suif_evaluacion_grupo.sql` |
| `03_ajustes_esquema.sql` | `suif_ajustes_esquema.sql` |
| `04_catalogos.sql` | `suif_catalogos.sql` |
| `05_grupos_multiples.sql` | `suif_grupos_multiples.sql` |
| `06_referencias_bancarias.sql` | `suif_referencias_bancarias.sql` |
| `07_rfc_persona.sql` | `suif_rfc_persona.sql` |
| `08_fecha_emision_referencia.sql` | `suif_referencia_fecha_emision.sql` |
| `09_roles_administrativos.sql` | `suif_roles_administrativos.sql` |
| `10_comprobante_fiscal.sql` | `suif_comprobante_fiscal.sql` |
| `11_convocatorias.sql` | `suif_convocatorias.sql` |
| `12_formato_pago.sql` | `suif_formato_pago.sql` |

Se retiraron, porque quedaron obsoletos o desfasados, y siguen en el historial
de git: `suif_instalacion_completa.sql` y `suif_esquema_final.sql` (no traían
los scripts 10 a 12; los sustituye `instalacion.sql`), `suif_lleno.sql` (datos
de prueba del esquema anterior, con roles viejos y claves en texto plano),
`suif_antiguo.sql` (esquema anterior al 11/08/2026) y
`suif_revierte_roles_administrativos.sql` (deshacía el renombre del rol 2).
