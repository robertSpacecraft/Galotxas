---
id: LEG-002
title: Política de privacidad
slug: privacidad
version: 1.0.0
status: vigente
published_at: 2026-08-06
reviewed_at: 2026-08-06
owner: Club Galotxes de Monover
source_draft: docs/legal-drafts/privacidad.borrador.md
summary: Información sobre los tratamientos de datos personales, sus finalidades, conservación y derechos.
---
# Política de privacidad

## Responsable y contacto

El responsable de los tratamientos descritos es **Club Galotxes de Monover**, con denominación pública **Club Galotxes Monòver**, CIF G03912193 y domicilio social en C/ Pierrot, 1, 1.º, 03640 Monóvar, Alicante. Jorge Sánchez Romero es el presidente y responsable web confirmado.

Para consultas sobre privacidad o para ejercer derechos puede escribirse a clubgalotxesmonover@hotmail.com. Las instalaciones deportivas habituales, Centro Polideportivo de Monóvar, Av. Novelda, s/n, 03640 Monòver, Alicante, son distintas del domicilio social.

## Finalidades y bases

Los datos se tratan sólo para finalidades determinadas y con la información necesaria en cada contexto.

| Contexto | Finalidad | Fundamento previsto |
|---|---|---|
| Cuentas y autenticación | Crear y proteger la cuenta, permitir el acceso y recuperar credenciales | Ejecución de la relación solicitada, medidas precontractuales cuando correspondan y obligaciones aplicables |
| Relación deportiva e inscripciones | Gestionar participantes, Escuela, competiciones, equipos, partidos, resultados y comunicaciones operativas | Gestión de la relación deportiva o de la inscripción y obligaciones aplicables |
| Contacto | Recibir, organizar y responder una consulta cuando el formulario llegue a activarse | Consentimiento asociado al envío voluntario de la consulta |
| Seguridad y administración | Prevenir abusos, limitar peticiones, diagnosticar incidencias y proteger cuentas y sistemas | Interés legítimo sujeto a validación y ponderación, además de las obligaciones aplicables |
| Identidad deportiva pública | Mostrar una identidad opcional en resultados y otras superficies públicas | Consentimiento específico, verificable y revocable |
| Imágenes y redes sociales | Publicar una imagen en cada canal autorizado | Consentimiento separado por finalidad y canal u otra base acreditada para el caso concreto |
| Junta directiva | Informar de la composición y cargos del órgano del club | Función institucional y fundamento aplicable, con publicación limitada a nombre y cargo |

Una aceptación necesaria para participar o inscribirse no autoriza por sí sola la publicación de la identidad o de imágenes. Esos usos requieren decisiones independientes.

## Cuentas, perfiles y autenticación

El registro y el área de cuenta pueden tratar nombre, apellidos, correo, credenciales protegidas, rol, estado de cuenta y tokens de acceso. El perfil deportivo puede incluir alias, fecha de nacimiento, nivel y otros datos necesarios para la gestión. Los identificadores de mayor impacto y las notas internas quedan restringidos a los contextos autorizados y no forman parte de las proyecciones públicas.

React conserva el token Bearer de autenticación en `localStorage.token`. El perfil se restaura desde el servidor y no se almacena en `localStorage.user`. El cierre de sesión elimina el token local correspondiente.

## Contacto

El formulario de Contacto permanece desactivado. La página pública no recoge ni conserva campos de consulta. Antes de activarlo se informará de la finalidad, el plazo, el destinatario operativo y el proveedor de correo; la persona usuaria deberá realizar una acción afirmativa no premarcada.

Cuando se active, la consulta podrá incluir nombre, correo, asunto y mensaje, además de datos técnicos minimizados para prevenir abuso. No debe incorporarse información innecesaria en el texto libre.

## Escuela de Galotxas

La solicitud de Escuela puede incluir datos del participante, fecha de nacimiento, teléfono y correo de contacto, nivel opcional y, cuando sea menor, datos de su representante y relación con el menor. Esta información se utiliza para tramitar y gestionar la inscripción, no para publicar al alumno.

Los centros educativos y las actividades coordinadas con ellos son información interna y no se exponen mediante la API pública de Escuela.

## Competición e identidad deportiva pública

La gestión deportiva trata inscripciones, equipos, partidos, resultados, clasificaciones, calendarios y reprogramaciones. La API pública utiliza una proyección minimizada y no publica identificadores personales, correo, fecha de nacimiento ni el perfil privado completo.

Para personas adultas, el sistema puede mostrar el alias deportivo y, si no existe, el nombre con la inicial del primer apellido. Para menores o personas cuya fecha de nacimiento no consta, la regla actual es cerrada: **sin autorización verificable y vigente se muestra “Participante”**. React representa esa cadena y no reconstruye una identidad con otros campos.

## Menores y autorización futura

La inscripción deportiva y la autorización para mostrar una identidad pública son independientes. La autorización pública será opcional, no condicionará la participación, no estará premarcada, será específica y revocable, y registrará su versión y alcance.

Los modos que podrán autorizarse en el futuro son:

- alias;
- nombre e inicial del primer apellido;
- identidad anónima.

Las iniciales también pueden permitir identificar a una persona y no se usarán sin autorización. El diseño futuro distinguirá:

- identidad en resultados;
- imágenes en la web;
- imágenes en redes sociales;
- conservación en archivo histórico.

Ese flujo todavía no está implementado. Su diseño prevé confirmación del representante para menores de 14 años; aceptación del menor y confirmación del representante entre 14 y 17 años; y consentimiento propio desde los 18 años. La confirmación se realizará mediante un enlace de correo de un solo uso, declaración de patria potestad o tutela, revisión administrativa y un mecanismo de revocación. No se solicitará un documento de identidad de forma general; cualquier comprobación adicional requerirá una duda justificada y una medida proporcionada.

Hasta que exista ese registro verificable, cualquier menor o edad desconocida mantiene la etiqueta “Participante”.

## Imágenes

La publicación de imágenes exige acreditar procedencia, autoría o cesión, personas identificables, presencia de menores, finalidad, canales, vigencia y procedimiento de retirada. La autorización para la web no se extiende automáticamente a redes sociales ni al archivo histórico. Los archivos existentes no se consideran autorizados por el mero hecho de estar en el repositorio.

## Administración, seguridad, logs y copias

Los administradores activos acceden a la información necesaria para sus funciones mediante el panel protegido. Se aplican controles de autenticación, autorización, CSRF, limitación de peticiones y registro técnico de incidencias. Los accesos y permisos deben revisarse conforme al principio de mínimo privilegio.

Los logs pueden contener datos técnicos como dirección IP, agente de usuario, identificador de cuenta o contexto de un error. Deben minimizarse y limitarse a diagnóstico y seguridad. Las copias de seguridad son una medida operativa de recuperación y no una fuente para conservar datos indefinidamente.

## Conservación

| Tratamiento | Plazo o criterio |
|---|---|
| Consultas de Contacto | 12 meses desde el cierre |
| Hash de IP contra abuso | 30 días como máximo, salvo incidente |
| Solicitud de Escuela retirada, rechazada o no formalizada | 6 meses desde el cierre |
| Alumnos de Escuela | Durante la inscripción y 2 años después |
| Cuentas inactivas | Revisión a 24 meses; aviso y eliminación tras 30 días |
| Logs ordinarios | 30 días |
| Logs de seguridad | 90 días |
| Copias de seguridad | Rotación de 30 días |
| Autorizaciones de imágenes | Mientras se publique la imagen y 3 años después |
| Datos completos de competición | Mientras sean necesarios para gestión activa |
| Resultados históricos | Conservación histórica con identidad minimizada |
| Junta directiva | Durante el cargo y después sólo en contexto histórico justificado |

Una reclamación, una obligación aplicable o un incidente de seguridad puede suspender el borrado de los datos estrictamente necesarios mientras subsista esa causa. Al vencer cada plazo, los datos se eliminarán o anonimizarán. No se conservarán indefinidamente por mera conveniencia y se distinguirán los datos privados operativos de los resultados históricos con identidad minimizada.

Estos criterios requieren procedimientos técnicos antes del despliegue productivo; esta versión no incorpora tareas automáticas de borrado.

## Proveedores y transferencias

El despliegue previsto contempla Vercel para el frontend, Railway para backend y base de datos, GitHub para el repositorio y el servicio asociado a la dirección pública de Hotmail para ese buzón. El proveedor de correo saliente del futuro formulario todavía no se ha seleccionado.

Antes de producción se revisarán la configuración efectiva, accesos, contratos, encargos, ubicación del tratamiento, copias, eliminación y, si existieran, transferencias internacionales y sus garantías. Esta política no atribuye una región, contrato o transferencia concreta sin verificarla.

## Derechos y reclamación

Las personas pueden solicitar acceso, rectificación, supresión, oposición, limitación y portabilidad cuando resulten aplicables, así como retirar un consentimiento sin afectar a la licitud del tratamiento previo. La solicitud puede enviarse al correo indicado, describiendo el derecho y aportando sólo la información necesaria para verificar la identidad y responder.

Si una persona considera que su solicitud no ha sido atendida adecuadamente, puede reclamar ante la [Agencia Española de Protección de Datos](https://www.aepd.es/).

## Seguridad y cambios

El club aplica medidas técnicas y organizativas proporcionadas al contexto y revisa sus riesgos. Ningún sistema es completamente inmune a incidentes; si se detecta uno, se evaluará y gestionará conforme a las obligaciones aplicables.

La política puede actualizarse por cambios normativos, operativos o técnicos. La cabecera muestra la versión y la fecha de publicación vigentes.
