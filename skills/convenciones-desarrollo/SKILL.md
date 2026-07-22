---
name: convenciones-desarrollo
description: "Aplica las convenciones de SUIF al crear, modificar o revisar código Laravel 5.5, PHP 7.1, Blade, CSS o JavaScript. Úsala para preservar la estructura legacy del proyecto, sus assets estáticos y sus requisitos de seguridad."
---

# Convenciones de desarrollo de SUIF

## Mantener el stack y el alcance

- Mantén compatibilidad estricta con Laravel 5.5 y PHP 7.1.0. No introduzcas sintaxis, dependencias ni APIs de versiones posteriores.
- Trabaja con PostgreSQL y la configuración existente. No sustituyas el framework por PHP plano, `include()`/`require()`, sesiones manuales ni consultas sin Eloquent o el query builder de Laravel.
- Conserva el alcance del sistema: certificaciones, resultados, documentos, pagos, sedes, referencias y paneles de participante y administración. No implementes una aplicación de exámenes.
- Ejecuta el entorno Docker en `http://localhost:8088` cuando sea necesario. Mantén Apache sirviendo `public/`; no expongas `resources/views` ni agregues `/public` a las URLs.

## Ubicar cada responsabilidad

- Declara rutas web en `routes/web.php`; usa grupos, prefijos, namespaces, nombres de ruta y middleware de Laravel cuando corresponda.
- Implementa la lógica HTTP en `app/Http/Controllers/`, agrupando los controladores de cada panel en `Admin/` y `Participante/`.
- Renderiza vistas Blade desde `resources/views/`. Reutiliza `layouts/` y `partials/`; no dupliques cabeceras, navegación, pie de página o scripts entre vistas.
- Usa `route()` y `asset()` en Blade para URLs internas y assets. Evita URLs relativas frágiles.
- Usa convenciones de Laravel para clases y métodos PHP: clases en `PascalCase`, métodos y variables en `camelCase`, namespaces y PSR-4 existentes. Conserva los nombres de rutas, tablas y columnas ya establecidos.
- Usa nombres descriptivos y consistentes en español para nuevas vistas, clases CSS y textos de interfaz, salvo convenciones o términos propios de Laravel.

## Construir vistas y assets

- Carga los assets directamente desde `public/assets`; el proyecto no usa npm, Vite, Laravel Mix, Sass ni una etapa de compilación frontend.
- Mantén estilos globales y reutilizables en `public/assets/css/app.css`.
- Mantén estilos de componentes compartidos en `public/assets/css/partials/` y estilos exclusivos de cada pantalla en `public/assets/css/pages/`.
- Usa `public/assets/img/backgrounds/fondo-sistema.jpg` como fondo institucional compartido del login y del sistema. Referéncialo desde CSS estático con `url('/assets/img/backgrounds/fondo-sistema.jpg')` o una variable CSS equivalente; no lo dupliques ni uses estilos inline o `data:` URI.
- Reserva `partials/navbar.blade.php` para la landing: es el único navbar con enlaces y botón de acceso. Usa `partials/navbar-sistema.blade.php` en autenticación y paneles internos; no agregues enlaces ni botón de inicio de sesión a esa variante institucional.
- Haz que cada layout cargue `app.css`, los parciales que use y, mediante `@yield('styles')` o `@stack('styles')`, el CSS específico de la vista. Limita los selectores de página a una clase del `body` o del contenedor raíz para evitar filtraciones entre pantallas.
- Mantén JavaScript compartido en `public/assets/js/main.js` y JavaScript exclusivo por pantalla en `public/assets/js/pages/`. Cárgalo desde los yields de scripts del layout; no escribas scripts ni estilos inline en Blade.
- Usa Bootstrap 5 como base cuando ya esté disponible en el layout y extiéndelo con CSS propio. Conserva las variables CSS y los patrones visuales definidos en `app.css`.
- Usa clases CSS con nombres legibles, minúsculas y guiones simples, por ejemplo `.login-tarjeta` o `.footer-enlace`. Reserva `id` para anclas, asociación `label`/campo o selección puntual de JavaScript.
- Mantén una indentación coherente con el archivo que edites y comenta únicamente decisiones no evidentes o bloques relevantes.

## Proteger datos y acceso

- Incluye `{{ csrf_field() }}` en todo formulario con método distinto de `GET`; es la sintaxis compatible con Laravel 5.5. Muestra errores de validación sin exponer información sensible.
- Protege rutas y acciones privadas con los middleware de autenticación y autorización adecuados. Usa `Auth`, sesiones y validadores de Laravel; nunca autentiques con comparaciones manuales de contraseñas.
- Valida entradas, restringe archivos cargados y conserva documentos, comprobantes, certificados, facturas y referencias fuera de `public`, bajo `storage/app/private/` según la estructura existente.
- No reveles secretos, credenciales, rutas privadas ni datos personales en vistas, logs o respuestas.

## Verificar cambios

- Comprueba que las rutas, nombres de vistas, controladores, assets y métodos referenciados existan antes de terminar.
- Ejecuta las comprobaciones compatibles con el entorno legacy cuando cambies comportamiento: carga de la ruta, `php artisan` pertinente y pruebas disponibles. No actualices dependencias como atajo para resolver incompatibilidades.
