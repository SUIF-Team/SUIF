# Diseño: redirección y orden del formulario de sedes

## Objetivo

Al crear o actualizar una sede, regresar a la bandeja administrativa de sedes y mostrar el aviso de confirmación correspondiente. Ordenar los campos de programación y aforo del formulario de creación y edición para que coincidan con la referencia proporcionada.

## Alcance

- `POST /admin/sedes` redirigirá a la ruta nombrada `admin.sedes.index` con el aviso `La sede se creó correctamente.`.
- `PUT /admin/sedes/{id}` redirigirá a la ruta nombrada `admin.sedes.index` con el aviso `La sede se actualizó correctamente.`.
- El formulario compartido mostrará sus campos de programación en este orden de lectura y tabulación: hora de inicio, fecha de inicio, hora de fin, fecha de fin y aforo máximo.
- En pantallas con dos columnas, el primer y segundo campo formarán la primera fila; el tercero y cuarto, la segunda; el aforo quedará primero en la tercera fila. En móvil mantendrá ese mismo orden en una columna.

## Compatibilidad y límites

- Se conservarán los nombres de campo, tipos HTML, atributos de validación, valores previos, CSRF y el método `PUT` de edición.
- No se modificarán el servicio `GestionSedes`, la persistencia, el mapa, las rutas, ni las reglas de validación del intervalo de evaluación.
- Los avisos seguirán usando el mismo flash `success` que ya consume la bandeja de sedes.
- No se tocarán los cambios existentes fuera de la funcionalidad de sedes.

## Verificación

- La prueba de alta comprobará persistencia, redirección a `admin.sedes.index` y aviso de creación.
- La prueba de edición válida comprobará persistencia, redirección a `admin.sedes.index` y aviso de actualización.
- Las vistas de crear y editar comprobarán el orden de las etiquetas de los cinco campos reordenados.
- Se ejecutará la prueba de gestión de sedes, la lista de rutas y la compilación de vistas de Laravel.
