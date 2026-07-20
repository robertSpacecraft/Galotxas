# React + Vite

## Knowledge

El Reglamento y los Conceptos canónicos se validan y compilan desde la carpeta hermana `knowledge/`:

```bash
npm run knowledge:check
npm run knowledge:build
```

La salida versionada es `src/generated/knowledge/knowledge.json`. Es un archivo generado y no debe editarse manualmente. En Fase 5A no se importa desde páginas React y los comandos `dev` y `build` no lo regeneran automáticamente porque el repositorio no dispone todavía de un contrato de CI/despliegue que garantice acceso a la raíz completa del monorepo.

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Oxc](https://oxc.rs)
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/)

## React Compiler

The React Compiler is not enabled on this template because of its impact on dev & build performances. To add it, see [this documentation](https://react.dev/learn/react-compiler/installation).

## Expanding the ESLint configuration

If you are developing a production application, we recommend using TypeScript with type-aware lint rules enabled. Check out the [TS template](https://github.com/vitejs/vite/tree/main/packages/create-vite/template-react-ts) for information on how to integrate TypeScript and [`typescript-eslint`](https://typescript-eslint.io) in your project.
