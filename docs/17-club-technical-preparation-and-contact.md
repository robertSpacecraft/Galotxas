# Preparación técnica de Club y contacto

## 1. Propósito

Este documento registra el cierre técnico de 7C.1. Prepara la futura vertical
`Club` y un canal de contacto seguro sin crear rutas públicas Club, páginas CMS
reales ni contenido institucional mediante código.

## 2. Alcance

7C.1 comprende la auditoría de assets y del CMS, la allowlist segura de
`mailto:`, el dominio `ContactRequest`, sus endpoints públicos, la bandeja
administrativa Blade, la notificación opcional, un servicio frontend aislado y
la guía de carga manual de las cuatro páginas institucionales.

## 3. Fuera de alcance

Permanecen fuera las rutas `/club/*`, los submenús Club y Aprende, Navbar,
footer, Home, formulario React visible, redirects, retirada de `/nosotros`,
contenido o publicación CMS, textos legales, cambios en `knowledge/`, cambio de
logotipo, proveedor de correo, despliegue y E2E.

## 4. Decisiones

- El CMS Blade sigue siendo la única fuente editorial institucional.
- El contacto se persiste localmente antes de cualquier notificación.
- El formulario y la notificación están desactivados por defecto y son flags
  independientes.
- Sólo se almacena la información mínima necesaria y la IP se conserva como
  HMAC SHA-256, nunca en claro.
- `frontend/dist` es salida generada ignorada y nunca una fuente.
- Los assets institucionales estables pertenecen a
  `frontend/public/media/club/`, pero usarlos públicamente exige procedencia,
  derechos, privacidad y revisión editorial.
- La incorporación del formulario sustituye únicamente la recomendación de
  Contacto informativo sin formulario de ADR-033; el resto de aquel contrato se
  conserva.

## 5. Assets

Matriz de fuentes y artefactos auditados:

| Recurso fuente | Ruta | Uso actual | Artefacto `dist` | Git | Decisión |
|---|---|---|---|---|---|
| Logotipo femenino actual | `frontend/src/assets/images/Logo_Galotxas_Femenino.png` | Navbar | Copia con hash generada por Vite | Versionado en source; `dist` ignorado | Conservar como fuente actual; no sustituir en 7C.1 |
| Hero de Nosotros | `frontend/src/assets/nosotros_hero.png` | `/nosotros` | Copia con hash | Versionado | No migrar sin validar procedencia y paridad |
| Grupo de Escuela | `frontend/src/assets/escuela_grupo.png` | `/nosotros` | Copia con hash | Versionado | No usar en Club: representa otra entidad |
| Torneo, jornada y detalle | `frontend/src/assets/torneo_local.png`, `jornada_iniciacion.png`, `detalle_juego.png` | `/nosotros` | Copias con hash | Versionados | No migrar automáticamente |
| Logotipo institucional | `frontend/public/media/club/club-logotipo.png` | Ninguno | Copia literal al construir | Versionado | Recurso candidato; no cambia el logotipo global en 7C.1 |
| Historia, actividad e instalaciones | `frontend/public/media/club/club-historia.jpg`, `club-actividad.JPG`, `club-instalaciones.jpg` | Ninguno | Copias literales al construir | Versionados | Candidatos sujetos a los gates de la sección 8 |

Los hashes no revelan duplicados exactos entre las fuentes de `public` y
`src/assets`. Las coincidencias de `dist` son copias generadas por Vite y no
duplicados que deban eliminarse.

## 6. `dist`

Tanto `.gitignore` como `frontend/.gitignore` excluyen `frontend/dist`.
`git ls-files frontend/dist` no devuelve archivos y
`git status --ignored --short frontend/dist` lo identifica como `!!`. Los
archivos compilados que reproducen imágenes o assets públicos coinciden por
hash con sus fuentes. No se ha editado, copiado ni utilizado ningún archivo con
hash como origen.

Los builds de validación deben usar un `outDir` temporal fuera del repositorio.
Si una futura revisión encontrase `dist` versionado, debe detenerse y resolver
la gobernanza del artefacto antes de editarlo.

## 7. Fuentes reales

La fuente real del logotipo del Navbar es el import de
`Logo_Galotxas_Femenino.png` en el componente React. Las cinco imágenes de
`/nosotros` proceden de imports en `frontend/src/assets`, no de `dist`. Los
cuatro nuevos recursos Club proceden de `frontend/public/media/club/` y se
referenciarán, en su caso, por rutas públicas estables `/media/club/...`.

No existe `frontend/public/media/club/junta-directiva/` ni se han aportado
retratos individuales. La autorización de nombres, cargos e imagen no crea por
sí sola un archivo o una procedencia técnica verificable.

## 8. Imágenes institucionales

| Archivo | Tipo y dimensiones | Peso | Uso propuesto | Procedencia/autorización | Alt provisional | Git | Optimización futura |
|---|---|---:|---|---|---|---|---|
| `club-actividad.JPG` | JPEG, 960 × 720 | 64.858 B | Actividad del club | Aportado por el usuario; autoría y derechos no constan en el repositorio | “Partida en las pistas del Club Galotxes Monòver” | Sí, asset estable | No prioritaria; revisar derechos antes de usar |
| `club-historia.jpg` | JPEG, 702 × 524 | 69.830 B | Historia | Aportado por el usuario; personas, fecha y derechos pendientes de ficha editorial | “Grupo histórico vinculado al Club Galotxes Monòver” | Sí | Revisar identificación, consentimiento y calidad |
| `club-instalaciones.jpg` | JPEG, 3219 × 2535 | 2.620.442 B | Instalaciones | Aportado por el usuario; contiene EXIF de dispositivo, fecha y GPS | “Pistas del Club Galotxes Monòver” | Sí | Sí: retirar metadatos sensibles y generar tamaño web en una fase autorizada |
| `club-logotipo.png` | PNG con transparencia, 531 × 470 | 240.931 B | Identidad Club | Aportado por el usuario; derechos de marca/autoría no constan en metadatos del repositorio | “Logotipo de Club Galotxes Monòver” | Sí | Evaluar optimización sin sustituir el original |

La recomendación es versionar estos cuatro originales estables en su ubicación
actual. Esta decisión no autoriza su publicación. Antes de usarlos deben quedar
registradas procedencia, derechos, consentimientos de personas, texto
alternativo definitivo y, para `club-instalaciones.jpg`, limpieza de EXIF y
derivado web. `escuela_grupo.png` y cualquier archivo de `dist` quedan
expresamente excluidos.

## 9. Generador CMS

El flujo `CmsPage` → `CmsBlock` → panel Blade → API pública → renderer React
permite administrar manualmente las cuatro páginas previstas. La creación de
páginas fuerza `draft`; la edición controla título, slug, estado,
`published_at`, descripción y campos SEO. Los bloques disponibles son
`heading`, `text`, `list`, `image`, `gallery`, `button` y `document_link`, con
orden manual.

El backend impide publicar sin bloques, trata una fecha futura como programada
y protege el último bloque de una página publicada. No hay upload de archivos,
biblioteca multimedia, revisión editorial, historial ni preview público de
borradores. El detalle Blade permite revisar datos y orden, pero la fachada
pública `/contenidos/{slug}` sólo devuelve páginas efectivamente publicadas.

Por tanto, el CMS es suficiente para la carga manual estructurada, pero 7C.2 no
debe comenzar hasta completar los gates de preview, contenido, derechos y
publicación.

## 10. Guía de carga manual

Reglas comunes:

1. Buscar primero el slug en el listado; editarlo si existe y no crear un
   duplicado. No ejecutar el seeder institucional para esta carga.
2. Crear o mantener la página en `draft`, sin `published_at`.
3. Añadir bloques en el orden acordado y comprobar en el detalle Blade título,
   slug, estado, orden, URLs, textos alternativos y metadatos.
4. No existe preview renderizado de borrador. La revisión pública requiere una
   capacidad de preview futura o una publicación controlada y reversible en un
   entorno no productivo; nunca publicar para “probar” en producción.
5. Completar responsable editorial, vigencia, derechos, SEO y aceptación humana
   antes de pasar a `published`.

| Página | Slug CMS actual | URL canónica futura | Título operativo | Bloques y orden recomendados | Imagen/URL | Enlaces | SEO, preview y publicación |
|---|---|---|---|---|---|---|---|
| Quiénes somos | `nosotros` | `/club/quienes-somos` | Quiénes somos | `heading`, textos breves, imagen o galería aprobada, `heading` de Junta directiva y lista de cargos | Sólo assets aprobados bajo `/media/club/`; priorizar logotipo, historia, actividad o instalaciones según función | Rutas internas o redes oficiales sólo tras revisión | SEO pendiente; acreditar paridad con `/nosotros`, identidad, nombres/cargos, derechos y alt antes de publicar |
| Contacto | `contacto` (debe crearse manualmente si no existe) | `/club/contacto` | Contacto | `heading`, texto, lista de canales/dirección, `button` de correo y enlaces de redes; el formulario React se añadirá sólo tras sus gates | Imagen opcional aprobada; no es necesaria para el mínimo | `mailto:` validado para el correo aprobado, `https:` para redes; sin `tel:` | SEO y privacidad pendientes; verificar enlaces y mantener formulario desactivado hasta aprobación legal/productiva |
| Federarse | `federarse` | `/club/federarse` | Federarse | `heading`, texto orientativo, lista de pasos confirmados y `button`/`document_link` al trámite oficial | Imagen opcional aprobada | URL federativa oficial por `https:`; no precios ni requisitos no confirmados | SEO, responsable y vigencia pendientes; publicar sólo con proceso revisado y enlace comprobado |
| Documentos | `documentos` | `/club/documentos` | Documentos | `heading`, texto breve y `document_link` al Manual interactivo | Sin imagen obligatoria | Ruta interna `/aprende-a-jugar/manual`; no duplicar Knowledge ni inventar PDF | SEO pendiente; comprobar accesibilidad del enlace y que no se presenta otro reglamento antes de publicar |

La tabla prescribe estructura, no copy editorial. Los datos reales se toman de
las decisiones aprobadas y se introducen exclusivamente desde Blade por la
persona responsable.

## 11. Protocolos CMS

Las imágenes y galerías siguen admitiendo sólo rutas internas absolutas y
`http(s)`. Los bloques `button` y `document_link` admiten además un
`mailto:` con una única dirección válida. Backend y renderer rechazan
`javascript:`, `data:`, `vbscript:`, esquemas arbitrarios, URLs protocol-relative
y `mailto:` con dirección malformada. `tel:` no se admite porque no se
publicará teléfono.

Los enlaces externos abren en una pestaña nueva con `rel="noreferrer"`; las
rutas internas y `mailto:` no. La ampliación se limita a enlaces y no convierte
correos en URLs válidas para imágenes.

## 12. Formulario

El formulario público futuro enviará de forma anónima nombre, correo, asunto,
mensaje, aceptación de privacidad y el honeypot `website`. En 7C.1 no existe
interfaz ni ruta React. La API permanece inaccesible funcionalmente mientras
`CONTACT_FORM_ENABLED=false` y responde 503 antes de validar o persistir.

El orden de aplicación es validar, persistir, intentar notificar si procede y
responder. Un fallo de correo no revierte ni oculta la solicitud guardada.

## 13. Modelo

`ContactRequest` contiene `id`, `name` (120), `email` (254), `subject` (200),
`message`, `status` (20), `consent_at`, `ip_hash` (64) y timestamps. No guarda
teléfono, archivos, DNI, user agent, cookies ni IP en claro.

`status` se castea al enum `new`, `read`, `closed`; `consent_at` es fecha
inmutable. Sólo los cuatro campos del mensaje son asignables. Los campos de
control se persisten internamente por el servicio. La migración es reversible y
añade un índice compuesto para el filtro administrativo por estado y fecha.

## 14. API

`GET /api/v1/contact/config` es anónimo y devuelve exclusivamente:

```json
{
  "message": null,
  "data": {
    "enabled": false
  }
}
```

`POST /api/v1/contact-requests` acepta el payload público allowlisted y, tras
persistir o consumir silenciosamente el honeypot, devuelve 201:

```json
{
  "message": "Tu mensaje se ha recibido correctamente.",
  "data": {
    "received": true
  }
}
```

La validación devuelve 422 con el envelope común; el límite, 429; y el flag
desactivado, 503 con `data: null`. Ninguna respuesta expone ID, estado, hash,
destinatario, driver o configuración interna.

## 15. Rate limit

El límite nominal es de cinco solicitudes por diez minutos. La clave combina
HMAC SHA-256 de la IP y HMAC SHA-256 del correo normalizado usando la clave de
aplicación. Ni la IP ni el correo aparecen en claro en la clave o en logs. La
sexta solicitud equivalente recibe el envelope 429 estable.

## 16. Honeypot

`website` es un campo público nullable de hasta 255 caracteres. Cuando llega
relleno y el resto del payload es válido, la API devuelve exactamente el mismo
201 mínimo, pero no persiste ni notifica. Así no revela al emisor automatizado
la causa del descarte. El campo nunca se almacena.

## 17. Privacidad

No se ha redactado texto legal. `consent_at` registra el instante de aceptación,
no una copia de la política. El formulario continuará desactivado hasta aprobar
identidad del responsable, finalidad, conservación, canal de derechos,
información específica del tratamiento y una versión o referencia de la
política. También debe definirse la retención y eliminación operativa de las
solicitudes antes de 7C.2/7D.

Seguimiento 7D.2A: el tratamiento real, su notificación por correo, el HMAC de
IP, la ausencia de borrado y los terceros pendientes están inventariados en
`20-legal-privacy-and-cookies-readiness.md`. Los textos de
`docs/legal-drafts/` son internos y no satisfacen este gate. El canal público
confirmado no acredita el destinatario interno del formulario. El club sí
confirma CIF, domicilio social y a Jorge Sánchez Romero como presidente y
responsable web; la representación legal general y las validaciones jurídicas
del tratamiento permanecen pendientes. La activación continúa bloqueada hasta
7D.2C y la preparación operativa.

## 18. Notificación

La notificación usa el mailer ya configurado por Laravel y sólo se intenta si
`CONTACT_NOTIFICATION_ENABLED=true` y `CONTACT_NOTIFICATION_TO` contiene un
destinatario. No hay proveedor, cola ni destinatario hardcodeados. En local se
puede mantener el mailer de log.

La solicitud se guarda primero. Si el envío falla, se registra sólo el ID de la
solicitud y la clase de excepción; no se registra el cuerpo, correo ni IP. La
API conserva el 201 para no convertir una entrega interna fallida en pérdida de
datos o información sensible para el usuario.

## 19. Administración

El panel Blade añade `Contacto` bajo el middleware existente `auth` +
`IsAdmin`. Permite listado paginado de 25 elementos, conteos y filtro validado
por estado, detalle escapado, transición `new` → `read` y cierre desde `new` o
`read`. CSRF protege las acciones.

No existen editar mensaje, borrar, reabrir, exportar, reenviar, adjuntar o
responder. Las transiciones conservan nombre, correo, asunto, mensaje,
consentimiento e identificador. No hay relaciones que puedan producir N+1.

## 20. Configuración

`backend/config/contact.php` define:

```dotenv
CONTACT_FORM_ENABLED=false
CONTACT_NOTIFICATION_ENABLED=false
CONTACT_NOTIFICATION_TO=
```

`.env.example` documenta los tres valores sin destinatario real. Los tests
sobrescriben configuración en memoria y usan fakes o mocks; no envían correo.
Producción se configurará en 7F después de superar privacidad, destinatario y
operación.

## 21. Frontend aislado

`frontend/src/features/contact/contactService.js` consulta la configuración y
envía solicitudes usando el cliente API común. Valida la forma de éxito y
normaliza 422, 429, 503, red y respuestas inesperadas en
`ContactServiceError`, conservando errores de campo sólo para 422.

No está importado por el router, Navbar, Home, `/nosotros` ni el CMS público.
Los tests de router fijan que `/club` y sus cuatro destinos continúan en 404.

## 22. Testing

El bloque `CLUB-CONTACT-TECHNICAL-FOUNDATION-1` cubre migración, enum, casts,
factory, scopes, validación, normalización, consentimiento, asignación masiva,
honeypot, rate limit, flags, persistencia, HMAC, notificación y fallo de correo;
además de administración, autorización, filtro, transiciones y respuestas
allowlisted. La cobertura CMS prueba `mailto:` válido y esquemas peligrosos.

Frontend cubre configuración, envío, 201, 422, 429, 503, red, respuesta
inesperada, renderer seguro y ausencia de nuevas rutas. La validación completa
se ejecuta con MariaDB mediante el runner aislado, tests/lint/build frontend,
Pint, `knowledge:check`, hashes canónicos y `git diff --check`. No se requiere
E2E porque no hay una ruta o interfaz pública nueva.

## 23. Gates de 7C.2

- cargar manualmente en Blade las cuatro páginas como borradores y obtener
  aceptación editorial;
- cerrar copy, responsables, vigencia, enlaces y paridad de `/nosotros`;
- registrar procedencia, derechos, consentimientos y alt definitivos de cada
  imagen; retirar EXIF y optimizar derivados mediante una fase autorizada;
- resolver preview seguro de borradores o un procedimiento equivalente en
  entorno no productivo;
- aprobar privacidad, versión de política, responsable, finalidad, retención y
  canal de derechos;
- configurar destinatario/operación de correo y mantener el flag desactivado
  hasta validar producción;
- implementar después las fachadas y formulario React accesible, con pruebas y
  publicación controlada;
- conservar rutas heredadas hasta acreditar paridad; aliases y redirects no
  forman parte de 7C.1.

## 24. Criterios de cierre

7C.1 queda técnicamente cerrada cuando assets y `dist` están auditados; CMS y
guía manual verificados; `mailto:` restringido; dominio, API, antispam,
administración, configuración, notificación opcional y servicio frontend
aislado validados; documentación y ADR actualizados; suites completas, Pint,
build, Knowledge y hashes pasan; y Git confirma que no se alteraron
`knowledge/`, `frontend/dist`, imágenes, contenido CMS, seeders ni rutas Club.

Este cierre no completa 7C, Fase 7 ni el MVP. La carga editorial y 7C.2 siguen
pendientes de revisión humana.

## Seguimiento de 7C.2

7C.2 consume la base aquí preparada mediante cuatro fachadas React diferidas y
el formulario condicionado de Contacto. La carga local se mantiene fuera del
repositorio; producción continúa requiriendo carga/publicación propia y el
formulario sigue desactivado por defecto hasta superar privacidad y operación.
El resultado, las pruebas y los gates restantes se documentan en
`18-club-public-facades.md`. Este seguimiento no reescribe los criterios
históricos con los que se cerró 7C.1.

## Seguimiento de 7D.2B

El endurecimiento de privacidad no modifica `ContactRequest`, sus endpoints ni
su administración. `CONTACT_FORM_ENABLED=false` sigue siendo el default y los
campos del formulario no se almacenan en el navegador. No se configura correo,
destinatario, conservación, primera capa o texto legal; esos gates productivos
pertenecen a 7D.2C.
