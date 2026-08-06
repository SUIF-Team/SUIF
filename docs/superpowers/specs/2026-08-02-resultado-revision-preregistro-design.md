# Resultado de revisión documental de pre-registros

## Alcance

Agregar el cierre del flujo administrativo de revisión documental sin integrar base de datos ni crear migraciones. El administrador guarda las resoluciones de todos los documentos requeridos y llega a una única pantalla final.

## Flujo y datos

1. La vista de documentación usa un formulario `POST` con protección CSRF. Vue sincroniza cada decisión reactiva en campos `documentos[id]`; no se habilita el guardado mientras falte una decisión.
2. El controlador administrativo compara las claves recibidas exactamente contra los identificadores requeridos en `PreRegistroDatosPrueba`, sin faltantes ni identificadores extra, y valida que cada resolución sea `aprobado` o `rechazado`.
3. Las decisiones y el resultado derivado se guardan temporalmente en la sesión bajo la clave existente del pre-registro.
4. El resultado se calcula exclusivamente en el servidor: todos aprobados producen `aprobado`; cualquier rechazo produce `revision`.
5. El `POST` redirige a `admin.documentos.resultado` (`GET /admin/documentos/{id}/resultado`). La acción `resultado()` valida al persona y exige que haya una revisión previamente guardada; de no existir redirige a `admin.documentos.show` y nunca construye un resultado por defecto. La vista final lee sólo la sesión, por lo que una recarga no reenvía ni duplica el guardado.

## Interfaz

La nueva vista reutilizable conserva el layout administrativo, navbar, footer, tarjeta de la persona, datos dinámicos y el progreso de dos etapas. Recibe variables de resultado para el título y los estados:

- Aprobado: `SOLICITUD APROBADA`; pre-registro y documentación en `Completado`.
- Revisión: `SOLICITUD EN REVISIÓN`; pre-registro en `Completado` y documentación en `En revisión`.

Los colores y clases se reutilizan de los estados ya existentes del módulo: paso completado para verde y paso actual para dorado. No se agregan nuevos tokens ni colores de estado. El mensaje final usa las clases visuales ya disponibles para esos dos tratamientos.

El componente `BackNavigation` se configura con el texto, destino y una nueva prop de etiqueta accesible. Muestra `Volver a la bandeja`, apunta a la ruta ya existente `admin.personas.index` y aplica el valor de la prop al atributo `aria-label` del enlace.

## Validación

Se verifican las rutas, la validación de solicitudes, el cálculo de ambos resultados, las pruebas disponibles y la presentación responsive en anchos de móvil, tablet y escritorio. No habrá una tercera resolución ni un parámetro URL que altere el resultado.

## Pendiente de base de datos

La sesión sustituye temporalmente el almacenamiento persistente. Cuando exista la base de datos, el servicio temporal deberá sustituirse por un repositorio o modelo, conservando la misma regla de cálculo en el servidor.
