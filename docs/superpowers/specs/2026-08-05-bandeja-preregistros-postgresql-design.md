# Diseño: bandeja de pre-registros con PostgreSQL

## Estado

Propuesta para revisión. No está aprobada ni implementada.

## Objetivo

Reemplazar las personas ficticias de `admin.personas.index` por las
solicitudes y personas que el flujo de persona ya registra en PostgreSQL.
La pantalla conservará la vista, los filtros en cliente, las rutas nombradas y
las clases CSS existentes.

La unidad de navegación pasa a ser `soli_id_solicitud`: una persona puede
tener más de una solicitud y la revisión administrativa corresponde a una
solicitud concreta, no únicamente a una persona.

## Límite de esta entrega

Esta entrega es de lectura. Incluye la bandeja y, si se aprueba el alcance
coherente, la ficha de detalle sólo para consulta. No persiste aprobaciones,
rechazos, observaciones ni estados de documentos; esas operaciones se
diseñarán como una entrega posterior.

No se modifican los archivos de `database/scripts/`, no se ejecuta SQL sobre
la base `suif` y no se agregan dependencias.

## Acceso durante desarrollo

Por decisión del equipo, las rutas `/admin` permanecerán abiertas durante el
desarrollo de esta entrega. Por ello no se agregan todavía `auth`, middleware
de rol ni una cuenta administrativa de prueba.

Esta excepción sólo aplica al entorno de desarrollo. Antes de exponer la
aplicación fuera de él, será obligatorio restringir el grupo administrativo a
usuarios autenticados con el rol `Administrador`; la bandeja contendrá datos
personales reales.

## Contrato de datos existente

El flujo de persona ya crea `usuario`, `persona`, `comunicacion`,
`trabajo`, `grado_persona`, `solicitud`, `documento`, `estado_documento` y
`estado_solicitud`. Cuando la persona envía todos sus documentos a
revisión, agrega un historial `En revisión` para cada documento y para la
solicitud.

Los estados son históricos, no columnas que se actualizan. La fuente de datos
debe tomar el registro con mayor identificador de `estado_solicitud` por cada
solicitud; no debe usar el primer estado ni confiar en sesión.

## Alcance recomendado

### A. Bandeja real

Crear un servicio de lectura, por ejemplo
`App\\Support\\Admin\\ConsultaPreRegistros`, con un método
`bandeja(): array`.

La consulta parte de `solicitud` y une:

- `persona` para identificadores y nombre;
- `entidad_federativa` para la entidad;
- una subconsulta del último `estado_solicitud` y su catálogo;
- `comunicacion` agregada por tipo sólo cuando sea necesaria para detalle.

La bandeja no realizará una consulta por persona. El servicio devolverá
el contrato que hoy consume Vue:

| Campo de la vista | Origen |
| --- | --- |
| `id` | `soli_id_solicitud` |
| `nombre`, `primer_apellido`, `segundo_apellido` | `persona` |
| `nombre_completo` | composición en PHP |
| `curp` | `pers_curp` |
| `fecha_registro` | `pers_fecha_registro` |
| `estado_bandeja` | mapeo del último estado de solicitud |
| `clase_estado` | mapeo explícito en PHP |
| `ruta_expediente` | `route('admin.personas.show', ...)` |

El mapeo visual propuesto conserva los textos de la interfaz:

| Estado persistido | Texto en bandeja | Clase |
| --- | --- | --- |
| `En revisión` | En revisión | amarilla |
| `Aprobada` | Aprobada | verde |
| `Rechazada` | Rechazado | roja |

Las solicitudes en `Pre-registro`, `Documentación` o `Cancelada` quedan fuera
de esta bandeja inicial, porque todavía no representan un expediente enviado a
revisión o un resultado histórico. Se ordenará por fecha de registro
descendente y se conservarán los filtros actuales en el navegador. El filtro
visible cambiará de `Aceptado` a `Aprobada` para que el texto sea idéntico al
catálogo de la base de datos.

### B. Ficha de sólo lectura (recomendada junto con A)

Las filas de la bandeja actual enlazan a `admin.personas.show`. Si se
conecta sólo A, ese enlace seguiría buscando un ID ficticio y terminaría en
404. Para una entrega consistente se recomienda que el mismo servicio exponga
`solicitud(int $id): ?array` y que `PersonaController@show` use el ID de
la solicitud real.

La ficha mapeará también los correos y teléfono desde `comunicacion`, el nivel
desde `grado_persona`/`nivel_profesional` y los indicadores laborales desde
`trabajo_persona`/`trabajo`. Las acciones de aceptar y rechazar se ocultarán o
mostrarán como no disponibles hasta que se apruebe el diseño de persistencia.
Así no aparentarán modificar datos reales mientras aún usan sesión.

No se agregará un identificador de negocio que no exista en el esquema. La
interfaz no debe deducir identificadores de CURP, solicitud ni convocatoria.

## Consulta y consistencia

El servicio usará Query Builder de Laravel y una subconsulta o `joinSub` para
el último estado por solicitud. Se emplea el identificador serial de
`estado_solicitud` como orden de historia, igual que el flujo de persona
usa el último `estado_documento`.

La consulta se limitará a convocatorias vigentes. Una convocatoria se considera
vigente cuando la fecha local actual de México se encuentra entre
`conv_fecha_inicio_registro` y `conv_fecha_fin`, inclusive. Se usa el fin del
proceso completo y no `conv_fecha_fin_registro`, porque la revisión documental
continúa después de cerrar el registro. La convocatoria 2026 ya contiene ambos
límites; las convocatorias futuras deberán capturarlos para aparecer en la
bandeja.

Si dos comunicaciones del mismo tipo aparecieran por error, el servicio elegirá
la de mayor `comu_id_comunicacion` y no duplicará la solicitud en la respuesta.

## Archivos previstos

| Archivo | Cambio propuesto |
| --- | --- |
| `app/Support/Admin/ConsultaPreRegistros.php` | Nuevo servicio de lectura y normalización. |
| `app/Http/Controllers/Admin/PersonaController.php` | `index()` y, si se aprueba B, `show()` consumen el servicio en lugar del mock. |
| `resources/views/admin/preregistro-detalle.blade.php` | Sólo con B: estado de sólo lectura y acciones no disponibles. |
| `tests/Feature/...` | Cobertura de acceso, consultas y contrato de respuesta cuando exista una base temporal preparada. |

`PreRegistroDatosPrueba` no se borrará en esta entrega mientras
`DocumentoController` y los POST administrativos todavía lo usen. Quedará sin
uso por la bandeja real y se retirará cuando se migre el flujo de revisión
documental completo.

## Casos de prueba

1. En desarrollo, la bandeja conserva el acceso temporal abierto definido por
   las rutas actuales.
2. La bandeja muestra únicamente solicitudes de convocatorias vigentes con
   último estado En revisión, Aprobada o Rechazada, en orden descendente.
3. Una solicitud con historial Pre-registro y después En revisión se muestra
   sólo una vez y con En revisión.
4. Una solicitud de una convocatoria fuera del intervalo vigente no aparece.
5. Solicitudes con comunicaciones o datos académicos ausentes no rompen el
   listado ni la ficha.
6. La ficha usa `soli_id_solicitud`, devuelve 404 para un ID inexistente y no
   expone datos de una solicitud distinta.
7. Los cuatro filtros actuales continúan operando sobre los datos reales.
8. Se validan `php artisan route:list`, `php artisan view:cache` y pruebas en
   una base temporal independiente, nunca mediante los scripts sobre `suif`.

## Decisiones que requieren aprobación

1. Confirmado: durante desarrollo no se incorpora todavía `auth` ni
   middleware; queda como requisito de despliegue.
2. Confirmado: el alcance incluye A+B, tabla y ficha de sólo lectura.
3. Confirmado: sólo se muestran convocatorias vigentes, usando el intervalo
   completo del proceso.
4. Confirmado: la interfaz muestra exactamente `Aprobada`, como lo define el
   catálogo actual de base de datos.

## Cuenta administrativa futura

Cuando se habilite la autenticación administrativa, el equipo tendrá que
definir cómo se crea la primera cuenta con rol `Administrador`. Actualmente el
catálogo sí contiene el rol, pero no hay una pantalla, comando ni seeder para
crear un usuario administrativo.

El proceso autorizado significa acordar una sola vía controlada para ese alta,
por ejemplo un seeder exclusivo para una base local o un comando Laravel que
reciba la clave fuera de Git. No se deben versionar credenciales ni insertar el
usuario mediante una consulta improvisada sobre datos reales. Esta decisión se
pospone porque la autenticación queda fuera del alcance actual.

## Fuera de alcance explícito

- Aprobar, rechazar o interrumpir solicitudes.
- Revisar, previsualizar o servir documentos.
- Guardar observaciones, plazos o notificaciones.
- Pagos, sedes, resultados, certificados y personas registradas.
- Cambios de esquema, scripts SQL, migraciones o datos de producción.
