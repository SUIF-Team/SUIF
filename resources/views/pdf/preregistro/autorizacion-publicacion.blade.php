{{--
    Formato 4: Autorización para publicar el nombre en el listado de personas
    certificadas. Texto tomado literalmente del formato oficial de la UIF
    (convocatoria 2024). La casilla «(si / no)» se llena a mano.
--}}
@extends('pdf.preregistro.layout')

@section('asunto', 'Autorización para la publicación de nombre en el listado de las personas que obtuvieron el Certificado.')

@section('apertura')
en términos de lo establecido en las Bases Décima Quinta, inciso d) y Vigésima Tercera, segundo
párrafo de la “Convocatoria en materia de prevención operaciones con recursos de procedencia
ilícita, dirigida a las personas físicas que realizan actividades vulnerables y a las responsables
encargadas del cumplimiento de las obligaciones establecidas en la Ley Federal para la Prevención e
Identificación de Operaciones con Recursos de Procedencia Ilícita”, publicada en el Diario Oficial
de la Federación el 16 de agosto de 2024, en mi carácter de Persona participante para la obtención
del Certificado a que se refiere la Base Segunda, inciso a) de la referida Convocatoria:
@endsection

@section('cuerpo')
    <p>
        <span class="opcion">(si / no)</span> <span class="dato">&nbsp;&nbsp;&nbsp;&nbsp;</span>&nbsp;<strong>OTORGO mi autorización para que, en caso de obtener un resultado aprobatorio en mi
        evaluación, la UIF publique en su portal de internet mi nombre completo sin abreviaturas,
        en el listado de las personas certificadas.</strong>
    </p>

    <p>
        En términos de lo dispuesto en el artículo 38 de la Ley Federal para la Prevención e
        Identificación de Operaciones con Recursos de Procedencia Ilícita, la identidad de quienes
        presenten avisos y, en su caso, de sus representantes se considera confidencial y reservada,
        por lo que la presente manifestación y, en su caso, publicación de datos personales no
        significa, por este sólo hecho, que me ubico en el supuesto a que hace referencia el
        artículo invocado.
    </p>
@endsection
