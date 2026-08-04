# Diseño: etiquetas genéricas de navegación administrativa

## Objetivo

Uniformar las etiquetas del componente de navegación administrativa para que comuniquen la posición dentro del flujo sin nombrar una bandeja específica y puedan reutilizarse en pantallas futuras.

## Reglas

- Las bandejas que vuelven al dashboard mostrarán “Volver al dashboard”.
- Las pantallas intermedias del flujo mostrarán “Atrás”.
- Las pantallas terminales, donde no existe un siguiente paso de avance, mostrarán “Volver a la bandeja”.
- Las etiquetas accesibles usarán el mismo texto genérico.

## Alcance

- Mantener `BackNavigation` con “Atrás” como valor predeterminado reutilizable.
- Configurar explícitamente las tres bandejas administrativas para el regreso al dashboard.
- Ajustar los contextos de retorno de detalle y documentación para “Atrás”, sin cambiar sus rutas de origen ya validadas; el detalle de pago también se clasifica como pantalla intermedia y usará “Atrás” en ambos textos.
- Ajustar los resultados de pre-registro, documentación y pago a “Volver a la bandeja”.
- No cambiar destinos, parámetros de origen ni la navegación funcional existente.

## Verificación

Se comprobarán todas las instancias de `BackNavigation`: pre-registros, participantes registrados, pagos, detalle de pre-registro, documentación, detalle de pago y notificaciones de resultado. La revisión confirmará que no permanezcan etiquetas que indiquen el nombre de una bandeja.
