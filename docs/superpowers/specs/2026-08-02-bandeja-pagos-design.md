# Diseño: Bandeja administrativa de pagos

## Objetivo

Incorporar la bandeja administrativa para consultar los comprobantes de pago
enviados por personas, disponible desde el dashboard en `GET /admin/pagos`
con la ruta nombrada `admin.pagos.index`.

## Arquitectura

- Conservar `Admin\\PagoController`, ya importado y enlazado en `routes/web.php`.
- Agregar `App\\Support\\Admin\\PagoDatosPrueba` como fuente temporal aislada y
  sustituible por un repositorio persistente posteriormente.
- El controlador ordenará los pagos por `fecha_envio_comprobante` de forma
  descendente y generará la URL nombrada del detalle para cada registro.
- La vista `admin.pagos` reutilizará el layout administrativo, el CSS de la
  bandeja de pre-registros y el componente Vue `BackNavigation`.

## Interfaz y datos

- Mostrar el título `Bandeja de pagos`, la descripción `Consulta y revisa los
  comprobantes de pago enviados por las personas.`, el buscador
  etiquetado `Buscar persona`, los botones `Filtrar` y `Limpiar`, y el
  listado `Pagos recibidos`. Sus columnas serán `Persona`, `Estatus` y
  `Acción`; cada enlace dirá `Revisar pago`. No se conservarán textos visibles
  como `Solicitudes` ni `Ver expediente`.
- Cada pago temporal contendrá identificador, nombre completo, iniciales, CURP,
  estatus y fecha de envío del comprobante.
- La fuente temporal tendrá al menos cinco personas representativos —por
  ejemplo Jordan Carrillo Guevara, María Fernanda López Castillo, Luis Alberto
  Reyes Mendoza, Claudia Hernández Ruiz y Diego Morales Cruz— distribuidos en
  los tres estatus y con fechas distintas para comprobar el orden descendente.
- Los estatus visuales serán `Por revisar`, `Aprobado` y `Rechazado`, usando las
  clases de estado amarilla, verde y roja ya existentes.
- La bandeja conservará las reglas responsive existentes para escritorio,
  tablet y móvil, sin agregar CSS duplicado.

## Filtros y navegación

- El filtro en cliente comparará sin distinguir mayúsculas/minúsculas nombre,
  CURP y estatus. `Filtrar` aplicará el término y `Limpiar` restaurará
  el arreglo ordenado original.
- El botón del dashboard usará `route('admin.pagos.index')`.
- `Revisar pago` apuntará a `admin.pagos.show`. Mientras no exista el detalle,
  ese destino temporal resolverá el identificador y regresará a la bandeja con
  un aviso informativo, sin validar ni rechazar pagos.
- `BackNavigation` se configurará con la etiqueta `Volver al dashboard` y la
  ruta `admin.dashboard`.

## Verificación

- Validar sintaxis PHP y listado de rutas.
- Ejecutar las pruebas Laravel existentes si el proyecto las configura.
- Comprobar las respuestas HTTP y la presencia de textos, enlaces y datos de
  demostración mediante pruebas de humo reproducibles.
