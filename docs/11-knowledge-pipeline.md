# Canalización build-time de Knowledge — Galotxas

## 1. Objetivo

KNOWLEDGE-COMPILER-1 formaliza y valida el contenido canónico de `knowledge/` y genera un artefacto estructurado para un futuro consumidor React. KNOWLEDGE-PUBLICATION-READINESS-1 normaliza en 5A.1 los estados, headings y grafo editorial para dejar el corpus preparado para 5B. Ninguno de los dos bloques registra rutas, crea una proyección pública, renderiza Markdown o publica Aprende a jugar o el Manual.

## 2. Fuente canónica

`knowledge/` es la única fuente editorial del Reglamento y los Conceptos. El JSON generado es un producto reproducible: no se edita manualmente, no sustituye al Markdown y no se sincroniza con Laravel, MariaDB o el CMS.

Flujo aprobado:

```text
knowledge/ → validación y compilación Node → JSON versionado → futuro consumidor React
```

## 3. Auditoría del corpus

La auditoría de 2026-07-20 localizó 44 archivos Markdown: 40 documentos compilables y cuatro exclusiones explícitas. `conceptos/instalaciones/` no existe; los elementos constructivos actuales permanecen en `conceptos/elementos/` y no se crea una colección vacía. Tras 5A.1 los 40 documentos están `Vigente`; REG-001–REG-008 corresponden al Reglamento inicial aprobado editorialmente.

Abreviaturas de la matriz:

- `H/P/UL`: headings ATX, párrafos y listas no ordenadas;
- `OL`: lista ordenada;
- `T`: tabla Markdown;
- `ID`: referencia estructural por identificador estable;
- `6/6`: los seis campos obligatorios están presentes.

| Archivo | Colección o clase | Metadatos | Markdown y referencias | Clasificación |
|---|---|---:|---|---|
| `knowledge/AGENTS.md` | Instrucciones | No aplica | Markdown técnico | Fuera del artefacto |
| `knowledge/README.md` | README técnico | No aplica | Markdown y enlaces relativos | Fuera del artefacto |
| `knowledge/conceptos/README.md` | Índice documental | 5/6, sin slug | H/P/UL | Fuera del artefacto |
| `knowledge/conceptos/elementos/area_de_juego.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/banco_de_saque.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/cajon.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/cancha.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/cuadro_de_saque.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/pared_de_grada.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/pared_del_resto.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/pilota.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/conceptos/elementos/punto_de_saque.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/red.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/tamboril.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/elementos/zona_de_recepcion_del_saque.md` | `conceptos/elementos` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/personas/jugador.md` | `conceptos/personas` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/conceptos/personas/equipo.md` | `conceptos/personas` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/conceptos/personas/delantero.md` | `conceptos/personas` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/conceptos/personas/trasero.md` | `conceptos/personas` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/conceptos/juego/arrastre.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/arrime.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/bolea.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/bote.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/falta.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/golpe.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/golpe_ganador.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/intercambio.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/juego.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/partido.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/puntuacion.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/quince.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/rebote.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/resto.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/saque.md` | `conceptos/juego` | 6/6 | H/P/UL, ID | Compilable |
| `knowledge/conceptos/juego/sentaura.md` | `conceptos/juego` | 6/6 | H/P/UL | Compilable |
| `knowledge/reglamento/00_metodologia.md` | Metodología documental | 5/6, sin slug | H/P/UL y código inline | Fuera del artefacto: declara no formar parte del reglamento |
| `knowledge/reglamento/01_modelo_cancha.md` | `reglamento` | 6/6 | H/P/UL | Compilable; slug normalizado en 5A |
| `knowledge/reglamento/02_reglamento.md` | `reglamento` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/reglamento/03_saque.md` | `reglamento` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/reglamento/04_desarrollo_jugada.md` | `reglamento` | 6/6 | H/P/UL/OL, ID | Compilable; slug normalizado en 5A |
| `knowledge/reglamento/05_perdida_del_quinze.md` | `reglamento` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/reglamento/06_sistema_puntuacion.md` | `reglamento` | 6/6 | H/P/UL/T, ID | Compilable; slug normalizado en 5A |
| `knowledge/reglamento/07_modalidad_parejas.md` | `reglamento` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |
| `knowledge/reglamento/08_casos_especiales.md` | `reglamento` | 6/6 | H/P/UL, ID | Compilable; slug normalizado en 5A |

No se localizaron imágenes, blockquotes, HTML, JSX/MDX, enlaces Markdown entre documentos ni bloques de código en los 40 documentos compilables. Las listas «Véase también» expresadas sólo como títulos continúan siendo texto editorial: no se convierten por heurística en relaciones.

## 4. Colecciones actuales

El orden estable es explícito:

1. `reglamento`: 8 documentos;
2. `conceptos/elementos`: 12 documentos;
3. `conceptos/personas`: 4 documentos;
4. `conceptos/juego`: 16 documentos.

La colección se deriva de la ruta. No se repite en el front matter. Un Markdown fuera de estas ubicaciones o de la lista de exclusión falla para evitar incorporaciones silenciosas.

## 5. Contrato de metadatos

Todo documento compilable exige exactamente estas claves escalares simples:

```yaml
---
id: REG-001
slug: modelo-de-la-cancha
titulo: Modelo de la cancha
version: 0.1.0
estado: Vigente
ultima_revision: 2026-07-20
---
```

Reglas:

- `id`: único global y ajustado al prefijo de su colección;
- `slug`: `kebab-case` ASCII, único dentro de la colección;
- `titulo`: no vacío y coincidente exactamente con el primer H1;
- `version`: SemVer numérico `X.Y.Z`;
- `estado`: `Borrador` o `Vigente`, los dos valores reales del corpus;
- `ultima_revision`: fecha de calendario ISO `YYYY-MM-DD` válida.

El cambio de estado requiere aprobación editorial. La publicación inicial de REG-001–REG-008 conserva `version: 0.1.0` porque no modifica su contenido semántico; futuras revisiones deberán valorar conscientemente el incremento de versión y actualizar `ultima_revision`.

No se admiten YAML arbitrario, arrays, objetos, bloques multilínea, claves desconocidas o duplicadas. No se añaden SEO, autor, imagen, keywords, dificultad, audiencia o tiempo de lectura.

## 6. IDs, slugs, namespaces y rutas lógicas

Patrones de ID:

- Reglamento: `REG-NNN`;
- Elementos: `CON-ELE-NNN`;
- Personas: `CON-PER-NNN`;
- Juego: `CON-JUE-NNN`.

El ID es único globalmente. El slug es único en su namespace de colección. La ruta lógica de salida se forma como `<collection>/<slug>`, por ejemplo `conceptos/juego/saque`; no es todavía una URL React ni aprueba un namespace público.

## 7. Orden determinista

Las colecciones usan el orden explícito anterior. Los documentos se ordenan por el sufijo numérico del ID, después por ID y finalmente por `sourcePath` como defensa estable. Headings y referencias conservan el orden de aparición. El recorrido del filesystem se ordena antes de procesarse.

## 8. Sintaxis Markdown

La versión inicial conserva Markdown como texto y reconoce headings ATX para generar el índice estructurado. Puede preservar párrafos, énfasis, listas ordenadas y no ordenadas, tablas, separadores, citas, código inline, bloques de código no ejecutable y enlaces inline, siempre sujetos a las reglas de seguridad.

No se implementa un parser o renderer Markdown completo. Los enlaces deben usar forma inline; referencias Markdown por etiqueta o enlaces inline mal formados se rechazan para no omitir su validación. Imágenes, HTML, JSX, MDX y bloques etiquetados como JavaScript, TypeScript, JSX, MDX o HTML se rechazan en v1. Las imágenes se aplazan hasta definir procedencia, licencia, rutas y texto alternativo.

### Contrato de headings

Cada documento debe contener exactamente un H1, que será el primer heading y coincidirá exactamente con `titulo`. Las secciones principales usan H2 y las subsecciones H3 o niveles inferiores cuando sean necesarios. El compilador rechaza headings fuera de H1–H6, H1 adicionales, headings anteriores al H1 y saltos ascendentes incoherentes como H2 → H4.

La normalización de 5A.1 conserva los textos y el orden de todos los headings. Los antiguos H1 de sección pasan a H2; los headings realmente subordinados pasan a H3. No se reformula ni reordena contenido.

## 9. Seguridad

El validador rechaza:

- `<script>` e `iframe`;
- atributos de evento HTML;
- HTML o JSX embebido;
- imports y exports MDX;
- expresiones MDX con llamadas o sintaxis ejecutable, sin rechazar llaves descriptivas simples;
- esquemas peligrosos y URLs no `http(s)`;
- bloques ejecutables JavaScript/TypeScript/MDX/HTML;
- imágenes Markdown;
- enlaces absolutos, destinos no compilables y traversal fuera de `knowledge/`;
- symlinks dentro del corpus.

El contenido nunca se evalúa ni pasa por `eval`, `Function` o `dangerouslySetInnerHTML`.

## 10. Referencias internas

El compilador valida referencias `REG-*` y `CON-*` presentes en el cuerpo. También admite enlaces Markdown inline relativos entre documentos compilables, anchors extraídos de headings y enlaces externos `http(s)`. Una referencia rota identifica el documento origen y el destino.

Además de resolver el destino, valida su estado: un documento `Vigente` sólo puede referenciar otro documento `Vigente`. Un borrador futuro puede referenciar borradores o vigentes, pero todos sus destinos deben seguir existiendo. El corpus actual contiene 108 relaciones documentales normalizadas, todas `Vigente → Vigente`.

El artefacto normaliza las relaciones a `targetId` y fragmento. No convierte estas relaciones en rutas públicas. Las menciones por título de «Véase también» no se enlazan automáticamente y quedan como deuda editorial para una fase que pueda asignar IDs con revisión humana.

## 11. Arquitectura del compilador

`frontend/scripts/knowledge/` separa:

- configuración de colecciones y exclusiones;
- descubrimiento seguro;
- lectura UTF-8 y control de LF;
- parsing del front matter escalar;
- análisis de headings, seguridad y referencias;
- validación global de IDs, slugs, órdenes y rutas;
- serialización JSON;
- escritura mediante archivo temporal y `rename`.

No usa dependencias externas ni ejecuta contenido.

## 12. Formato del artefacto

El esquema versionado es:

```json
{
  "schemaVersion": 1,
  "collections": [],
  "documents": []
}
```

Cada colección incluye `id`, `title`, `order` y `documentCount`. Cada documento incluye `id`, `slug`, `title`, `version`, `status`, `lastRevision`, `collection`, `sourcePath`, `outputPath`, `order`, `markdown`, `headings` y `references`.

No contiene rutas absolutas, timestamp de generación, datos Git, usuario, HTML precompilado o contenido de los cuatro archivos excluidos.

El artefacto estructural conserva el estado de cada documento y puede representar `Borrador` o `Vigente`. El corpus actual contiene 40 documentos `Vigente` y cero borradores. El JSON canónico continúa sin ser un artefacto público: 5B deberá crear una proyección separada y probar que sólo incorpora estados publicables; React no debe importar el corpus completo ni improvisar esa política.

## 13. Ubicación y política de versionado

La salida es `frontend/src/generated/knowledge/knowledge.json` y se versiona. No se añade a `.gitignore`.

No existe configuración CI o de despliegue que garantice que un build ejecutado con `frontend/` como raíz pueda leer la carpeta hermana `knowledge/`. Por ello 5A no acopla `dev` ni `build` al compilador. Versionar la salida permite una entrega reproducible sin improvisar configuración de hosting. El test del corpus real exige que el JSON versionado coincida byte a byte con una compilación actual.

Esta política puede revisarse cuando CI y despliegue estén definidos y acrediten acceso fiable al monorepo completo.

## 14. Comandos

Desde `frontend/`:

```bash
npm run knowledge:check
npm run knowledge:build
npm run test:run
npm run lint
npm run build
```

`knowledge:check` valida metadatos, headings, grafo editorial, referencias y seguridad sin escribir. `knowledge:build` aplica las mismas invariantes antes de crear directorios o reemplazar la salida y usa escritura atómica. Ni `dev` ni `build` regeneran por sí solos el artefacto en 5A.1.

## 15. Testing

KNOWLEDGE-COMPILER-1 cubre con fixtures temporales:

- descubrimiento, exclusiones y orden;
- contrato de front matter y valores controlados;
- unicidad global y por namespace;
- scripts, iframe, eventos, URLs peligrosas, MDX, JSX y traversal;
- archivos, anchors e IDs válidos o rotos;
- mismos bytes con distinto orden de creación;
- ausencia de timestamp y rutas absolutas;
- corpus real, colecciones, IDs y exclusión de README;
- sincronía del artefacto versionado;
- creación de directorio, escritura completa y preservación de una salida previa ante error.

Los tests no modifican `knowledge/` y limpian sus directorios temporales.

KNOWLEDGE-PUBLICATION-READINESS-1 añade cobertura para H1 único, orden y coincidencia del título, jerarquía sin saltos, niveles H1–H6, relaciones permitidas y prohibidas según estado, diagnósticos con origen y destino, 40 documentos vigentes, tabla de REG-006, validación sin escritura y sincronía determinista del artefacto.

## 16. Diagnóstico de errores

Los fallos usan un código estable y, cuando corresponde, `sourcePath`, ID y elemento responsable. Entre ellos están `HEADING_H1_FIRST`, `HEADING_H1_MULTIPLE`, `HEADING_HIERARCHY_INVALID`, `REFERENCE_STATUS_UNPUBLISHABLE`, `METADATA_REQUIRED`, `ID_DUPLICATE`, `REFERENCE_FILE_MISSING` o `SECURITY_MDX_MODULE`. La CLI escribe el diagnóstico en stderr y devuelve un código distinto de cero.

## 17. Flujo editorial y responsabilidades

1. La persona responsable edita Markdown y metadatos en `knowledge/`.
2. Revisa impacto sobre reglas ejecutables cuando corresponda.
3. Ejecuta `npm run knowledge:check`.
4. Regenera con `npm run knowledge:build`.
5. Incluye fuente y artefacto en el mismo cambio Git.
6. Ejecuta tests, lint y build frontend.
7. La revisión humana valida contenido y estructura; React no edita la fuente.
8. Un cambio de estado o de significado exige autorización editorial y revisión de versión; una normalización técnica no permite reformular contenido.

## 18. Futuro consumo desde React

El bloqueo editorial detectado al preparar 5B queda resuelto: el corpus tiene 40 documentos vigentes, H1 único y grafo publicable. 5B puede construir una proyección pública separada y después su consumidor sin leer archivos del sistema en navegador. Todavía debe implementar y probar filtrado, estructura renderizable, rutas, navegación, seguridad y accesibilidad. El `outputPath` canónico es una identidad lógica, no una promesa de URL.

## 19. Límites y deuda aplazada

- no hay renderer ni rutas públicas;
- no existe colección de Historia, Instalaciones independiente o Escuela;
- «Véase también» por título no es todavía relación estructural;
- no existe todavía proyección pública ni transformación a estructura renderizable;
- imágenes, multimedia, búsqueda y filtros quedan fuera;
- no se integra el compilador en CI, dev o build hasta confirmar el contexto de despliegue;
- no se modifica contenido deportivo ni se resuelven denominaciones pendientes.

## 20. Criterios de aceptación

5A se considera completada cuando los 40 documentos pasan validación, dos compilaciones producen los mismos bytes, el artefacto versionado coincide con el corpus, los cuatro archivos técnicos quedan excluidos, referencias y seguridad fallan de forma explícita, y la suite frontend, lint y build continúan correctos sin nuevas rutas, backend o dependencias.

5A.1 se considera completada cuando REG-001–REG-008 y los 32 Conceptos están `Vigente`, cada documento tiene un único H1 coincidente con `titulo`, la jerarquía no contiene saltos, las 108 referencias documentales son `Vigente → Vigente`, REG-006 conserva su tabla y la regeneración continúa sincronizada y determinista. Esto habilita 5B, pero no crea su artefacto público, renderer, rutas o páginas.
