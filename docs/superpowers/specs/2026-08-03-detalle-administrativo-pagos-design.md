# Diseño: detalle administrativo de pagos

## Objetivo

Implementar el expediente de revisión individual para un pago seleccionado desde la bandeja administrativa. La ruta existente `admin.pagos.show` conservará el identificador interno del pago y mostrará el expediente únicamente cuando el registro sea elegible para revisión.

## Alcance

- Adaptar el prototipo de expediente al layout y patrones visuales existentes de SUIF.
- Reutilizar `layouts.admin`, el navbar, footer, fondo institucional, `BackNavigation` y las clases de `admin-preregistro.css`.
- Agregar datos temporales del expediente sólo en `App\Support\Admin\PagoDatosPrueba`.
- Renderizar perfil del participante, tres step cards, comprobante, datos bancarios y acciones temporales.
- Añadir rutas controladas para el comprobante y los enlaces temporales de validación o rechazo, sin persistir ni modificar estados.

## Flujo y rutas

1. La bandeja genera `admin.pagos.show` con `id`, nunca con CURP, folio ni datos visibles.
2. `PagoController@show` obtiene el pago desde `PagoDatosPrueba` y valida, antes de renderizar, que:
   - el pre-registro esté completado;
   - la documentación esté completada o aprobada;
   - exista un comprobante;
   - el pago tenga estado disponible para revisión.
3. Si no existe o no cumple una condición, se redirige a `admin.pagos.index` con un mensaje flash explicativo.
4. Si cumple, se renderiza `admin.pago-detalle`.
5. `admin.pagos.comprobante` será un destino autorizado y controlado. Mientras no exista almacenamiento, valida las mismas condiciones y redirige con un aviso, sin exponer rutas físicas.
6. Los enlaces temporales de validar y rechazar apuntarán a destinos controlados que no alteran datos y vuelven al expediente con aviso de funcionalidad pendiente.

## Datos temporales

`PagoDatosPrueba` seguirá siendo la única fuente mock. Cada pago incluirá los datos del participante necesarios para el perfil, los estados de pre-registro y documentación, disponibilidad de revisión y los datos de comprobante, monto, referencia bancaria, banco y fecha de pago. Los escenarios no elegibles quedan en esta misma fuente para comprobar las redirecciones de protección.

## Interfaz

La vista reutilizará las tarjetas de perfil, progreso, estado y acciones de `admin-preregistro.css`:

- Perfil: iniciales, nombre completo, CURP, folio, ubicación si existe y estado del pago.
- Progreso: Pre-registro y Documentación completados en verde; Pago como paso actual amarillo con `aria-current="step"`.
- Tarjeta “Pago / Referencia bancaria”: nombre del comprobante ajustable, enlace “Abrir comprobante” con `target="_blank"` y `rel="noopener noreferrer"`, monto, referencia, banco y fecha.
- Acciones: enlaces “Validar pago” y “Rechazar pago” con variantes visuales ya existentes, sin cambio de estado.
- Regreso: componente `BackNavigation` con “Volver a la bandeja”, dirigido mediante `admin.pagos.index`.

Se creará una hoja específica mínima para el bloque del comprobante, las filas de datos y sus ajustes responsive. No se duplicarán el layout, los estilos globales ni los estilos de componentes existentes.

## Accesibilidad y responsive

- Estructura semántica con `header`, `nav`, `main`, `section`, `dl` y enlaces accesibles.
- Estados con texto, además de color.
- Foco visible mediante las reglas existentes.
- Step cards, datos y acciones se apilan en móvil; nombres y referencias pueden ajustarse sin provocar scroll horizontal.

## Verificación en VM

En la VM se deberán ejecutar los comandos de rutas, caché de vistas y pruebas pertinentes. Se probarán: pago válido, ID inexistente, pre-registro incompleto, documentación incompleta o rechazada, comprobante inexistente, regreso a bandeja y vistas móvil, tablet y escritorio.
