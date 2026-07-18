# Knowledge AGENTS — Galotxas

## Propósito y autoridad

Este documento contiene las reglas específicas para el contenido de `knowledge/`.

Complementa a `/AGENTS.md`. Para cualquier archivo de esta carpeta se aplican ambos documentos y prevalece este cuando concrete una regla editorial propia de `knowledge/`.

`knowledge/` es la fuente canónica del conocimiento estable sobre las Galotxas. No es un CMS ni sustituye el contenido operativo que debe gestionar un administrador.

---

## Responsabilidades editoriales

- `reglamento/` contiene la formulación normativa editorial del deporte.
- `conceptos/` contiene vocabulario, elementos y definiciones canónicas.
- El futuro Manual consumirá y organizará este conocimiento, pero no será una segunda fuente de reglas o conceptos.
- La Escuela de Galotxas es un área distinta del Manual. Su posible contenido estable podrá basarse en `knowledge/` cuando se defina una colección específica; su actividad operativa pertenecerá al backend CMS.

No crear nuevas colecciones o estructuras físicas sin contenido real, contrato editorial y decisión documentada.

---

## Rigor y procedencia

- No inventar reglas, excepciones, nombres ni terminología.
- No importar conceptos de otros deportes por analogía.
- Cuando una afirmación no esté confirmada, registrar la incertidumbre de forma explícita en lugar de presentarla como regla.
- Conservar la procedencia y los permisos de uso cuando existan referencias externas o aportaciones de terceros.
- Si una regla canónica cambia y puede afectar al comportamiento ejecutable, revisar obligatoriamente el backend, sus pruebas y la documentación técnica.

---

## Identidad, archivos y relaciones

- Los identificadores editoriales deben ser estables y no reutilizarse para otro concepto.
- Los slugs públicos deben ser estables y usar `kebab-case`.
- Los nombres de archivo deben usar `snake_case` y conservar la extensión `.md`.
- Las relaciones entre documentos deben realizarse mediante ID estable cuando el contrato editorial las admita, no mediante títulos susceptibles de cambiar.
- Un renombrado o movimiento exige revisar referencias, IDs, slugs, artefactos generados, URLs y consumidores antes de aplicarse.
- No cambiar simultáneamente ID y slug sin una migración documentada.

No añadir campos nuevos o normalizar metadatos de forma parcial hasta que exista un contrato editorial aprobado para toda la colección afectada.

---

## Formato y contenido ejecutable

- Usar UTF-8, finales de línea LF y ausencia de espacios finales.
- No usar MDX.
- No incluir HTML ejecutable o arbitrario, scripts, componentes ni código que se interprete en el navegador.
- React debe consumir artefactos generados y validados desde `knowledge/`; no mantener copias manuales equivalentes en JSX.
- El compilador futuro será responsable de validar y generar datos para React. `knowledge/` no se sirve directamente mediante una API Laravel en la primera versión del Manual.

---

## Multimedia y protección

- Todo recurso debe tener procedencia, permisos de uso y responsable identificables.
- Definir texto alternativo y contexto de uso para imágenes informativas.
- No añadir vídeos pesados al repositorio salvo decisión expresa sobre tamaño y distribución.
- Las imágenes o datos de menores requieren autorización verificable, criterios de privacidad, finalidad y vigencia de uso antes de incorporarse.
- No introducir material cuya licencia o consentimiento sea incierto.

---

## Higiene y validación

- No dejar scripts temporales, backups, archivos `.orig`, cachés, resultados generados ni residuos dentro de `knowledge/`.
- Antes de un commit, validar estructura, IDs y slugs cuando existan herramientas oficiales; revisar enlaces y relaciones; comprobar UTF-8/LF y ejecutar `git diff --check`.
- Verificar que un cambio no duplica una definición ya existente en `reglamento/`, `conceptos/`, base de datos, seeders o React.
- Revisar `/docs/10-content-governance.md` y actualizar la documentación afectada cuando cambien la autoridad, estructura o consumo del conocimiento.
