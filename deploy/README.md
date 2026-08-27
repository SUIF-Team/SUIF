# Operación en el servidor (VM AlmaLinux)

Este directorio versiona lo que se instala **fuera** de Laravel: el respaldo
automático de la base y el checklist de despliegue de las correcciones de
seguridad. Todo lo que sigue se ejecuta en la VM, no en el equipo local.

## Respaldo diario de PostgreSQL

`respaldos/` contiene tres archivos:

| Archivo | Qué es |
|---|---|
| `suif-respaldo.sh` | `pg_dump -Fc` de la base `suif` a `/var/respaldos/suif`, verifica el dump con `pg_restore --list` y conserva 14 días. |
| `suif-respaldo.service` | Unidad `oneshot` que ejecuta el script como usuario `postgres` (peer auth por socket: sin contraseñas ni `.pgpass`). |
| `suif-respaldo.timer` | Lo dispara a diario a las 02:30 (±15 min). `Persistent=true` recupera la corrida si la VM estaba apagada a esa hora. |

### Instalación (una sola vez)

```bash
sudo install -m 755 deploy/respaldos/suif-respaldo.sh /usr/local/bin/suif-respaldo.sh
sudo cp deploy/respaldos/suif-respaldo.service deploy/respaldos/suif-respaldo.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now suif-respaldo.timer
```

Primera corrida supervisada:

```bash
sudo systemctl start suif-respaldo.service
journalctl -u suif-respaldo --no-pager -n 20
ls -lh /var/respaldos/suif/
systemctl list-timers | grep suif
```

Si el script falla, la unidad queda en estado `failed` y se ve en
`systemctl --failed`; revisar el journal antes de reintentar.

### Restauración (simulacro mensual)

Siempre sobre una base temporal, nunca sobre `suif` (regla de AGENTS.md):

```bash
sudo -u postgres createdb suif_verifica
sudo -u postgres /usr/pgsql-18/bin/pg_restore --no-owner -d suif_verifica /var/respaldos/suif/suif_FECHA.dump
sudo -u postgres psql -d suif_verifica -c "SELECT count(*) FROM persona;" -c "SELECT count(*) FROM solicitud;"
sudo -u postgres dropdb suif_verifica
```

### Notas

- Si el responsable de la base exige respaldar con el rol `suif` por TCP en
  lugar del usuario `postgres`, crear `/var/lib/pgsql/.pgpass` con
  `127.0.0.1:5432:suif:suif:CONTRASEÑA` (dueño `postgres`, `chmod 600`) y
  agregar `-h 127.0.0.1 -U suif` al `pg_dump` del script.
- **Pendiente explícito**: la retención local de 14 días no protege contra la
  pérdida de la VM. Los dumps contienen datos personales; la copia periódica a
  otro host o almacenamiento institucional queda a cargo del responsable de
  infraestructura.

## Checklist de despliegue de las correcciones de seguridad

En orden, después del `git pull` que traiga esta serie de cambios:

1. `cd /var/www/SUIF && php artisan optimize:clear` (obligatorio: el caché de
   rutas viejo conservaría las rutas retiradas y no aplicaría los `throttle`).
2. Editar `.env`:
   - `MAIL_MAILER=smtp` con host, puerto y credenciales del relevo
     institucional real (eliminar el host de mailtrap y la clave legada
     `MAIL_DRIVER`). Con `log`, las claves de acceso quedan en texto plano en
     el log del servidor y nadie recibe su correo.
   - `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`, `LOG_DAILY_DAYS=14` (eliminar
     `APP_LOG` y `APP_LOG_LEVEL`, que Laravel 13 ignora).
3. Recachear en orden: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
4. Probar el correo real con una cuenta propia y confirmar que llega y que el
   cuerpo no aparece en `storage/logs/`:
   ```bash
   php artisan tinker --execute="Mail::to('cuenta_de_prueba@ejemplo.mx')->send(new App\Mail\ClaveAcceso('AAAA-BBBB-CCCC'));"
   ```
5. **Purgar el log actual** — contiene claves de acceso en claro:
   `truncate -s 0 storage/logs/laravel.log`.
6. **Purgar las sesiones** — pueden contener claves en claro y cierran las
   sesiones activas (aceptable con pocos usuarios):
   `find storage/framework/sessions -name 'sess_*' -delete` (o borrar todo
   menos `.gitkeep`/`.gitignore`).
7. **Rotar la clave de cada persona ya registrada** (sus claves estuvieron en
   el log). Con `php artisan tinker`, por cada CURP:
   ```php
   $p = App\Models\Persona::where('pers_curp', 'CURP...')->first();
   $clave = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
   $p->usuario->update(['usua_clave_acceso' => Hash::make($clave)]);
   $correo = DB::table('comunicacion')
       ->join('tipo_comunicacion', 'tico_id_tipo_comunicacion', '=', 'comu_id_tipo_comunicacion')
       ->where('comu_id_persona', $p->pers_id_persona)
       ->where('tico_tipo_comunicacion', 'Correo principal')
       ->value('comu_descripcion');
   Mail::to($correo)->send(new App\Mail\ClaveAcceso($clave));
   unset($clave);
   ```
   Si algún envío falla, entregar la clave por el canal institucional que
   defina la coordinación — nunca por el log ni por un archivo compartido.
8. Instalar el respaldo automático (sección anterior) y verificar el primer
   dump.
9. Verificaciones finales:
   - `php artisan route:list | grep -Ei 'reiniciar|demo'` no devuelve nada.
   - `curl -i http://localhost/persona/resultados/demo/x` responde 404.
   - Seis intentos seguidos de login fallido devuelven la página 429.
   - `systemctl is-active httpd postgresql-18` responde `active` dos veces.
10. Agendar el simulacro mensual de restauración y resolver la copia de
    respaldos fuera del host.
