<h1>Nueva solicitud de contacto</h1>

<p><strong>Nombre:</strong> {{ $contactRequest->name }}</p>
<p><strong>Correo:</strong> {{ $contactRequest->email }}</p>
<p><strong>Asunto:</strong> {{ $contactRequest->subject }}</p>

<p><strong>Mensaje:</strong></p>
<p>{!! nl2br(e($contactRequest->message)) !!}</p>

<p>
    La solicitud ya está guardada en el panel administrativo con el identificador
    {{ $contactRequest->id }}.
</p>
