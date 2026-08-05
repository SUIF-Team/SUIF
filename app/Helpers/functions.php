<?php

if (!function_exists('asset_versionado')) {
	/**
	 * Devuelve la URL de un archivo de public/ con la marca de tiempo de su
	 * última modificación. Obliga al navegador a descargarlo de nuevo cuando
	 * cambia, sin que el usuario tenga que borrar su caché.
	 */
	function asset_versionado($ruta)
	{
		$archivo = public_path($ruta);

		if (is_file($archivo)) {
			return asset($ruta).'?v='.filemtime($archivo);
		}

		return asset($ruta);
	}
}