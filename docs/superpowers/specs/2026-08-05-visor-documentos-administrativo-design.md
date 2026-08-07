# Diseño: visor administrativo de documentos de pre-registro

## Estado

Aprobado. Pendiente de implementación.

## Objetivo

Permitir que la persona administradora consulte los PDFs que la persona
ya cargó para una solicitud visible en la bandeja de pre-registros. El visor
será de sólo lectura: no aprobará, rechazará ni guardará observaciones. Esas
acciones se resolverán en la entrega de persistencia posterior.

## Hallazgos del flujo actual

El flujo persona ya guarda cada archivo bajo el disco local privado de
Laravel, en rutas como `preregistro/cargas/{solicitud}/...pdf`, y persiste esa
ruta en `documento.docu_path`. Esos archivos no se exponen mediante
`public/`.

La relación disponible es:

```text
solicitud -> documento -> tipo_documento
                       -> historial estado_documento -> c_estado_documento
```

El estado de un documento también es histórico. El vigente es el registro con
mayor `esdo_id_estado_documento` de ese documento. Una solicitud puede no
tener estado documental todavía; en ese caso se mostrará como `Cargado`, sin
inventar un estado persistido.

Actualmente `ConsultaPreRegistros::solicitud()` devuelve `documentos: []` y
la pantalla administrativa de documentación, sus botones y previsualización
dependen de `PreRegistroDatosPrueba` y de sesión. No se reutilizará esa
pantalla ni sus POST, porque mostraría controles que aún no persisten datos
reales.

## Alcance de esta entrega

1. Incorporar al expediente real un listado de los documentos existentes de
   su propia solicitud: tipo, nombre original, fecha/hora de carga y estado
   documental vigente.
2. Agregar una ruta administrativa `GET` con nombre, por ejemplo
   `admin.personas.documentos.ver`, que sirva el PDF en línea.
3. Añadir a cada documento del expediente el control `Abrir`, que cargará el
   PDF en un modal de la misma pantalla. La respuesta no expondrá la ruta
   interna de almacenamiento.
4. Mantener el expediente y el visor en modo sólo lectura.

No se modificarán scripts SQL, esquema, archivos ya cargados, rutas de
persona, estados, observaciones ni dependencias.

## Consulta real del expediente

Se ampliará `App\Support\Admin\ConsultaPreRegistros` con una consulta de
documentos por `soli_id_solicitud`. La consulta unirá `documento` con
`tipo_documento` y una subconsulta del último estado por `docu_id_documento`.
Devolverá un contrato de presentación sin `docu_path`, por ejemplo:

| Campo | Fuente |
| --- | --- |
| `id` | `docu_id_documento` |
| `titulo` | `tipo_documento.tido_tipo_documento` |
| `nombre` | `documento.docu_nombre` |
| `fecha_carga` | `docu_fecha_carga` + `docu_hora_carga` |
| `estado` | último `c_estado_documento.esdo_estado_documento`, o `Cargado` si no existe historial |
| `ruta_visor` | ruta nombrada construida en el controlador |

El expediente conservará el alcance que ya aplica la bandeja: sólo solicitudes
de convocatorias vigentes cuyo último estado sea `En revisión`, `Aprobada` o
`Rechazada`. Así, ni la ficha ni el visor permiten enumerar expedientes de
otra convocatoria o fuera de la población administrativa actual.

No se utilizará el nombre del tipo como identificador del archivo, porque el
identificador estable y verificable es `docu_id_documento` dentro de una
solicitud concreta.

## Ruta y autorización de objeto

La ruta recibirá dos identificadores numéricos: la solicitud de la ficha y el
documento que se desea consultar. El controlador no usará una ruta de archivo
proporcionada por URL.

Antes de entregar un archivo, solicitará al servicio una fila que cumpla
simultáneamente:

- `documento.docu_id_documento` coincide con el identificador solicitado;
- `documento.soli_id_solicitud` coincide con la solicitud de la URL;
- la solicitud pertenece a la misma población vigente visible en la bandeja.

Si no se cumple cualquiera de las tres condiciones, o el archivo ya no existe
en el disco local, la respuesta será `404`. No se diferenciará un documento
inexistente de uno perteneciente a otra solicitud.

En términos prácticos, estas tres comprobaciones impiden que cambiar números
en la URL dé acceso a archivos ajenos:

- El primer punto localiza el documento solicitado, por ejemplo el documento
  `42`.
- El segundo exige que ese documento `42` esté registrado exactamente dentro
  de la solicitud que también viene en la URL, por ejemplo la solicitud `7`.
  Si el documento `42` pertenece a la solicitud `8`, se devuelve `404` aunque
  el archivo exista.
- El tercero aplica la misma regla de negocio de la bandeja: la solicitud `7`
  debe ser de una convocatoria vigente y estar en uno de los estados visibles.
  Así una URL conocida no permite consultar expedientes históricos, cerrados o
  fuera del trabajo administrativo actual.

La lectura se hará mediante el disco local configurado por Laravel y una ruta
obtenida exclusivamente de la base de datos. Antes de responder se comprobará
que el archivo exista. El nombre de descarga se limitará a un nombre base
seguro, sin directorios. Por el contrato de carga existente sólo se servirán
PDFs y la respuesta incluirá, como mínimo:

- `Content-Type: application/pdf`;
- `Content-Disposition: inline` con nombre seguro;
- `X-Content-Type-Options: nosniff`;
- `Cache-Control: private, no-store`.

La ruta no dará acceso estático a `storage/`, ni revelará `docu_path` en Blade,
JSON, mensajes de error o HTML.

## Límite de seguridad durante desarrollo

Por la decisión ya aprobada, `/admin` continúa sin autenticación ni middleware
de rol mientras se desarrolla. Por ello esta entrega sí protege la relación
solicitud–documento, evita rutas de almacenamiento y evita traversal, pero no
puede impedir que una persona no autenticada que conozca una URL administrativa
consulte un documento válido. Antes de un despliegue debe añadirse la
autorización de rol `Administrador`; sin ella no se podrá considerar el visor
seguro para producción.

## Presentación

En `admin.preregistro-detalle` se mostrará una sección `Documentos cargados`
después de los datos de la persona. Cada fila contendrá tipo, nombre,
fecha/hora, estado y el control `Abrir`.

Al pulsarlo, Vue abrirá un único modal accesible de la ficha y asignará al
visor PDF la ruta controlada de ese documento. No habrá navegación, pestaña
nueva ni recarga de la página. El modal mostrará el tipo y nombre del archivo,
un botón para cerrarlo y un `iframe` con la previsualización nativa del
navegador. Al cerrarlo se retirará la ruta del `iframe` para detener la carga
del documento y evitar que quede visible en memoria de la interfaz.

Se incorporarán cierre con `Escape`, botón explícito de cierre, foco inicial
en ese botón y devolución del foco al control `Abrir` que activó el modal. El
PDF sigue siendo entregado por la ruta controlada; el modal no recibe ni
construye rutas de `storage`.

Si la solicitud no tiene documentos, se mostrará `No hay documentos cargados
para esta solicitud.` No se rellenará la lista con documentos requeridos que
no existen en la base.

## Archivos previstos

| Archivo | Cambio |
| --- | --- |
| `app/Support/Admin/ConsultaPreRegistros.php` | Consulta, normalización y resolución acotada de documentos. |
| `app/Http/Controllers/Admin/DocumentoController.php` | Acción GET de sólo lectura para servir un PDF verificado. |
| `app/Http/Controllers/Admin/PersonaController.php` | Construye las rutas nombradas del visor para el expediente real. |
| `routes/web.php` | Nueva ruta GET nombrada del visor. |
| `resources/views/admin/preregistro-detalle.blade.php` | Lista de documentos y modal de consulta. |
| `public/assets/js/pages/admin-preregistro.js` | Estado del modal, foco y URL del visor. |
| `public/assets/css/pages/admin-preregistro.css` | Estilos exclusivos de la lista y el modal. |

## Verificación

1. Una solicitud visible con documentos muestra únicamente sus filas reales y
   cada control `Abrir` carga el PDF correspondiente en el modal, sin recargar
   ni abandonar la ficha.
2. Un documento de otra solicitud, aunque su ID exista, responde `404`.
3. Un ID no numérico, una solicitud fuera de convocatoria vigente, una ruta de
   archivo ausente y un documento inexistente responden `404` sin filtrar
   datos.
4. La respuesta de un PDF incorpora los encabezados definidos y no expone la
   ruta interna.
5. La ficha no muestra botones de aprobación, rechazo o guardado.
6. Se ejecutan `php -l`, `php artisan route:list`, `php artisan view:cache` y
   comprobación HTTP contra Apache.

La cobertura automatizada de estos casos requiere una base temporal con el
esquema de solicitudes/documentos. Como el proyecto no tiene migraciones ni
pruebas de negocio versionadas, no se crearán ni poblarán tablas sobre `suif`.
Antes de agregar pruebas de integración se acordará una base de prueba
independiente y la forma autorizada de inicializar su esquema.

## Decisiones para aprobar

1. ¿El visor se limita a PDFs, tal como la carga actual, y se presenta en un
   modal de la misma ficha?
2. ¿El visor conserva el filtro actual de convocatorias vigentes, de forma que
   ni siquiera desde una URL se pueda consultar documentación de una
   convocatoria ya cerrada?
3. ¿Confirmas que esta entrega será estrictamente de consulta y que las
   observaciones y decisiones documentales pasan a la etapa posterior?
