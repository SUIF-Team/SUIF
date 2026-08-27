# Guía para agentes — SUIF

## Contexto del proyecto

- SUIF es una aplicación Laravel para el proceso administrativo de certificaciones de la FCA-UNAM.
- Stack fijado: PHP 8.4.23, Laravel 13.x, PostgreSQL 18.4, Apache y Node.js 24.18 LTS.
- El entorno de desarrollo y despliegue se ejecuta directamente en la VM, sin Docker. Apache nativo sirve la aplicación en `http://localhost` y apunta a `public/`.
- No cambies versiones del stack ni agregues dependencias Composer, npm, bundlers o frameworks sin acuerdo explícito.
- El archivo `.env` es local y no se versiona. No expongas secretos, datos reales ni configuraciones privadas.

## Arquitectura y convenciones

- `routes/web.php` es la fuente de verdad de verbos, URIs y nombres de ruta. Genera enlaces con rutas nombradas (`route()`), no con URLs escritas manualmente.
- Mantén MVC: controladores coordinan, vistas Blade presentan y modelos o servicios encapsulan persistencia y lógica de dominio.
- Las vistas viven en `resources/views/`; los layouts y parciales existentes deben reutilizarse antes de crear estructuras o componentes duplicados.
- Los estilos compartidos están en `public/assets/css/app.css` y `public/assets/css/partials/`; los estilos exclusivos van en `public/assets/css/pages/`.
- Los scripts compartidos viven en `public/assets/js/main.js` y los de una pantalla en `public/assets/js/pages/`. La interfaz actual usa Blade, CSS y JavaScript directo, con Vue 3 por CDN en las vistas que lo requieren.
- No uses estilos inline ni bloques `<style>` en Blade. Reutiliza el fondo institucional, navbar, footer, componentes y clases de estado existentes cuando apliquen.
- Mantén nombres de dominio y textos de interfaz en español. Aplica PSR-12 en PHP y conserva cambios pequeños, acotados y revisables.
- No alteres flujos ajenos ni archivos modificados por otra persona si no forman parte de la tarea.

## Seguridad y flujos administrativos

- Usa formularios `POST`, `@csrf` y validación del servidor para operaciones de escritura.
- La autorización se resuelve por **privilegio** (`PRIVILEGIO_ROL`), nunca comparando el nombre del rol. Los gates viven en `AppServiceProvider` y el grupo `admin` de `routes/web.php` exige `auth` más el permiso de cada módulo. Toda ruta administrativa nueva se cuelga del gate que le corresponda.
- Si un flujo aún no tiene persistencia, aísla los datos de demostración para poder reemplazarlos posteriormente.
- No presentes como terminado un flujo que todavía carezca de controlador, validación, persistencia o autorización.

## Reglas de base de datos

- Los archivos dentro de `database/scripts/` son scripts entregados por el responsable de la base de datos.
- No deben modificarse, reformatearse ni reemplazarse sin autorización.
- No ejecutar scripts SQL sobre la base `suif` sin aprobación explícita.
- Las pruebas deben realizarse primero en una base temporal independiente.
- No usar `DROP DATABASE`, `CASCADE` ni eliminar información existente.
- No convertir scripts SQL en migraciones Laravel salvo solicitud explícita.
- No incluir credenciales, dumps ni datos reales en Git.

## Entorno y verificación

- La aplicación se visualiza mediante Apache nativo en `http://localhost`; su `DocumentRoot` es `/var/www/SUIF/public`. No iniciar ni usar Docker Compose para ejecutar o visualizar el proyecto.
- En una primera instalación, ejecuta directamente en la VM `composer install --no-interaction --prefer-dist` y `php artisan key:generate` cuando aún no exista `APP_KEY`.
- Ejecuta las comprobaciones de Laravel con el PHP instalado en la VM: `php artisan route:list`, `php artisan test` y, cuando corresponda, `php artisan view:cache`.
- PostgreSQL 18 se ejecuta como servicio nativo y Laravel conecta a `127.0.0.1:5432` con los valores de `.env`. Verifica los servicios con `systemctl is-active httpd` y `systemctl is-active postgresql-18` cuando se requiera acceso administrativo.
- No hay migraciones, seeders ni pruebas automatizadas de negocio versionadas actualmente. Antes de conectar datos reales, define el esquema y la estrategia de pruebas.
- Antes de entregar, revisa el diff, ejecuta las pruebas pertinentes y documenta los límites o `TODO` que queden pendientes.
