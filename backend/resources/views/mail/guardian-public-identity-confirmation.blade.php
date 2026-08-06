<!DOCTYPE html>
<html lang="es">
<body>
    <h1>Confirma una decisión de identidad deportiva</h1>
    <p>
        Club Galotxes de Monover ha recibido una solicitud opcional para mostrar
        la identidad de una persona menor en la competición pública.
    </p>
    <p>
        Modo solicitado: <strong>{{ $authorization->mode->label() }}</strong>.<br>
        Alcance: calendarios, partidos, resultados, clasificaciones, rankings e
        histórico de competición.<br>
        Aviso: {{ $authorization->notice_id }} versión {{ $authorization->notice_version }}.
    </p>
    <p>
        La inscripción y la participación no dependen de esta decisión. El enlace
        es de un solo uso y caduca el
        {{ $authorization->confirmation_token_expires_at->format('d/m/Y H:i') }}.
    </p>
    <p><a href="{{ $confirmationUrl }}">Revisar, confirmar o rechazar la solicitud</a></p>
    <p>
        Confirmar no publica automáticamente ninguna identidad: el club debe
        revisar y vincular después la solicitud con el jugador correcto. Puedes
        retirar una autorización escribiendo a clubgalotxesmonover@hotmail.com.
    </p>
    <p>
        Consulta la <a href="{{ $privacyUrl }}">Política de privacidad</a>.
    </p>
</body>
</html>
