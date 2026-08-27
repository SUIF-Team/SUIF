#!/usr/bin/env bash
# Respaldo diario de la base suif. Pensado para correr como usuario
# postgres vía systemd (peer auth por el socket local: sin contraseñas).
set -euo pipefail

PGDUMP=/usr/pgsql-18/bin/pg_dump
PGRESTORE=/usr/pgsql-18/bin/pg_restore
BASE=suif
DESTINO=/var/respaldos/suif
DIAS_RETENCION=14

FECHA=$(date +%Y-%m-%d_%H%M)
ARCHIVO="$DESTINO/suif_${FECHA}.dump"

mkdir -p "$DESTINO"
chmod 700 "$DESTINO"

# -Fc: formato custom, comprimido y restaurable por partes con pg_restore.
"$PGDUMP" -Fc --no-password -d "$BASE" -f "$ARCHIVO.parcial"

# Verificación mínima: el archivo es un dump legible y completo.
"$PGRESTORE" --list "$ARCHIVO.parcial" > /dev/null

mv "$ARCHIVO.parcial" "$ARCHIVO"

# Retención local: se conservan los últimos DIAS_RETENCION días.
find "$DESTINO" -maxdepth 1 -name 'suif_*.dump' -mtime +"$DIAS_RETENCION" -delete

echo "Respaldo correcto: $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1))"
