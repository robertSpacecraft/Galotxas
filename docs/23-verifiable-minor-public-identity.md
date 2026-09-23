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
de la autorización. El expediente puede originarse en una inscripción de
Escuela o directamente en la ficha administrativa de un `Player` menor ya
existente; ambos orígenes comparten el mismo alcance, evidencia y lifecycle.

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

PROFILE-SELF-SERVICE-1 no sustituye esta fuente ni añade booleanos de edad. Una
persona ya conocida como menor no puede cambiar ni borrar su fecha desde Mi
Panel. Una persona adulta no puede autodeclararse menor, y cualquier cambio
real queda bloqueado si existe una autorización vinculada pendiente o aprobada.
La comprobación y la escritura se serializan mediante bloqueo transaccional del
jugador y de las autorizaciones relevantes.

## 6. Modos

- `alias`: sólo el alias normalizado; si falta, `Participante`.
- `name_initial`: nombres de pila e inicial Unicode del primer apellido; si
  faltan datos, `Participante`.
- `anonymous`: registra expresamente la ausencia de identidad individual y
  siempre produce `Participante`.

Ningún modo permite nombre completo ni fallback entre modalidades.
`anonymous` se conserva como decisión explícita del formulario de Escuela; el
origen administrativo directo sólo inicia `alias` o `name_initial`, porque la
ausencia de autorización efectiva ya mantiene `Participante`.

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

No se requiere otro esquema para el origen directo: `school_enrollment_id` ya
es nullable y el expediente nace con `player_id`. La creación se serializa
bloqueando el jugador y rechaza otra autorización `pending` o `approved` para
el mismo jugador y alcance. Una fila `approved` sigue bloqueando aunque haya
dejado de ser efectiva por edad, flags o evidencia: debe cerrarse mediante el
lifecycle existente, sin sustitución silenciosa.

`PublicIdentityAuthorizationEvent` registra la secuencia mínima: solicitud,
anonimato, envío o fallo, confirmación o rechazo, vinculación inicial o
corrección del vínculo, conformidad, aprobación, denegación, revocación,
caducidad y reenvío. Cada vínculo administrativo conserva actor y fecha; una
corrección conserva además el identificador interno anterior y el nuevo. En el
origen directo, `REQUESTED` conserva como actor al administrador que registró
la declaración previa del representante. Escuela mantiene el actor nulo de su
solicitud pública histórica.

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

El correo y la página de confirmación usan texto común a ambos orígenes:
confirmar no publica automáticamente la identidad y el club debe completar la
revisión. No afirman que siempre quede una vinculación posterior. Para el
origen directo, el correo privado añade sólo el nombre registrado del menor
vinculado, sin DOB, DNI, ID interno ni alias, para que el representante
identifique el sujeto. Esa referencia no forma parte de lookup, confirmación,
rechazo, logs ni eventos.

La página de confirmación muestra el contenido compilado completo de
`NOTICE-PUBLIC-IDENTITY-MINORS` antes de ofrecer las decisiones. Compara la
versión devuelta por lookup con la versión local del aviso y falla cerrada, sin
botones de confirmar o rechazar, si no coinciden. No persiste ni duplica el
texto legal.

`PUBLIC_IDENTITY_NOTIFICATION_ENABLED=false` es el valor por defecto. Esta fase
no configura SMTP ni otro proveedor productivo.

## 12. Orígenes de solicitud

### 12.1. Escuela

`POST /api/v1/school/enrollments` exige por separado el aviso de inscripción
`NOTICE-SCHOOL-ENROLLMENT`, actualmente versión `1.0.0`; la Política enlazada
permanece en `1.1.0`. Para menores y sólo con
`PUBLIC_IDENTITY_AUTHORIZATION_ENABLED=true`, admite el objeto opcional
`public_identity_authorization` con modo, versión y declaración de autoridad
cuando el modo es identificable.

El formulario React presenta la sección separada, la versión, el responsable,
la finalidad, las modalidades, sus consecuencias, confirmación posterior,
retirada y enlace a Privacidad. No duplica el correo, no pide DNI y no persiste
datos en almacenamiento del navegador.

### 12.2. Origen directo desde Player

`POST /admin/players/{player}/public-identity-authorizations` sólo está
disponible bajo la sesión administrativa existente. Acepta un payload cerrado
con representante, relación, correo, uno de los modos `alias` o `name_initial`,
registro de la declaración previa del representante y la pareja vigente de
aviso. El sujeto procede siempre del route model binding; `player_id`,
`anonymous` y cualquier campo adicional se rechazan.

La casilla administrativa no declara que el operador ejerza patria potestad o
tutela. Confirma que el representante indicado ya declaró ante el Club que la
ejerce y que solicita la tramitación. `guardian_authority_declared_at`
representa el instante en que el Club registra esa manifestación, y
`REQUESTED` identifica al administrador autenticado que la registró.

Antes de persistir se exige DOB conocida, minoría actual, aviso reconocido,
evidencia del representante, ausencia de otra solicitud pendiente o aprobada y
datos suficientes para el modo exacto. `alias` requiere un alias no vacío;
`name_initial`, nombres de pila y primer apellido; no hay fallback entre modos.
La ausencia de expediente o el rechazo del representante mantienen
`Participante`; administración no crea un expediente `anonymous`.

Los flags de autorización y notificación bloquean toda creación directa cuando
están desactivados, porque la confirmación por correo forma parte del flujo. El
envío ocurre después de la transacción; un fallo conserva el expediente
pendiente y deja disponible el reenvío existente.

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

Estas reglas de candidatos y corrección pertenecen sólo al origen Escuela. Una
autorización directa nace vinculada de forma inequívoca al `Player` desde cuya
ficha se solicitó, no muestra candidatos y rechaza explícitamente todo intento
de `linkPlayer()`. El sujeto no puede cambiarse aunque aún no exista
confirmación: cualquier corrección exige cerrar el expediente según corresponda
y crear otro desde el jugador correcto.

La vinculación administrativa bloquea también la fila `players` antes de
asociar el expediente. Así no puede competir con una corrección DOB propia. El
servicio de perfil nunca religa, revoca, elimina o modifica evidencia de
autorización.

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

Los cambios naturales de edad no exigen una revocación automática: la eficacia
se recalcula en cada lectura. Al cumplir 18 años la autorización deja de ser
efectiva; al cruzar el umbral 14–17 se exige la conformidad ya definida. Mi
Panel no crea atajos ni casillas que sustituyan esos requisitos.

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

La ficha de un jugador menor muestra la proyección resuelta por
`PublicPlayerIdentityService`, el expediente relevante y, cuando no existe un
bloqueo `pending`/`approved`, el formulario directo. DOB ausente, mayoría de
edad, flags desactivados o ausencia de un modo afirmativo proyectable no ofrecen
acciones que vayan a fallar. La casilla registra una manifestación previa del
representante, no una declaración de patria potestad del administrador. El
detalle distingue visualmente el origen: Escuela conserva candidatos y
relinking previo a evidencia; Player muestra el sujeto fijo y ninguna
selección.

## 21. Seguridad

Los flags de autorización y notificación nacen desactivados. Las rutas públicas
aceptan únicamente POST con cuerpo cerrado, los enlaces inválidos, usados o
caducados comparten `404`, y la pantalla aislada usa `noindex`, no incluye
Navbar/footer ni carga terceros. Las acciones Blade mantienen sesión, CSRF y
autorización administrativa existentes.

## 22. Privacidad

Se almacena sólo la evidencia necesaria: correo normalizado, representante y
relación aportados en Escuela o en la solicitud administrativa directa,
versión, fechas y actores. En el origen directo, la fecha de declaración es el
momento de registro por el Club y el evento de solicitud conserva al admin que
lo hizo. No se guarda token
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

MINOR-PUBLIC-IDENTITY-DIRECT-1 añade pruebas de creación directa, flags,
proyección posible, rechazo de `anonymous`, actor de solicitud, semántica de la
declaración, estados bloqueantes e históricos, notificación posterior a
persistencia, referencia privada mínima en correo, payload admin cerrado, ficha
de Player, detalle de ambos orígenes, rechazo de relink, confirmación pública
sin PII, aviso íntegro con versión coincidente y aprobación/revocación en los
dos grupos de edad. `PublicIdentityAuthorizationTest` conserva la regresión
completa del origen Escuela.

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

## 26. Seguimiento de 7D.2C2B

7D.2C2B completa después la primera capa y operación técnica de Contacto con un
aviso separado, destinatario/remitente configurables, persistencia como
recepción, fallos/reintentos, conservación, holds y anonimización. No mezcla ni
modifica la autorización de identidad de menores. Proveedor, entrega y
activación productiva continúan en 7F.

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

### 27.1. Aceptación humana del origen directo

El recorrido local usa sólo datos ficticios y un capturador SMTP desechable
ligado a loopback; no usa el mailer `log`, no consulta hashes en MariaDB y no
añade endpoints o visualización administrativa del token:

1. activar temporalmente ambos flags únicamente en el entorno local;
2. configurar el mailer local contra el capturador SMTP y abrir la ficha de un
   `Player` menor ficticio;
3. crear la solicitud desde la card de identidad pública;
4. recuperar el enlace exclusivamente desde la bandeja local del capturador y
   comprobar que el correo identifica al menor correcto; abrirlo, verificar el
   aviso específico y confirmar la decisión;
5. si tiene entre 14 y 17 años, registrar la conformidad informada;
6. aprobar y comprobar en una superficie pública de competición que deja de
   mostrarse `Participante` y aparece exactamente el modo elegido;
7. revocar y comprobar la retirada inmediata a `Participante`.

En staging este recorrido sólo procede dentro de una ventana autorizada, con
datos ficticios, flags temporales y un canal de correo seguro accesible al
operador. El token nunca se obtiene desde la base de datos, logs, una respuesta
admin o un mecanismo de depuración del producto. Sin entrega real configurada,
la automatización con `Mail::fake()` es la única evidencia disponible y el
walkthrough de correo queda pendiente.

## 28. Criterios de cierre

7D.2C2A queda cerrada cuando fuente y proyecciones legales coinciden, migración
y seeder aislado funcionan en MariaDB, backend y frontend aplican fail-closed,
administración no permite aprobar evidencia incompleta, revocación es inmediata,
tests y E2E pasan y `git diff --check` queda limpio. El posterior 7D.2C2B cierra
7D.2, no 7D.3, 7D, Fase 7 ni MVP.

Seguimiento: 7D.3 se implementa posteriormente sin modificar esta autorización;
sus 61 escenarios E2E cierran 7D. Fase 7, despliegue y MVP continúan pendientes.


---

**Nota de seguimiento posterior (Fase 7F.2):** Tras la aceptación de staging, ciertas decisiones (como el modelo de navegación en Competición y el aplazamiento de noticias y multimedia persistente) han sido promovidas o refinadas en la Fase 7F.2. Ver `docs/28-preproduction-product-refinement.md` y `ADR-042`.
