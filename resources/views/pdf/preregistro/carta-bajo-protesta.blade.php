{{--
    Formato 3: Declaración bajo protesta de decir verdad.
    Texto tomado literalmente del formato oficial de la UIF (convocatoria 2024).
    Este formato cita el DOF del 19 de agosto, no el del 16 como los otros tres.
--}}
@extends('pdf.preregistro.layout')

@section('asunto', 'Declaración bajo protesta de decir verdad')

@section('apertura')
en términos de lo establecido en las Bases Décima, Décima Tercera y Décima Quinta, inciso c) de la
“Convocatoria para la Certificación en materia de operaciones con recursos de procedencia ilícita,
dirigida a las personas físicas que realizan actividades vulnerables y a las responsables
encargadas del cumplimiento de las obligaciones establecidas en la Ley Federal para la Prevención e
Identificación de Operaciones con Recursos de Procedencia Ilícita”, publicada en el Diario Oficial
de la Federación el 19 de agosto de 2024, en mi carácter de Persona participante para la obtención
del Certificado al que se refiere la Base Segunda, inciso a) de la referida Convocatoria,
<strong>declaro bajo protesta de decir verdad lo siguiente:</strong>
@endsection

@section('cuerpo')
    <table class="incisos">
        <tr>
            <td class="incisos__letra">a)</td>
            <td>Que no he sido condenado(a) mediante sentencia firme por algún delito patrimonial;</td>
        </tr>
        <tr>
            <td class="incisos__letra">b)</td>
            <td>
                Que no estoy inhabilitado(a) mediante resolución firme para desempeñar un empleo,
                cargo o comisión en el servicio público federal, estatal o municipal, o en el
                sistema financiero mexicano o de cualquier otro país;
            </td>
        </tr>
        <tr>
            <td class="incisos__letra">c)</td>
            <td>
                Que no obra ninguna operación o actividad vulnerable relacionada a mi persona en la
                que se pueda advertir un riesgo o posibilidad de que los recursos pudieran provenir
                de actividades ilícitas o pudieran estar destinados a favorecer, prestar ayuda,
                auxilio o cooperación de cualquier especie para la comisión de los delitos previstos
                en los artículos 139 Quáter o 400 Bis del Código Penal Federal; y
            </td>
        </tr>
        <tr>
            <td class="incisos__letra">d)</td>
            <td>
                Que la información y documentación proporcionada para la obtención del Certificado
                es veraz.
            </td>
        </tr>
    </table>
@endsection
