<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUIF - Certificaciones FCA UNAM</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons (requerido por footer) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Usamos tipografías con pesos más marcados para el look editorial -->
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,400&family=Open+Sans:wght@300;400;500;600&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos del proyecto (navbar, footer, etc.) -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Vue.js 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>

    <style>
        :root {
            --unam-blue: #0b1a2e;
            --unam-gold: #c19b44;
            --bg-cream: #f9f8f4;
            --bg-white: #ffffff;
            --border-light: #e0ddd5;
            --border-dark: #0b1a2e;
            --text-dark: #1a1a1a;
            --text-muted: #555555;
        }
        
        body {
            font-family: 'Open Sans', sans-serif;
            color: var(--text-dark);
            background-color: var(--bg-cream);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, .serif-font {
            font-family: 'Merriweather', serif;
        }

        /* Utilidades Editoriales */
        .fine-border-top { border-top: 1px solid var(--border-light); }
        .fine-border-bottom { border-bottom: 1px solid var(--border-light); }
        .fine-border-dark { border-color: var(--border-dark) !important; }
        
        .text-huge {
            font-size: clamp(3rem, 5vw, 5rem);
            line-height: 1.1;
            letter-spacing: -0.02em;
            font-weight: 700;
        }

        /* El navbar ahora usa los estilos de ../css/style.css */

        /* Botones estilo píldora */
        .btn-pill {
            border-radius: 50px;
            padding: 12px 32px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .btn-gold {
            background-color: var(--unam-gold);
            color: #ffffff;
            border: 1px solid var(--unam-gold);
        }
        .btn-gold:hover {
            background-color: transparent;
            color: var(--unam-gold);
        }
        .btn-blue {
            background-color: var(--unam-gold);
            color: #ffffff;
            border: 1px solid var(--unam-gold);
        }
        .btn-blue:hover {
            background-color: var(--bg-cream);
            color: var(--text-dark);
        }

        .hero-split {
            min-height: calc(100vh - 71px); /* Resta altura del nav */
            display: flex;
            flex-wrap: wrap;
        }
        .hero-left {
            background-color: var(--bg-cream);
            padding: 8% 6%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .hero-right {
            background-color: var(--unam-blue);
            padding: 5%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .hero-right-bg-logo {
            position: absolute;
            width: 120%;
            opacity: 0.03;
            transform: rotate(-15deg);
        }
        .hero-uif-logo {
            max-width: 80%;
            z-index: 2;
        }

        .bento-section {
            padding: 100px 0;
            background-color: var(--bg-white);
        }
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1px;
            background-color: var(--border-light);
            border: 1px solid var(--border-light);
        }
        .bento-item {
            background-color: var(--bg-white);
            padding: 3rem 2rem;
            transition: all 0.4s ease;
            text-align: center;
        }
        .bento-item:hover {
            background-color: var(--unam-blue);
            color: var(--bg-white);
        }
        .bento-item:hover .bento-title, 
        .bento-item:hover .bento-desc {
            color: var(--bg-white);
        }
        .bento-item:hover .bento-icon {
            color: var(--unam-gold);
            transform: scale(1.1);
        }
        .bento-icon {
            font-size: 2.5rem;
            color: var(--unam-blue);
            margin-bottom: 1.5rem;
            transition: all 0.4s ease;
            display: block;
        }
        .bento-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
            transition: color 0.4s ease;
        }
        .bento-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            transition: color 0.4s ease;
        }

        .northvolt-section {
            background-color: var(--bg-cream);
            padding: 100px 0;
        }
        .nv-container {
            background: var(--bg-white);
            border: 1px solid var(--border-light);
            display: flex;
            flex-wrap: wrap;
        }
        .nv-left {
            padding: 5rem 4rem;
            border-right: 1px solid var(--border-light);
        }
        .nv-right {
            display: flex;
            flex-direction: column;
        }
        .nv-right-top {
            background-color: var(--unam-gold);
            color: var(--bg-white);
            padding: 4rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .nv-right-bottom {
            background-color: var(--unam-blue);
            color: var(--bg-white);
            padding: 4rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        @media (max-width: 991px) {
            .nv-left { border-right: none; border-bottom: 1px solid var(--border-light); padding: 3rem 2rem;}
            .nv-right-top, .nv-right-bottom { padding: 3rem 2rem; }
        }

        .palmer-section {
            background-color: var(--bg-white);
            padding: 100px 0;
        }
        .palmer-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        .palmer-header h2 {
            font-size: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 300;
        }
        .palmer-header .subtitle {
            font-family: 'Open Sans', sans-serif;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: var(--unam-gold);
            display: block;
            margin-bottom: 1rem;
        }

        .process-row {
            display: flex;
            border-top: 1px solid var(--border-light);
            padding: 3rem 0;
            transition: background 0.3s;
        }
        .process-row:hover {
            background-color: var(--bg-cream);
        }
        .process-num {
            font-family: 'Merriweather', serif;
            font-size: 3rem;
            font-weight: 300;
            color: var(--unam-gold);
            width: 100px;
            line-height: 1;
        }
        .process-content h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .inst-box {
            padding: 3rem;
            border: 1px solid var(--border-light);
            height: 100%;
        }
        .inst-box h4 {
            font-size: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 1rem;
        }
        .inst-box ul {
            list-style: none;
            padding: 0;
        }
        .inst-box ul li {
            position: relative;
            padding-left: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            color: var(--text-muted);
        }
        .inst-box ul li::before {
            content: "—";
            position: absolute;
            left: 0;
            color: var(--unam-gold);
        }

        .faq-accordion .accordion-item {
            background-color: transparent;
            border: none;
            border-bottom: 1px solid var(--border-light);
            border-radius: 0 !important;
        }
        .faq-accordion .accordion-item:first-of-type {
            border-top: 1px solid var(--border-light);
        }
        .faq-accordion .accordion-button {
            background-color: transparent;
            font-family: 'Merriweather', serif;
            font-size: 1.2rem;
            color: var(--text-dark);
            padding: 1.5rem 0;
            box-shadow: none;
        }
        .faq-accordion .accordion-button:not(.collapsed) {
            color: var(--unam-gold);
            background-color: transparent;
        }
        .faq-accordion .accordion-body {
            padding: 0 0 1.5rem 0;
            color: var(--text-muted);
            font-size: 1rem;
        }

        /*** Estilos del Footer ***/
        .footer-top {
            background: #f7f6f2;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            padding: 2.5rem 0;
        }

        .footer-visitas {
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .footer-visitas-item {
            margin-bottom: 0.25rem;
        }

        .footer-visitas-item strong {
            color: var(--unam-gold);
        }

        .footer-redes-titulo {
            color: var(--text-dark);
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
            color: var(--text-dark);
            text-decoration: none;
            transition: transform 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
        }

        .footer-redes-enlace:hover {
            transform: translateY(-2px);
            background: rgba(2, 16, 36, 0.06);
            border-color: rgba(2, 16, 36, 0.35);
        }

        .footer-logo-dorado {
            height: 80px;
            width: auto;
            max-width: 45%;
            background-color: var(--unam-gold);
            -webkit-mask-size: contain;
            mask-size: contain;
            -webkit-mask-repeat: no-repeat;
            mask-repeat: no-repeat;
            -webkit-mask-position: center;
            mask-position: center;
            display: inline-block;
        }

        footer {
            width: 100%;
            margin-top: auto;
        }

        .copyright {
            background: var(--unam-blue);
            font-size: 0.85rem;
            width: 100%;
        }

        .justify {
            text-align: justify !important;
        }

        .copyright a {
            color: var(--unam-gold);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .copyright a:hover {
            color: var(--bg-cream);
            text-decoration: underline;
        }

        /*** Botón volver arriba ***/
        .back-to-top {
            position: fixed;
            display: none;
            right: 30px;
            bottom: 30px;
            z-index: 99;
            background-color: var(--unam-gold);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 14px rgba(156, 110, 9, 0.4);
            transition: all 0.3s ease-in-out;
        }

        .back-to-top:hover {
            background-color: #805a06;
            color: #ffffff;
            transform: translateY(-5px);
            box-shadow: 0 8px 22px rgba(156, 110, 9, 0.6);
        }

        .btn-lg-square {
            width: 50px;
            height: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

<!-- Contenedor Vue -->
<div id="app">

    <?php include '../includes/navbar.php'; ?>

    <section id="inicio" class="container-fluid p-0">
        <div class="row g-0 hero-split">
            <!-- Lado Izquierdo (Texto) -->
            <div class="col-lg-6 hero-left">
                <div style="max-width: 600px; margin: 0 auto;">
                    <span class="d-block mb-3" style="color: var(--unam-gold); font-weight: 600; letter-spacing: 2px; text-transform: uppercase;">
                        Plataforma Oficial
                    </span>
                    <h1 class="text-huge serif-font mb-4 text-dark">
                        SUIF<br>
                        <span style="font-size: 0.5em; font-weight: 300; display: block; margin-top: -10px;">Certificaciones FCA UNAM</span>
                    </h1>
                    <p class="mb-5" style="font-size: 1.1rem; color: var(--text-muted);">
                        El espacio digital diseñado para gestionar tu desarrollo profesional. Consulta convocatorias, realiza pre-registros, genera pagos y da seguimiento a tu certificación institucional en un solo lugar.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#convocatoria" class="btn btn-pill btn-gold">Descubrir Convocatoria</a>
                        <a href="#proceso" class="btn btn-pill" style="border: 1px solid var(--border-dark); color: var(--text-dark);">Conoce el proceso</a>
                    </div>
                </div>
            </div>
            <!-- Lado Derecho (Visual) -->
            <div class="col-lg-6 hero-right">
                <img src="unam_logo.png" alt="Escudo UNAM" class="hero-right-bg-logo">
                <img src="../img/logos/UIF_blanco.png" alt="Unidad de Inteligencia Financiera" class="hero-uif-logo">
            </div>
        </div>
    </section>

    <section class="bento-section container-fluid px-4 px-lg-5">
        <div class="palmer-header mb-5">
            <span class="subtitle">Servicios</span>
            <h2 class="serif-font">¿Qué puedes hacer en SUIF?</h2>
        </div>
        
        <div class="bento-grid">
            <div class="bento-item" v-for="(card, index) in cards" :key="index">
                <i :class="['bento-icon', card.icon]"></i>
                <h4 class="bento-title serif-font">{{ card.title }}</h4>
                <p class="bento-desc mb-0">{{ card.desc }}</p>
            </div>
        </div>
    </section>

    <section id="convocatoria" class="northvolt-section">
        <div class="container">
            <div class="nv-container">
                <!-- Izquierda: Tipografía Grande -->
                <div class="col-lg-7 nv-left">
                    <span style="text-transform: uppercase; letter-spacing: 2px; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2rem;">
                        Periodo Vigente [2026-1]
                    </span>
                    <h2 class="serif-font" style="font-size: clamp(2.5rem, 4vw, 3.5rem); line-height: 1.2; font-weight: 700; color: var(--unam-blue); margin-bottom: 2rem;">
                        Certificación en<br>[Nombre de la Certificación]
                    </h2>
                    <p style="font-size: 1.1rem; color: var(--text-muted); max-width: 90%;">
                        Te invitamos a consultar el documento normativo completo donde encontrarás los requisitos detallados, fechas límite, sedes de aplicación disponibles y toda la información oficial necesaria para participar en este ciclo.
                    </p>
                </div>
                <!-- Derecha: Bloques de Color -->
                <div class="col-lg-5 nv-right">
                    <div class="nv-right-top">
                        <h4 class="serif-font mb-3">Revisa detalladamente</h4>
                        <p class="mb-0" style="opacity: 0.9; font-size: 0.95rem;">
                            Es fundamental leer todos los apartados normativos antes de iniciar tu proceso de pre-registro en la plataforma.
                        </p>
                    </div>
                    <div class="nv-right-bottom">
                        <h4 class="serif-font mb-4">Documento Oficial</h4>
                        <a href="#" class="btn btn-pill" style="background: white; color: var(--unam-blue); align-self: flex-start;">
                            <i class="fas fa-arrow-down me-2"></i> Descargar PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="proceso" class="palmer-section">
        <div class="container" style="max-width: 900px;">
            <div class="palmer-header">
                <span class="subtitle">Paso a paso</span>
                <h2 class="serif-font">Proceso de Certificación</h2>
            </div>
            
            <div class="process-list mt-5">
                <!-- Ciclo Vue para el proceso -->
                <div class="process-row" v-for="step in steps" :key="step.id">
                    <div class="process-num">0{{ step.id }}</div>
                    <div class="process-content">
                        <h4 class="serif-font" style="color: var(--unam-blue);">{{ step.title }}</h4>
                        <p class="mb-0 text-muted">{{ step.desc }}</p>
                    </div>
                </div>
                <div class="border-top" style="border-color: var(--border-light) !important;"></div>
            </div>

            <div class="text-center mt-5 pt-4">
                <a href="#" class="btn btn-pill btn-gold px-5">Iniciar mi Pre-registro</a>
            </div>
        </div>
    </section>

    <section id="instructivo" class="palmer-section" style="background-color: var(--bg-cream);">
        <div class="container">
            <div class="palmer-header mb-5">
                <span class="subtitle">Preparación</span>
                <h2 class="serif-font">Instructivo</h2>
            </div>

            <div class="row g-0">
                <div class="col-md-6 p-3">
                    <div class="inst-box" style="background: white;">
                        <h4 class="serif-font" style="color: var(--unam-blue);">Documentos Requeridos</h4>
                        <ul>
                            <li>Identificación oficial vigente (INE, pasaporte o cédula)</li>
                            <li>Título profesional o acta de examen profesional</li>
                            <li>Cédula profesional (copia legible)</li>
                            <li>Fotografía digital reciente (.jpg, fondo blanco)</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 p-3">
                    <div class="inst-box" style="background: white;">
                        <h4 class="serif-font" style="color: var(--unam-blue);">Fechas Clave</h4>
                        <ul>
                            <li><strong>Pre-registro:</strong> [fecha inicio] al [fecha cierre]</li>
                            <li><strong>Aplicación de examen:</strong> [fecha]</li>
                            <li><strong>Publicación de resultados:</strong> [fecha]</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="faq" class="palmer-section border-top fine-border-light">
        <div class="container" style="max-width: 800px;">
            <div class="palmer-header">
                <span class="subtitle">Resolución de Dudas</span>
                <h2 class="serif-font">Preguntas Frecuentes</h2>
            </div>

            <div class="accordion faq-accordion mt-5" id="accordionFaq">
                <div class="accordion-item" v-for="(faq, index) in faqs" :key="index">
                    <h2 class="accordion-header" :id="'heading'+index">
                        <button class="accordion-button" :class="{ 'collapsed': index !== 0 }" type="button" data-bs-toggle="collapse" :data-bs-target="'#collapse'+index">
                            {{ faq.q }}
                        </button>
                    </h2>
                    <div :id="'collapse'+index" class="accordion-collapse collapse" :class="{ 'show': index === 0 }" data-bs-parent="#accordionFaq">
                        <div class="accordion-body">
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const { createApp } = Vue;

    createApp({
        data() {
            return {
                cards: [
                    { icon: 'far fa-edit', title: 'Pre-registro', desc: 'Completa tu registro y sube tu documentación desde cualquier lugar.' },
                    { icon: 'far fa-credit-card', title: 'Pagos Seguros', desc: 'Obtén tu referencia bancaria y realiza el pago ágilmente.' },
                    { icon: 'fas fa-map-marker-alt', title: 'Logística', desc: 'Elige la sede y el horario que mejor se adapten a tu agenda.' },
                    { icon: 'fas fa-search', title: 'Resultados', desc: 'Revisa el estado de tu trámite y descarga tus constancias.' }
                ],
                steps: [
                    { id: 1, title: 'Pre-registro y Documentación', desc: 'Completa tus datos personales y sube la documentación requerida al sistema.' },
                    { id: 2, title: 'Generación de Referencia', desc: 'Obtén tu referencia bancaria personalizada para realizar el pago correspondiente.' },
                    { id: 3, title: 'Validación de Pago', desc: 'Sube tu comprobante de pago; nuestro equipo de finanzas lo validará para confirmar tu inscripción.' },
                    { id: 4, title: 'Selección de Sede y Horario', desc: 'Una vez validado, elige la sede y el horario de aplicación que mejor se adapte a ti.' },
                    { id: 5, title: 'Consulta de Resultados', desc: 'Ingresa en la fecha estipulada para conocer tu puntaje y descargar tu constancia.' }
                ],
                faqs: [
                    { q: '¿Quién puede inscribirse a la certificación?', a: 'Pueden inscribirse profesionales con título y cédula profesional en las áreas de contaduría, administración, informática o áreas afines, de acuerdo con los requisitos establecidos en la convocatoria vigente.' },
                    { q: '¿Cuál es el costo de la certificación?', a: 'El costo varía según el tipo de certificación. Consulta la convocatoria completa para conocer las tarifas vigentes. El pago se realiza mediante referencia bancaria generada desde este portal.' },
                    { q: '¿Qué documentos necesito para el pre-registro?', a: 'Requieres identificación oficial vigente, título profesional o acta de examen, cédula profesional y una fotografía digital reciente en formato .jpg con fondo blanco. Consulta el apartado de Instructivo para más detalles.' },
                    { q: '¿Cómo genero mi referencia de pago?', a: 'Una vez que tu pre-registro sea validado, se habilitará la opción para descargar tu línea de captura y formato de pago referenciado en tu panel de usuario.' },
                    { q: '¿Qué pasa si mi comprobante de pago no es válido?', a: 'Si el departamento de finanzas detecta alguna irregularidad, se te notificará por correo electrónico para que vuelvas a subir un documento válido dentro del periodo establecido.' }
                ]
            }
        }
    }).mount('#app');
</script>

</body>
</html>