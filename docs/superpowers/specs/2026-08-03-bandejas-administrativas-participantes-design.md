# Diseño: bandeja de participantes registrados y filtros administrativos

## Objetivo

Incorporar una bandeja administrativa de participantes registrados que conserve el layout, comportamiento y componentes de la bandeja de pre-registros. Unificar el bloque de filtros de participantes, pre-registros y pagos sin efectuar una refactorización amplia ni integrar base de datos.

## Alcance

- Añadir `admin.participantes.registrados.index` en `/admin/participantes-registrados`, bajo el grupo administrativo vigente.
- Enlazar la acción “Participantes registrados” del dashboard a esa ruta.
- Reutilizar `layouts.admin`, el fondo institucional, navbar, footer, `BackNavigation`, las clases de la bandeja y el script Vue existente.
- Crear una fuente de datos mock específica para los participantes registrados, preparada para sustituirse por una consulta desde controlador o base de datos.
- Incluir un parcial Blade de filtros reutilizable por pre-registros, participantes registrados y pagos. Sus parámetros definirán opciones de búsqueda y los valores de estado de cada bandeja.
- Mantener los cambios dentro del módulo administrativo; no se modifica el archivo SQL, la base de datos ni pantallas no relacionadas.

## Navegación y detalle

1. El dashboard enlaza a `admin.participantes.registrados.index`.
2. La nueva bandeja ordena los mocks de forma descendente por `fecha_registro`.
3. Cada fila usa iniciales o avatar, nombre completo, la etiqueta de etapa y el enlace “Ver expediente”.
4. El enlace reutiliza `admin.participantes.show` con el identificador interno del participante. No se crea una nueva pantalla de expediente.
5. El regreso usa `BackNavigation` hacia `admin.dashboard`.

## Datos y estados

Cada registro temporal tendrá, como mínimo, `id`, `nombre`, `primer_apellido`, `segundo_apellido`, `nombre_completo`, CURP, `etapa`, `estado`, `clase_estado` y ruta de detalle. Pre-registros y participantes registrados conservan `fecha_registro`; pagos conserva `fecha_envio_comprobante`. Cada root declara su campo de fecha en `data-campo-fecha`, para que el script compartido formatee el valor correcto sin asumir un nombre de propiedad. Pagos se normalizará con los tres campos de nombre, sin eliminar `nombre_completo` ni sus datos propios.

`etapa` y `estado` son independientes. La etiqueta siempre muestra `etapa`; su color sólo se obtiene de `clase_estado`, que tendrá uno de estos valores CSS: `admin-bandeja-preregistros-estado-aceptado` (verde), `admin-bandeja-preregistros-estado-revision` (amarillo), `admin-bandeja-preregistros-estado-proceso` (azul) o `admin-bandeja-preregistros-estado-rechazado` (rojo). No habrá condiciones que calculen el color a partir del texto de la etapa.

Los mocks de participantes registrados usarán los mismos `id` de `PreRegistroDatosPrueba`. Así, “Ver expediente” puede reutilizar con seguridad `admin.participantes.show`, cuyo resolvedor actual se apoya en esa fuente. Sus estados de filtro serán Todos, Correcto, Pendiente de validación, En proceso y Con incidencia; las etapas representadas serán Pre-registro, Documentación, Pago y Evaluación.

## Filtros

Las tres bandejas comparten el formulario visual y su ciclo de aplicar/limpiar/resultados vacíos. El parcial recibe un prefijo de IDs accesibles y la colección de opciones de estado; el root de cada bandeja declara el nombre de su campo de estado en `data-campo-estado`, por lo que el script conserva los nombres existentes (`estado_bandeja`, `estatus` y `estado`) sin ramificaciones por pantalla:

- Campo: Nombre(s), apellido paterno, apellido materno, CURP.
- Término de búsqueda.
- Estado.
- Botones Filtrar y Limpiar.

Pre-registros conserva sus valores: Todos, En revisión, Aceptado y Rechazado. Pagos adopta Todos, Por revisar, Aprobado y Rechazado. Participantes registrados usa los valores definidos arriba. El script Vue filtra por el campo seleccionado y por el valor configurado de `data-campo-estado`; no realiza búsquedas implícitas por folio u otros campos. Cada registro expone `clase_estado`, que Vue aplica directamente para el color de la etiqueta.

## Interfaz y responsive

Se preservan tipografía, tarjetas, controles, foco, hover y espaciados de `admin-bandeja-preregistros.css`. La columna central de la nueva bandeja se llama “Etapa”. Se conserva la cuadrícula adaptable entre 501 y 767 px y, a 500 px o menos, se ocultan los encabezados y cada registro se apila como tarjeta, dejando visibles participante, etiqueta de etapa y acción sin scroll horizontal. Se añadirá únicamente la clase azul de estado si aún no existe.

## Verificación en VM

En la VM se validarán rutas, caché de vistas y pruebas de Laravel. Manualmente se comprobará el dashboard, la nueva bandeja, filtrado por los cuatro campos, cada filtro de estado, limpiar, resultado vacío, enlaces de expediente y la respuesta en móvil, tablet y escritorio. Los datos siguen siendo mock y no representan registros reales.
