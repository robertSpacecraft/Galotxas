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

## Escuela de Galotxas

La ruta pública `/escuela` se carga de forma diferida desde `src/features/school/`. Su servicio usa la instancia Axios común para consumir `GET /api/v1/school` y `POST /api/v1/school/enrollments`; el hook local gestiona carga, ausencia válida, error y reintento sin estado global o persistencia.

React conserva el orden y los campos autorizados por Laravel, presenta programa, niveles, horarios, ubicaciones, contacto y apertura, y sólo muestra el formulario cuando el agregado indica inscripciones abiertas. El cálculo local de minoría de edad controla la presentación del representante y la validación básica, pero el backend continúa siendo la autoridad. Los datos personales no se almacenan en URL, storage, logs o telemetría y se limpian tras una respuesta `201`.

La feature enlaza al Manual mediante su helper de ruta, pero no importa `public-knowledge.json` ni duplica contenido pedagógico. `schoolRoutes.js` centraliza `/escuela` para Router, Navbar y Home. El slug CMS legado `academy` continúa independiente y no se redirige.

## Contacto institucional

`src/features/contact/contactService.js` prepara de forma aislada la consulta
de `GET /api/v1/contact/config` y el envío a
`POST /api/v1/contact-requests`. Normaliza 422, 429, 503, errores de red y
respuestas inesperadas, pero no está integrado en ninguna ruta o componente
público. Las rutas `/club/*` y el formulario visible pertenecen a 7C.2.

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
