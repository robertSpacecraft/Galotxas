BORRADOR INTERNO — NO PUBLICAR

# Contacto — trazabilidad de la primera capa

Este borrador de 7D.2A queda sustituido como fuente operativa por
`legal/notices/contact-form.md`, aviso `NOTICE-CONTACT-FORM` versión `1.0.0`.
No se importa en runtime ni se presenta en React.

## Decisiones resueltas en 7D.2C2B

- Responsable: `Club Galotxes de Monover`.
- Finalidad: recibir, organizar, atender y gestionar la consulta voluntaria.
- Datos necesarios: nombre, correo, asunto, mensaje y aceptación; HMAC temporal
  de IP para prevención de abuso.
- Base: consentimiento asociado al envío voluntario, coherente con la Política
  de privacidad 1.1.0.
- Destinatarios: personal autorizado y categorías técnicas necesarias, sin
  inventar proveedor productivo.
- Conservación: 12 meses desde cierre; hold proporcionado por reclamación,
  incidente u obligación.
- HMAC: máximo 30 días salvo incidente que justifique bloqueo temporal.
- Derechos: `clubgalotxesmonover@hotmail.com` y `/legal/privacidad`.
- Acción afirmativa: casilla no premarcada vinculada a ID y versión.
- Recepción: acreditada por persistencia; correo auxiliar no condicionante.

## Gates aún abiertos

`CONTACT_FORM_ENABLED=false` y `CONTACT_NOTIFICATION_ENABLED=false` permanecen
como defaults. Antes de producción, 7F debe resolver proveedor, remitente
verificado, credenciales, destinatario, entrega, rebotes, HTTPS, SPF/DKIM/DMARC
cuando corresponda, logs, scheduler, backups/restauración, staging y rollback.

El teléfono continúa siendo privado y no forma parte de este borrador, aviso,
código, fixtures, logs o documentación operativa.
