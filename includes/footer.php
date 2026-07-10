<?php
/**
 * REUSABLE FOOTER COMPONENT COPY
 * 
 * Este archivo contiene una copia completa de la estructura HTML/PHP,
 * estilos CSS, comportamiento JS y dependencias necesarias para replicar 
 * el footer del sitio web en otro proyecto.
 * 
 * CONTENIDO:
 * 1. Dependencias externas (Bootstrap, Font Awesome, etc.)
 * 2. Estructura HTML/PHP (del footer y botón de volver arriba)
 * 3. Estilos CSS (variables de color y clases de diseño)
 * 4. JavaScript (comportamiento del botón de volver arriba)
 * 5. Recursos gráficos requeridos
 */
?>

<!-- ==========================================
     1. DEPENDENCIAS EXTERNAS (Agregar en el <head> y antes de cerrar </body>)
     ==========================================
     Para que el footer se visualice correctamente, asegúrate de tener:
     - Bootstrap 5 (CSS y JS Bundle)
     - Bootstrap Icons
     - Font Awesome 5 (o superior, para iconos de redes sociales)
     - jQuery (requerido por el script del botón "volver arriba")

     Ejemplo de CDN a incluir en tu nuevo sitio:
     
     <!-- En el <head> -->
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
     <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
     <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">

     <!-- Antes de cerrar </body> -->
     <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
-->


<!-- ==========================================
     2. ESTRUCTURA HTML / PHP (Copia exacta de footer.php y volver_arriba_btn.php)
     ========================================== -->

<!-- Footer Start -->
<footer class="mt-auto">
    <!-- Sección superior: visitas, redes sociales, logo UNAM -->
    <div class="footer-top">
        <div class="container">
            <div class="row align-items-center">

                <!-- Número de visitas (Código PHP del contador original) -->
                <div class="col-md-4 text-center text-md-start mb-3 mb-md-0">
                    <div class="footer-visitas">
                        <p class="footer-visitas-item">&rarr; Número de visitas: <strong>
                                <?php
                                /*
                                 * Contador de visitas SEFCA
                                 * Compatible con PHP 5.3.
                                 *
                                 * Cuenta solo una visita por entrada de usuario.
                                 * Evita que el contador suba al hacer refresh.
                                 */

                                $archivo_contador = __DIR__ . "/contador.txt";
                                $archivo_visitantes = __DIR__ . "/contador_visitantes.txt";

                                /*
                                 * Para contar cada 30 minutos:
                                 * $tiempo_entrada = 1800;
                                 *
                                 * Para contar una vez por día, cambia a:
                                 * $tiempo_entrada = 86400;
                                 */
                                $tiempo_entrada = 86400;

                                $pagina_actual = basename($_SERVER["PHP_SELF"]);
                                $visitas = 0;

                                /*
                                 * Crear carpeta de destino si no existe.
                                 */
                                if (!is_dir(dirname($archivo_contador))) {
                                    mkdir(dirname($archivo_contador), 0755, true);
                                }

                                /*
                                 * Crear archivos si no existen.
                                 */
                                if (!file_exists($archivo_contador)) {
                                    file_put_contents($archivo_contador, "0", LOCK_EX);
                                }

                                if (!file_exists($archivo_visitantes)) {
                                    file_put_contents($archivo_visitantes, "", LOCK_EX);
                                }

                                /*
                                 * Leer visitas actuales.
                                 */
                                $contenido_contador = file_get_contents($archivo_contador);
                                $visitas = (int) $contenido_contador;

                                /*
                                 * Solo incrementar en index.php.
                                 */
                                if ($pagina_actual == "index.php") {

                                    /*
                                     * Crear una huella básica del usuario.
                                     * Esto evita depender de sesiones/cookies, porque este footer normalmente
                                     * se carga después de que ya empezó el HTML.
                                     */
                                    $ip_usuario = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
                                    $agente_usuario = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";
                                    $idioma_usuario = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : "";

                                    $huella_usuario = md5($ip_usuario . "|" . $agente_usuario . "|" . $idioma_usuario);

                                    $ahora = time();
                                    $limite = $ahora - $tiempo_entrada;

                                    $usuario_ya_contado = false;
                                    $visitantes_validos = array();

                                    /*
                                     * Abrir archivo de visitantes y bloquearlo para evitar conteos dobles
                                     * si hay varias visitas simultáneas.
                                     */
                                    $fp_visitantes = fopen($archivo_visitantes, "c+");

                                    if ($fp_visitantes) {
                                        if (flock($fp_visitantes, LOCK_EX)) {

                                            $contenido_visitantes = "";

                                            rewind($fp_visitantes);

                                            while (!feof($fp_visitantes)) {
                                                $contenido_visitantes .= fread($fp_visitantes, 8192);
                                            }

                                            $lineas = explode("\n", $contenido_visitantes);

                                            foreach ($lineas as $linea) {
                                                $linea = trim($linea);

                                                if ($linea == "") {
                                                    continue;
                                                }

                                                $partes = explode("|", $linea);

                                                if (count($partes) != 2) {
                                                    continue;
                                                }

                                                $huella_guardada = $partes[0];
                                                $tiempo_guardado = (int) $partes[1];

                                                /*
                                                 * Mantener solo registros recientes.
                                                 */
                                                if ($tiempo_guardado >= $limite) {
                                                    $visitantes_validos[] = $huella_guardada . "|" . $tiempo_guardado;

                                                    if ($huella_guardada == $huella_usuario) {
                                                        $usuario_ya_contado = true;
                                                    }
                                                }
                                            }

                                            /*
                                             * Si el usuario no ha sido contado durante esta entrada,
                                             * se registra y se incrementa el contador.
                                             */
                                            if (!$usuario_ya_contado) {
                                                $visitantes_validos[] = $huella_usuario . "|" . $ahora;

                                                /*
                                                 * Incrementar contador con bloqueo.
                                                 */
                                                $fp_contador = fopen($archivo_contador, "c+");

                                                if ($fp_contador) {
                                                    if (flock($fp_contador, LOCK_EX)) {
                                                        $contenido_actual = "";

                                                        rewind($fp_contador);

                                                        while (!feof($fp_contador)) {
                                                            $contenido_actual .= fread($fp_contador, 8192);
                                                        }

                                                        $visitas = (int) $contenido_actual;
                                                        $visitas++;

                                                        rewind($fp_contador);
                                                        ftruncate($fp_contador, 0);
                                                        fwrite($fp_contador, (string) $visitas);
                                                        fflush($fp_contador);

                                                        flock($fp_contador, LOCK_UN);
                                                    }

                                                    fclose($fp_contador);
                                                }
                                            }

                                            /*
                                             * Guardar lista limpia de visitantes recientes.
                                             */
                                            rewind($fp_visitantes);
                                            ftruncate($fp_visitantes, 0);

                                            if (count($visitantes_validos) > 0) {
                                                fwrite($fp_visitantes, implode("\n", $visitantes_validos) . "\n");
                                            }

                                            fflush($fp_visitantes);
                                            flock($fp_visitantes, LOCK_UN);
                                        }

                                        fclose($fp_visitantes);
                                    }
                                }

                                echo number_format($visitas);
                                ?>
                            </strong></p>
                        <p class="footer-visitas-item">&rarr; Desde: 01/03/2026</p>
                    </div>
                </div>

                <!-- Redes sociales -->
                <div class="col-md-4 text-center mb-3 mb-md-0">
                    <p class="footer-redes-titulo">Síguenos en</p>
                    <div class="footer-redes">
                        <a href="https://www.facebook.com/SEFCAUNAM" target="_blank" rel="noopener noreferrer"
                            class="footer-redes-enlace" aria-label="Facebook">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="https://www.youtube.com/@SEFCA" target="_blank" rel="noopener noreferrer"
                            class="footer-redes-enlace" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>

                <!-- Logos UNAM con máscara CSS (requiere imágenes locales en img/) -->
                <div class="col-md-4">
                    <div class="d-flex flex-nowrap justify-content-center justify-content-md-end align-items-center gap-3">
                        <div class="footer-logo-dorado"
                            style="width: 150px; -webkit-mask-image: url('img/unam_gran_universidad_dorado.png'); mask-image: url('img/unam_gran_universidad_dorado.png');"
                            role="img" aria-label="UNAM - Nuestra gran Universidad">
                        </div>

                        <div class="footer-logo-dorado"
                            style="width: 150px; -webkit-mask-image: url('img/475_logo_dorado.png'); mask-image: url('img/475_logo_dorado.png');"
                            role="img" aria-label="475+ años de historia">
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Sección inferior: copyright y legales -->
    <div class="container-fluid copyright text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-2 text-center text-md-start mb-3 mb-md-0">
                    Hecho en México
                    <br>
                    D.R. &copy; <?php echo date("Y"); ?>
                </div>
                <div class="col-md-10 justify">
                    Esta página puede ser reproducida con fines no lucrativos, siempre y cuando no se mutile, se cite la
                    fuente completa y su dirección electrónica. De otra forma requiere permiso previo por escrito de la
                    institución.
                    <a href="https://www.fca.unam.mx/docs/aviso_privacidad.pdf" target="_blank"
                        rel="noopener noreferrer">AVISO DE PRIVACIDAD</a>.
                    Sitio web administrado por el Centro de Informática de la Facultad de Contaduría y
                    Administración (<a href="https://cifca.fca.unam.mx/" target="_blank"
                        rel="noopener noreferrer">CIFCA</a>).
                    <br>
                    <a href="https://www.fca.unam.mx/docs/permanentes/seguridad.pdf" target="_blank"
                        rel="noopener noreferrer">Documento de seguridad</a> |
                    <a href="https://www.fca.unam.mx/docs/permanentes/aws.pdf" target="_blank"
                        rel="noopener noreferrer">Instrumento jurídico</a> |
                    <a href="https://www.fca.unam.mx/docs/permanentes/aviso_simplificado.pdf">Aviso de privacidad
                        simplificado</a>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- Footer End -->

<!-- Botón Volver Arriba (volver_arriba_btn.php) -->
<a href="#inicio" class="btn btn-lg btn-gold btn-lg-square rounded-circle back-to-top custom-btn-glow">
    <i class="bi bi-arrow-up d-flex align-items-center justify-content-center"></i>
</a>


<!-- ==========================================
     3. ESTILOS CSS (Añadir a tu archivo .css o dentro de una etiqueta <style>)
     ========================================== -->
<style>
/* Variables de color personalizadas del tema original */
:root {
    --dorado-unam: #9c6e09;
    --azul-unam: #11304b;
    --secondary: #545454;
    --light: #fdf5eb;
    --dark: #1a1a1a;
    --fuente-texto: "Roboto", sans-serif;
}

/*** Estilos generales del Footer ***/
.footer .btn.btn-link {
    display: block;
    margin-bottom: 5px;
    padding: 0;
    text-align: left;
    color: var(--light);
    font-weight: normal;
    text-transform: capitalize;
    transition: 0.3s;
}

.footer .btn.btn-link::before {
    position: relative;
    content: "\f105";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    color: var(--light);
    margin-right: 10px;
}

.footer .btn.btn-link:hover {
    color: var(--dorado-unam);
    letter-spacing: 1px;
    box-shadow: none;
}

/*** Footer — Sección superior ***/
.footer-top {
    background: #f7f6f2;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    padding: 2.5rem 0;
}

.footer-visitas {
    color: var(--dark);
    font-size: 0.95rem;
}

.footer-visitas-item {
    margin-bottom: 0.25rem;
}

.footer-visitas-item strong {
    color: var(--dorado-unam);
}

.footer-redes-titulo {
    color: var(--dark);
    font-size: 1.1rem;
    font-weight: 500;
    margin-bottom: 0.75rem;
}

.footer-redes {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.footer-redes-enlace {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.18);
    background: #fff;
    color: var(--dark);
    text-decoration: none;
    transition:
        transform 0.15s ease,
        background-color 0.15s ease,
        border-color 0.15s ease;
}

.footer-redes-enlace:hover {
    transform: translateY(-2px);
    background: rgba(2, 16, 36, 0.06);
    border-color: rgba(2, 16, 36, 0.35);
}

.footer-unam-logo {
    height: 80px;
    width: auto;
    object-fit: contain;
}

/* Logos con máscara (permite colorear imágenes PNG transparentes con el color de fondo especificado) */
.footer-logo-dorado {
    height: 80px;
    width: auto;
    max-width: 45%;

    /* Pinta el fondo interno del color del sitio */
    background-color: var(--dorado-unam);

    /* Aplica la imagen transparente como un recorte (máscara) */
    -webkit-mask-size: contain;
    mask-size: contain;
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    -webkit-mask-position: center;
    mask-position: center;

    display: inline-block;
}

/*** Footer — Copyright ***/
footer {
    width: 100%;
    margin-top: auto;
}

.copyright {
    background: var(--azul-unam);
    font-size: 0.85rem;
    width: 100%;
}

.justify {
    text-align: justify !important;
}

.copyright a {
    color: var(--dorado-unam);
    text-decoration: none;
    transition: color 0.3s ease;
}

.copyright a:hover {
    color: var(--light);
    text-decoration: underline;
}

/*** Botón volver arriba (Back to top) ***/
.back-to-top {
    position: fixed;
    display: none;
    right: 30px;
    bottom: 30px;
    z-index: 99;
}

.btn-gold {
    background-color: #9c6e09 !important;
    color: #ffffff !important;
    border: none !important;
    box-shadow: 0 4px 14px rgba(156, 110, 9, 0.4) !important;
    transition: all 0.3s ease-in-out !important;
}

.btn-gold:hover {
    background-color: #805a06 !important;
    color: #ffffff !important;
    transform: translateY(-5px);
    box-shadow: 0 8px 22px rgba(156, 110, 9, 0.6) !important;
}

.btn-lg-square {
    width: 50px;
    height: 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>


<!-- ==========================================
     4. COMPORTAMIENTO JAVASCRIPT (Añadir a tu archivo .js o etiqueta <script>)
     ========================================== -->
<script>
$(document).ready(function() {
    // Control de visibilidad y acción del botón volver arriba (Back to top)
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('.back-to-top').stop(true, true).fadeIn(200);
        } else {
            $('.back-to-top').stop(true, true).fadeOut(200);
        }
    });

    $('.back-to-top').click(function () {
        $('html, body').stop().animate({ scrollTop: 0 }, 200, 'easeInOutExpo');
        return false;
    });
});
</script>


<!-- ==========================================
     5. RECURSOS GRÁFICOS REQUERIDOS (Imágenes del footer)
     ==========================================
     Los logos dorados de la UNAM utilizan máscaras CSS que cargan imágenes locales.
     Asegúrate de copiar estas dos imágenes de la carpeta img/ de tu sitio actual
     a la carpeta img/ del nuevo sitio web:
     
     - img/unam_gran_universidad_dorado.png
     - img/475_logo_dorado.png
-->
