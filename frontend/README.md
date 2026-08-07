# React + Vite

## Knowledge

El Reglamento y los Conceptos canónicos se validan y compilan desde la carpeta hermana `knowledge/`:

```bash
npm run knowledge:check
npm run knowledge:build
```

Las salidas versionadas son:

- `src/generated/knowledge/knowledge.json`: artefacto canónico completo; nunca se importa en código del navegador;
- `src/generated/knowledge/public-knowledge.json`: proyección exclusiva de documentos `Vigente`, sin Markdown ni metadatos editoriales privados.

Ambos son archivos generados y no deben editarse manualmente. `knowledge:check` valida los dos en memoria y `knowledge:build` los reemplaza de forma coordinada. Los comandos `dev` y `build` no regeneran automáticamente porque todavía no existe un contrato de CI/despliegue que garantice acceso a la raíz completa del monorepo.

React consume la proyección únicamente mediante `src/features/knowledge/knowledgeRepository.js`. Los helpers de la misma feature centralizan las rutas, anchors de colección y fragmentos bajo `/aprende-a-jugar`; `KnowledgeRenderer` renderiza nodos seguros ya compilados y no interpreta Markdown ni inyecta HTML. Las páginas no deben buscar directamente dentro del JSON.

El repositorio conserva el orden canónico y resuelve el contexto y los vecinos dentro de cada colección. La tabla de contenidos usa exclusivamente `headings` H2–H6 del artefacto; no analiza bloques o Markdown en runtime. `App.jsx` carga con `React.lazy` sólo las tres páginas de Aprende, por lo que el repositorio, el renderer y `public-knowledge.json` permanecen fuera del chunk inicial. El fallback `Suspense` es un estado anunciado dentro del `<main>` existente y no representa una 404.

## Legal

Los tres textos públicos proceden de la carpeta hermana `legal/` y utilizan un
compilador independiente del pipeline de Knowledge:

```bash
npm run legal:check
npm run legal:build
```

La salida versionada `src/generated/legal/public-legal.json` contiene bloques
seguros, nunca Markdown crudo ni paths de borradores. `legal:check` valida la
fuente, el determinismo y la sincronía byte a byte; `npm run build` lo ejecuta
antes de Vite y falla si el artefacto está desactualizado.

`src/features/legal/` sólo admite `LEG-001`–`LEG-003` y registra mediante lazy
loading `/legal/aviso-legal`, `/legal/privacidad` y `/legal/cookies`. `/legal`,
descendientes desconocidos e IDs manipulados usan la 404. El renderer no
consulta API, CMS o Knowledge. El Navbar permanece intacto y el footer añade un
grupo de Información legal con esos tres destinos.

## Escuela de Galotxas

La ruta pública `/escuela` se carga de forma diferida desde `src/features/school/`. Su servicio usa la instancia Axios común para consumir `GET /api/v1/school` y `POST /api/v1/school/enrollments`; el hook local gestiona carga, ausencia válida, error y reintento sin estado global o persistencia.

React conserva el orden y los campos autorizados por Laravel, presenta programa, niveles, horarios, ubicaciones, contacto y apertura, y sólo muestra el formulario cuando el agregado indica inscripciones abiertas. El cálculo local de minoría de edad controla la presentación del representante y la validación básica, pero el backend continúa siendo la autoridad. Los datos personales no se almacenan en URL, storage, logs o telemetría y se limpian tras una respuesta `201`.

La feature enlaza al Manual mediante su helper de ruta, pero no importa `public-knowledge.json` ni duplica contenido pedagógico. `schoolRoutes.js` centraliza `/escuela` para Router, Navbar y Home. El slug CMS legado `academy` continúa independiente y no se redirige.

## Contacto institucional

`src/features/contact/contactService.js` prepara de forma aislada la consulta
de `GET /api/v1/contact/config` y el envío a
`POST /api/v1/contact-requests`. Normaliza 422, 429, 503, errores de red y
respuestas inesperadas. La fachada `/club/contacto` conserva siempre el CMS y
monta la primera capa y el formulario accesible sólo cuando la API y el aviso
compilado coinciden en ID, versión y URL de Privacidad. La casilla no está
premarcada; los campos no se guardan en storage, URL o logs añadidos y se
limpian tras 201. El acuse confirma persistencia, no entrega de correo.

## Club

`src/features/club/` define un mapa cerrado para las cuatro fachadas diferidas
`/club/quienes-somos`, `/club/contacto`, `/club/federarse` y
`/club/documentos`. Cada ruta consume únicamente su slug CMS, valida la
identidad de la respuesta, reutiliza `CmsBlockRenderer` y cubre carga, error,
reintento, 404, respuesta inválida y bloques vacíos. No existe `/club`, las
rutas `/contenidos/:slug` y `/nosotros` se conservan. Fase 7D.1 incorpora estas
fachadas al disclosure Club sin crear una landing `/club`.

## Navegación, Home y footer

`src/navigation/publicNavigation.js` es la configuración única del árbol
público. Inicio y Competición son enlaces; Aprende y Club son disclosures sin
ruta propia y Cuenta permanece separada. La configuración declara hijos,
coincidencias exactas y por prefijo, visibilidad y audiencia, y centraliza los
destinos institucionales y redes usados por el footer.

Desktop y móvil reutilizan el mismo marcado. Los grupos no dependen de hover,
son mutuamente excluyentes, cierran al navegar o hacer click fuera y devuelven
el foco con Escape. `aria-current="page"` se reserva para rutas exactas; el
grupo conserva estado visual en descendientes. `App.jsx` aporta el destino
`main#main-content` del skip link y monta un footer global tras el contenido.

Home contiene únicamente copy de interfaz y enlaces a Competición, Aprende,
Manual, Escuela y las fachadas Club; no consume API, CMS o el JSON Knowledge.
Club, School y Knowledge continúan cargándose de forma diferida. El contrato y
los gates legales de 7D.2 se detallan en
[`docs/19-navigation-home-and-footer.md`](../docs/19-navigation-home-and-footer.md).

## Sesión e identidad pública

`src/api/authSession.js` es la única abstracción de persistencia de sesión.
Conserva sólo el token Bearer en `localStorage`; el perfil vive en memoria y se
restaura con `GET /me` al recargar. Login y registro no persisten el usuario, y
logout, `401` o `419` eliminan token, dato legado y estado de sesión. Un `403`
de autorización conserva Cuenta; sólo el `403` explícito de usuario inactivo
limpia el token que Laravel ya ha revocado. Mantener el Bearer accesible a
JavaScript conserva un riesgo XSS que no se resuelve sin una migración de
autenticación específica.

Las vistas públicas de competición consumen exclusivamente
`public_display_name`; no reconstruyen nombres desde alias, apellidos, correo o
identificadores. La interfaz usa la pila tipográfica del sistema y no solicita
Google Fonts, Bunny Fonts ni recursos de jsDelivr.

Escuela presenta, sólo cuando Laravel habilita el contrato, una sección
separada para solicitar `anonymous`, `alias` o `name_initial` de un menor. El
aviso procede del artefacto legal versionado y la privacidad de inscripción se
acepta aparte. `/public-identity/confirm` es una ruta lazy aislada, sin
Navbar/footer, con `noindex`; toma el token del fragmento, lo elimina y lo envía
exclusivamente en cuerpos POST. No usa almacenamiento del navegador ni calcula
si una autorización es efectiva.

`dist/` es siempre salida generada ignorada. No debe editarse ni utilizarse como
fuente de imágenes o módulos; para una comprobación que no altere el árbol se
debe construir hacia un `outDir` temporal.

## E2E aislado

`npm run e2e` usa exclusivamente el proyecto `galotxas-e2e` y `backend/docker/docker-compose.e2e.yml`. Antes de levantar o limpiar, una guarda comprueba la configuración resuelta, `APP_ENV=e2e`, la base `galotxas_e2e`, el almacenamiento `tmpfs` y la ausencia de volúmenes, redes o nombres de contenedor de desarrollo. La limpieza siempre recibe proyecto y archivo explícitos.

```bash
npm run e2e:install
npm run e2e
```

No se debe sustituir el runner por un `docker compose down --volumes` manual. Consulta [`docs/13-docker-environment-isolation.md`](../docs/13-docker-environment-isolation.md) para la matriz de entornos, guardas y verificación.

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Oxc](https://oxc.rs)
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/)

## React Compiler

The React Compiler is not enabled on this template because of its impact on dev & build performances. To add it, see [this documentation](https://react.dev/learn/react-compiler/installation).

## Expanding the ESLint configuration

If you are developing a production application, we recommend using TypeScript with type-aware lint rules enabled. Check out the [TS template](https://github.com/vitejs/vite/tree/main/packages/create-vite/template-react-ts) for information on how to integrate TypeScript and [`typescript-eslint`](https://typescript-eslint.io) in your project.
