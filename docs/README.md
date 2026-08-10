# Galotxas — Índice de documentación

Este directorio contiene la documentación técnica y funcional del proyecto Galotxas.

La documentación se organiza para separar claramente:

- dominio deportivo;
- arquitectura;
- contrato API;
- panel administrativo Blade;
- entorno de desarrollo y pruebas;
- roadmap;
- decisiones arquitectónicas;
- criterios de uso de Resources;
- contrato de navegación y rutas públicas;
- gobernanza de contenidos y arquitectura pública;
- canalización build-time del conocimiento canónico.
- auditoría y contrato de Escuela de Galotxas.
- aislamiento entre los entornos Docker.
- auditoría de paridad y plan del MVP completo.
- contrato editorial y de navegación final del MVP.
- auditoría de preparación de la vertical institucional Club.
- preparación técnica de Club y del formulario de contacto.
- fachadas públicas de Club y formulario condicionado.
- navegación agrupada, Home y footer estructural.
- preparación legal, privacidad, identidad pública, cookies y terceros.
- endurecimiento técnico de privacidad, identidad pública y recursos externos.
- fuente legal pública versionada, páginas legales y footer.
- autorización verificable y revocable de identidad pública de menores.
- primera capa, operación, retención y activación controlada de Contacto.
- SEO, canonicalización, indexación fail-closed y accesibilidad pública.
- preparación operativa fail-closed de la Escuela de Galotxas.
- preparación productiva, entornos y runbooks de despliegue.

## Orden recomendado de lectura

1. [Glosario](00-glossary.md)
2. [Dominio](01-domain.md)
3. [Arquitectura](02-architecture.md)
4. [Contrato API actual y objetivo](03-api-contract.md)
5. [Panel administrativo Blade](04-admin-panel.md)
6. [Entorno y pruebas](05-testing.md)
7. [Roadmap y deuda técnica](06-roadmap.md)
8. [Decisiones arquitectónicas](07-decisions.md)
9. [Resources y serialización](08-resources.md)
10. [Contrato de navegación y rutas públicas](09-public-navigation.md)
11. [Candidato MVP y proceso de publicación](09-release-candidate.md)
12. [Gobernanza de contenidos y arquitectura pública](10-content-governance.md)
13. [Canalización build-time de Knowledge](11-knowledge-pipeline.md)
14. [Auditoría y contrato de Escuela de Galotxas](12-school-of-galotxas.md)
15. [Aislamiento de entornos Docker](13-docker-environment-isolation.md)
16. [Auditoría de paridad y plan del MVP completo](14-mvp-parity-audit.md)
17. [Contrato editorial y de navegación del MVP](15-mvp-editorial-and-navigation-contract.md)
18. [Auditoría de preparación de la vertical Club](16-club-vertical-readiness-audit.md)
19. [Preparación técnica de Club y contacto](17-club-technical-preparation-and-contact.md)
20. [Fachadas públicas de Club](18-club-public-facades.md)
21. [Navegación agrupada, Home y footer](19-navigation-home-and-footer.md)
22. [Preparación legal, privacidad y cookies](20-legal-privacy-and-cookies-readiness.md)
23. [Endurecimiento de privacidad e identidad pública](21-privacy-hardening-and-public-identity.md)
24. [Páginas legales públicas versionadas](22-versioned-legal-pages.md)
25. [Identidad pública verificable de menores](23-verifiable-minor-public-identity.md)
26. [Operación y primera capa de privacidad de Contacto](24-contact-operation-and-privacy-layer.md)
27. [SEO, accesibilidad e indexación pública](25-public-seo-accessibility-and-indexing.md)
28. [Preparación operativa de Escuela](26-school-operational-readiness.md)
29. [Preparación productiva y runbook de despliegue](27-production-readiness-and-deployment-runbook.md)

El contrato de navegación inventaría el router y los enlaces actuales y conserva
el histórico de la arquitectura pública desde Fase 3. El contrato de Fase 7B
cierra su evolución para el MVP: Inicio y Competición como enlaces, Aprende y
Club como grupos de revelación, Cuenta separada y cuatro rutas institucionales
canónicas. Las rutas Club ya tienen fachadas CMS diferidas y 7D.1 implementa el
grupo del Navbar, Home veraz y footer global. Las rutas legales y los enlaces
de footer se implementan en 7D.2C1; 7D.2C2B completa la capacidad técnica de
Contacto y su activación productiva permanece pendiente de 7F. La auditoría 7D.2A
consolida las fuentes institucionales, identidad, tratamientos,
almacenamientos y terceros y crea borradores internos no publicables. El documento de
gobernanza define qué
información pertenece al dominio Laravel, al CMS administrable o al
conocimiento canónico. El contrato de la canalización documenta cómo se valida
y compila `knowledge/` sin convertir el artefacto en fuente editorial. El
contrato de Escuela concreta su reparto híbrido: 6B.1 implementa programa,
niveles, ubicaciones y horarios administrables, 6B.2 añade inscripciones,
gestión privada y POST anónimo, 6B.3 incorpora centros y actividades sólo
administrativos, 6B.4 añade la lectura pública cerrada y 6C completa la
experiencia React. El documento de aislamiento registra la remediación 6C.1,
los tres proyectos Compose y las guardas de limpieza. La auditoría 7A compara
backend, Blade, API y React y prioriza los bloques pendientes; Fase 7B añade
plantillas, matriz legal, gates y el plan refinado sin aportar contenido real.
La auditoría 7C.0 contrasta ese contrato con el CMS, Knowledge, `/nosotros`, los
recursos versionados y la información editorial aportada; recomienda preparar
contenido privado antes de implementar las fachadas públicas de 7C.
7C.1 audita los assets y `dist`, valida el flujo CMS, incorpora el dominio y la
administración privada de contacto con flags desactivados por defecto y deja la
guía manual y los gates de 7C.2 sin publicar páginas o rutas Club.
7C.2 implementa las cuatro fachadas, sus estados remotos y el formulario
condicionado, conserva el legado y registra los gates productivos y de 7D en el
documento 18, sin convertir React o los fixtures E2E en fuente editorial.
7D.1 aplica el árbol aprobado con disclosures accesibles, conserva Cuenta y
rutas legadas, y registra Home/footer y sus gates en el documento 19.
7D.2A registra la base técnica y los gates jurídicos en el documento 20, sin
añadir rutas legales, enlaces de footer, contenido CMS ni activación de
Contacto.
7D.2B aplica la minimización deportiva fail-closed, elimina el perfil del
almacenamiento del navegador y retira recursos externos automáticos; el
documento 21 registra los contratos, pruebas y riesgos residuales sin publicar
legal ni habilitar Contacto.
7D.2C1 promueve los tres borradores aprobados a la fuente Git `legal/`, genera
una proyección build-time independiente y publica las rutas y el grupo legal
del footer; el documento 22 registra el contrato y mantiene 7D.2C2B, Contacto y
producción abiertos.
7D.2C2A incorpora dentro de `legal/` un aviso de formulario separado, modela la
autorización de identidad de menores, integra Escuela, confirmación y Blade y
aplica la proyección fail-closed en Competición. 7D.2C2B añade el aviso de
Contacto, config fail-closed, consentimiento, notificación auxiliar, retención,
holds y anonimización; el documento 24 cierra 7D.2 sin activar producción.
7D.3 centraliza el inventario SEO, canonical, aliases, metadatos, robots,
sitemap, foco y anuncio SPA; los 61 escenarios E2E pasan y cierran 7D.3 y 7D.
7E prepara después la apertura fail-closed de Escuela, su contenido
administrable, aviso propio, trazabilidad y retención sin cargar datos reales
ni activar producción. 7F.1 prepara después Vercel, Railway, entornos,
preflights, health, bootstrap administrativo y runbooks sin conectar servicios;
el despliegue manual, imágenes, Fase 7 y MVP continúan abiertos.
El conocimiento estable del deporte se mantiene por separado en
[`knowledge/`](../knowledge/README.md); los documentos técnicos describen el
software y no sustituyen esa fuente editorial.

Los dos documentos con prefijo `09-` conservan nombres históricos distintos: uno pertenece al contrato público de la Fase 3A y el otro al proceso del candidato MVP. No son versiones alternativas del mismo documento.

## Relación con AGENTS.md

Los archivos `AGENTS.md` no son documentación histórica ni roadmap.

Su función es dar instrucciones estables a agentes y desarrolladores:

- `/AGENTS.md`: reglas globales del monorepo.
- `/backend/AGENTS.md`: reglas específicas de Laravel, API, Blade y dominio backend.
- `/frontend/AGENTS.md`: reglas específicas de React, Vite, consumo API y componentes.
- `/knowledge/AGENTS.md`: reglas editoriales específicas del conocimiento canónico.

La documentación de este directorio contiene el detalle funcional y técnico que puede evolucionar con el proyecto.

## Regla de mantenimiento

Cuando una implementación cambie de forma relevante el comportamiento del sistema, debe revisarse si afecta a alguno de estos documentos.

Cambios habituales que deben reflejarse aquí:

- nuevas entidades de dominio;
- nuevos flujos funcionales;
- cambios en el contrato API;
- cambios de autenticación o seguridad;
- cambios en el sistema de rankings;
- cambios en el flujo de inscripción;
- cambios en el entorno Docker o testing;
- decisiones arquitectónicas nuevas;
- deuda técnica aceptada explícitamente;
- cambios en fuentes de contenido, publicación o arquitectura pública;
- cambios en navegación, rutas canónicas, aliases o redirects públicos.

## Estado de la documentación

Esta documentación describe el estado real del proyecto en su fase actual.

Cuando exista diferencia entre:

1. código real;
2. documentación;
3. objetivo futuro;

Debe indicarse de forma explícita. No se debe presentar un objetivo futuro como si ya estuviera implementado.
