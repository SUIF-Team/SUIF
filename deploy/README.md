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

## Respaldo automático del `.env`

El `.env` ya no está en git, así que la única copia es la del servidor.
`respaldos/` trae cuatro archivos para respaldarlo solo:

| Archivo | Qué es |
|---|---|
| `suif-env-respaldo.sh` | Copia `/var/www/SUIF/.env` a `/root/respaldos/env/env-AAAAMMDD-HHMMSS` (carpeta 700, archivos 600). Si no cambió desde la última copia no crea otra; conserva las 20 más recientes. Si el `.env` falta o está vacío, falla sin tocar las copias. |
| `suif-env-respaldo.service` | Unidad `oneshot` que corre el script como root: el `.env` es `root:apache 640` y `postgres` no puede leerlo, por eso no se reutiliza el respaldo de la base. |
| `suif-env-respaldo.path` | Vigila el `.env` y dispara el respaldo **cada vez que cambia** (una edición, un `key:generate`). |
| `suif-env-respaldo.timer` | Red de seguridad: una corrida diaria a las 02:45. Como el script no duplica copias iguales, no cuesta nada. |

### Instalación (una sola vez)

Como root, en `/var/www/SUIF`:

```bash
install -m 755 deploy/respaldos/suif-env-respaldo.sh /usr/local/bin/suif-env-respaldo.sh
cp deploy/respaldos/suif-env-respaldo.service deploy/respaldos/suif-env-respaldo.path deploy/respaldos/suif-env-respaldo.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now suif-env-respaldo.path suif-env-respaldo.timer
```

Primera corrida y prueba del disparo por cambio. `printf '' >> .env` abre y
cierra el archivo sin alterarlo: el `.path` debe dispararse y el script
responder «Sin cambios»:

```bash
systemctl start suif-env-respaldo.service && ls -l /root/respaldos/env/
printf '' >> /var/www/SUIF/.env && sleep 2 && journalctl -u suif-env-respaldo --no-pager -n 5
systemctl list-timers | grep suif; systemctl is-active suif-env-respaldo.path
```

### Restauración

```bash
ls -l /root/respaldos/env/
install -m 640 -o root -g apache /root/respaldos/env/env-AAAAMMDD-HHMMSS /var/www/SUIF/.env
cd /var/www/SUIF && php artisan config:clear && php artisan config:cache
```

Las copias viven en el mismo servidor: salvan de un borrado o una mala
edición, no de perder la máquina. Si se perdieran todas, el `.env` se
reconstruye desde `.env.example`, con una llave nueva (`key:generate`, cierra
las sesiones) y una contraseña nueva para el rol `suif` (con
`sudo -u postgres`, como en «Sacar `.env` de git y rotar las credenciales»).

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

## Paso 3 de la depuración: configuración y almacenamiento

La rama `refactor/configuracion-laravel13` hace dos cosas que exigen trabajo en
el servidor:

- Deja en `config/` sólo lo que difiere del framework y usa los nombres de
  variables de Laravel 13 (`CACHE_STORE`, `FILESYSTEM_DISK`, `MAIL_MAILER`,
  `QUEUE_CONNECTION`).
- Lleva todos los archivos subidos a `storage/app/private`: el disco `local`
  (documentos del pre-registro) pasa de `storage/app` a `storage/app/private`
  y el de `referencias` a `storage/app/private/referencias`. Las rutas
  guardadas en la base son relativas a cada disco, así que **no se tocan**;
  sólo se mueven las carpetas.

Se hace como root, en `/var/www/SUIF`, en este orden. **Si cualquier paso
imprime ALTO o no cuadra, aplica la reversa del final**; nada de lo anterior
a ella es irreversible. Las fotos de configuración `/root/cfg-*.txt` se
guardan sin las líneas de contraseñas, llaves ni URLs, y se borran al
terminar.

En esta rama `.env` todavía está versionado (lo resuelve la sección «Sacar
`.env` de git y rotar las credenciales»): después del paso 5, `git status` lo
mostrará como modificado. Es esperado y no estorba al cambio de rama ni a la
reversa.

0. **Chequeo previo**, con el sitio arriba. Los dos destinos no deben tener
   archivos propios (sólo un `.gitignore`), y `git status` sólo debe mostrar
   los `.env.*` y archivos sueltos ya conocidos:
   ```bash
   git status --short
   find storage/app/private/preregistro storage/app/private/referencias -mindepth 1 ! -name .gitignore 2>/dev/null | head
   ```
   Si el `find` imprime algo, no sigas: hay que averiguar qué son esos
   archivos antes de mover nada.
1. **Línea base**, todavía en la rama anterior:
   ```bash
   php artisan optimize:clear
   for k in cache queue filesystems mail database logging session; do COLUMNS=150 php artisan config:show $k | grep -viE 'password|secret|token|key|url' > /root/cfg-antes-$k.txt; done
   php artisan tinker --execute='$d=Storage::disk("local");$r=Storage::disk("referencias");$docs=DB::table("documento")->whereNotNull("docu_path")->pluck("docu_path");$refs=DB::table("referencia_bancaria")->where("reba_path","<>","")->pluck("reba_path");echo "docs ".$docs->count()." faltan ".$docs->reject(fn($p)=>$d->exists($p))->count()."; refs ".$refs->count()." faltan ".$refs->reject(fn($p)=>$r->exists($p))->count().PHP_EOL;'
   find storage/app -type f ! -name '.git*' | wc -l
   ```
   Anota las dos salidas: se comparan en el paso 7. `COLUMNS` fija el ancho
   de la salida: sin él depende del tamaño de la ventana SSH y cualquier
   cambio de tamaño entre los pasos 1 y 6 haría diferir todas las líneas.
2. **Respaldo** de los archivos y del `.env`, con nombres fijos que usa la
   reversa:
   ```bash
   tar czf /root/suif-storage-antes-paso3.tgz storage/app && cp .env /root/env-antes-paso3.bak
   ```
3. **Mantenimiento y rama nueva**:
   ```bash
   php artisan down && git fetch origin && git switch refactor/configuracion-laravel13
   ```
   Si el `git switch` falla, no cambió nada: `php artisan up` y revisa el
   mensaje antes de reintentar.
4. **Mover las carpetas.** El bloque no mueve nada si algún destino ya tiene
   archivos, y mueve cada carpeta sólo si existe:
   ```bash
   if [ -z "$(find storage/app/private/preregistro storage/app/private/referencias -mindepth 1 2>/dev/null | head -1)" ]; then
     rmdir storage/app/private/preregistro storage/app/private/referencias 2>/dev/null
     for d in preregistro referencias; do [ -d storage/app/$d ] && mv -v storage/app/$d storage/app/private/; done
     chown -R apache:apache storage/app/private && ls -Zd storage/app/private/*
   else
     echo "ALTO: un destino ya tiene archivos; no se movió nada. Aplica la reversa."
   fi
   ```
   Las carpetas deben tener el mismo dueño y el mismo contexto SELinux que
   `comprobantes`. `mv` dentro del mismo sistema de archivos conserva el
   contexto; si alguna quedara distinta, aplica la reversa.
5. **Actualizar el `.env`.** Primero mira qué hay (de `REDIS_*` sólo los
   nombres, para no mostrar la contraseña):
   ```bash
   grep -nE '^(APP_LOG|CACHE_|QUEUE_|BROADCAST_|FILESYSTEM_|MAIL_(MAILER|DRIVER|ENCRYPTION)|LOG_)' .env; grep -oE '^REDIS_[A-Z_]+' .env
   ```
   Luego renombra y borra. Manda siempre lo que leía la configuración
   anterior: para caché y disco sólo leía la clave vieja, así que la nueva se
   descarta aunque exista; para correo y colas leía primero la nueva y usaba
   la vieja de respaldo.
   ```bash
   sed -i -e '$a\' .env
   for par in CACHE_DRIVER:CACHE_STORE FILESYSTEM_DRIVER:FILESYSTEM_DISK; do v=${par%%:*}; n=${par##*:}; sed -i -e "/^$n=/d" -e "s/^$v=/$n=/" .env; done
   for par in MAIL_DRIVER:MAIL_MAILER QUEUE_DRIVER:QUEUE_CONNECTION; do v=${par%%:*}; n=${par##*:}; if grep -q "^$n=" .env; then sed -i "/^$v=/d" .env; else sed -i "s/^$v=/$n=/" .env; fi; done
   sed -i '/^\(APP_LOG\|APP_LOG_LEVEL\|BROADCAST_DRIVER\|MAIL_ENCRYPTION\|REDIS_HOST\|REDIS_PASSWORD\|REDIS_PORT\|LOG_CHANNEL\|LOG_LEVEL\|LOG_DAILY_DAYS\)=/d' .env
   printf 'LOG_CHANNEL=daily\nLOG_LEVEL=warning\nLOG_DAILY_DAYS=14\n' >> .env
   ```
   El log diario ya lo pedía el checklist de seguridad. Desde aquí Laravel
   escribe en `storage/logs/laravel-AAAA-MM-DD.log`; el `laravel.log` viejo se
   queda como está.
6. **Comparar la configuración efectiva**, archivo por archivo:
   ```bash
   php artisan optimize:clear
   for k in cache queue filesystems mail database logging session; do COLUMNS=150 php artisan config:show $k | grep -viE 'password|secret|token|key|url' > /root/cfg-despues-$k.txt; echo "===== $k"; diff -u /root/cfg-antes-$k.txt /root/cfg-despues-$k.txt; done
   ```
   Diferencias esperadas, y ninguna otra:
   - `filesystems`: cambian `disks.local.root` y `disks.referencias.root`;
     desaparecen `cloud` y los discos `documentos`, `facturas` y
     `certificados`; `public` y `s3` ganan claves del framework.
   - `cache`: los stores salen del framework (desaparece `apc`, aparecen
     `lock_path` y otros); `default` y `prefix` no cambian.
   - `queue`: conexiones y `failed` del framework; `default` no cambia.
   - `mail`: desaparece `mailers.smtp.encryption` y aparecen los mailers del
     framework; `default`, el resto de `smtp` y `from` no cambian.
   - `database`: `mysql`, `mariadb`, `sqlsrv` y `redis` del framework, y
     `migrations` pasa a arreglo; `default`, `pgsql` y `sqlite` no cambian.
   - `logging`: `default` pasa a `daily` y los niveles a `warning`.
   - `session`: nada.

   **Si cambia `default` en cache, queue, mail, database o filesystems, o
   cualquier dato de `pgsql`, de `smtp` (salvo `encryption`), de `from` o de
   `session`, aplica la reversa.**
7. **Repetir los conteos del paso 1** (el `tinker` y el `find`). Deben dar
   los mismos totales y los mismos faltantes; si no, reversa.
8. **Probar y reabrir.** Las pruebas no dependen del modo mantenimiento, así
   que corren con el sitio abajo. La cadena va unida con `&&`: si las pruebas
   o un caché fallan, se detiene antes de `up` y el sitio sigue en
   mantenimiento. El `chown` devuelve a Apache lo que root haya creado en
   `storage` (vistas compiladas, log del día):
   ```bash
   php artisan config:clear && php artisan test && php artisan config:cache && php artisan route:cache && php artisan view:cache && chown -R apache:apache storage/logs storage/framework && php artisan up && rm -f /root/cfg-*.txt && echo LISTO
   ```
   Si no imprime `LISTO`, reversa.
9. **Humo en el navegador:** ver un documento del pre-registro desde la
   persona y desde el administrador, descargar un formato de referencia, ver
   un comprobante de pago y subir un documento de prueba.

**Reversa**, desde cualquier punto después del paso 3 y en este orden (las
carpetas vuelven antes de cambiar de rama y sólo si no pisan nada):
```bash
php artisan down
for d in preregistro referencias; do [ -d storage/app/private/$d ] && [ ! -e storage/app/$d ] && mv -v storage/app/private/$d storage/app/; done
git switch refactor/depuracion-codigo-muerto && cp /root/env-antes-paso3.bak .env && php artisan optimize:clear && chown -R apache:apache storage/logs storage/framework && php artisan up
rm -f /root/cfg-*.txt
```

`database/scripts/README.md` y `suif_limpia_datos.sql` todavía nombran las
rutas viejas (`storage/app/preregistro`, `storage/app/referencias`); son del
responsable de la base y hay que avisarle para que los actualice.

## Sacar `.env` de git y rotar las credenciales

`.env` estuvo versionado desde julio de 2026 y, desde `ffc4ff1` (18 ago), con
la `APP_KEY` y la contraseña de la base de producción, en un repositorio
público. La rama `fix/env-fuera-de-git` deja de rastrearlo e ignora cualquier
copia (`.env.*`, salvo `.env.example`). El historial no se reescribe: después
de rotar, los valores viejos ya no abren nada.

Rotar la `APP_KEY` cierra todas las sesiones: cada quien vuelve a iniciar
sesión. SUIF no cifra datos en la base ni firma URLs, así que no se pierde nada
más. El respaldo diario no usa contraseña (peer auth como `postgres`), así que
rotar la de la base no lo afecta.

Como root, en `/var/www/SUIF`, en este orden. **Si algo no cuadra, detente y
aplica la reversa.**

0. **Diagnóstico**, con el sitio arriba:
   ```bash
   git branch --show-current && git status --short
   ```
   Debe decir `refactor/configuracion-laravel13`, con ` M .env` más los
   archivos sueltos ya conocidos.
   ```bash
   ss -ltnp | grep -E ':5432\b'
   ```
   Si PostgreSQL escucha sólo en `127.0.0.1`/`::1`, la contraseña filtrada no
   servía desde fuera del servidor. Si escucha en `0.0.0.0` o en una IP
   pública, anótalo: hay que revisar `pg_hba.conf` y el firewall aparte.
   ```bash
   sudo -u postgres psql -Atc 'show shared_preload_libraries'; test -e /var/lib/pgsql/.pgpass && echo "HAY .pgpass"; ls -la .env*
   ```
   Si la primera línea menciona `pgaudit` o `pg_stat_statements`, detente:
   registrarían el `ALTER ROLE` con la contraseña y hay que apagarlos antes.
   Si aparece `HAY .pgpass`, el respaldo usa la contraseña del rol y habrá que
   actualizarla también. El `ls` muestra qué copias del `.env` hay para el
   paso 6.
1. **Respaldo** del `.env`, sólo legible por root. No lo repitas después del
   paso 3: pisaría el respaldo con el `.env` ya rotado.
   ```bash
   install -m 600 .env /root/env-antes-rotacion.bak
   ```
2. **Mantenimiento y rama nueva.** El `git rm --cached` va antes del cambio de
   rama: sin él, git se niega a cambiar porque `.env` está modificado; con él,
   el archivo se queda en disco y sólo deja de estar rastreado.
   ```bash
   php artisan down && git rm -q --cached .env && git fetch origin && git switch fix/env-fuera-de-git && ls -l .env && git status --short
   ```
   Debe listar `.env` y `git status` ya no debe mencionarlo, ni a los
   `.env.*`. **Sólo si el `git switch` falla**, deshaz y reabre:
   `git restore --staged .env && php artisan up`. Si salió bien, no lo corras
   (respondería «did not match» sin hacer nada) y sigue al paso 3.
3. **Rotar la contraseña de la base.** La contraseña se genera aquí y viaja a
   `psql` y a `sed` por la entrada estándar, así que no queda en el historial
   de bash ni en la lista de procesos. La sesión apaga el registro de
   sentencias —también el de sentencias fallidas— para que el `ALTER ROLE` no
   quede en el log de PostgreSQL. La contraseña sale de `/dev/urandom` con
   `od` (el servidor no trae `openssl`). El `tr` quita comillas y retornos de
   carro que pudiera tener el `.env`:
   ```bash
   PW=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n') && DBUSER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"\r') && grep -q '^DB_PASSWORD=' .env && echo "rol: $DBUSER, largo: ${#PW}"
   ```
   Debe imprimir `rol: suif, largo: 48`. Si no imprime nada, falta
   `DB_PASSWORD` en el `.env`: detente.
   ```bash
   printf "SET log_statement = 'none'; SET log_min_duration_statement = -1; SET log_min_error_statement = panic; ALTER ROLE \"%s\" PASSWORD '%s';\n" "$DBUSER" "$PW" | sudo -u postgres psql -q -v ON_ERROR_STOP=1 && printf 's|^DB_PASSWORD=.*|DB_PASSWORD=%s|\n' "$PW" | sed -i -f /dev/stdin .env && unset PW && echo ROTADA
   ```
   Debe imprimir `ROTADA`. Desde aquí y hasta el paso 5 la app no conecta con
   la configuración en caché; el sitio sigue en mantenimiento.
4. **Rotar la `APP_KEY`.** `key:generate` termina «bien» aunque no logre
   reemplazar la llave (pasa si el valor está entre comillas), así que al
   final se compara con el respaldo:
   ```bash
   php artisan config:clear && php artisan key:generate --force && ! grep -qxF "$(grep '^APP_KEY=' /root/env-antes-rotacion.bak)" .env && echo LLAVE-ROTADA
   ```
   Debe imprimir `LLAVE-ROTADA`; si no, la llave sigue siendo la filtrada.
5. **Comprobar, probar y reabrir.** El `tinker` confirma que la nueva
   contraseña conecta; la cadena se detiene antes de `up` si algo falla. El
   `.env` queda de root y sólo legible por Apache:
   ```bash
   php artisan config:clear && php artisan tinker --execute='echo "conexion ".DB::scalar("select 1").PHP_EOL;' && php artisan test && php artisan config:cache && php artisan route:cache && php artisan view:cache && chown root:apache .env && chmod 640 .env && chown -R apache:apache storage/logs storage/framework && php artisan up && echo LISTO
   ```
   Debe imprimir `conexion 1` y, al final, `LISTO`.
6. **Sacar del sitio las copias viejas del `.env`.** Tienen las credenciales
   ya muertas; se guardan fuera del directorio web por si hicieran falta. Mueve
   los nombres que el `ls` del paso 0 haya mostrado, además de `.env` y
   `.env.example`, que se quedan:
   ```bash
   install -d -m 700 /root/env-viejos && for f in .env.save .env.server .env.servidor; do [ -f "$f" ] && mv -vn "$f" /root/env-viejos/; done; ls -la .env*
   ```
7. **Humo en el navegador:** iniciar sesión como persona y como
   administrador (las sesiones anteriores ya no sirven), abrir el tablero y
   ver un documento.

**Reversa de credenciales**, si el paso 3, 4 o 5 falló. Devuelve a la base la
contraseña anterior —la filtrada: es sólo para salir del paso, y hay que
repetir la rotación en cuanto se entienda la falla— y restaura el `.env`:
```bash
OLD=$(grep -E '^DB_PASSWORD=' /root/env-antes-rotacion.bak | cut -d= -f2- | tr -d '"\r') && DBUSER=$(grep -E '^DB_USERNAME=' /root/env-antes-rotacion.bak | cut -d= -f2- | tr -d '"\r')
```
```bash
printf "SET log_statement = 'none'; SET log_min_duration_statement = -1; SET log_min_error_statement = panic; ALTER ROLE \"%s\" PASSWORD '%s';\n" "$DBUSER" "$OLD" | sudo -u postgres psql -q -v ON_ERROR_STOP=1 && unset OLD && cp /root/env-antes-rotacion.bak .env && php artisan optimize:clear && php artisan up
```
Volver además a la rama anterior sólo hace falta si el problema es el código
de la rama. Allí `.env` sigue rastreado y git lo pisaría sin avisar, así que se
aparta el archivo, se cambia de rama y se restaura:
```bash
mv .env /root/env-tmp && git switch refactor/configuracion-laravel13 && cp /root/env-antes-rotacion.bak .env && rm -f /root/env-tmp && php artisan optimize:clear
```

### Aviso para quien tenga un clon del repositorio

Al traer un commit que deja de rastrear un archivo, git **borra** ese archivo
del disco si no tenía cambios locales. Antes de hacer `pull` o `switch` a
`fix/env-fuera-de-git` —o a `main` cuando se fusione—:
```bash
cp .env ../suif-env-respaldo
```
```bash
git pull
```
```bash
[ -f .env ] || cp ../suif-env-respaldo .env
```
Si tu `.env` tiene cambios, `git pull` se niega («Please commit your changes
or stash them»). No uses `stash`: al recuperarlo sale un conflicto. Deja de
rastrearlo y vuelve a intentar:
```bash
git rm -q --cached .env && git pull
```
Ese `.env` era el de producción, y sus credenciales ya no sirven. Para
desarrollo, parte de `.env.example`, apunta a una base local y genera una
llave propia con `php artisan key:generate`.

**Cuidado con las ramas y commits anteriores a este cambio** (`main` hasta que
se fusione, `git checkout` a un commit viejo, `git bisect`): todavía rastrean
`.env`, y como ahora está ignorado, git lo **sobrescribe sin avisar** con la
versión del repositorio. Aparta el archivo antes de cambiar y devuélvelo
después, o usa `git switch --no-overwrite-ignore`, que en ese caso se niega
en lugar de pisarlo.

Al fusionar a `main` una rama que haya modificado `.env` después de este
cambio sale un conflicto modify/delete: se resuelve con `git rm .env`.
