{{--
    partials/alertas.blade.php
    Migrado desde: app/views/partials/alertas.php
    Componente para mostrar mensajes flash de éxito, error o advertencia.
    Usa la sesión de Laravel: session('success'), session('error'), session('warning').
--}}
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
