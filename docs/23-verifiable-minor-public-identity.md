# Identidad pública verificable de menores

## 1. Propósito

Este documento registra `VERIFIABLE-MINOR-PUBLIC-IDENTITY-1`, correspondiente a
la Fase 7D.2C2A. Define la autorización opcional, específica, informada,
versionada, verificable y revocable que permite proyectar una identidad
deportiva mínima de una persona menor.

La regla de seguridad es cerrada: sin autorización efectiva, Laravel devuelve
`Participante`.

## 2. Alcance

El único alcance admitido es `public_competition_identity`. Afecta a
calendarios, partidos, resultados, clasificaciones, rankings e histórico
deportivo público minimizado. La decisión se aplica en backend mediante
`PublicPlayerIdentityService`; React no reconstruye identidad ni recibe datos
de la autorización.

## 3. Fuera de alcance

No cubre fotografías, vídeo, redes sociales, publicidad, archivo histórico de
imágenes, CMS institucional, DNI general, despliegue, proveedor de correo
productivo, Contacto ni jobs programados de purga. No cambia la política
pública de personas adultas.

## 4. Política

La inscripción y la identidad pública son procesos independientes. Omitir o
rechazar la autorización no impide inscribirse, entrenar o competir. La opción
inicial del formulario es anónima, no está premarcada una modalidad
identificable y toda condición ausente produce `Participante`.

## 5. Edades

- Menor de 14 años: confirmación del representante y revisión administrativa.
- De 14 a 17 años: además, conformidad informada del menor registrada por un
  administrador.
- Desde 18 años: la autorización del representante deja de aplicarse y se usa
  de forma independiente la política adulta vigente de 7D.2B: alias cuando
  exista o, en su defecto, nombres de pila e inicial del primer apellido. Una
  autorización anterior, incluida una revocada, no se interpreta como decisión
  adulta.

La edad procede de `Player.birth_date`; nunca se infiere de una categoría. La
fecha declarada en Escuela sólo sirve para exigir representante y para validar
la vinculación posterior.

## 6. Modos

- `alias`: sólo el alias normalizado; si falta, `Participante`.
- `name_initial`: nombres de pila e inicial Unicode del primer apellido; si
  faltan datos, `Participante`.
- `anonymous`: registra expresamente la ausencia de identidad individual y
  siempre produce `Participante`.

Ningún modo permite nombre completo ni fallback entre modalidades.

## 7. Alcance versionado

La autorización guarda el alcance cerrado y la pareja `notice_id` y
`notice_version`. Sólo se reconoce
`NOTICE-PUBLIC-IDENTITY-MINORS` versión `1.0.0`, compilada desde
`legal/notices/public-identity-minors.md`. Un alcance o versión desconocidos
invalidan la eficacia.

## 8. Estados

La máquina de estados contiene `pending`, `approved`, `denied`, `revoked` y
`expired`. La confirmación del representante no cambia `pending` a `approved`:
la revisión administrativa sigue siendo obligatoria. Una denegación,
revocación o caducidad es histórica; otra decisión requiere una solicitud
nueva.

## 9. Modelo

`PublicIdentityAuthorization` conserva relaciones explícitas con la
inscripción, el jugador, quien registra conformidad, quien revisa y quien
revoca. MariaDB restringe alcance, modo y estado mediante enums, aplica
integridad referencial y reserva un único `approval_slot = 1` por jugador y
alcance. Los estados históricos no se borran al crear otra solicitud.

`PublicIdentityAuthorizationEvent` registra la secuencia mínima: solicitud,
anonimato, envío o fallo, confirmación o rechazo, vinculación inicial o
corrección del vínculo, conformidad, aprobación, denegación, revocación,
caducidad y reenvío. Cada vínculo administrativo conserva actor y fecha; una
corrección conserva además el identificador interno anterior y el nuevo.

## 10. Tokens

Los tokens son aleatorios de 64 caracteres, se guardan exclusivamente como
SHA-256, caducan por defecto a las 48 horas, son de un solo uso y se invalidan
al confirmar, rechazar o reenviar. El enlace usa un fragmento
`#token=...`: el fragmento no forma parte de la petición HTTP inicial. React lo
captura en memoria una sola vez y ejecuta `history.replaceState` durante esa
captura, antes de cualquier petición remota. La API lo recibe después sólo en
el cuerpo de un POST. No se guarda en almacenamiento web o persistente ni se
registra en logs; recargar o volver atrás no lo recupera ni repite la operación.
Blade, Resources, respuestas y eventos nunca muestran el token ni su hash.

## 11. Correo

`GuardianPublicIdentityConfirmation` usa una plantilla local sin recursos
remotos y permite revisar, confirmar o rechazar. La persistencia termina antes
del intento de envío. Un fallo mantiene la autorización pendiente, registra un
evento sanitizado con sólo la clase técnica y permite reenvío administrativo.

`PUBLIC_IDENTITY_NOTIFICATION_ENABLED=false` es el valor por defecto. Esta fase
no configura SMTP ni otro proveedor productivo.

## 12. Escuela

`POST /api/v1/school/enrollments` exige por separado la información de
privacidad de la inscripción, actualmente versión `1.1.0`. Para menores y sólo
con `PUBLIC_IDENTITY_AUTHORIZATION_ENABLED=true`, admite el objeto opcional
`public_identity_authorization` con modo, versión y declaración de autoridad
cuando el modo es identificable.

El formulario React presenta la sección separada, la versión, el responsable,
la finalidad, las modalidades, sus consecuencias, confirmación posterior,
retirada y enlace a Privacidad. No duplica el correo, no pide DNI y no persiste
datos en almacenamiento del navegador.

## 13. Vínculo con jugador

`SchoolEnrollment` no tiene una relación fiable automática con `Player`. La
solicitud nace ligada a la inscripción y sin jugador. La coincidencia exacta de
fecha de nacimiento sólo filtra candidatos compatibles: no prueba identidad,
no produce vínculo automático y ni siquiera un candidato único queda
preseleccionado. Cero o varios candidatos mantienen la autorización pendiente.

Blade muestra únicamente nombre interno, apellido, alias cuando exista y fecha
de nacimiento de los candidatos compatibles. Un administrador debe seleccionar
uno y confirmar expresamente que ha comprobado que corresponde a la persona de
la inscripción. Hasta entonces la autorización no puede aprobarse ni afectar a
la API pública. Un payload con otra fecha se rechaza en backend y el vínculo no
se expone mediante la API pública.

Antes de que exista confirmación del representante o conformidad del menor, una
corrección explícita puede sustituir el vínculo y genera un evento histórico
diferenciado. Después de confirmar evidencia no puede cambiarse silenciosamente
el sujeto: se debe cerrar o revocar el expediente cuando corresponda y registrar
una autorización nueva. Las autorizaciones aprobadas tampoco admiten
revinculación.

## 14. Confirmación del representante

Los POST públicos de consulta, confirmación y rechazo están bajo
`/api/v1/public-identity/confirmation`, usan respuestas genéricas y rate limits
basados en HMAC del IP y token. No devuelven nombre, correo, nacimiento o IDs.
Confirmar sólo registra evidencia; rechazar termina la solicitud como
`denied`.

## 15. Conformidad del menor

Entre 14 y 17 años, Blade exige una declaración administrativa explícita de
que el menor recibió el aviso versionado y manifestó conformidad. Se guardan
fecha y administrador. No se solicita un correo adicional del menor.

## 16. Revisión administrativa

Sólo un administrador activo puede listar, filtrar, consultar y actuar. La
aprobación valida en una transacción: vínculo fiable, minoría actual, modo,
alcance y versión reconocidos, confirmación del representante, conformidad
cuando procede y ausencia de otra autorización aprobada. El modo no es editable
durante la revisión.

## 17. Proyección pública

`PublicPlayerIdentityService` carga autorizaciones de forma anticipada en
partido, calendario, standings y los rankings de categoría, campeonato,
temporada e histórico. Sólo `public_display_name` llega a los Resources
públicos. No se exponen autorización, estado, motivo, correo, nacimiento,
versión ni relaciones privadas. Si el flag se desactiva, el comportamiento de
menores vuelve inmediatamente a `Participante`.

## 18. Revocación

Blade permite revocar una autorización aprobada y conservar un motivo privado
mínimo. La revocación libera el slot efectivo y cambia inmediatamente todas las
proyecciones públicas a `Participante`, sin alterar inscripción, jugador ni
resultados. El canal de derechos publicado permite solicitarla; no existe un
token permanente de revocación.

## 19. Retención

La política operativa conserva la evidencia mientras se usa la identidad y
tres años después de denegación, revocación o finalización. Los residuos
técnicos de tokens usados o caducados son purgables a los 30 días. Esta fase no
programa borrados; una reclamación puede suspender sólo la eliminación
estrictamente necesaria.

## 20. Administración

El panel ofrece listado y filtros por estado, modo, grupo de edad y fecha,
detalle, vínculo de jugador, conformidad 14–17, aprobación, denegación,
revocación, reenvío e historial. El reenvío está limitado y reemplaza el token
anterior. No existen borrado, exportación ni edición retroactiva desde la
interfaz.

## 21. Seguridad

Los flags de autorización y notificación nacen desactivados. Las rutas públicas
aceptan únicamente POST con cuerpo cerrado, los enlaces inválidos, usados o
caducados comparten `404`, y la pantalla aislada usa `noindex`, no incluye
Navbar/footer ni carga terceros. Las acciones Blade mantienen sesión, CSRF y
autorización administrativa existentes.

## 22. Privacidad

Se almacena sólo la evidencia necesaria: correo normalizado, representante y
relación ya aportados a Escuela, versión, fechas y actores. No se guarda token
en claro, DNI, IP completa, payload de correo ni texto legal duplicado. Los
eventos usan metadata allowlisted y no replican correo o notas.

## 23. Testing

Los Feature tests en MariaDB cubren constraints, unicidad efectiva,
transiciones, cero, uno y varios candidatos, selección y corrección explícitas,
fechas incompatibles, bloqueo tras evidencia, aprobación sin vínculo, grupos de
edad, modos, fallos cerrados, token, caducidad, uso único, reenvío, rate
limiting, correo fake y fallido, historial, permisos, independencia de Escuela
y privacidad de la API. Vitest/RTL cubre formulario, versiones, modos, foco,
errores, doble envío, aislamiento, captura y retirada inmediata del fragmento,
almacenamientos, logs, navegación atrás, recarga y decisión pública.

## 24. E2E

`E2ESmokeSeeder` sólo crea fixtures ficticias en `APP_ENV=e2e` y
`galotxas_e2e`. El entorno habilita los flags y fuerza un fallo de transporte
controlado; los tokens conocidos existen sólo en el código de fixture y la base
guarda sus hashes. Playwright recorre inscripción, confirmación, revisión,
conformidad, aprobación, proyección, revocación, rechazo, caducidad, legales,
Manual, Contacto oculto, 320 px y ausencia de recursos remotos.

## 25. Riesgos residuales

- Falta configurar y verificar entrega de correo en un entorno productivo.
- La vinculación manual exige un procedimiento operativo para resolver dudas
  de representación sin pedir documentos por defecto.
- La retención está definida pero no existe purga programada.
- El canal de retirada es atendido manualmente.
- Una futura modificación del aviso necesita estrategia explícita de renovación
  para autorizaciones anteriores.

## 26. Gates de 7D.2C2B

7D.2C2B permanece pendiente y corresponde a la primera capa y operación del
formulario de Contacto: destinatario, proveedor y remitente, envío y errores,
conservación y borrado, capacidad de atención y activación controlada.

Las autorizaciones de imágenes, web, redes sociales y archivo histórico siguen
pendientes como un frente independiente posterior, sin numeración aprobada. No
forman parte de 7D.2C2B ni pueden reutilizar la autorización de identidad
deportiva.

## 27. Gates de producción

Antes de activar flags: proveedor y remitente validados, entrega y rebotes,
URL HTTPS, secreto de aplicación, política de logs, responsable de atención,
procedimiento de vinculación y dudas de representación, revocación, conservación
y borrado, backup, staging, rollback y aceptación humana. La activación de
Contacto tiene gates propios y no deriva de este flujo.

## 28. Criterios de cierre

7D.2C2A queda cerrada cuando fuente y proyecciones legales coinciden, migración
y seeder aislado funcionan en MariaDB, backend y frontend aplican fail-closed,
administración no permite aprobar evidencia incompleta, revocación es inmediata,
tests y E2E pasan y `git diff --check` queda limpio. No cierra 7D.2C2B, 7D.2,
7D, Fase 7 ni MVP.
