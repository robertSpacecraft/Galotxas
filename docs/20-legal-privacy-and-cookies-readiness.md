# Preparación legal, privacidad y cookies

## 1. Propósito y estado

Este documento registra **LEGAL-PRIVACY-READINESS-1**, la Fase 7D.2A, y su
reauditoría técnica tras 7D.2B. Es una base documental interna para preparar
7D.2C. No constituye
asesoramiento jurídico, no publica textos legales y no autoriza por sí misma
Contacto, Escuela, registro de cuentas, identidad deportiva, imágenes o
despliegue productivo.

7D.2A queda limitada históricamente a inventario, consolidación de fuentes,
borradores y gates. 7D.2B aplica minimización y retirada de terceros. 7D.2C1
promueve después tres textos a `legal/`, publica sus rutas y los enlaza en el
footer. Tras 7D.2C2B, la capacidad técnica de Contacto y 7D.2 quedan cerradas;
7D.3 implementa después la indexación fail-closed y sus 61 escenarios E2E
cierran 7D. Fase 7 y el MVP siguen pendientes, y
`CONTACT_FORM_ENABLED=false` continúa siendo el valor por defecto.

Los datos y riesgos de esta auditoría conservan su valor como evidencia de
preparación. La política pública, su compilador y los gates actualizados se
documentan en `22-versioned-legal-pages.md`; los borradores de esta fase no se
han convertido en fuente runtime.

## 2. Alcance y fuentes revisadas

Se revisaron los `AGENTS.md`, README y guías de estilo aplicables; la
documentación 00–19 y el changelog; modelos, migraciones, Requests, servicios,
controladores, Resources, rutas, Blade, configuración y pruebas backend; el
router, cliente HTTP, sesión de autenticación, páginas, metadatos, assets,
fixtures y E2E frontend; la configuración Docker; y la referencia histórica
`EST-REF-001` completa.

La auditoría es estática: no acredita la configuración ni los datos de un
entorno productivo inexistente. Factories, seeders, `.env.example` y fixtures
demuestran capacidad técnica, no datos reales ni decisiones jurídicas.

La precedencia para resolver datos institucionales es:

1. documento jurídico original;
2. dato legal confirmado por el club;
3. acuerdo formal;
4. documentación administrativa vigente;
5. CMS;
6. documentación técnica;
7. tradición oral;
8. inferencia.

## 3. Estatutos históricos

`EST-REF-001` es una transcripción histórica en valenciano, organizada por
páginas y artículos. El fichero está versionado en
`knowledge/referencias/institucionales/EST-REF-001_estatutos_club_galotxes_de_monover_1980.md`.
Su contenido SHA-256 auditado es
`17314902d717fa94a3b4016dcd5d6bed7ee6a94a233a314b58fb7ce83d56eadc`.

| Aspecto | Resultado |
|---|---|
| Identificador | `EST-REF-001` |
| Naturaleza | Transcripción histórica; no documento jurídico vigente acreditado |
| Fecha de constitución | 31 de marzo de 1980, apoyada por la referencia estatutaria histórica y confirmada por el club; un certificado registral vigente sería evidencia adicional |
| Procedencia del original | `PENDIENTE DE CONFIRMACIÓN` |
| Autoría de la transcripción/digitalización | `PENDIENTE DE CONFIRMACIÓN` |
| Integridad | El cuerpo no se modernizó, corrigió ni reconcilió |
| Límites | Contiene referencias normativas y datos institucionales históricos que no prueban la situación actual |
| Estado editorial | Referencia institucional histórica, fuera de las colecciones compilables |
| Publicación Manual | Excluida de los artefactos canónico y público mediante allowlist exacta y test de descubrimiento |

El nombre de archivo se normalizó mediante el `git mv` realizado manualmente
por el usuario. Git conserva el cambio como renombrado al 100 %, no existe una
copia duplicada y el hash del contenido permanece intacto.

Las referencias normativas de 1980 y la cláusula transitoria del texto son
históricas. No deben presentarse como legislación vigente, inscripción actual,
domicilio actual, composición vigente o aprobación administrativa actual.

## 4. Identidad institucional

- Denominación jurídica confirmada por el club: `Club Galotxes de Monover`.
- Denominación pública confirmada: `Club Galotxes Monòver`.
- CIF confirmado por el club como dato legal y administrativo: `G03912193`.
- Domicilio social confirmado por el club: C/ Pierrot, 1, 1.º, 03640
  Monóvar, Alicante.
- Correo público confirmado: `clubgalotxesmonover@hotmail.com`.
- Instalaciones confirmadas: Centro Polideportivo de Monóvar, Av. Novelda,
  s/n, 03640 Monòver, Alicante.
- Fecha de constitución: 31 de marzo de 1980, apoyada por la referencia
  estatutaria histórica.
- Presidente actual y responsable web confirmado: Jorge Sánchez Romero. Su
  condición formal de representante legal con alcance jurídico general queda
  pendiente de acreditación expresa.
- Junta confirmada para publicación institucional: Jorge Sánchez Romero,
  Carlos Bernabé, Abel Payá, José Carlos Payá, Antonio Bernabé, Álvaro
  Marhuenda y Óscar Colomer, con los cargos detallados en la matriz.
- Facebook e Instagram: perfiles confirmados en la configuración pública de
  navegación.
- Teléfono: dato privado. Su valor no forma parte de esta documentación ni de
  los borradores y no debe publicarse.

La denominación pública ya aparece en Home, footer, metadatos de Club y
navegación. La denominación jurídica no debe sustituirla en interfaz salvo en
el contexto legal que apruebe 7D.2C. Las variantes observadas en auditorías
históricas, el JSX legado y el estatuto no prevalecen sobre estas dos
denominaciones confirmadas.

La búsqueda de conflictos localiza dos superficies frontend legadas que no se
modifican en esta fase: `/nosotros` continúa montando
`frontend/src/pages/Nosotros/Nosotros.jsx` con una denominación diferente, y
`frontend/src/components/Layout/Layout.jsx` conserva un claim federativo en un
componente no usado por el layout global actual. También quedan las menciones
históricas explicadas en `16-club-vertical-readiness-audit.md`. La ruta
`/nosotros` y su paridad/migración siguen aplazadas; estos textos no acreditan
identidad ni relación federativa.

## 5. Matriz institucional de verdad

`PENDIENTE DE CONFIRMACIÓN` significa que no existe evidencia suficiente para
afirmar o publicar el dato. “Vigencia” separa confirmación actual de mera
referencia histórica.

| Dato | Valor | Fuente y precedencia | Confianza | Ámbito | Publicidad | Vigencia | Conflicto | Acción |
|---|---|---|---|---|---|---|---|---|
| Denominación jurídica | `Club Galotxes de Monover` | Dato legal confirmado por el club (2) | Alta para preparar borrador | Legal | Publicable sólo tras validación jurídica | Actual confirmada por el club | Grafías históricas y de auditorías previas | Acreditar documento original en 7D.2C |
| Denominación pública | `Club Galotxes Monòver` | Dato confirmado por el club (2) y configuración frontend | Alta | Identidad pública | Pública | Actual | No coincide literalmente con la jurídica, por diseño | Mantener separación explícita |
| CIF | `G03912193` | Dato legal y administrativo confirmado por el club (2) | Alta | Legal/fiscal | Publicable en texto legal validado | Actual confirmada por el club | Ninguno observado | Conservar evidencia administrativa; no presentarlo como certificación registral |
| Domicilio social | C/ Pierrot, 1, 1.º, 03640 Monóvar, Alicante | Dato legal y administrativo confirmado por el club (2) | Alta | Legal | Publicable en texto legal validado | Actual confirmada por el club | Difiere del domicilio histórico y de las instalaciones | Mantenerlo separado de ambos; conservar evidencia administrativa |
| Instalaciones | Centro Polideportivo de Monóvar, Av. Novelda, s/n, 03640 Monòver, Alicante | Dato confirmado por el club (2) | Alta | Operativo/institucional | Publicable tras revisión editorial | Actual confirmada | Terminología municipal previa distinta | Mantener como instalaciones, no domicilio |
| Correo | `clubgalotxesmonover@hotmail.com` | Dato confirmado por el club (2) | Alta | Contacto público | Público | Actual confirmada | Destinatario operativo del formulario no configurado | Confirmar responsable y capacidad de atención |
| Teléfono | Valor deliberadamente omitido | Dato confirmado por el club (2) | Alta | Contacto privado | Privado | Actual | Auditoría histórica lo documentó como candidato público | Mantener fuera de CMS, frontend y borradores |
| Presidencia y responsabilidad web | Jorge Sánchez Romero | Confirmación expresa del club (2) | Alta | Gobierno/operación web | Publicable como presidente y responsable web | Actual confirmada | No equivale por sí sola a representación legal general | Acreditar separadamente el alcance jurídico de la representación |
| Representante legal general | `PENDIENTE DE ACREDITACIÓN EXPRESA` | Los estatutos históricos describen el cargo, pero no prueban inscripción o mandato actual | Media sobre la regla histórica; insuficiente para alcance actual | Legal | No afirmar todavía con alcance general | Pendiente | Presidencia confirmada, representación formal no acreditada | Aportar documentación vigente si el texto legal debe identificarla |
| Constitución | 31 de marzo de 1980 | Referencia estatutaria histórica y confirmación del club (1–2) | Alta como fecha histórica | Historia/legal | Publicable con contexto histórico | Histórica confirmada | No acredita por sí sola el estado registral actual | Un certificado vigente puede añadirse como evidencia, no como condición para negar la fecha |
| Fines | Fomento y práctica de la actividad físico-deportiva de Galotxes | Estatutos históricos y confirmación operativa del club (1–2) | Alta como finalidad histórica/operativa | Institucional | Publicable tras revisión editorial | Confirmada operativamente; adecuación normativa actual pendiente | La formulación estatutaria puede requerir actualización jurídica | Revisar adecuación normativa, sin negar el contenido confirmado |
| Junta | Jorge Sánchez Romero — Presidente; Carlos Bernabé — Vicepresidente; Abel Payá — Secretario; José Carlos Payá — Tesorero; Antonio Bernabé — Vocal; Álvaro Marhuenda — Vocal; Óscar Colomer — Vocal | Composición confirmada por el club para publicación institucional (2) | Alta | Gobierno | Pública | Actual confirmada por el club | Inscripción registral y periodo de mandato no acreditados | Publicar sólo con sus cargos; registrar revisión, mandato e inscripción cuando se acrediten |
| Registro deportivo | `PENDIENTE DE CONFIRMACIÓN` | Sin certificación vigente | Nula | Legal/deportivo | No publicar | Desconocida | Las menciones históricas no prueban alta actual | Aportar número y documento |
| Redes | Facebook e Instagram confirmados | Dato confirmado por el club (2) y configuración frontend | Alta | Comunicación | Públicas | Actual confirmada | Ninguno observado | Revisar titularidad periódicamente |
| Estatutos | `EST-REF-001`, transcripción histórica | Referencia versionada; original pendiente | Media como transcripción, nula como vigencia | Histórico/legal | Restringida; no Manual | 1980/histórica | Nombre, normativa y datos históricos | Custodiar original y localizar versión vigente |
| Reglamento y Manual | 40 documentos públicos desde Knowledge | Corpus canónico y compilador validados | Alta | Conocimiento deportivo | Público | Vigente editorialmente | No es estatuto del club | Mantener fuente única en Knowledge |
| Historia | `PENDIENTE DE CONFIRMACIÓN` | CMS/JSX y tradición previa sin fuente suficiente | Baja | Institucional | No publicar como hecho acreditado | Desconocida | Relatos y fechas previas no verificados | Crear ficha de fuentes y aprobación |
| Federación | FedPiVal como organismo de referencia comunicado; relación administrativa actual `PENDIENTE DE CONFIRMACIÓN` | Comunicación del club (2), sin documento administrativo vigente | Media para identificar el organismo; insuficiente para afirmar afiliación | Institucional/deportivo | Puede nombrarse sólo como referencia tras revisión; no afirmar afiliación actual | Relación pendiente | Claims federativos legados diferentes | Confirmar denominación, vínculo administrativo y URL oficial |
| Imágenes y menores | Assets existentes sin registro completo | Repositorio y auditorías técnicas (6) | Alta sobre existencia; baja sobre derechos | Institucional | No publicables hasta gate | Desconocida | Algunos archivos contienen personas y metadatos | Completar registro, permisos y sanitización |
| Identidad pública de jugadores adultos | Alias; sin alias, nombre + inicial del primer apellido | Decisión cerrada de 7D.2A e implementación 7D.2B (3) | Alta como política técnica | Competición | Pública mediante allowlist | Implementada | Sin fallback a nombre completo | Auditar datos reales y política de retirada antes de producción |
| Identidad pública de jugadores menores | Etiqueta neutra `Participante` mientras no exista autorización explícita | Implementación fail-closed 7D.2B; revisión jurídica/deportiva pendiente | Alta sobre minimización técnica; nula sobre autorización futura | Competición/minores | No identificable por defecto | Fail-closed implementado | El dominio tiene nacimiento opcional, pero no autorización de identidad | Definir autorización/exclusión y retirada antes de cualquier identidad distinta |

## 6. Inventario de tratamientos reales

La “retención técnica” describe lo observable en el repositorio; no equivale a
un plazo legal aprobado.

| Flujo | Interesados y datos | Finalidad técnica/origen | Almacenamiento y accesos | Salidas | Borrado/retención técnica | Riesgos | Gate productivo |
|---|---|---|---|---|---|---|---|
| Cuentas y autenticación | Usuarios: nombre, apellidos, correo, hash de contraseña, rol, activo, foto opcional; tokens y restablecimiento | Registro/login React y administración | MariaDB; perfil React sólo en memoria tras bootstrap `/me`; administradores activos gestionan usuarios | Correo de restablecimiento mediante mailer configurado | Usuario hasta borrado administrativo; reset token 60 min; Sanctum sin expiración global y revocación del token actual en logout | Sólo Bearer en `localStorage`; persiste riesgo XSS y tokens antiguos no se revocan al nuevo login; base jurídica y derechos ausentes | Información de privacidad, política de cuenta, expiración/revocación y proveedor de correo |
| Sesión Blade | Administradores: identificador, IP, user-agent y payload de sesión | Autenticación administrativa | Tabla `sessions`; cookie de primera parte; acceso técnico a DB | Ninguna salida funcional explícita | Inactividad configurable, 120 min por defecto; limpieza probabilística | Sesión no cifrada en DB por defecto; producción debe exigir HTTPS/cookie segura | Configuración productiva, acceso, retención y seguridad aprobados |
| Perfil deportivo | Jugadores: vínculo a cuenta, alias, slug, DNI opcional, nacimiento, género, nivel, licencia, mano, notas, estado | Gestión deportiva y autoperfil parcial | MariaDB; usuario ve su perfil; administradores ven datos completos | Proyecciones autenticadas y, parcialmente, API pública deportiva | Cascada al borrar usuario; sin purga automática separada | Datos identificativos y fecha de nacimiento; DNI/licencia/notas de mayor impacto | Minimización, bases, plazos, derechos e identidad pública implementada |
| Contacto | Remitente: nombre, correo, asunto, mensaje, aceptación e IP con HMAC | Solicitud anónima; formulario condicionado | MariaDB y bandeja Blade para administradores | Mail opcional con contenido completo al destinatario configurado | No hay borrado ni purga; estados `new/read/closed` conservan el registro | Texto libre, destinatario/proveedor/retención no definidos | Mantener desactivado; aprobar primera capa, política, destinatario, mailer y borrado |
| Escuela | Participante, nacimiento, teléfono, correo, representante/relación si menor, nivel, cuenta opcional, estados, fechas y notas | Solicitud pública y gestión de inscripción | MariaDB; administración Blade; usuario queda enlazado si se autentica | Respuesta pública mínima; sin alumnos en GET público | No existe `destroy`; estados conservan datos indefinidamente en términos técnicos | Menores, contacto obligatorio y notas; regla de retención y autorización pendientes | Privacidad específica, criterio de menores, conservación, accesos y operación antes de abrir |
| Centros y actividades | Personas de contacto de centros, teléfono/correo; fechas, alumnado esperado y notas | Coordinación interna de Escuela | MariaDB y Blade administrativo | Sin API pública | Borrado conservador; sin purga automática documentada | Contactos profesionales y texto libre; posible información de menores en notas | Instrucciones internas, minimización, acceso y conservación |
| Inscripciones competitivas | Usuario/jugador, campeonato, categoría sugerida, estado de pago y comentario | Solicitud y gestión deportiva | MariaDB; usuario y administradores | API autenticada para el interesado y administrativa para admin | Cascada con usuario/jugador/campeonato; sin plazo autónomo | Comentarios y estado de pago; IDs y correo en contexto administrativo | Información, necesidad de campos, conservación y accesos |
| Resultados y reprogramaciones | Usuario/jugador, lado, marcadores, comentarios, fechas y pista | Workflow deportivo autenticado y trazabilidad | MariaDB; participantes y administradores | APIs autenticadas; resultados validados e identidad deportiva llegan a API pública | Cascadas ligadas a partido/usuario/jugador; sin purga autónoma | Comentarios libres, trazabilidad y exposición de identidad | Política deportiva, rectificación y allowlists públicas |
| Rankings, calendarios y partidos públicos | `public_display_name`, tipo de entrada, resultados y métricas; sin IDs personales ni objetos de jugador | Visualización pública de competición | Derivados de MariaDB; sin copia frontend persistente | Cualquier visitante; potencial indexación y cachés intermedias | Mientras exista el dato fuente; no hay retirada pública específica | Adultos minimizados; menores/edad ausente neutrales; retirada y datos reales pendientes | Revisar datos reales, autorización de menores y procedimiento de retirada |
| Junta directiva | Personas y cargos actuales confirmados por el club | Contenido institucional | Confirmación directa del club; futuro CMS | Visitantes cuando se publique | Sin política técnica definida | Exposición estable e indexable; inscripción y mandato pendientes | Nombre completo + cargo, sin alias; registrar revisión, periodo de mandato e inscripción cuando se acrediten |
| Imágenes | Personas identificables, posibles menores, ubicación y metadatos | Contenido institucional | Assets versionados y directorio `frontend/public`; posible CMS por URL | Una URL bajo `public` puede servirse directamente; GitHub conserva historial | No hay retirada coordinada, caducidad ni sanitización implementadas | Derechos, menores, GPS/EXIF y copias en Git/build | Registro y autorización completos; retirar metadatos y definir proceso de retirada |
| Administración | Usuarios, jugadores, solicitudes, contacto, Escuela y contenido | Operación interna | Blade protegido por sesión, admin activo y CSRF | Recursos CSS/JS locales; ninguna exportación implementada | Según cada tabla; no hay política global | Acceso amplio por rol único, sin permisos granulares ni auditoría de lecturas | Censo de administradores, mínimo privilegio, formación y registro operativo |
| Logs, rate limit y caché | IP, correo o hashes, ID de usuario, excepciones | Seguridad, límites y diagnóstico | Caché configurada; logs locales/`stderr` según entorno | Canal externo sólo si se configura; no hay proveedor acreditado | Canal `single` por defecto sin rotación observable; `daily` conservaría 14 días por defecto | Claves de rate limit de auth incluyen correo e IP en claro lógico; excepciones pueden incorporar contexto no controlado | Elegir canal, minimizar, fijar retención técnica y revisar datos registrados |
| Base, copias, correo y hosting | Todos los datos persistidos o transmitidos | Infraestructura | MariaDB; copias no implementadas; mailer `log` por defecto; hosting pendiente | Proveedores `PENDIENTE DE CONFIRMACIÓN` | Sin política de backup/restauración/borrado productiva | Pérdida, acceso, transferencias o retención desconocidas | Cerrar 7F y contratos/ubicaciones/medidas antes de producción |

Bases jurídicas, plazos legales, destinatarios jurídicos, encargados y
transferencias internacionales quedan `PENDIENTE DE CONFIRMACIÓN`. No se
deducen del modelo de datos ni de la finalidad técnica.

## 7. Identidad deportiva, menores y Junta

La política adulta se aplica mediante una sola proyección backend:

1. alias deportivo cuando exista;
2. en su ausencia, nombre y la inicial del primer apellido.

`PublicPlayerIdentityService` exige nacimiento conocido y mayoría de edad,
normaliza espacios y nunca usa el nombre completo como fallback. Los Resources
de partido, calendario, standings y rankings envían `public_display_name`; no
envían IDs personales, nombre, apellidos, alias, correo, nacimiento ni miembros
de equipo. React consume esa cadena mediante un helper fail-closed y no
reconstruye identidad. Fixtures y tests usan identidades ficticias; no validan
la proporcionalidad de datos reales. El slug de jugador puede derivarse de
alias o nombre completo, pero no existe ruta pública de perfil ni se expone en
estos Resources.

| Superficie auditada | Resultado |
|---|---|
| Modelos | `Player` conserva alias, slug, DNI, nacimiento, género, licencia y notas, relacionado con nombre/apellidos/correo de `User` |
| API/Resources | Rankings, standings, calendarios y partidos usan allowlists y `public_display_name`; los Resources autenticados conservan el perfil propio completo |
| Frontend | Superficies públicas muestran sólo `public_display_name` y el fallback neutro; no combinan nombre, apellido, email o usuario |
| Fixtures/tests | Identidades sintéticas; documentan la forma contractual, no consentimiento ni edad de datos reales |
| Blade | Administradores activos ven nombre, correo, DNI, nacimiento, licencia, solicitudes y notas según sección |
| Búsqueda | No se localiza buscador público de personas |
| Metadatos | No se localiza inclusión deliberada de nombres de jugadores en títulos o descripciones de página |
| URLs | Partidos usan ID y categorías/championships usan slugs; el slug de jugador no tiene ruta pública actual, pero puede derivar del nombre civil |
| Logs | No se localiza logging deliberado de identidad deportiva; excepciones y canales de infraestructura requieren revisión productiva |

Para menores no se aplica automáticamente la regla adulta. El dominio dispone
de nacimiento opcional, pero no de autorización, alias autorizado o exclusión
pública. 7D.2B devuelve `Participante` tanto para menores como para nacimiento
ausente, sin exponer el motivo. La autorización específica, el cambio de edad,
la retirada y cualquier migración futura están `PENDIENTE DE CONFIRMACIÓN`.
Esta minimización permite cerrar el bloqueo técnico sin autorizar identidad de
menores; datos reales y operación siguen siendo gates de producción.

La Junta confirmada por el club para publicación institucional utiliza nombre
y apellidos completos más cargo, sin alias deportivo: Jorge Sánchez Romero —
Presidente; Carlos Bernabé — Vicepresidente; Abel Payá — Secretario; José
Carlos Payá — Tesorero; Antonio Bernabé — Vocal; Álvaro Marhuenda — Vocal; y
Óscar Colomer — Vocal. La inscripción registral de la Junta y su periodo de
mandato continúan pendientes. Jorge Sánchez Romero está confirmado como
presidente y responsable web; esa confirmación no acredita por sí sola una
representación legal general.

## 8. Imágenes

Los cuatro assets bajo `frontend/public/media/club/` son técnicamente
alcanzables por URL directa en un despliegue aunque una página no los enlace.
Las auditorías previas no acreditan autoría, licencia ni autorización de las
personas y detectaron metadatos sensibles en un archivo. No se modifican en
7D.2A, pero no deben considerarse aprobados.

| Archivo | Observación técnica | Personas/menores | Estado |
|---|---|---|---|
| `club-actividad.JPG` | Actividad, autoría y licencia no acreditadas | Identificabilidad y minoría pendientes | No aprobado |
| `club-historia.jpg` | Grupo histórico sin ficha de evento, fecha o derechos | Identidades, menores y autorizaciones pendientes | No aprobado |
| `club-instalaciones.jpg` | Instalaciones; auditoría previa detectó EXIF de dispositivo, fecha y GPS | Personas no acreditadas | No aprobado; requiere sanitización |
| `club-logotipo.png` | Logotipo sin acreditación de titularidad/licencia | No aplica a identidad personal observable | No aprobado |

Registro operativo mínimo obligatorio por archivo:

| Campo | Contenido exigido |
|---|---|
| Procedencia | Fuente exacta y forma de recepción |
| Autor/cedente | Identidad y capacidad para autorizar |
| Actividad | Evento o contexto real |
| Fecha | Captura y, si difiere, recepción |
| Personas identificables | Inventario o criterio documentado |
| Menores | Sí/no/desconocido y medida específica |
| Autorización | Documento, alcance y responsable de verificación |
| Canales | Web, redes, prensa u otros, sin extensión implícita |
| Vigencia | Inicio, fin o condición revisable |
| Retirada | Canal, responsable, plazo operativo y copias afectadas |
| Custodio | Persona/rol que conserva evidencia |
| Archivo | Ruta, hash o identificador inequívoco |
| Estado publicable | Pendiente/aprobado/retirado, con fecha y revisor |

## 9. Cookies y almacenamientos

| Elemento | Emisor/finalidad | Duración observable | Parte y esencialidad técnica | Entorno y activación previa | Pendiente jurídico |
|---|---|---|---|---|---|
| Cookie de sesión Laravel (`SESSION_COOKIE` o nombre derivado) | Laravel; sesión Blade/admin | 120 min de inactividad por defecto; no expira al cerrar navegador por defecto | Primera parte; esencial para administración autenticada | Backend web; se crea al usar sesión, sin banner | Confirmar nombre, dominio, HTTPS, duración e información aplicable |
| CSRF de Blade | Laravel; token oculto ligado a la sesión, sin cookie CSRF propia observada en el cliente React | Asociado a la sesión/formulario | Primera parte; esencial de seguridad | Login y panel Blade, sin banner | Verificar cookies efectivas en el despliegue |
| Sanctum Bearer | Laravel/React; autenticar API | Token servidor sin expiración global; navegador hasta logout, borrado, `401`, `419` o `403` explícito de usuario inactivo; el `403` ordinario lo conserva | Primera parte; esencial para cuenta, pero almacenado en web storage, no cookie | Sólo tras registro/login | Revisar expiración, revocación y migración a HttpOnly/SameSite |
| `localStorage.token` | React; conservar Bearer | Sin caducidad propia | Primera parte; esencial para sesión elegida | Tras login/registro | Seguridad y transparencia; no es cookie pero sí almacenamiento |
| `localStorage.user` | Sin uso runtime desde 7D.2B; el bootstrap elimina cualquier valor legado sin migrarlo | No aplica | Almacenamiento eliminado | No se escribe; se borra al iniciar y al limpiar sesión | Verificar regresión en despliegue final |
| `sessionStorage`, IndexedDB, Cache Storage | Ningún uso runtime localizado | No aplica | No observado | No se activan | Revalidar en build/despliegue final |
| Service worker | Ninguno localizado | No aplica | No observado | No se registra | Revalidar en build final |
| Analítica, píxeles, publicidad, vídeo, mapas e iframes | Ninguno localizado | No aplica | No observado | No se activan | Revalidar si se incorporan |
| Google Fonts (`fonts.googleapis.com`) | Eliminado en 7D.2B; frontend con pila de sistema | No aplica al código actual | Sin petición automática observada | No se activa | Reauditar build y despliegue final |
| jsDelivr Bootstrap | Eliminado en 7D.2B; panel con CSS/JS locales | No aplica al código actual | Sin petición automática observada | No se activa | Reauditar panel desplegado |
| Bunny Fonts | Eliminado en 7D.2B de la vista raíz Laravel | No aplica al código actual | Sin petición automática observada | No se activa | Reauditar backend desplegado |
| Enlaces Facebook/Instagram | Meta; navegación voluntaria | Sólo al activar el enlace según código propio | Mero enlace a tercero | No hay iframe/pixel ni petición al cargar atribuible a esos enlaces | Informar destino externo cuando corresponda |

No se observa `remember-me` activo: existe la columna estándar
`remember_token`, pero el login Blade no presenta ni procesa esa opción.

**Conclusión técnica provisional tras 7D.2B:** no se observan recursos no
esenciales de terceros que se activen antes de una acción del usuario en el
código y las superficies verificadas. Permanecen mecanismos técnicos de sesión
y autenticación pendientes de reflejar en la política definitiva. Esta
conclusión no es un dictamen legal; no se implementa banner y el despliegue real
debe reauditarse.

La denominación histórica 7F.2C “Banners” no altera esa conclusión: la
implementación es una rejilla estática de patrocinadores/colaboradores cargada
desde la API propia. No integra píxeles, analítica, scripts, iframes, impresión
o tracking de clics y no escribe cookies ni almacenamiento. El único contacto
con el tercero ocurre cuando la persona activa voluntariamente una web HTTPS;
el enlace usa `rel="sponsored noopener noreferrer"`. La revisión jurídica de
la relación de patrocinio y de los derechos de cada logo continúa siendo
responsabilidad del club.

## 10. Terceros y encargados potenciales

| Tercero/elemento | Rol técnico observable | Estado | Pendiente |
|---|---|---|---|
| MariaDB | Motor de base de datos del proyecto | Software local en Docker; proveedor productivo no elegido | Hosting, accesos, región, contrato, copias y borrado |
| Correo | Mailer Laravel, `log` por defecto; SMTP configurable | Servicio productivo y destinatarios pendientes | Proveedor, DPA/contrato, región, transferencias, retención y seguridad |
| GitHub | Repositorio y posible herramienta de desarrollo | Proveedor de desarrollo; no se observa pipeline de despliegue en el repo | Visibilidad del repo, accesos, historial y política sobre datos reales |
| Vercel | Candidato documental para frontend | Sin configuración versionada ni despliegue acreditado | Cuenta, región, contrato, logs, dominio y rol |
| Railway | Candidato documental para backend/DB | Sin configuración versionada ni despliegue acreditado | Cuenta, región, contrato, logs, backups, mail y rol |
| Google Fonts | Retirado del frontend en 7D.2B | Sin carga automática en el código actual | Reauditar despliegue antes de publicar |
| jsDelivr | Retirado del panel Blade en 7D.2B | Sustituido por CSS/JS locales | Reauditar despliegue antes de publicar |
| Bunny Fonts | Retirado de la vista raíz Laravel en 7D.2B | Sin carga automática en el código actual | Reauditar despliegue antes de publicar |
| Facebook/Instagram | Destinos externos | Meros enlaces; no SDK/pixel observado | Información al usuario y titularidad de perfiles |
| Imágenes | Archivos locales versionados | No hay CDN/gestor externo acreditado | Autoría, licencias, consentimientos, metadatos y retirada |
| Analítica/APIs externas | Ninguna integración observada | No configurada | Auditar de nuevo antes de añadirla |
| Panel Blade | Herramienta administrativa propia | No tercero; usa recursos de presentación locales | Usuarios autorizados, trazabilidad, formación y mínimo privilegio |

No se atribuye a ninguno la condición jurídica de encargado, región, DPA,
contrato o transferencia: todo ello queda `PENDIENTE DE CONFIRMACIÓN`.

## 11. Borradores internos

Los borradores viven exclusivamente en `docs/legal-drafts/`, comienzan con la
advertencia obligatoria y no se importan desde React, Laravel, CMS ni el
compilador Knowledge. Incorporan los datos confirmados y conservan marcadores
explícitos en lugar de inventar registro, representación legal general, bases,
plazos, destinatarios, proveedores o transferencias.

## 12. Riesgos, bloqueos y gates de 7D.2C

Antes de publicar páginas legales o activar recogidas productivas se debe:

1. conservar la evidencia administrativa del CIF y domicilio social
   confirmados; acreditar, si procede, la representación legal general, la
   inscripción y mandato de la Junta y el registro deportivo;
2. localizar original y versión vigente de estatutos, sin promover la
   transcripción de 1980 a fuente actual;
3. aprobar responsable, finalidades, bases jurídicas, derechos, destinatarios,
   encargados, transferencias y plazos legales por tratamiento;
4. definir borrado técnico para cuentas, contacto, Escuela, competición,
   tokens, sesiones, logs, correo y backups;
5. aprobar el modelo futuro de autorización/exclusión para identidad de
   menores si se pretende sustituir la etiqueta neutra aplicada en 7D.2B;
6. auditar datos deportivos reales antes de exposición;
7. completar el registro de imágenes, retirar metadatos sensibles y resolver
   menores/retirada antes de servirlas;
8. reauditar el despliegue real para confirmar que no reintroduce Google
   Fonts, Bunny Fonts, jsDelivr u otros recursos automáticos;
9. elegir y contratar infraestructura, base, correo y backups, documentando
   región, accesos, seguridad y transferencias sin inferencias;
10. validar profesionalmente los cinco borradores y fijar versión/fecha sólo
    entonces;
11. implementar después, en 7D.2C, rutas, primera capa, enlaces y pruebas sin
    activar Contacto hasta superar además sus gates operativos;
12. ejecutar 7F y 7G antes de declarar el MVP productivo.

El correo público puede mostrarse como canal editorial, pero no acredita el
destinatario interno del formulario ni su operación. Las instalaciones no son
el domicilio social. El club confirma la Junta actual y la referencia
histórica apoya la fecha de constitución y los fines; ninguna de esas fuentes
acredita la inscripción y mandato actuales de la Junta, el registro deportivo,
la relación federativa administrativa vigente o la adecuación normativa actual
de los fines.

## Seguimiento de 7D.2C2A

El gate de identidad de menores del punto 5 queda implementado exclusivamente
para `public_competition_identity`: aviso versionado, decisión opcional,
confirmación, vínculo con jugador, conformidad 14–17, revisión, revocación e
identidad fail-closed. La Política de privacidad vigente refleja este
tratamiento en su versión `1.1.0`.

Esto no resuelve imágenes, proveedores, Contacto, representación dudosa,
operación productiva o purga programada. La primera capa y operación de
Contacto pasan a 7D.2C2B y los gates productivos a 7F. Las imágenes conservan
su valor como frente independiente posterior, todavía sin numeración aprobada.

## Seguimiento de 7D.2C2B

Los hallazgos históricos de ausencia de versión, retención y purga quedan
remediados técnicamente para Contacto: aviso `NOTICE-CONTACT-FORM`, aceptación
exacta, estados de notificación, cierre a 12 meses, hold, anonimización y HMAC
purgable a 30 días. La Política pública 1.1.0 ya contenía base, plazo, derechos
y proveedor pendiente, por lo que no se altera su versión.

El destinatario y remitente son configuración privada, pero ningún valor real
se incorpora al repositorio. Proveedor, credenciales, entrega, rebotes, logs,
scheduler, backups, restauración y activación se mantienen como gates de 7F.

## Seguimiento de 7E

Los hallazgos históricos de Escuela sobre primera capa, plazos y ausencia de
purga quedan remediados técnicamente con `NOTICE-SCHOOL-ENROLLMENT` 1.0.0,
evidencia versionada, vencimiento, holds, anonimización y
`school:purge-expired --dry-run`. La Política de privacidad 1.1.0 ya recogía
seis meses para solicitudes no formalizadas o rechazadas y dos años tras la
baja de alumnos, por lo que no cambia de versión.

El agregado público ya no serializa el teléfono o correo del programa y el
limitador de inscripción usa HMAC para no incorporar IP o correo en claro a la
clave. `SCHOOL_ENROLLMENT_ENABLED=false` conserva la operación productiva
cerrada. Configuración real, revisión humana, proveedor de correo, scheduler,
backups, restore, logs y despliegue continúan como gates de 7F.


---

**Nota de seguimiento posterior (Fase 7F.2):** Tras la aceptación de staging, ciertas decisiones (como el modelo de navegación en Competición y el aplazamiento de noticias y multimedia persistente) han sido promovidas o refinadas en la Fase 7F.2. Ver `docs/28-preproduction-product-refinement.md` y `ADR-042`.
