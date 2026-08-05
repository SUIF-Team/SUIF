@extends('layouts.landing')

@section('title', 'SUIF — Certificaciones FCA UNAM')
@section('body_class', 'landing-page home-page')

@section('styles')
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/home.css') }}">
@endsection

@section('content')
<div id="app">
    <section id="inicio" class="container-fluid p-0" aria-labelledby="home-title">
        <div class="row g-0 hero-split">
            <div class="col-lg-6 hero-left">
                <div class="hero-copy">
                    <span class="home-eyebrow">Plataforma oficial</span>
                    <h1 id="home-title" class="hero-title">
                        SUIF
                        <span>Certificaciones FCA UNAM</span>
                    </h1>
                    <p class="hero-description">
                        El espacio digital diseñado para gestionar tu desarrollo profesional. Consulta convocatorias, realiza pre-registros, genera pagos y da seguimiento a tu certificación institucional en un solo lugar.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#convocatoria" class="btn btn-pill btn-gold">Descubrir convocatoria</a>
                        <a href="#proceso" class="btn btn-pill btn-outline-institutional">Conoce el proceso</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 hero-right" aria-label="Identidad de la Unidad de Inteligencia Financiera">
                <img src="{{ asset('assets/img/logos/unam-logo.png') }}" alt="" class="hero-right-bg-logo" aria-hidden="true">
                <img src="{{ asset('assets/img/logos/uif-blanco.png') }}" alt="Unidad de Inteligencia Financiera México" class="hero-uif-logo">
            </div>
        </div>
    </section>

    <section class="services-section container-fluid px-4 px-lg-5" aria-labelledby="services-title">
        <div class="section-heading">
            <span class="section-kicker">Servicios</span>
            <h2 id="services-title">¿Qué puedes hacer en SUIF?</h2>
        </div>

        <div class="services-grid">
            <article class="service-card" v-for="(card, index) in cards" :key="index">
                <i :class="['service-card__icon', card.icon]" aria-hidden="true"></i>
                <h3>@{{ card.title }}</h3>
                <p>@{{ card.desc }}</p>
            </article>
        </div>
    </section>

    <section id="convocatoria" class="convocation-section" aria-labelledby="convocation-title">
        <div class="container">
            <div class="convocation-panel">
                <div class="col-12 col-lg-7 convocation-copy">
                    <span class="period-label">Periodo vigente [2026-1]</span>
                    <h2 id="convocation-title">Certificación en<br>Materia de Prevención de Operaciones con Recursos de Procedencia Ilícita</h2>
                    <p>
                        Te invitamos a consultar el documento normativo completo donde encontrarás los requisitos detallados, fechas límite, sedes de aplicación disponibles y toda la información oficial necesaria para participar en este ciclo.
                    </p>
                </div>

                <div class="col-12 col-lg-5 convocation-actions">
                    <div class="convocation-highlight">
                        <h3>Revisa detalladamente</h3>
                        <p>Es fundamental leer todos los apartados normativos antes de iniciar tu proceso de pre-registro en la plataforma.</p>
                    </div>
                    <div class="convocation-download">
                        <h3>Documento oficial</h3>
                        <a href="#" class="btn btn-pill document-button">
                            <i class="fas fa-arrow-down me-2" aria-hidden="true"></i>Descargar PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="proceso" class="content-section" aria-labelledby="process-title">
        <div class="container process-container">
            <div class="section-heading">
                <span class="section-kicker">Paso a paso</span>
                <h2 id="process-title">Proceso de certificación</h2>
            </div>

            <div class="process-list">
                <article class="process-row" v-for="step in steps" :key="step.id">
                    <div class="process-number" aria-hidden="true">@{{ step.number }}</div>
                    <div class="process-content">
                        <h3>@{{ step.title }}</h3>
                        <p>@{{ step.desc }}</p>
                    </div>
                </article>
                <div class="process-divider" aria-hidden="true"></div>
            </div>

            <div class="text-center mt-5 pt-4">
                <a href="#" class="btn btn-pill btn-gold px-5">Iniciar mi pre-registro</a>
            </div>
        </div>
    </section>

    <section id="instructivo" class="content-section instructions-section" aria-labelledby="instructions-title">
        <div class="container">
            <div class="section-heading">
                <span class="section-kicker">Preparación</span>
                <h2 id="instructions-title">Instructivo</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <article class="instruction-card">
                        <h3>Documentos requeridos</h3>
                        <ul>
                            <li>Formatos requeridos (Solicitud, Aceptación, Carta, Autorización)</li>
                            <li>Identificación oficial vigente (Credencial para votar o pasaporte)</li>
                            <li>Clave Única de Registro de Población (CURP)</li>
                            <li>Comprobante de pago (Transferencia electrónica o Factura)</li>
                        </ul>
                    </article>
                </div>
                <div class="col-md-6">
                    <article class="instruction-card">
                        <h3>Fechas clave</h3>
                        <ul>
                            <li><strong>Pre-registro:</strong> [fecha inicio] al [fecha cierre]</li>
                            <li><strong>Aplicación de examen:</strong> [fecha]</li>
                            <li><strong>Publicación de resultados:</strong> [fecha]</li>
                        </ul>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section id="faq" class="content-section faq-section" aria-labelledby="faq-title">
        <div class="container faq-container">
            <div class="section-heading">
                <span class="section-kicker">Resolución de dudas</span>
                <h2 id="faq-title">Preguntas frecuentes</h2>
            </div>

            <div class="accordion faq-accordion" id="accordionFaq">
                <div class="accordion-item" v-for="(faq, index) in faqs" :key="index">
                    <h3 class="accordion-header" :id="'heading' + index">
                        <button
                            class="accordion-button"
                            :class="{ collapsed: index !== 0 }"
                            type="button"
                            data-bs-toggle="collapse"
                            :data-bs-target="'#collapse' + index"
                            :aria-expanded="index === 0 ? 'true' : 'false'"
                            :aria-controls="'collapse' + index">
                            @{{ faq.q }}
                        </button>
                    </h3>
                    <div
                        :id="'collapse' + index"
                        class="accordion-collapse collapse"
                        :class="{ show: index === 0 }"
                        :aria-labelledby="'heading' + index"
                        data-bs-parent="#accordionFaq">
                        <div class="accordion-body">@{{ faq.a }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="{{ asset_versionado('assets/js/pages/home.js') }}"></script>
@endsection
