# SUIF — Sistema Integral de Certificaciones

SUIF es una aplicación web de la Facultad de Contaduría y Administración de la UNAM para el seguimiento administrativo de un proceso de certificación. El proyecto conserva un stack legado y se está reconstruyendo de forma gradual; por ello se priorizan la compatibilidad, los cambios acotados y la trazabilidad.

> SUIF no aplica ni administra exámenes. Su alcance es el pre-registro, documentación, referencias y pagos, selección de sede, consulta de resultados, certificados y los paneles de participante y administración.

## Estado actual

- La landing pública (`/`) está maquetada y muestra información de la certificación. Parte de su contenido (periodo, nombre de certificación, fechas y enlaces de descarga) continúa como texto de ejemplo.
- La pantalla de acceso en `resources/views/auth/login.blade.php` ya está adaptada visualmente a Blade con campos CURP y clave, CSRF, espacios para mostrar errores de validación, navbar institucional y footer. La validación del servidor, la persistencia y la autenticación real por CURP/clave siguen pendientes; el cierre de sesión ya cuenta con una acción de controlador compatible con Laravel.
- El dashboard de participante (`/participante/dashboard`) construye temporalmente su avance a partir de datos de sesión y valores de ejemplo; aún no consulta modelos ni una base de datos de negocio. En entorno local también dispone de una vista de demostración sin autenticación para revisar todas sus transiciones visuales.
- Las vistas, rutas y controladores de los módulos de participante y administración están en diferentes etapas de preparación. Varios controladores no tienen acciones implementadas y sus vistas contienen `TODO`; no se deben considerar funcionalidades terminadas.
- Las rutas administrativas sólo exigen el middleware `auth` en este momento. La autorización por rol de administrador no está implementada.
- No hay migraciones, seeders ni pruebas automatizadas versionadas. Antes de conectar flujos reales se debe definir el esquema de datos y la estrategia de pruebas.

## Stack y compatibilidad

| Capa | Tecnología / versión | Uso |
|---|---|---|
| Backend | PHP **7.1.0** | Versión exacta de la imagen Docker y plataforma de Composer. |
| Framework | Laravel **5.5** | Rutas, controladores, Blade y estructura MVC. |
| Servidor web | Apache | El `DocumentRoot` apunta a `public/`. |
| Dependencias PHP | Composer **2.2.29** | Instalado dentro de la imagen de la aplicación. |
| Base de datos | PostgreSQL **16** | Servicio Docker `db`. |
| Frontend | Blade, CSS y JavaScript directos | Los assets se sirven desde `public/assets`; no hay Node, Vite, Mix, Sass ni un paso de compilación. |
| Bibliotecas en la interfaz pública | Bootstrap 5.3.3, Font Awesome 6.4.0 y Vue 3 | Se cargan por CDN en las vistas que los usan. |

PHP 7.1 y Laravel 5.5 son versiones sin soporte de seguridad. Este entorno existe para reproducir y mantener el sistema legado; no debe exponerse a Internet sin una revisión de seguridad y actualización planificada.

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
3. `css/pages/*.css`: reglas de una pantalla concreta, por ejemplo `home.css` o `participante-dashboard.css`.
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
docker compose build app
docker compose up -d db
docker compose run --rm app composer install --no-interaction --prefer-dist --no-plugins
docker compose run --rm app php artisan key:generate
docker compose up -d app
```

`.env.example` y el valor de respaldo de `compose.yaml` fijan el mapeo definitivo `8088:80`. Si ya existe un archivo `.env` anterior, comprueba que contenga `APP_URL=http://localhost:8088` y `APP_PORT=8088`.

Para comprobar el entorno:

```powershell
docker compose ps
docker compose exec app php -v
docker compose exec app composer --version
docker compose exec app php artisan --version
```

La salida de PHP debe empezar con `PHP 7.1.0` y Composer debe indicar `2.2.29`.

En inicios posteriores basta con:

```powershell
docker compose up -d
```

### Revisar el dashboard del participante sin base de datos

Mientras `APP_ENV=local`, el dashboard se puede abrir sin autenticación en:

<http://localhost:8088/participante/dashboard/demo>

La última parte de la URL permite comprobar estados concretos del flujo:

| Escenario | URL |
|---|---|
| Inicio del trámite | `/participante/dashboard/demo/inicio` |
| Pre-registro completado | `/participante/dashboard/demo/preregistro-completo` |
| Referencia generada | `/participante/dashboard/demo/referencia-generada` |
| Pago enviado y en validación | `/participante/dashboard/demo/validando-pago` |
| Pago validado | `/participante/dashboard/demo/pago-validado` |
| Sede seleccionada y resultado en espera | `/participante/dashboard/demo/sede-seleccionada` |
| Resultado publicado | `/participante/dashboard/demo/resultado-publicado` |
| Certificado disponible | `/participante/dashboard/demo/certificado-disponible` |
| Pago con observaciones | `/participante/dashboard/demo/pago-rechazado` |

Estas rutas sólo se registran en el entorno local. En otros ambientes el dashboard real continúa protegido por el middleware `auth`.

Los estados visuales del dashboard tienen una única leyenda compartida:

| Color | Estado |
|---|---|
| Verde | Completado |
| Amarillo | Pendiente |
| Azul | En proceso |
| Rojo | Rechazado |

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
| Autenticación | `GET /login`, `POST /login`, `POST /logout` | El diseño del formulario CURP/clave y el cierre de sesión están integrados; falta definir el esquema de datos e implementar el inicio de sesión. |
| Participante | `/participante/dashboard`, pre-registro, pago, referencia, documentos, sede, resultados, certificado y facturación | Rutas declaradas y protegidas con `auth`; el dashboard usa datos temporales y las pantallas enlazadas tienen acciones de lectura. Las operaciones de escritura y el contenido definitivo de varios módulos continúan pendientes. |
| Administración | `/admin/dashboard`, participantes, pagos, referencias, documentos, sedes y resultados | Rutas declaradas y protegidas con `auth`; acciones y autorización administrativa están mayormente pendientes. |

`routes/web.php` es la fuente de verdad para el detalle de verbos HTTP y nombres de ruta. No se debe enlazar una pantalla como funcional hasta que su controlador, validaciones, persistencia y autorización estén implementados.

## Lineamientos de mantenimiento

- Mantener compatibilidad con PHP 7.1.0; no introducir sintaxis ni dependencias que requieran versiones posteriores sin una decisión explícita de actualización.
- No agregar paquetes Composer, npm, bundlers ni frameworks nuevos sin validar su compatibilidad y acordar el cambio.
- Mantener la separación MVC: las vistas presentan, los controladores coordinan y los modelos/persistencia encapsulan datos.
- Usar formularios `POST`, protección CSRF con `{{ csrf_field() }}` —compatible con Laravel 5.5— y validación del lado servidor al implementar flujos.
- Aplicar autorización por rol antes de habilitar operaciones administrativas.
- Antes de cambios estructurales en PostgreSQL, definir migraciones y contar con respaldo del ambiente afectado.
- Mantener los cambios pequeños, revisables y documentar los límites o `TODO` que permanezcan.

## Documentación de referencia

El directorio `docs/` conserva material institucional de referencia, incluido el instructivo, convocatoria, preguntas frecuentes y lineamientos de pago. Esos documentos no sustituyen la implementación de los flujos en la aplicación.
