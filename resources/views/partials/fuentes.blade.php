{{-- Fuentes del sistema: Merriweather para titulos, Open Sans para lo demas.
     Una sola peticion para auth, persona y admin, con los tres pesos que usa
     la escala (Merriweather 700; Open Sans 400/600/700), mas Raleway 400 para
     el nombre del sistema en el encabezado. Las familias van en orden
     alfabetico: la API css2 rechaza la peticion si no lo estan. La landing
     conserva su propio enlace porque aun no migra a esta identidad. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Open+Sans:wght@400;600;700&family=Raleway:wght@400&display=swap" rel="stylesheet">
