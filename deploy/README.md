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
# El destino se crea como root porque el usuario postgres no puede
# crear directorios bajo /var; después el dueño es postgres.
sudo install -d -o postgres -g postgres -m 700 /var/respaldos/suif
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

## Dependencias nuevas de Composer

Cuando un `git pull` trae un `composer.json` con un paquete que la VM todavía
no tiene —hoy `phpoffice/phpspreadsheet`, que genera los reportes en Excel—
hay que instalarlo antes de tocar los cachés.

**Todo esto se corre dentro de `/var/www/SUIF`.** Ejecutarlo desde `/root` es
el error más fácil de cometer: Composer busca el `composer.json` del
directorio actual, no encuentra ninguno y responde *«Composer could not find a
composer.json file in /root»*. El proyecto no está roto; sólo estabas parado en
otro lado.

```bash
cd /var/www/SUIF
```

PhpSpreadsheet exige extensiones que dompdf no pedía. Conviene comprobarlas
antes, porque si falta alguna el `composer update` aborta a la mitad y deja el
`composer.json` modificado con `vendor/` a medias:

```bash
php -m | grep -ixE 'gd|zip|xml|xmlreader|xmlwriter|simplexml|mbstring|iconv|fileinfo|ctype'
```

Deben aparecer las diez. En AlmaLinux las que suelen faltar se instalan con
`sudo dnf install php-gd php-xml php-pecl-zip` y requieren reiniciar `httpd`.

### Por qué no como root

Composer avisa *«Do not run Composer as root/super user!»* y en este caso el
aviso importa: lo que escriba quedaría con dueño `root` dentro de `vendor/`, y
el usuario con el que corre Apache dejaría de poder leerlo. Primero hay que ver
de quién son los archivos:

```bash
stat -c '%U:%G' /var/www/SUIF /var/www/SUIF/vendor
```

Con ese dueño —normalmente `apache`— la instalación va así:

```bash
sudo -u apache composer update phpoffice/phpspreadsheet
```

Si `apache` tiene la shell en `nologin` y ese comando no arranca, la salida es
correr como root y devolver el dueño inmediatamente después, sin dejar el
estado a medias:

```bash
composer update phpoffice/phpspreadsheet && chown -R apache:apache vendor composer.lock
```

Se actualiza **sólo ese paquete**, sin `--with-dependencies`: sus dependencias
(`markbaker/complex`, `markbaker/matrix`, `maennchen/zipstream-php`,
`composer/pcre`, `psr/simple-cache`) son todas nuevas y entran igual, mientras
que la bandera además movería versiones de paquetes que hoy funcionan. Sólo si
Composer se queja de un conflicto conviene reintentar con `-W`.

Después de instalar, el orden de cachés de la sección siguiente sigue siendo
obligatorio: `php artisan optimize:clear` antes de recachear. Las rutas nuevas
de `/admin/reportes` no existen para la aplicación mientras el caché viejo esté
puesto, y sus middleware `can:` tampoco se aplicarían.

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
