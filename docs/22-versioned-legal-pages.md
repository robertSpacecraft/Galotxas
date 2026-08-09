# Páginas legales públicas versionadas

## 1. Propósito y estado

Este documento registra `VERSIONED-LEGAL-PAGES-1`, la Fase 7D.2C1. El bloque
publica la fuente legal controlada y las tres páginas legales del MVP sin
activar Contacto, correo saliente, consentimientos de menores, imágenes o
despliegue.

7D.2C1 queda cerrada cuando la fuente, el artefacto, React, footer, pruebas y
documentación pasan su validación. Tras 7D.2C2B, 7D.2 queda cerrada; 7D.3
implementa después la indexación fail-closed y sus 61 escenarios E2E cierran
7D. Fase 7 y el MVP siguen pendientes.

## 2. Fuente de verdad

La fuente canónica es `legal/`, versionada en Git y separada tanto del CMS como
de `knowledge/`:

```text
legal/
├── README.md
├── aviso-legal.md
├── privacidad.md
├── cookies.md
└── notices/
    ├── public-identity-minors.md
    └── contact-form.md
```

`README.md` describe el proceso y no se publica. Las páginas siguen limitadas a
los tres nombres. Desde 7D.2C2B, `notices/` admite exactamente los avisos de
identidad de menores y Contacto; son fuentes de formulario y no una cuarta página
pública. Cualquier otro fichero, subdirectorio o enlace simbólico hace fallar
la compilación.

Los administradores no crean ni editan estos textos desde Blade. React tampoco
es su fuente editorial: importa exclusivamente la proyección generada.

## 3. Contrato de metadatos

Cada documento declara:

| Campo | Contrato inicial |
|---|---|
| `id` | `LEG-001`, `LEG-002` o `LEG-003` según allowlist |
| `title` | Título público y H1 exacto |
| `slug` | Slug cerrado de la ruta |
| `version` | SemVer, inicialmente `1.0.0` |
| `status` | Sólo `vigente` en el contrato v1 |
| `published_at` | Fecha ISO válida, inicialmente `2026-08-06` |
| `reviewed_at` | Fecha ISO no anterior a la publicación |
| `owner` | `Club Galotxes de Monover` |
| `source_draft` | Trazabilidad al borrador interno promovido |
| `summary` | Descripción pública usada como metadato de página |

El cuerpo empieza por un único H1 idéntico a `title` y mantiene jerarquía de
headings sin saltos. La ruta del borrador se valida, pero no se incluye en el
artefacto público.

## 4. Compilador build-time

`frontend/scripts/legal/` implementa un pipeline independiente, sin importar
scripts, hashes o artefactos de Knowledge. Sus comandos son:

```bash
cd frontend
npm run legal:check
npm run legal:build
```

`legal:build` valida y genera
`frontend/src/generated/legal/public-legal.json` y, desde 7D.2C2A, las copias
cerradas `frontend/src/generated/legal/form-notices.json` y
`backend/resources/generated/legal/form-notices.json`. `legal:check` compila
dos veces, exige bytes deterministas y compara todas las fuentes con los JSON
versionados.
`npm run build` ejecuta primero esa comprobación, por lo que un artefacto
ausente o desactualizado bloquea el build.

El compilador valida UTF-8, LF, ausencia de espacios finales, campos
obligatorios y desconocidos, SemVer, fechas, estado, identidad jurídica, IDs,
slugs, filenames, duplicados, H1 y jerarquía. El parser sólo proyecta headings,
párrafos, énfasis, listas, tablas, separadores y enlaces HTTPS etiquetados.
Rechaza HTML/JSX, MDX, código, imágenes, URLs peligrosas, listas anidadas,
marcadores internos y datos con forma de teléfono.

La proyección contiene nodos seguros; no contiene Markdown crudo,
`source_draft`, paths de borradores ni referencias a Knowledge. No se genera
dentro de `frontend/dist`.

## 5. Rutas y frontend

`frontend/src/features/legal/` define una configuración cerrada, repositorio,
navegación, renderer, página y estilos. Las rutas diferidas exactas son:

| Ruta | Documento |
|---|---|
| `/legal/aviso-legal` | Aviso legal |
| `/legal/privacidad` | Política de privacidad |
| `/legal/cookies` | Política de cookies y almacenamiento local |

No existe `/legal`. Esa URL, cualquier descendiente desconocido y cualquier ID
no permitido conservan la URL y muestran la 404 de React. No hay redirects,
canonical, llamadas API, lectura CMS o importación de Knowledge.

Cada página muestra un único H1, versión, fecha, navegación entre los tres
documentos, title y description propios. Los enlaces externos usan
`target="_blank"` y `rel="noopener noreferrer"` con indicación accesible. Las
tablas viven en una región con desplazamiento horizontal y la composición se
adapta desde 320 px.

El Navbar conserva Inicio, Competición, Aprende y Club. Los tres enlaces
legales forman un grupo independiente del footer global.

## 6. Aviso legal

El aviso identifica la denominación jurídica `Club Galotxes de Monover`, la
denominación pública `Club Galotxes Monòver`, CIF `G03912193`, domicilio social,
correo público y a Jorge Sánchez Romero como presidente y responsable web.
Separa expresamente el domicilio de las instalaciones del Centro Polideportivo
de Monóvar y no afirma registro deportivo, mandato o representación legal
general no acreditados.

También regula el objeto del sitio, acceso y uso, disponibilidad y correcciones
de información, enlaces externos, derechos de propiedad intelectual y de
terceros, comunicación de incidencias, formulación prudente de legislación y
jurisdicción, y vigencia de la versión.

## 7. Privacidad

La política diferencia cuentas, relación deportiva, Escuela, competición,
Contacto, seguridad, identidad pública, Junta e imágenes. Distingue gestión de
la relación o inscripción, obligaciones aplicables, interés legítimo sujeto a
validación, consentimiento de identidad e imágenes y consentimiento de
Contacto cuando se active.

No presenta una aceptación general como autorización para publicar menores.
Los datos públicos deportivos continúan bajo la proyección backend minimizada
de 7D.2B.

## 8. Menores e identidad futura

La inscripción y la identidad pública son independientes. La futura
autorización será opcional, específica, no premarcada, revocable y con versión
y alcance. Podrá elegir alias, nombre e inicial o anonimato, y separará
resultados, imágenes web, imágenes sociales y archivo histórico.

Las iniciales también pueden identificar. Hasta que exista autorización
verificable y vigente, un menor o una edad desconocida aparece como
`Participante`.

El flujo futuro aún no implementado prevé confirmación del representante por
debajo de 14 años; aceptación del menor y confirmación del representante entre
14 y 17; consentimiento propio desde los 18; enlace de correo de un solo uso;
declaración de patria potestad o tutela; revisión y revocación. No se solicitará
un documento de identidad de forma general.

## 9. Conservación

La política pública incorpora los siguientes criterios operativos aprobados:

| Tratamiento | Plazo o criterio |
|---|---|
| Consultas de Contacto | 12 meses desde el cierre |
| Hash de IP contra abuso | 30 días como máximo, salvo incidente |
| Solicitud de Escuela retirada, rechazada o no formalizada | 6 meses desde el cierre |
| Alumnos de Escuela | Durante la inscripción y 2 años después |
| Cuentas inactivas | Revisión a 24 meses; aviso y eliminación tras 30 días |
| Logs ordinarios | 30 días |
| Logs de seguridad | 90 días |
| Copias de seguridad | Rotación de 30 días |
| Autorizaciones de imágenes | Mientras se publique la imagen y 3 años después |
| Datos completos de competición | Mientras sean necesarios para gestión activa |
| Resultados históricos | Conservación histórica con identidad minimizada |
| Junta directiva | Durante el cargo y después sólo en contexto histórico justificado |

Una obligación, reclamación o incidente puede suspender el borrado de lo
estrictamente necesario. Después corresponde eliminar o anonimizar. Esta fase
no implementa jobs, comandos de purga ni nuevos estados.

## 10. Cookies y recursos externos

La política refleja el inventario técnico tras 7D.2B: no hay analítica,
publicidad, píxeles, embeds, widgets sociales, Google Fonts, Bunny Fonts o
jsDelivr. Los enlaces externos sólo actúan por decisión del usuario.

Laravel puede usar sesión y CSRF en administración. React mantiene el Bearer
en `localStorage.token`, no usa `localStorage.user`, y Contacto no persiste
campos porque el formulario está desactivado. No se muestra banner mientras la
web pública no incorpore mecanismos no esenciales; cualquier cambio exige
reauditoría previa.

## 11. Proveedores y despliegue

La arquitectura prevista cita Vercel para frontend, Railway para backend y
MariaDB, GitHub para el repositorio y el servicio asociado al buzón Hotmail
público. El correo saliente del formulario no está elegido. No se atribuyen
regiones, DPA, contratos o transferencias sin comprobarlos.

La revisión de contratos, configuración, accesos, regiones, logs, borrado,
backups y transferencias es gate de producción.

## 12. Contacto

`CONTACT_FORM_ENABLED=false` continúa siendo el valor seguro. 7D.2C2B añade
`NOTICE-CONTACT-FORM` versión `1.0.0`, su proyección, consentimiento trazable,
config fail-closed, retención y operación. La ruta conserva el CMS y sólo monta
la primera capa/formulario si API y artefacto coinciden. No se configura
proveedor ni se activa correo o formulario productivos.

## 13. CMS y persistencia por entorno

Las páginas legales no son páginas CMS. El CMS institucional vive en MariaDB y
su contenido no viaja con Git: cada entorno tiene su propia base y debe cargar
o importar sus páginas. Un despliegue normal aplica migraciones incrementales
y nunca debe ejecutar `migrate:fresh`, `db:wipe` o seeders E2E sobre producción.

Backups y restauración son gates de 7F. Un futuro `cms:export`/`cms:import` es
recomendable para paridad editorial, pero no se implementa en esta fase.

## 14. Borradores históricos

`docs/legal-drafts/` se conserva como trazabilidad de 7D.2A. Sus tres
borradores promovidos apuntan a la versión pública 1.0.0 y los otros dos siguen
siendo material interno. Ningún archivo de esa carpeta se importa, compila o
sirve al navegador.

## 15. Testing

El bloque `VERSIONED-LEGAL-PAGES-1` cubre:

- allowlist de tres documentos, metadatos, duplicados, estado, fechas, cuerpo,
  H1, marcadores, patrón telefónico, determinismo y sincronía;
- exclusión de README, borradores y Knowledge;
- repositorio fail-closed, tres rutas exactas, lazy loading, metadatos,
  navegación, footer, enlaces externos, tablas y 404;
- ausencia de Navbar legal, banner, API/CMS, Contacto visible y recursos remotos;
- Playwright sobre documentos, navegación, 404, regresión y viewport de 320 px.

La validación conserva los hashes de ambos artefactos Knowledge y de
`EST-REF-001`, construye hacia un directorio temporal y no modifica
`frontend/dist`.

## 16. Seguimiento de 7D.2C2A

El aviso `NOTICE-PUBLIC-IDENTITY-MINORS` versión `1.0.0` queda separado de las
tres páginas. El compilador valida ID, propietario, alcance, estado, fechas,
versión y contenido, y rechaza avisos desconocidos. React usa su proyección en
Escuela; Laravel valida la versión al solicitar y aprobar. La Política de
privacidad sube de `1.0.0` a `1.1.0` porque incorpora el tratamiento real y su
retención. No se añade una ruta `/legal/*` ni un enlace de footer para el aviso.

Seguimiento 7D.2C2B: `NOTICE-CONTACT-FORM` 1.0.0 es el segundo aviso exacto.
Ambos declaran `privacy_url: /legal/privacidad`; el compilador proyecta
`privacyUrl`, mantiene tres páginas, exige dos avisos y bloquea artefactos
desactualizados. Contacto muestra el aviso sólo cuando su config coincide.

Seguimiento 7E: `NOTICE-SCHOOL-ENROLLMENT` 1.0.0 es el tercer aviso exacto y
usa el scope `school_enrollment`. Informa de datos necesarios, menores,
conservación, derechos e independencia de la autorización de identidad
pública. Laravel exige su ID y versión en la inscripción y React lo obtiene del
artefacto generado. La Política de privacidad permanece en `1.1.0` porque ya
incluía el tratamiento y los plazos escolares; siguen existiendo tres páginas
legales y los avisos no crean rutas propias.

## 17. Riesgos y gates pendientes

Permanecen abiertos:

- revisión humana y jurídica de cualquier futura versión legal;
- registro, autorización, sanitización y retirada de imágenes;
- automatización proporcionada de conservación y borrado;
- proveedor y operación de Contacto;
- contratos, regiones, transferencias y configuración reales;
- CSP, expiración/revocación de Bearer y eventual migración a cookies seguras;
- exportación/importación CMS, backups y restauración;
- activación productiva de canonical, sitemap y robots, redirects y metadata
  por respuesta para crawlers sin JavaScript;
- QA de despliegue y aceptación humana.

7D.2C2B resuelve los gates técnicos propios de Contacto; 7F conserva proveedor,
entrega, logs, scheduler, backups y activación. Las
imágenes continúan en un frente independiente posterior, sin numeración
aprobada. Publicar estas páginas no acredita por sí solo que Contacto, imágenes,
consentimientos o infraestructura estén preparados. Fase 7 y el MVP siguen
pendientes.

## 18. Seguimiento de 7D.3

Las tres páginas Legal entran en el sitemap determinista con sus fechas
canónicas y aportan título y summary desde la proyección, sin copiar texto en
el manifiesto. Los avisos de formularios continúan fuera de rutas y sitemap.
Robots, canonical y sitemap quedan implementados pero cerrados por defecto; los
61 escenarios E2E de 7D.3 pasan. Dominio, activación y verificación sobre el
host final pertenecen a 7F.
