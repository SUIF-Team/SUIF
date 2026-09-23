#!/usr/bin/env bash
# Respaldo del .env de SUIF. Corre como root vía systemd: lo dispara
# suif-env-respaldo.path cada vez que el archivo cambia y, como red de
# seguridad, suif-env-respaldo.timer una vez al día.
set -euo pipefail

ORIGEN=/var/www/SUIF/.env
DESTINO=/root/respaldos/env
CONSERVAR=20

# Las copias llevan la contraseña de la base y la APP_KEY: sólo root.
umask 077
install -d -m 700 "$DESTINO"

# Un .env borrado o vacío (p. ej. tras un pull que lo dejó de rastrear) no se
# respalda: la unidad queda en failed y los respaldos anteriores se conservan.
if [ ! -s "$ORIGEN" ]; then
    echo "No existe $ORIGEN o está vacío; no se respalda nada" >&2
    exit 1
fi

# El nombre lleva la fecha, así que el orden alfabético es el cronológico.
ULTIMO=$(find "$DESTINO" -maxdepth 1 -name 'env-*' -type f | sort | tail -n 1)

if [ -n "$ULTIMO" ] && cmp -s "$ORIGEN" "$ULTIMO"; then
    echo "Sin cambios desde $ULTIMO"
    exit 0
fi

ARCHIVO="$DESTINO/env-$(date +%Y%m%d-%H%M%S)"
install -m 600 "$ORIGEN" "$ARCHIVO"

# Retención: se conservan los CONSERVAR más recientes.
find "$DESTINO" -maxdepth 1 -name 'env-*' -type f | sort | head -n -"$CONSERVAR" | xargs -r rm -f --

echo "Respaldo del .env: $ARCHIVO"
