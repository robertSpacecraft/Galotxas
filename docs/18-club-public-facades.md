# Fachadas públicas de Club

## Seguimiento tras 7D.2C1

El footer añade un grupo legal real sin cambiar las cuatro fachadas, el
disclosure Club o sus fuentes CMS. La política general de privacidad ya es
pública, pero Contacto permanece condicionado y desactivado por defecto; no se
añade primera capa ni correo en este bloque. Los fixtures E2E no acreditan una
activación productiva.

## 1. Propósito

Este documento registra `CLUB-PUBLIC-FACADES-1`, cierre técnico de 7C.2. React
publica cuatro fachadas institucionales sobre contenido administrado en el CMS,
sin convertir JSX, seeders o `knowledge/` en fuentes editoriales.

## 2. Alcance

El bloque comprende las cuatro rutas canónicas, consumo CMS cerrado, estados
remotos, metadatos, layout responsive, formulario de Contacto condicionado por
la API, fixtures E2E aislados, pruebas y documentación. No altera el dominio
CMS ni el contrato de Contacto creado en 7C.1.

## 3. Fuera de alcance

No incorpora el disclosure Club al Navbar, landing `/club`, Home o footer
globales, páginas legales, activación productiva, correo productivo, redirects
HTTP, canonical, SEO completo, contenido en `knowledge/`, multimedia nueva ni
limpieza del legado. Esas tareas permanecen en 7D o bloques posteriores.

## 4. Rutas

Las únicas rutas nuevas son `/club/quienes-somos`, `/club/contacto`,
`/club/federarse` y `/club/documentos`. `/club` y cualquier descendiente no
registrado continúan resolviéndose mediante la 404 de React, sin redirección.

## 5. Slugs

La configuración inmutable aplica este mapeo exacto:

| Ruta | Slug CMS |
|---|---|
| `/club/quienes-somos` | `nosotros` |
| `/club/contacto` | `contacto` |
| `/club/federarse` | `federarse` |
| `/club/documentos` | `documentos` |

La fachada recibe un identificador interno cerrado; nunca obtiene un slug de
la URL ni funciona como otra ruta CMS genérica. También rechaza una respuesta
cuyo slug no coincida con el esperado.

## 6. CMS

`CmsPage` y `CmsBlock` siguen siendo la única fuente editorial. La fachada usa
el servicio y el renderer CMS existentes; Laravel entrega sólo páginas
publicadas y actuales. React no consulta ni filtra borradores, no infiere copy y
no reproduce bloques en componentes específicos.

## 7. Estados

Cada fachada diferencia carga anunciada, contenido, error recuperable con
reintento, 404, respuesta inválida y lista de bloques vacía. Una respuesta
parcial sin `blocks` se trata como página válida vacía; título ausente o slug
ajeno se rechazan para evitar mostrar otra página. Bloques desconocidos se
omiten mediante el renderer tolerante existente.

## 8. Contacto

`/club/contacto` muestra primero el contenido CMS y consulta de forma
independiente `GET /api/v1/contact/config`. Un fallo de configuración no oculta
el contenido y ofrece reintento. `enabled: false` muestra sólo un aviso neutro;
únicamente el booleano exacto `true` monta el formulario.

Seguimiento 7D.2C2B: `true` ya no basta por sí solo. React exige además que
ID, versión y URL de Privacidad coincidan con `NOTICE-CONTACT-FORM` compilado;
una discrepancia oculta el formulario sin afectar al CMS.

## 9. Formulario

Cuando está habilitado, el formulario muestra primero el aviso versionado y
envía nombre, correo, asunto, mensaje, aceptación, ID/versión y el honeypot
invisible `website` al POST existente. Valida el
contrato básico, asocia errores, enfoca el primer campo inválido, impide doble
envío, conserva datos corregibles ante 422/429/503/red/respuesta inesperada y
los elimina tras el 201. No recoge teléfono, adjuntos, DNI o metadatos internos.

## 10. Privacidad

La primera capa procede de `legal/notices/contact-form.md`, enlaza la Política
de privacidad vigente y la casilla no está premarcada. Producción mantiene
`CONTACT_FORM_ENABLED=false` hasta configurar proveedor, remitente, entrega,
logs, scheduler, backups y operación de 7F.
El frontend no persiste los campos en URL, storage, logs o telemetría añadida.

## 11. Aliases

No se añaden redirects ni aliases de servidor. La ruta histórica `/nosotros`
se conserva con su componente actual porque no puede acreditarse paridad con
el contenido manual y variable de todos los entornos. Su futura retirada exige
comparación editorial y estrategia reversible.

## 12. Legado

`/contenidos` y `/contenidos/:slug` permanecen intactos; por ello las cuatro
URLs `/contenidos/{slug}` siguen funcionando cuando el CMS publica las páginas.
`academy`, Prensa y Federaciones no se renombran, agrupan ni eliminan en este
bloque.

## 13. Metadatos

Cada fachada usa `seo_title` y `seo_description` del CMS. Si faltan, utiliza el
título real de la página y una descripción funcional neutra de la configuración
cerrada. La helper común restaura metadatos al desmontar. No se introduce
canonical hasta coordinar aliases, indexación y hosting.

## 14. Imágenes

El renderer admite las URLs seguras que ya reconoce el CMS, incluidas rutas
versionadas bajo `/media/club/`. 7C.2 no mueve, optimiza o importa imágenes, no
usa `dist`, no reutiliza `escuela_grupo.png` y no cambia el logotipo global.
Derechos, consentimiento, alt y metadatos sensibles siguen siendo controles
editoriales por pieza.

## 15. Accesibilidad

La fachada aporta un único H1 con el título CMS, estados anunciados, foco
visible, error y reintento accesibles. El formulario tiene labels visibles,
`aria-invalid`, `aria-describedby`, región de envío y foco tras error o éxito.
La jerarquía y los textos alternativos de bloques siguen bajo responsabilidad
editorial: React no corrige silenciosamente un H1 o alt incorrecto del CMS.

## 16. Responsive

El contenedor limita ancho y rompe URLs largas; imágenes conservan el renderer
responsive. Campos y botones respetan el viewport, el formulario permanece en
una columna y sus acciones ocupan el ancho disponible en móvil estrecho. La
matriz E2E comprueba 320, 768 y 1280 píxeles sin overflow crítico.

## 17. Testing

Vitest cubre configuración cerrada, mapeo, Suspense, carga, éxito, metadatos,
404, error/reintento, respuesta inválida, bloques vacíos y Contacto independiente.
El formulario cubre validación, consentimiento, honeypot, 201, 422, 429, 503,
red, respuesta inesperada, foco, doble envío y ausencia de storage. Las suites
del Navbar confirman que no aparece Club antes de 7D.

## 18. E2E

`E2ESmokeSeeder` crea únicamente en `APP_ENV=e2e` y `galotxas_e2e` cuatro
páginas publicadas con copy ficticio/técnico. El Compose E2E habilita el
formulario sólo en ese entorno. Playwright recorre rutas y slugs, CMS y
metadatos, 404 ausente, legado, config false/true, 201, 422, teclado, responsive,
descendientes 404 y ausencia de Club en Navbar.

## 19. Carga editorial manual

La carga local de los cuatro slugs se realizó manualmente fuera del repositorio
y queda separada de código y fixtures. El repositorio no contiene sus textos ni
puede certificar estado, paridad, vigencia, enlaces, derechos o aprobación de
cada entorno. `DatabaseSeeder` y los seeders de desarrollo no se modifican.

## 20. Publicación local

Un administrador puede publicar temporalmente las páginas en su entorno para
revisión. Si permanecen como borrador o programadas en el futuro, las fachadas
devuelven la 404 pública prevista. 7C.2 no publicó ni modificó registros locales;
carga, revisión y publicación productivas siguen siendo manuales por entorno.

## 21. Gates posteriores a 7C

7D.1 implementa el árbol único con disclosures Aprende/Club, paridad
desktop/móvil, Home y footer estructurales. 7D.2B endurece almacenamiento,
identidad deportiva y recursos externos sin activar Contacto. 7D.2C1 publica
los textos legales sin activar el formulario. Antes de activarlo en producción,
7D.2C2 debe cerrar primera capa, correo y operación. También siguen pendientes paridad de `/nosotros`, aliases/redirects,
canonical, indexación de
`/contenidos`, contenido por entorno y aceptación humana.

## 22. Criterios de cierre

7C.2 y 7C quedan técnicamente cerradas al existir las cuatro rutas exactas y
diferidas, fuente CMS única, estados completos, Contacto condicionado, pruebas
unitarias/backend/E2E y documentación validadas, sin cambios en Navbar,
`knowledge/`, `frontend/dist`, imágenes o datos locales. Fase 7 y el MVP siguen
abiertos: la implementación posterior de 7D.1 y la publicación legal 7D.2C1 no
autorizan los gates productivos de 7D.2C2.

## 23. Seguimiento de 7D.1

Las cuatro fachadas ya son descubribles desde el disclosure Club y el footer
global mediante sus rutas canónicas. El Navbar y Home no importan `ClubPage`,
no cargan contenido CMS y no alteran el mapa ruta/slug. Club sigue diferido;
las rutas legadas y los estados de publicación permanecen intactos. La
validación E2E usa exclusivamente las páginas ficticias del entorno protegido.

## 24. Seguimiento de 7D.2A

La auditoría legal confirma la denominación pública usada por las fachadas y
separa la denominación jurídica. También clasifica como confirmados por el club
el CIF, el domicilio social, el presidente/responsable web y la Junta
publicable, y apoya la fecha de constitución en los estatutos históricos. La
inscripción y mandato de la Junta, la representación legal general y el
registro deportivo siguen pendientes. El correo confirmado puede mantenerse
como canal editorial; el teléfono es privado. Los borradores legales no se
cargan en CMS ni habilitan el formulario. Assets, identidad deportiva,
menores, proveedores, plazos y destinatario del formulario siguen siendo gates
antes de producción.

## 25. Seguimiento de 7D.2B

Las fachadas, slugs, contenido CMS y formulario condicionado no cambian. Las
pruebas de privacidad confirman que, con la configuración por defecto,
Contacto permanece oculto y no persiste datos en web storage. Las rutas
legales continúan ausentes y las imágenes no se modifican ni se autorizan para
producción.
