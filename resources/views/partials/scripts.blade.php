<?php
/*
 * Autor: MarnueLgh
 * Fecha: 09/07/2026
 * Versión: 1.2
 * Descripción: Librerías externas y scripts propios del proyecto.
 *
 * Vue se carga aquí y no en cada vista: antes se repetía la misma etiqueta en
 * una veintena de pantallas y ninguna llevaba integrity, así que un CDN
 * comprometido ejecutaba código arbitrario dentro del panel administrativo.
 * Los componentes compartidos vienen detrás para que las apps de página, que
 * se insertan más abajo con @yield/@stack, ya encuentren window.SUIF y
 * window.SUIFComponentes listos.
 */
?>
<!-- Bootstrap 5 JS Bundle (con Popper para dropdowns) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Vue 3 (build global de producción) -->
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js" integrity="sha384-arPHRzOKPl8g3Rbe/cQBWYPnq4HcxfPFSFWD3qvI/hc2XQf+4GkVqkOlWgjN5mD3" crossorigin="anonymous"></script>

<!-- Scripts propios del proyecto -->
<script src="{{ asset_versionado('assets/js/main.js') }}"></script>

<!-- Componentes Vue compartidos -->
<script src="{{ asset_versionado('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset_versionado('assets/js/components/Alertas.js') }}"></script>
<script src="{{ asset_versionado('assets/js/components/FormularioAjax.js') }}"></script>
