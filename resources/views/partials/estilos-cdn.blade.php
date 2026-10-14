{{-- Hojas de estilo externas, con integrity como las librerías de
     partials/scripts: si el CDN sirviera otro archivo, el navegador lo
     descarta en vez de aplicarlo. crossorigin hace falta para que la
     comprobación corra, y Font Awesome lo necesita además para sus fuentes.
     Los hashes son los que publican Bootstrap y cdnjs para estas versiones;
     al cambiar de versión hay que cambiar también el hash. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
