(function () {
    'use strict';

    var root = document.getElementById('app');

    if (!root || !window.Vue) {
        return;
    }

    window.Vue.createApp({
        data: function () {
            return {
                cards: [
                    { icon: 'far fa-edit', title: 'Pre-registro', desc: 'Completa tu registro y sube tu documentación desde cualquier lugar.' },
                    { icon: 'far fa-credit-card', title: 'Pagos seguros', desc: 'Obtén tu referencia bancaria y realiza el pago correspondiente.' },
                    { icon: 'fas fa-map-marker-alt', title: 'Planificación', desc: 'Selecciona la sede y el horario de aplicación de la evaluación.' },
                    { icon: 'fas fa-search', title: 'Resultados', desc: 'Revisa el estado de tu trámite y descarga tus constancias.' }
                ],
                steps: [
                    { id: 1, number: '01', title: 'Pre-registro y documentación', desc: 'Completa tus datos personales y sube la documentación requerida al sistema.' },
                    { id: 2, number: '02', title: 'Generación de referencia', desc: 'Obtén una referencia bancaria para realizar el pago correspondiente.' },
                    { id: 3, number: '03', title: 'Validación de pago', desc: 'Sube tu comprobante de pago; nuestro equipo de finanzas lo validará para confirmar tu inscripción.' },
                    { id: 4, number: '04', title: 'Selección de sede y horario', desc: 'Una vez validado, selecciona la sede y el horario de aplicación de la evaluación.' },
                    { id: 5, number: '05', title: 'Consulta de resultados', desc: 'Ingresa en la fecha estipulada para conocer el puntaje obtenido y descargar tu constancia.' }
                ],
                faqs: [
                    { q: '¿Por qué medio se realiza el registro?', a: 'El registro de su solicitud se llevará a cabo mediante la Plataforma web en [URL del sitio web]' },
                    { q: '¿A través de qué medios se puede dar seguimiento al estatus del proceso?', a: 'Ingresando a la Plataforma web y consultando los correos electrónicos principal y alterno que registraron.' },
                    { q: '¿Quiénes pueden obtener la Certificación?', a: 'Las personas físicas que realizan Actividades Vulnerables a las que se refiere el artículo 17 de la LFPIORPI; las personas responsables encargadas de cumplimiento, y aquellas personas interesadas que cumplan con lo establecido en la Convocatoria.' },
                    { q: '¿Existe algún curso de preparación para la evaluación?', a: 'La FCA no imparte ninguna capacitación o curso de preparación para obtener el Certificado, únicamente proporciona el temario de estudio y guía.' },
                    { q: '¿El Certificado que emite la FCA cuenta con alguna vigencia?', a: 'La vigencia del Certificado será de cinco años, a partir de la fecha de su emisión.' },
                    { q: '¿Cuál es la cuota que deberá cubrirse para la evaluación de obtención del Certificado?', a: 'La cuota de recuperación será de $7,000.00 (siete mil pesos 00/100 M.N.) y se pagará directamente a la Organización evaluadora.' },
                    { q: '¿Estoy obligada u obligado a contar con la Certificación que otorga la FCA?', a: 'No. Esto, de conformidad con el artículo 34 Bis de las Reglas de Carácter General a que se refiere la LFPIORPI.' },
                    { q: 'Si cuento con la Certificación de la CNBV, ¿puedo obtener la certificación de la FCA?', a: 'Sí. No existe incompatibilidad entre ambas Certificaciones.' },
                    { q: '¿Es un requisito llevar a cabo alguna Actividad Vulnerable para poder obtener la Certificación?', a: 'No. De acuerdo con la Convocatoria, aquellas personas físicas interesadas que cumplan con lo establecido en la misma podrán participar en el proceso.' }
                ]
            };
        }
    }).mount(root);
}());
