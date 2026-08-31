{{--
    Aviso de privacidad integral del trámite de certificación.
    Contenido conforme al artículo 21 de la Ley General de Protección de Datos
    Personales en Posesión de Sujetos Obligados (DOF 20/03/2025): una sección
    por fracción y en el mismo orden de la ley.
--}}
@extends('layouts.landing')

@section('title', 'SUIF — Aviso de privacidad integral')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/aviso-privacidad.css') }}">
@endsection

@section('content')
<article class="aviso">
    <div class="container aviso__contenedor">
        <header class="aviso__encabezado">
            <p class="aviso__institucion">Sistema Integral de Certificaciones · SUIF</p>
            <h1>Aviso de privacidad integral</h1>
            <p class="aviso__entrada">
                Aquí se explica qué datos personales pide este sistema, para qué se usan, a quién se
                entregan y cómo puedes decidir sobre ellos. Está redactado conforme a los artículos 20,
                21 y 22 de la Ley General de Protección de Datos Personales en Posesión de Sujetos
                Obligados, publicada en el Diario Oficial de la Federación el 20 de marzo de 2025.
            </p>
            <p class="aviso__version">Última actualización: 30 de agosto de 2026 · Versión 1.0</p>
        </header>

        <p class="aviso__pendiente">
            <strong>Versión en revisión.</strong> Lo que aparece marcado como
            <mark class="aviso__marca">[por confirmar]</mark> está pendiente de validación por la
            Unidad de Transparencia de la UNAM y por el área responsable del trámite. El resto
            corresponde al funcionamiento real del sistema.
        </p>

        <nav class="aviso__indice" aria-label="Contenido del aviso">
            <h2>Contenido</h2>
            <ol>
                <li><a href="#responsable">Quién es responsable de tus datos</a></li>
                <li><a href="#datos">Qué datos personales se tratan</a></li>
                <li><a href="#fundamento">Con qué fundamento se tratan</a></li>
                <li><a href="#finalidades">Para qué se usan tus datos</a></li>
                <li><a href="#derechos">Cómo ejercer tus derechos ARCO y de portabilidad</a></li>
                <li><a href="#unidad">Domicilio de la Unidad de Transparencia</a></li>
                <li><a href="#transferencias">A quién se transfieren tus datos</a></li>
                <li><a href="#negativa">Cómo negarte a un uso o a una transferencia</a></li>
            </ol>
            <ul class="aviso__indice-extra">
                <li><a href="#conservacion">Cuánto tiempo se conservan</a></li>
                <li><a href="#seguridad">Cómo se protegen</a></li>
                <li><a href="#navegacion">Qué ocurre cuando navegas en el sitio</a></li>
                <li><a href="#cambios">Cambios a este aviso</a></li>
            </ul>
        </nav>

        <section id="responsable" class="aviso__seccion">
            <h2>1. Quién es responsable de tus datos</h2>
            <p>
                La <strong>Facultad de Contaduría y Administración de la Universidad Nacional Autónoma
                de México</strong> es la responsable del tratamiento de los datos personales que recaba
                este sistema, con domicilio en Circuito Exterior s/n, Ciudad Universitaria, Alcaldía
                Coyoacán, C.P. 04510, Ciudad de México.
                <mark class="aviso__marca">[por confirmar: denominación exacta del área responsable del
                trámite y su domicilio de contacto]</mark>
            </p>
            <p>
                Para dudas sobre el uso del sistema puedes escribir a
                <a href="mailto:{{ config('suif.soporte_correo') }}">{{ config('suif.soporte_correo') }}</a>.
                Las solicitudes de derechos ARCO no se atienden por esa vía: van por los medios del
                punto 5.
            </p>
        </section>

        <section id="datos" class="aviso__seccion">
            <h2>2. Qué datos personales se tratan</h2>
            <p>Según la etapa del trámite, el sistema recaba:</p>

            <h3>Identificación</h3>
            <ul>
                <li>Nombre y apellidos.</li>
                <li>Clave Única de Registro de Población (CURP).</li>
                <li>Registro Federal de Contribuyentes (RFC).</li>
                <li>Entidad federativa.</li>
                <li>Último grado de estudios.</li>
            </ul>

            <h3>Contacto</h3>
            <ul>
                <li>Correo electrónico principal y correo alterno.</li>
                <li>Teléfono celular.</li>
            </ul>

            <h3>Perfil declarado para la certificación</h3>
            <ul>
                <li>Si realizas actividades vulnerables.</li>
                <li>Si eres persona responsable del cumplimiento de las obligaciones de la Ley Federal
                    para la Prevención e Identificación de Operaciones con Recursos de Procedencia
                    Ilícita.</li>
            </ul>

            <h3>Documentos que cargas</h3>
            <ul>
                <li>Identificación oficial y CURP.</li>
                <li>Solicitud de obtención del certificado, firmada.</li>
                <li>Aceptación para recibir notificaciones por vía electrónica.</li>
                <li>Declaración bajo protesta de decir verdad.</li>
                <li>Autorización, o negativa, para publicar tu nombre en el listado de personas
                    certificadas.</li>
            </ul>

            <h3>Pago</h3>
            <ul>
                <li>Referencia bancaria asignada y comprobante de pago.</li>
                <li>Si pides factura: razón social, RFC, régimen fiscal, código postal, correo de
                    facturación y uso del CFDI.</li>
            </ul>

            <h3>Trámite y resultado</h3>
            <ul>
                <li>Sede, grupo, fecha y horario asignados.</li>
                <li>Resultado de la evaluación, aprobatorio o no aprobatorio, y en su caso el
                    certificado emitido.</li>
                <li>Tu cuenta de acceso y el registro de las revisiones administrativas del
                    expediente.</li>
            </ul>

            <p class="aviso__nota">
                <strong>No se recaban datos personales sensibles</strong>: el sistema no pide origen
                étnico o racial, estado de salud, información genética, creencias religiosas o
                filosóficas, opiniones políticas, preferencia sexual ni afiliación sindical.
            </p>
        </section>

        <section id="fundamento" class="aviso__seccion">
            <h2>3. Con qué fundamento se tratan</h2>
            <ul>
                <li>Artículos 6º, apartado A, y 16, párrafo segundo, de la Constitución Política de los
                    Estados Unidos Mexicanos.</li>
                <li>Artículos 1, 3, 16 a 22 y 37 a 51 de la Ley General de Protección de Datos
                    Personales en Posesión de Sujetos Obligados.</li>
                <li>Lineamientos para la protección de datos personales en posesión de la UNAM,
                    publicados en Gaceta UNAM.
                    <mark class="aviso__marca">[por confirmar: fecha y vigencia de la versión aplicable
                    después de la ley de 2025]</mark></li>
                <li>Artículo 34 Bis de las Reglas de Carácter General a que se refiere la Ley Federal
                    para la Prevención e Identificación de Operaciones con Recursos de Procedencia
                    Ilícita, y la convocatoria de certificación publicada por la Unidad de Inteligencia
                    Financiera en el Diario Oficial de la Federación.
                    <mark class="aviso__marca">[por confirmar: fecha de la convocatoria vigente]</mark></li>
            </ul>
        </section>

        <section id="finalidades" class="aviso__seccion">
            <h2>4. Para qué se usan tus datos</h2>

            <h3>Finalidades necesarias para el trámite</h3>
            <p>
                Sin estos usos no es posible atender tu solicitud, y por eso no dependen de tu
                consentimiento:
            </p>
            <ul>
                <li>Identificarte, crear tu cuenta y enviarte la clave de acceso al sistema.</li>
                <li>Integrar tu expediente y revisar la documentación que cargas.</li>
                <li>Asignarte una referencia bancaria y verificar tu pago.</li>
                <li>Asignarte sede, grupo, fecha y horario, y emitir tu comprobante.</li>
                <li>Notificarte por correo electrónico el estado de tu trámite y el resultado de tu
                    evaluación.</li>
                <li>Gestionar la emisión y entrega de tu certificado.</li>
                <li>Atender requerimientos de autoridades competentes, auditorías y rendición de
                    cuentas.</li>
            </ul>

            <h3>Finalidades que requieren tu consentimiento</h3>
            <p>
                Estas sólo ocurren si tú las autorizas, y puedes negarte sin que tu trámite se vea
                afectado:
            </p>
            <ul>
                <li><strong>Publicación de tu nombre.</strong> Que la Unidad de Inteligencia Financiera
                    publique tu nombre completo en su portal, en el listado de personas certificadas,
                    si tu resultado es aprobatorio. Lo autorizas o lo niegas en el formato
                    «Autorización de la publicación» que firmas y cargas durante el pre-registro.</li>
                <li><strong>Emisión de CFDI.</strong> Tus datos fiscales sólo se piden y se usan si
                    eliges factura en lugar de ticket cuando se valida tu pago.</li>
            </ul>
        </section>

        <section id="derechos" class="aviso__seccion">
            <h2>5. Cómo ejercer tus derechos ARCO y de portabilidad</h2>
            <p>
                Puedes solicitar el <strong>acceso</strong> a tus datos, su <strong>rectificación</strong>
                cuando sean inexactos o incompletos, su <strong>cancelación</strong> cuando consideres
                que no son necesarios, y <strong>oponerte</strong> a un uso concreto. También puedes
                pedir la <strong>portabilidad</strong> de tus datos y revocar el consentimiento que
                hayas otorgado.
            </p>
            <p>
                La solicitud se presenta ante la Unidad de Transparencia de la UNAM por cualquiera de
                estas vías:
            </p>
            <ul>
                <li>Plataforma Nacional de Transparencia:
                    <a href="http://www.plataformadetransparencia.org.mx/" target="_blank" rel="noopener noreferrer">plataformadetransparencia.org.mx</a>.</li>
                <li>Correo electrónico:
                    <a href="mailto:unidaddetransparencia@unam.mx">unidaddetransparencia@unam.mx</a>.</li>
                <li>De forma presencial, en el domicilio del punto 6.</li>
            </ul>
            <p>
                La respuesta se emite en los plazos que fija la Ley General de Protección de Datos
                Personales en Posesión de Sujetos Obligados.
                <mark class="aviso__marca">[por confirmar: plazo de respuesta y requisitos de la
                solicitud conforme a la ley de 2025]</mark>
            </p>
        </section>

        <section id="unidad" class="aviso__seccion">
            <h2>6. Domicilio de la Unidad de Transparencia</h2>
            <p>
                Unidad de Transparencia de la UNAM, Circuito Norponiente del Estadio Olímpico, Ciudad
                Universitaria, C.P. 04510, Ciudad de México.
                <mark class="aviso__marca">[por confirmar: teléfono y horario de atención]</mark>
            </p>
        </section>

        <section id="transferencias" class="aviso__seccion">
            <h2>7. A quién se transfieren tus datos</h2>
            <ul>
                <li><strong>Unidad de Inteligencia Financiera de la Secretaría de Hacienda y Crédito
                    Público:</strong> recibe tu expediente porque es quien dictamina y expide el
                    certificado conforme a su convocatoria.</li>
                <li><strong>Institución bancaria:</strong> los datos mínimos de tu referencia, para
                    conciliar el pago del trámite.</li>
                <li><strong>Servicio de Administración Tributaria:</strong> tus datos fiscales, cuando
                    pides CFDI.</li>
                <li><strong>Autoridades competentes:</strong> cuando lo requieran en ejercicio de sus
                    atribuciones legales.</li>
            </ul>
            <p>
                Estas transferencias derivan del propio trámite y de disposiciones legales, por lo que
                no requieren tu consentimiento. La publicación de tu nombre en el listado de personas
                certificadas sí lo requiere y se rige por el punto 4.
                <mark class="aviso__marca">[por confirmar con el área jurídica: destinatarios y su
                fundamento]</mark>
            </p>
            <p>
                El artículo 38 de la Ley Federal para la Prevención e Identificación de Operaciones con
                Recursos de Procedencia Ilícita considera confidencial y reservada la identidad de
                quienes presentan avisos; autorizar la publicación de tu nombre no implica, por ese solo
                hecho, ubicarte en ese supuesto.
            </p>
        </section>

        <section id="negativa" class="aviso__seccion">
            <h2>8. Cómo negarte a un uso o a una transferencia</h2>
            <ul>
                <li>Para que tu nombre <strong>no</strong> se publique en el listado de personas
                    certificadas: marca «no» en el formato «Autorización de la publicación» antes de
                    cargarlo.</li>
                <li>Para no entregar datos fiscales: elige ticket en lugar de CFDI cuando se valide tu
                    pago.</li>
                <li>En cualquier momento posterior, escribiendo a la Unidad de Transparencia por las
                    vías del punto 5.</li>
            </ul>
        </section>

        <section id="conservacion" class="aviso__seccion">
            <h2>Cuánto tiempo se conservan</h2>
            <p>
                Tu expediente se conserva mientras dura el trámite y después por el plazo que fija el
                catálogo de disposición documental de la Universidad, para efectos de comprobación,
                auditoría y consulta de tu certificado.
                <mark class="aviso__marca">[por confirmar: plazos de conservación y destino final del
                expediente]</mark>
            </p>
        </section>

        <section id="seguridad" class="aviso__seccion">
            <h2>Cómo se protegen</h2>
            <ul>
                <li>Entras al sistema con una clave personal que se guarda cifrada: nadie puede
                    consultarla, sólo restablecerla.</li>
                <li>Cada pantalla administrativa exige un privilegio específico; quien revisa
                    documentos no ve lo mismo que quien revisa pagos.</li>
                <li>Los documentos, comprobantes, referencias y certificados se guardan fuera de la
                    carpeta pública del servidor y sólo se entregan a quien está autorizado.</li>
                <li>La comunicación con el sitio viaja cifrada.</li>
            </ul>
        </section>

        <section id="navegacion" class="aviso__seccion">
            <h2>Qué ocurre cuando navegas en el sitio</h2>
            <p>
                El sitio usa una <strong>cookie de sesión estrictamente necesaria</strong> para mantener
                tu sesión iniciada y proteger los formularios. No hay cookies de analítica, de
                publicidad ni de seguimiento entre sitios. El navegador guarda además, sólo en tu
                dispositivo, si ya cerraste el aviso que aparece al entrar.
            </p>
            <p>
                Algunos recursos visuales —Bootstrap, Font Awesome y Google Fonts— se cargan desde
                servicios externos, que por esa petición pueden conocer tu dirección IP. El servidor
                registra las peticiones que recibe con fines de seguridad y diagnóstico.
            </p>
        </section>

        <section id="cambios" class="aviso__seccion">
            <h2>Cambios a este aviso</h2>
            <p>
                Cualquier cambio se publicará en esta misma dirección, con su fecha de actualización.
                Te sugerimos consultarla al iniciar un nuevo trámite.
            </p>
        </section>

        <p class="aviso__pie">
            Versión resumida:
            <a href="{{ route('aviso-privacidad.simplificado') }}">aviso de privacidad simplificado</a>.
        </p>
    </div>
</article>
@endsection
