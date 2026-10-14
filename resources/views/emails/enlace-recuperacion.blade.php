{{-- Correo de texto: el enlace va sin escapar porque {{ }} convertiría el
     & de la firma en &amp; y el enlace dejaría de servir. --}}
Recibimos una solicitud para recuperar la clave de acceso de tu cuenta SUIF.

Abre este enlace para generar una clave nueva:

{!! $enlace !!}

El enlace sirve una sola vez y caduca en {{ $vigencia }} minutos. Tu clave actual sigue funcionando hasta que lo uses.

Si tú no lo solicitaste, ignora este mensaje: tu cuenta no cambió.

Este mensaje se generó automáticamente; no respondas a este correo.
