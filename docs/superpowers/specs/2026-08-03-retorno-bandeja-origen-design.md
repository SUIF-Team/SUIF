# Diseño: retorno a la bandeja de origen

## Objetivo

Conservar la bandeja administrativa desde la que se abre un expediente, para que las pantallas de detalle, documentación y resultado regresen siempre a la misma bandeja y no a la bandeja de pre-registros por defecto.

## Alcance

- Introducir un origen administrativo validado con dos valores permitidos: `preregistros` y `participantes_registrados`.
- Generar los enlaces “Ver expediente” de cada bandeja con el origen correspondiente.
- Propagar ese origen en redirecciones, formularios y enlaces de los flujos de pre-registro y documentación.
- Calcular en un único soporte la normalización y el contexto completo de regreso: `origen`, ruta, etiqueta y etiqueta accesible para `admin.participantes.index` o `admin.participantes.registrados.index`.
- Mantener el flujo de pagos sin cambios funcionales, ya que su detalle ya vuelve a `admin.pagos.index`.

## Flujo

1. La bandeja de pre-registros abre el expediente con `origen=preregistros`; la de participantes registrados con `origen=participantes_registrados`.
2. Cada endpoint GET o POST del flujo normaliza el valor mediante el mismo soporte antes de mostrar una vista o redirigir. Cualquier valor ausente o no permitido se convierte a `preregistros`; nunca se usa como URL o nombre de ruta directo.
3. El detalle de pre-registro recibe el origen y arma su barra de regreso desde el soporte centralizado.
4. Aceptar, rechazar, abrir documentación, validar, interrumpir y mostrar resultados preservan únicamente el origen normalizado mediante parámetros o campos ocultos protegidos por CSRF.
5. Las redirecciones de protección o de estado ya resuelto conservan el origen: detalle hacia documentación o resultado; documentación hacia detalle o resultado; y todos los POST hacia documentación o resultado.
6. La validación fallida de documentos no dependerá de `Referer` ni de `back()`: reconstruirá con una ruta nombrada la URL de documentación y su origen normalizado, para que el formulario y sus campos ocultos se vuelvan a renderizar con el mismo contexto.
7. Documentación y notificaciones reciben del soporte centralizado la ruta y las etiquetas correctas. `NotificacionResultado::paraPago()` no recibe ni modifica este contexto.

## Seguridad y compatibilidad

El origen se limita a una lista blanca y sólo genera rutas nombradas internas. Los enlaces existentes sin parámetro conservan el comportamiento de pre-registros. No se duplica ninguna pantalla ni se crean rutas de detalle alternativas.

## Verificación en VM

Se comprobará que cada flujo iniciado desde ambas bandejas vuelva al origen correcto desde: detalle inicial, documentación, resultado de documentación, rechazo y rutas de protección. También se verificará que un valor de origen inválido vuelva de forma segura a pre-registros.
