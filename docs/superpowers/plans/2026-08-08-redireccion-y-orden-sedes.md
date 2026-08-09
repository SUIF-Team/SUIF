# Redirección y orden del formulario de sedes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Regresar a la bandeja de sedes al crear o editar una sede y presentar los campos de horario, fechas y aforo en el orden solicitado.

**Architecture:** El controlador administrativo conservará la creación, actualización y validación existentes; sólo cambiará las rutas de redirección posteriores y conservará los flashes de éxito. La vista Blade compartida reordenará sus nodos de campos dentro de la cuadrícula existente, que ya define dos columnas en escritorio y una en móvil.

**Tech Stack:** PHP 8.4, Laravel 13, Blade, PHPUnit/Laravel feature tests y CSS existente.

## Global Constraints

- No agregar dependencias Composer, npm, bundlers ni frameworks.
- Usar las rutas nombradas existentes en `routes/web.php`; no modificar rutas ni scripts SQL.
- Mantener textos de interfaz en español, PSR-12, CSRF, validación del servidor y los atributos HTML existentes.
- No editar los cambios ajenos en `public/assets/css/pages/persona-preregistro.css`, `resources/views/home/index.blade.php` ni `resources/views/persona/preregistro.blade.php`.
- Ejecutar las verificaciones Laravel con el PHP instalado en la VM; no usar Docker.

---

## Estructura de archivos

- `app/Http/Controllers/Admin/SedeController.php`: decide la ruta de regreso y el mensaje flash después de guardar.
- `resources/views/admin/sede-formulario.blade.php`: conserva el formulario compartido y cambia el orden DOM de los cinco campos afectados.
- `tests/Feature/GestionSedesTest.php`: protege las redirecciones, avisos, persistencia y orden del formulario.

### Task 1: Redirección y orden accesible de campos de sede

**Files:**
- Modify: `tests/Feature/GestionSedesTest.php:17-99`
- Modify: `app/Http/Controllers/Admin/SedeController.php:42-73`
- Modify: `resources/views/admin/sede-formulario.blade.php:84-109`

**Interfaces:**
- Consumes: rutas `admin.sedes.store`, `admin.sedes.update`, `admin.sedes.create`, `admin.sedes.edit` y la sesión flash `success` de Laravel.
- Produces: respuestas `302` a `admin.sedes.index` después de creación o edición válida; formularios con el orden `Hora de inicio`, `Fecha de inicio`, `Hora de fin`, `Fecha de fin`, `Aforo máximo`.

- [ ] **Step 1: Escribir las pruebas que fallan**

En `test_crud_requiere_administrador_y_guarda_nombre_independiente_de_direccion`, cambiar la expectativa posterior al `POST` por:

```php
$respuesta
    ->assertRedirect(route('admin.sedes.index'))
    ->assertSessionHas('success', 'La sede se creó correctamente.');
```

En el caso válido de `test_edicion_programa_una_sede_pendiente_y_valida_el_intervalo`, cambiar la cadena del `PUT` por:

```php
->assertRedirect(route('admin.sedes.index'))
->assertSessionHas('success', 'La sede se actualizó correctamente.');
```

Añadir un método que compruebe el orden en ambas pantallas:

```php
public function test_formularios_de_sede_muestran_horario_fechas_y_aforo_en_el_orden_solicitado(): void
{
    $orden = [
        'Hora de inicio *',
        'Fecha de inicio *',
        'Hora de fin *',
        'Fecha de fin *',
        'Aforo máximo *',
    ];

    $this->actingAs(Usuario::findOrFail(2))
        ->get(route('admin.sedes.create'))
        ->assertOk()
        ->assertSeeInOrder($orden);

    [$idSede] = $this->crearSedeProgramada(2);

    $this->actingAs(Usuario::findOrFail(2))
        ->get(route('admin.sedes.edit', $idSede))
        ->assertOk()
        ->assertSeeInOrder($orden);
}
```

- [ ] **Step 2: Ejecutar la prueba para verificar que falla**

Run: `php artisan test tests/Feature/GestionSedesTest.php`

Expected: fallo de redirección porque `store` aún devuelve `admin.sedes.edit`; y fallo de orden porque el formulario aún inicia con `Aforo máximo`.

- [ ] **Step 3: Implementar el cambio mínimo**

En `SedeController::store`, conservar `$gestion->crear($this->validar($request));` y cambiar sólo la respuesta por:

```php
return redirect()
    ->route('admin.sedes.index')
    ->with('success', 'La sede se creó correctamente.');
```

En `SedeController::update`, conservar el bloque `try/catch` y cambiar sólo la respuesta de éxito por:

```php
return redirect()
    ->route('admin.sedes.index')
    ->with('success', 'La sede se actualizó correctamente.');
```

En `sede-formulario.blade.php`, dentro de `admin-sedes-formulario-grid`, conservar exactamente los mismos `id`, `name`, `type`, atributos y expresiones `old()`, pero ubicar los bloques en este orden: `hora_inicio`, `fecha_inicio`, `hora_fin`, `fecha_fin`, `cupo`. No cambiar `admin-sedes-formulario-grid`, ya que sus dos columnas y su regla móvil existente producen la cuadrícula requerida.

- [ ] **Step 4: Ejecutar la prueba para verificar que pasa**

Run: `php artisan test tests/Feature/GestionSedesTest.php`

Expected: PASS, 5 pruebas sin fallos; se conserva la persistencia de sede y evaluación, y se validan redirecciones, flashes y orden de formularios.

- [ ] **Step 5: Verificar integración Laravel y revisar el diff**

Run:

```bash
php artisan route:list --name=admin.sedes
php artisan view:cache
php artisan test tests/Feature/GestionSedesTest.php
git diff --check
git diff -- app/Http/Controllers/Admin/SedeController.php resources/views/admin/sede-formulario.blade.php tests/Feature/GestionSedesTest.php
```

Expected: las seis rutas administrativas de sedes permanecen disponibles; las vistas compilan; las cinco pruebas pasan; y el diff sólo contiene las redirecciones, el orden de los cinco campos y sus regresiones.

- [ ] **Step 6: Confirmar el cambio de implementación**

```bash
git add app/Http/Controllers/Admin/SedeController.php resources/views/admin/sede-formulario.blade.php tests/Feature/GestionSedesTest.php
git commit -m "fix: volver a la bandeja de sedes al guardar"
```
