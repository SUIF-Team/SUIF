# Diseño: dashboard y personas registradas con PostgreSQL

## Objetivo

Conectar el dashboard administrativo y la bandeja general de personas con los
datos persistidos por el pre-registro, sin estados, conteos ni registros de
demostración.

## Definición de persona registrada

Una persona registrada tiene un usuario con rol `Persona`, un registro en
`persona` y al menos una solicitud. Es el resultado que deja el pre-registro al
guardar los datos y entregar la clave de acceso. La consulta no se limita a la
convocatoria vigente porque la bandeja presenta una vista general del sistema.

Si una persona tiene varias solicitudes, la bandeja muestra el estado vigente
de la solicitud más reciente. El estado vigente es el último renglón de
`estado_solicitud` y su texto proviene de `c_estado_solicitud`.

## Dashboard

El dashboard consulta dos indicadores reales:

- Total de personas registradas.
- Total de solicitudes cuyo último estado es `En revisión`.

Los indicadores de pagos y certificados no presentan un cero ficticio: se
muestran como `Sin datos persistidos` mientras esos módulos no cuenten con una
fuente real.

## Bandeja general

La bandeja lista una fila por persona con nombre, fecha de registro y estado
real de la solicitud. Los filtros de estado se construyen con el catálogo de la
base de datos y el color se deriva únicamente para presentación:

- `Aprobada`: verde.
- `Rechazada` y `Cancelada`: rojo.
- Los demás estados persistidos: amarillo.

La bandeja general no enlaza a una vista de detalle. Una persona puede tener
varias solicitudes y todavía no existe una entidad de dominio que represente un
detalle general único. La bandeja operativa de pre-registros conserva su vista
por solicitud.

## Refactor de dominio

- Controladores y namespaces: `Persona`.
- Rutas de usuario: prefijo `/persona` y nombres `persona.*`.
- Rutas administrativas: `/admin/personas*` y nombres `admin.personas.*`.
- Vistas, layouts, assets, variables de sesión y rol: `Persona`.
- Las rutas y nombres anteriores se retiran por completo.

## Persistencia y seguridad de datos

Las métricas y filas se obtienen con Query Builder. La consulta selecciona la
última solicitud y el último estado mediante subconsultas agrupadas; no realiza
una consulta adicional por cada fila. La interfaz no crea identificadores ni
estados derivados que no existan en el esquema.

La posible vista general de detalle queda como modificación futura hasta que se
definan su entidad, relación con varias solicitudes y reglas de autorización.

## Verificación

- Pruebas con una base SQLite temporal para roles, personas, solicitudes,
  historial y catálogo de estados.
- Pruebas de rutas nuevas y ausencia de rutas anteriores.
- Renderizado HTTP del dashboard, bandeja y pre-registro mediante Apache.
- Verificación del catálogo de roles y de los conteos contra PostgreSQL.
- Validación de sintaxis PHP y JavaScript, rutas Laravel y caché de Blade.
