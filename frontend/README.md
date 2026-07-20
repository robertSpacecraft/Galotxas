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

React consume la proyección únicamente mediante `src/features/knowledge/knowledgeRepository.js`. Los helpers de la misma feature centralizan las rutas bajo `/aprende-a-jugar`; `KnowledgeRenderer` renderiza nodos seguros ya compilados y no interpreta Markdown ni inyecta HTML. Las páginas no deben buscar directamente dentro del JSON.

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Oxc](https://oxc.rs)
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/)

## React Compiler

The React Compiler is not enabled on this template because of its impact on dev & build performances. To add it, see [this documentation](https://react.dev/learn/react-compiler/installation).

## Expanding the ESLint configuration

If you are developing a production application, we recommend using TypeScript with type-aware lint rules enabled. Check out the [TS template](https://github.com/vitejs/vite/tree/main/packages/create-vite/template-react-ts) for information on how to integrate TypeScript and [`typescript-eslint`](https://typescript-eslint.io) in your project.
