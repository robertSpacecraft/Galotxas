# Knowledge

La carpeta `knowledge/` contiene el conocimiento canónico y estable sobre las Galotxas. A diferencia de `docs/`, que documenta el producto y su arquitectura técnica, esta carpeta preserva las reglas editoriales del deporte y su vocabulario propio.

`knowledge/` no es un CMS. Las noticias, convocatorias, actividades y demás contenido operativo que deba editar un administrador pertenecen al backend CMS conforme a [`docs/10-content-governance.md`](../docs/10-content-governance.md).

## Objetivos

- Preservar el conocimiento tradicional de las Galotxas.
- Documentar el reglamento y la terminología de forma rigurosa y estructurada.
- Mantener una única fuente editorial para cada regla o concepto.
- Servir de referencia al software cuando representa elementos o reglas del deporte.
- Permitir en el futuro la generación validada del Manual y de contenido divulgativo estable.

## Estructura actual

```text
knowledge/
├── AGENTS.md
├── README.md
├── conceptos/
│   ├── README.md
│   ├── elementos/
│   ├── juego/
│   └── personas/
└── reglamento/
```

- `reglamento/` contiene la formulación normativa editorial.
- `conceptos/` contiene vocabulario y definiciones, agrupados actualmente en elementos, juego y personas.

No existen todavía colecciones de Instalaciones independiente, Historia, Escuela, multimedia o referencias. Se crearán únicamente cuando exista contenido real y se haya aprobado su contrato editorial.

Las colecciones compilables actuales son `reglamento` (`REG-001`–`REG-008`), `conceptos/elementos`, `conceptos/personas` y `conceptos/juego`. `AGENTS.md`, los README y `reglamento/00_metodologia.md` no forman parte del artefacto canónico.

## Contrato de front matter

Todo documento compilable debe comenzar con seis campos escalares simples:

```yaml
---
id: CON-JUE-008
slug: saque
titulo: Saque
version: 1.0.0
estado: Vigente
ultima_revision: 2026-07-20
---
```

- `id` es estable y único globalmente; usa `REG-NNN`, `CON-ELE-NNN`, `CON-PER-NNN` o `CON-JUE-NNN` según la ruta.
- `slug` usa `kebab-case` ASCII y es único dentro de su colección.
- `titulo` coincide con el primer H1.
- `version` usa SemVer `X.Y.Z`.
- `estado` admite los valores reales `Borrador` y `Vigente`.
- `ultima_revision` es una fecha ISO válida `YYYY-MM-DD`.

La colección se deriva de la ruta y el orden del sufijo numérico del ID. No se admiten campos YAML complejos ni metadatos especulativos.

Cada documento contiene exactamente un H1, que debe ser el primer heading y coincidir con `titulo`. Las secciones principales usan H2 y las subsecciones H3 o niveles inferiores coherentes, sin saltos arbitrarios y siempre dentro de H1–H6.

Un cambio de estado requiere aprobación editorial humana. Las futuras revisiones deben actualizar `ultima_revision` y evaluar conscientemente si el alcance semántico exige cambiar `version`; una normalización técnica no autoriza por sí sola a reformular contenido.

## Referencias y seguridad

Las relaciones canónicas deben usar IDs estables. El compilador valida también enlaces Markdown relativos y anchors cuando existan. Un destino inexistente o una ruta que salga de `knowledge/` invalida el corpus. Además, un documento `Vigente` sólo puede referenciar otro documento `Vigente`; un borrador futuro podrá relacionar borradores o vigentes mientras los destinos existan. Las menciones de «Véase también» que sólo contienen un título continúan siendo texto y no se convierten por heurística en relaciones.

No se admiten MDX, JSX, HTML, scripts, iframes, eventos HTML, expresiones ejecutables, URLs peligrosas ni imágenes en el contrato v1. El cuerpo se conserva como Markdown; no se compila a HTML ni se ejecuta.

## Validación y generación

Desde `frontend/`:

```bash
npm run knowledge:check
npm run knowledge:build
```

`knowledge:check` valida metadatos, headings, referencias, estados, proyección pública, seguridad y determinismo sin escribir. `knowledge:build` aplica las mismas invariantes y genera de forma coordinada `frontend/src/generated/knowledge/knowledge.json` y `frontend/src/generated/knowledge/public-knowledge.json`. El primero conserva el corpus editorial completo; el segundo incluye sólo documentos `Vigente`, transforma el subconjunto Markdown aprobado a nodos seguros y no expone datos de borradores. Fuente y artefactos deben incluirse juntos en Git; los JSON generados no se editan manualmente. El contrato completo se documenta en [`docs/11-knowledge-pipeline.md`](../docs/11-knowledge-pipeline.md).

## Principios

### Fuente única

Cada regla o concepto debe definirse en una sola fuente canónica. Los demás documentos y consumidores deben relacionarse con ella, no mantener copias editables.

### Coherencia y rigor

La terminología debe ser consistente con el reglamento y los conceptos existentes. Las incertidumbres se hacen explícitas; no se inventan reglas ni se importan por analogía desde otros deportes.

### Trazabilidad

Una modificación relevante debe revisarse para determinar si afecta al dominio ejecutable del backend, a los artefactos consumidos por React o a la documentación técnica.

### Evolución controlada

El conocimiento puede ampliarse, corregirse y reorganizarse, pero los IDs, slugs y relaciones que se definan deben permanecer estables o disponer de una migración documentada.

### Respeto por la tradición

Cuando existan varias denominaciones tradicionales, podrán conservarse como variantes o sinónimos. El proyecto puede adoptar una denominación principal para mantener consistencia sin borrar esa riqueza cultural.

## Relación con el Manual

El Manual es un consumidor y una organización pública de este conocimiento, no una segunda fuente editorial. Fase 5B publica los 40 documentos `Vigente` mediante una proyección segura, un repositorio frontend y un renderer semántico; React no importa el artefacto canónico ni interpreta Markdown. Las referencias públicas deben usar la forma explícita `ID – etiqueta` y resolver a otro documento público antes de escribir. Las futuras modificaciones editoriales continúan realizándose exclusivamente en `knowledge/` y requieren regenerar ambos JSON. El Manual no utiliza MDX, HTML ejecutable, base de datos, API Laravel ni CRUD Blade.

## Relación futura con la Escuela de Galotxas

La Escuela es una sección pública independiente del Manual. Su contenido pedagógico estable podrá proceder de una futura colección de `knowledge/`; sus actividades, fechas, noticias, galerías o inscripciones pertenecerán al backend CMS. Esta fase no crea nuevas carpetas para la Escuela.

## Reglas aplicables

Las instrucciones editoriales para modificar esta carpeta se encuentran en [`AGENTS.md`](AGENTS.md). La matriz de fuentes y los criterios para elegir entre conocimiento canónico y CMS se mantienen en [`docs/10-content-governance.md`](../docs/10-content-governance.md).
