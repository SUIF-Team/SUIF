# Dashboard administrativo de SUIF

## Objetivo

Integrar una primera versión visual y funcional del dashboard administrativo
en Laravel 5.5 y PHP 7.1, usando el prototipo aprobado como referencia y los
componentes institucionales existentes.

## Arquitectura

- `Admin\\DashboardController@index` entregará un resumen temporal con cuatro
  indicadores en cero y el catálogo de acciones del panel.
- `resources/views/admin/dashboard.blade.php` presentará el resumen, las
  acciones y el cierre de sesión, sin cifras ni lógica de negocio fija.
- `resources/views/layouts/admin.blade.php` continuará aportando navbar,
  footer, tipografías, fondo institucional y scripts compartidos.
- `public/assets/css/pages/admin-dashboard.css` contendrá estilos aislados y
  responsive del dashboard.

## Datos y acciones

El resumen tendrá las claves `personas_registradas`,
`preregistros_pendientes`, `pagos_pendientes` y
`certificados_pendientes`, todas inicializadas en cero mientras no exista la
persistencia aprobada.

Las siete acciones del prototipo se conservarán. Ninguna se habilitará por el
momento: aunque algunas rutas están declaradas, sus controladores no tienen
acción `index()` y navegar a ellas produciría errores. Se mostrarán como
"Próximamente", sin URL navegable. No se agregarán accesos para revisión de
documentos ni gestión de resultados: pertenecen al alcance administrativo
documentado, pero no aparecen en el prototipo y requieren aprobación aparte.
El cierre de sesión usará un formulario `POST` con
`action="{{ route('logout') }}"` y `@csrf`.

## Límites y validación

No se crearán migraciones, modelos, consultas, roles ni módulos
administrativos. Durante el desarrollo local, el grupo administrativo podrá
prescindir temporalmente de `auth` para revisar sus pantallas por URL; deberá
restaurarse antes de cualquier despliegue. La autorización por rol sigue
pendiente. Se verificará con `route:list`, `view:clear`, revisión de diff y
renderizado local cuando el entorno esté disponible.
