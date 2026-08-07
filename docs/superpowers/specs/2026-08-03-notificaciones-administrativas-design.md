# Diseño: notificaciones administrativas reutilizables

## Objetivo

Unificar las notificaciones de aprobación, revisión y rechazo de los flujos administrativos de pre-registro, documentación y pago. La interfaz reutilizará el layout administrativo, la información dinámica de la persona, las step cards, el componente de regreso y la hoja de estilos existente, sin duplicar pantallas por resultado o contexto.

## Alcance

- Crear una única vista Blade de notificación que recibe un arreglo de configuración preparado por el controlador.
- Convertir el resultado actual de documentación (`aprobado` y `revision`) en consumidores de dicha vista.
- Implementar los rechazos de pre-registro, interrupción de documentación y rechazo de pago mediante solicitudes `POST` protegidas por CSRF.
- Persistir el estado únicamente en la sesión, conforme al mecanismo temporal existente; no se crearán migraciones ni se tocará la base de datos.
- Normalizar el diseño de todos los resultados, incluido un contenedor de mensaje central compacto, evitando el espacio vertical sobrante del prototipo.

## Contrato de la vista

La vista recibirá `persona` y `notificacion`. `persona` se normalizará en cada controlador a `{ iniciales, nombre_completo, curp, entidad_federativa }`, incluso para pago, cuyo origen hoy conserva sólo `nombre_completo` e `iniciales`. `notificacion` contendrá:

- `titulo`: `SOLICITUD APROBADA`, `SOLICITUD EN REVISIÓN`, `SOLICITUD RECHAZADA` o `PAGO RECHAZADO`.
- `estado_general`: texto de la insignia de perfil.
- `clase_estado`: variante visual de la insignia.
- `pasos`: lista ordenada de `{ titulo, estado, clase, actual }`. El paso que originó la resolución será `actual` para aprobación, revisión y rechazo; los resultados de pago marcan `Pago` y los de pre-registro o documentación marcan su paso correspondiente.
- `ruta_regreso`, `etiqueta_regreso` y `etiqueta_regreso_accesible`.
- `contexto`: `preregistro`, `documentacion` o `pago`, disponible como información semántica y para futuras variaciones justificadas.

La plantilla sólo renderiza esta configuración. No calcula resultados ni determina rutas, para conservar separación MVC.

## Reglas de estado

| Contexto | Título | Pasos |
| --- | --- | --- |
| Pre-registro rechazado | `SOLICITUD RECHAZADA` | Pre-registro: Rechazado (rojo); Documentación: Pendiente (gris). |
| Documentación aprobada | `SOLICITUD APROBADA` | Pre-registro y Documentación: Completado (verde). |
| Documentación en revisión | `SOLICITUD EN REVISIÓN` | Pre-registro: Completado (verde); Documentación: En revisión (dorado). |
| Documentación interrumpida | `SOLICITUD RECHAZADA` | Pre-registro: Completado (verde); Documentación: Rechazado (rojo). |
| Pago rechazado | `PAGO RECHAZADO` | Pre-registro y Documentación: Completado (verde); Pago: Rechazado (rojo). |

Todos los rechazos presentan el estado general `Rechazado`. Las notificaciones de pre-registro y documentación vuelven a `admin.personas.index`; la de pago vuelve a `admin.pagos.index`.

## Flujo y rutas

1. Se agregará una ruta `POST` nombrada para rechazar pre-registro y se usará la ruta de resultado de pre-registro, también nombrada y limitada a `GET`. El botón existente se reemplazará por un formulario con `@csrf`.
2. Se agregará una ruta `POST` nombrada para interrumpir documentación. La ruta de resultado documental existente aceptará también el resultado `rechazado`; no se construirá un resultado desde parámetros de URL.
3. El rechazo de pago conservará su nombre de ruta existente, pero se limitará a `POST`; no subsistirá el `GET` ni el enlace temporal. La vista enviará un formulario con `@csrf`.
4. Se agregará una ruta de resultado de pago, nombrada y limitada a `GET`. Tras validar la elegibilidad del pago, el `POST` guarda el resultado temporal y redirige a dicha ruta, que repite las comprobaciones antes de renderizar.
5. Los resultados `GET` sólo se muestran cuando la sesión contiene el estado esperado para su contexto; de lo contrario redirigen a la pantalla administrativa correspondiente.
6. El flujo de pago no reutilizará datos del pre-registro: su controlador adaptará el arreglo de `PagoDatosPrueba` al contrato común de la vista.

## Datos temporales y validación

Los controladores comprueban que el identificador exista antes de escribir en sesión. Los flujos de pre-registro usan la clave de sesión existente `suif.admin.preregistro.{id}`. El pago usa una clave de sesión específica para evitar mezclar dominios. El resultado sólo se muestra si su estado temporal corresponde a un resultado permitido; de lo contrario redirige a la pantalla de revisión adecuada.

Las transiciones se validan en servidor: un pre-registro sólo puede aceptarse o rechazarse desde `En revisión`; la documentación sólo puede abrirse o interrumpirse después de completar el pre-registro; y un pago sólo puede rechazarse cuando sigue disponible para revisión. Una acción repetida o contradictoria no sobrescribe el resultado y vuelve con un aviso. Ambas bandejas leerán el estado temporal y actualizarán su texto y disponibilidad de acción: los pre-registros y pagos rechazados ya no aparecerán disponibles para revisión.

Las rutas administrativas de escritura requieren autenticación y autorización administrativa antes de habilitarse fuera del entorno temporal. El proyecto aún no dispone de una autenticación ni roles funcionales; por ello la implementación no inventará una política. La restauración de `auth` y una autorización de rol administrativo son un prerrequisito de despliegue y se documentarán como bloqueo de producción. Mientras la aplicación conserva deliberadamente sus rutas administrativas abiertas para el demo, los cambios sólo operarán sobre datos de prueba guardados en sesión.

No se añade persistencia, migraciones, SQL ni dependencias. La autorización continuará sujeta a las rutas administrativas actuales; antes de producción deberá cubrirse con middleware y autorización por rol.

## Interfaz, accesibilidad y responsive

Se preservan `layouts.admin`, navbar, footer, fondo institucional, `BackNavigation`, tipografías, tarjetas y estilos compartidos de `admin-preregistro.css`. Se incorporan variantes explícitas `--rechazado` para insignia, step card y mensaje central, distintas de `--pendiente`; el mapeo de las cuatro variantes es completado (verde), revisión (dorado), rechazado (rojo) y pendiente (gris). Los estados se comunican por texto además de color. El contenedor de resultado reducirá su `min-height` para conservar jerarquía sin margen blanco excesivo. Las step cards siguen en dos o tres columnas cuando cabe el contenido y pasan a una columna en móvil, incluido pago; los focos visibles existentes se mantienen. El mensaje de resultado tendrá `role="status"` y el título del documento reflejará el resultado.

## Verificación posterior en VM

- Consultar la lista de rutas y cachear vistas.
- Probar `POST` con y sin token CSRF para los tres rechazos.
- Probar transiciones contradictorias o repetidas y confirmar que no cambian el estado.
- Probar resultados aprobados, en revisión y rechazados, incluidos refrescos, bandejas y destinos de regreso.
- Verificar un pago no elegible, ID inexistente y las vistas en móvil, tablet y escritorio.
- Verificar autenticación y autorización por rol antes de desplegar la funcionalidad administrativa fuera del demo.
