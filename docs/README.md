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
- gobernanza de contenidos y arquitectura pública.
- canalización build-time del conocimiento canónico.

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

El contrato de navegación inventaría el router y los enlaces actuales, fija las cinco áreas canónicas y define compatibilidad, accesibilidad, SEO y gates de implementación sin presentar las rutas futuras como existentes. El documento de gobernanza define qué información pertenece al dominio Laravel, al CMS administrable o al conocimiento canónico. El contrato de la canalización documenta cómo se valida y compila `knowledge/` sin convertir el artefacto en fuente editorial. El conocimiento estable del deporte se mantiene por separado en [`knowledge/`](../knowledge/README.md); los documentos técnicos describen el software y no sustituyen esa fuente editorial.

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
