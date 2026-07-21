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
                    { icon: 'far fa-credit-card', title: 'Pagos seguros', desc: 'Obtén tu referencia bancaria y realiza el pago ágilmente.' },
                    { icon: 'fas fa-map-marker-alt', title: 'Logística', desc: 'Elige la sede y el horario que mejor se adapten a tu agenda.' },
                    { icon: 'fas fa-search', title: 'Resultados', desc: 'Revisa el estado de tu trámite y descarga tus constancias.' }
                ],
                steps: [
                    { id: 1, number: '01', title: 'Pre-registro y documentación', desc: 'Completa tus datos personales y sube la documentación requerida al sistema.' },
                    { id: 2, number: '02', title: 'Generación de referencia', desc: 'Obtén tu referencia bancaria personalizada para realizar el pago correspondiente.' },
                    { id: 3, number: '03', title: 'Validación de pago', desc: 'Sube tu comprobante de pago; nuestro equipo de finanzas lo validará para confirmar tu inscripción.' },
                    { id: 4, number: '04', title: 'Selección de sede y horario', desc: 'Una vez validado, elige la sede y el horario de aplicación que mejor se adapte a ti.' },
                    { id: 5, number: '05', title: 'Consulta de resultados', desc: 'Ingresa en la fecha estipulada para conocer tu puntaje y descargar tu constancia.' }
                ],
                faqs: [
                    { q: '¿Quién puede inscribirse a la certificación?', a: 'Pueden inscribirse profesionales con título y cédula profesional en las áreas de contaduría, administración, informática o áreas afines, de acuerdo con los requisitos establecidos en la convocatoria vigente.' },
                    { q: '¿Cuál es el costo de la certificación?', a: 'El costo varía según el tipo de certificación. Consulta la convocatoria completa para conocer las tarifas vigentes. El pago se realiza mediante referencia bancaria generada desde este portal.' },
                    { q: '¿Qué documentos necesito para el pre-registro?', a: 'Requieres identificación oficial vigente, título profesional o acta de examen, cédula profesional y una fotografía digital reciente en formato .jpg con fondo blanco. Consulta el apartado de Instructivo para más detalles.' },
                    { q: '¿Cómo genero mi referencia de pago?', a: 'Una vez que tu pre-registro sea validado, se habilitará la opción para descargar tu línea de captura y formato de pago referenciado en tu panel de usuario.' },
                    { q: '¿Qué pasa si mi comprobante de pago no es válido?', a: 'Si el departamento de finanzas detecta alguna irregularidad, se te notificará por correo electrónico para que vuelvas a subir un documento válido dentro del periodo establecido.' }
                ]
            };
        }
    }).mount(root);
}());
