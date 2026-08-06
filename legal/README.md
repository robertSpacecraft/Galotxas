# Fuente legal pública

Esta carpeta es la fuente canónica versionada en Git de los textos legales
públicos de Galotxas. Es independiente del CMS y de `knowledge/`.

Documentos admitidos:

- `aviso-legal.md` → `/legal/aviso-legal`;
- `privacidad.md` → `/legal/privacidad`;
- `cookies.md` → `/legal/cookies`.

No se admite un cuarto documento sin cambiar previamente el contrato cerrado y
su documentación. `README.md` queda fuera de la proyección pública. Los
borradores históricos continúan en `docs/legal-drafts/` y nunca se importan en
runtime.

Cada documento utiliza front matter con los campos `id`, `title`, `slug`,
`version`, `status`, `published_at`, `reviewed_at`, `owner`, `source_draft` y
`summary`. El cuerpo debe comenzar por un único H1 idéntico al título.

La validación y generación se ejecutan desde `frontend/`:

```bash
npm run legal:check
npm run legal:build
```

`legal:check` falla si la fuente no cumple el contrato o si el artefacto
versionado no coincide con ella. La salida se genera en
`frontend/src/generated/legal/public-legal.json`; nunca en `frontend/dist`.

Los cambios requieren revisión editorial y jurídica, incremento de versión,
fechas de publicación y revisión coherentes, regeneración del artefacto y
ejecución de las pruebas. No se incorporan teléfonos, datos sin confirmar,
notas internas ni instrucciones operativas en los documentos públicos.
