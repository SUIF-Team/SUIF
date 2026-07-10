/*
 * Autor: MarnueLgh
 * Fecha: 09/07/2026
 * Versión: 1.0
 * Descripción: Funciones Javascript vanilla y controladores de eventos interactivos.
 */

// Agregar fondo oscuro al navbar al hacer scroll
document.addEventListener('DOMContentLoaded', function () {
	const navbar = document.querySelector('.navbar');

	if (navbar) {
		window.addEventListener('scroll', function () {
			if (window.scrollY > 50) {
				navbar.classList.add('bg-dark');
			} else {
				navbar.classList.remove('bg-dark');
			}
		});
	}
});
