# SUIF — Sistema Integral de Certificaciones

SUIF es una aplicación web de la Facultad de Contaduría y Administración de la UNAM para el seguimiento administrativo de un proceso de certificación. El proyecto se está reconstruyendo de forma gradual sobre un stack moderno y fijado por versión exacta (ver tabla abajo); por ello se priorizan la compatibilidad entre el equipo, los cambios acotados y la trazabilidad.

> SUIF no aplica ni administra exámenes. Su alcance es el pre-registro, documentación, referencias y pagos, selección de sede, consulta de resultados, certificados y los paneles de persona y administración.

## Estado actual

- La landing pública (`/`) está maquetada y muestra información de la certificación. Parte de su contenido (periodo, nombre de certificación, fechas y enlaces de descarga) continúa como texto de ejemplo.
- La pantalla de acceso en `resources/views/auth/login.blade.php` autentica contra la persona y su clave persistida, con validación del servidor, CSRF y cierre de sesión.
- El dashboard de persona (`/persona/dashboard`) consulta en la base el pre-registro, la solicitud y la documentación; los módulos posteriores siguen incompletos.
- El dashboard administrativo y la bandeja general de personas consultan PostgreSQL. Los indicadores sin persistencia se identifican como tales y no muestran conteos ficticios.
- Las vistas, rutas y controladores de los módulos de persona y administración están en diferentes etapas de preparación. Varios controladores no tienen acciones implementadas y sus vistas contienen `TODO`; no se deben considerar funcionalidades terminadas.
- Las rutas administrativas conservan temporalmente el acceso abierto de desarrollo. La autenticación y autorización por rol de administrador siguen pendientes.
- No hay migraciones ni seeders de negocio versionados. Las pruebas automatizadas crean un esquema temporal independiente para no modificar la base real.

## Stack y compatibilidad

| Capa | Tecnología / versión | Uso |
|---|---|---|
| Backend | PHP **8.4.23** | Versión exacta de la imagen Docker y plataforma de Composer. |
| Framework | Laravel **13.x** (`^13.8`) | Rutas, controladores, Blade y estructura MVC. Configuración vía `bootstrap/app.php` (sin `Kernel.php`). |
| Servidor web | Apache | El `DocumentRoot` apunta a `public/`. |
| Dependencias PHP | Composer **2.10.2** | Instalado dentro de la imagen de la aplicación. |
| Base de datos | PostgreSQL **18.4** | Servicio Docker `db`; el volumen se monta en `/var/lib/postgresql` (formato requerido desde Postgres 18+). |
| Node.js | **24.18 LTS** | Instalado dentro de la imagen de la aplicación, listo para Vite. |
| Frontend | Blade, CSS y JavaScript directos | Los assets se sirven desde `public/assets` con `asset()`. Vite/Vue quedan disponibles en el contenedor para adopción futura; hoy no hay paso de compilación obligatorio. |
| Bibliotecas en la interfaz pública | Bootstrap 5.3.3, Font Awesome 6.4.0 y Vue 3 | Se cargan por CDN en las vistas que los usan. |

Todas las versiones anteriores están fijadas de forma exacta (misma imagen Docker, mismo `composer.json`) para que el equipo trabaje en entornos idénticos aunque cada quien use su propia PC. No cambies una versión sin acordarlo con el resto del equipo.

## Estructura relevante

```text
app/Http/Controllers/       Controladores HTTP
config/                     Configuración de Laravel y SUIF
database/                   Directorios de migraciones, factories y seeders (sin implementación versionada)
docker/apache/              VirtualHost de Apache (DocumentRoot: public/)
public/assets/
  css/app.css               Base visual y variables compartidas
  css/partials/             Estilos de componentes comunes (navbar y footer)
  css/pages/                Estilos exclusivos de cada pantalla
  img/backgrounds/          Fondos visuales compartidos del sistema
  js/main.js                Comportamientos compartidos de la interfaz pública
  js/pages/                 JavaScript exclusivo por pantalla
resources/views/            Vistas Blade, layouts y parciales
routes/web.php              Rutas web
```

### Estilos y scripts

No se utiliza un único `style.css` ni un bundler. La organización vigente es modular y los archivos se cargan directamente desde `public/assets` con `asset()`:

1. `css/app.css`: tokens, reglas base y utilidades que se reutilizan.
2. `css/partials/*.css`: componentes comunes, actualmente navbar y footer.
3. `css/pages/*.css`: reglas de una pantalla concreta, por ejemplo `home.css` o `persona-dashboard.css`.
4. `js/main.js` y `js/pages/*.js`: comportamiento compartido o exclusivo de una pantalla, respectivamente.

Las vistas Blade deben contener la estructura y las clases CSS, no bloques `<style>` ni estilos inline. Una pantalla carga su CSS particular desde la sección que expone su layout. La landing es el ejemplo actual: carga `app.css`, los parciales y después `pages/home.css`.

La landing usa `partials/navbar.blade.php`, que es el único navbar con enlaces de navegación y botón de acceso. El login y las pantallas internas usan `partials/navbar-sistema.blade.php`, un encabezado institucional sin menú de secciones ni botón de inicio de sesión. Ambos tienen una altura base de `120px`; sus logotipos comparten la misma interacción y abren los sitios institucionales configurados en `config/suif.php`.

El fondo compartido del login y las pantallas internas del sistema se conserva en `public/assets/img/backgrounds/fondo-sistema.jpg`. Se referencia desde CSS estático con `url('/assets/img/backgrounds/fondo-sistema.jpg')` o mediante la variable compartida definida en `app.css`; no se duplica, no se convierte a `data:` URI y no se declara como estilo inline. La landing mantiene su composición visual propia.

La identidad común también usa `public/assets/img/logos/475_logo.png` en el footer y `public/assets/img/logos/fca-unam-logo.ico` como favicon de todos los layouts.

## Ejecutar con Docker

### Requisitos

- Docker Desktop con Docker Compose v2.
- Git para obtener el repositorio.

El puerto definitivo de la aplicación es **8088**. La aplicación se abre en <http://localhost:8088/> y Apache sirve Laravel desde `public/`; no se debe abrir `resources/views` directamente ni añadir `/public` a la URL.

### Primera instalación (PowerShell)

Desde la raíz del repositorio:

```powershell
Copy-Item .env.example .env
```

Antes de iniciar PostgreSQL, abre `.env` y define una contraseña local no vacía en `DB_PASSWORD`. No reutilices esa contraseña fuera del entorno de desarrollo. Después ejecuta:

```powershell
docker compose up -d --build
docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app php artisan key:generate
```

`.env.example` y el valor de respaldo de `compose.yaml` fijan el mapeo definitivo `8088:80`. Si ya existe un archivo `.env` anterior, comprueba que contenga `APP_URL=http://localhost:8088` y `APP_PORT=8088`.

Para comprobar el entorno:

```powershell
docker compose ps
docker compose exec app php -v
docker compose exec app composer --version
docker compose exec app php artisan --version
```

La salida de PHP debe empezar con `PHP 8.4.23` y Composer debe indicar `2.10.2`.

En inicios posteriores basta con:

```powershell
docker compose up -d
```

Los estados visuales del dashboard tienen una única leyenda compartida:

| Color | Estado |
|---|---|
| Verde | Completado |
| Amarillo | Pendiente |
| Azul | En proceso |
| Rojo | Rechazado |

Los módulos completados o pendientes muestran únicamente su estado. Los módulos accionables —en proceso o rechazados— muestran un solo botón con la etiqueta `Continuar`.

Para detener los contenedores sin borrar los datos de PostgreSQL:

```powershell
docker compose down
```

La base de datos se expone al equipo anfitrión en `localhost:5433` de forma predeterminada. Dentro de Docker, Laravel usa el host `db` y el puerto `5432`.

### Configuración de base de datos

La configuración inicial está en `.env.example`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=suif
DB_USERNAME=suif
DB_PASSWORD=
DB_FORWARD_PORT=5433
```

Define `DB_PASSWORD` únicamente en el archivo `.env` local, que no se versiona. Las demás credenciales de ejemplo son sólo para desarrollo y no se deben reutilizar en un ambiente compartido o de producción.

## Rutas disponibles

| Área | Rutas principales | Estado verificable |
|---|---|---|
| Pública | `GET /` | Landing disponible. |
| Autenticación | `GET /login`, `POST /login`, `POST /logout` | Inicio y cierre de sesión integrados con los usuarios persistidos. |
| Persona | `/persona/dashboard`, pre-registro, pago, referencia, documentos, sede, resultados, certificado y facturación | Rutas declaradas; el dashboard consulta la solicitud y documentación persistidas. Los módulos posteriores continúan pendientes. |
| Administración | `/admin/dashboard`, personas, pagos, referencias, documentos, sedes y resultados | Dashboard y bandeja general de personas conectados a la base; la autorización por rol y otros módulos continúan pendientes. |

`routes/web.php` es la fuente de verdad para el detalle de verbos HTTP y nombres de ruta. No se debe enlazar una pantalla como funcional hasta que su controlador, validaciones, persistencia y autorización estén implementados.

## Lineamientos de mantenimiento

- Mantener el stack fijado a PHP 8.4.23 / Laravel ^13.8 / PostgreSQL 18.4 (ver tabla arriba); si el equipo decide actualizar, cambiar `Dockerfile`, `compose.yaml` y `composer.json` juntos y avisar a todo el equipo antes de reconstruir.
- No agregar paquetes Composer, npm, bundlers ni frameworks nuevos sin validar su compatibilidad y acordar el cambio.
- Mantener la separación MVC: las vistas presentan, los controladores coordinan y los modelos/persistencia encapsulan datos.
- Usar formularios `POST`, protección CSRF con `@csrf` —Laravel 13 renombró el middleware a `PreventRequestForgery`— y validación del lado servidor al implementar flujos.
- Aplicar autorización por rol antes de habilitar operaciones administrativas.
- Antes de cambios estructurales en PostgreSQL, definir migraciones y contar con respaldo del ambiente afectado.
- Mantener los cambios pequeños, revisables y documentar los límites o `TODO` que permanezcan.

## Documentación de referencia

El directorio `docs/` conserva material institucional de referencia, incluido el instructivo, convocatoria, preguntas frecuentes y lineamientos de pago. Esos documentos no sustituyen la implementación de los flujos en la aplicación.
