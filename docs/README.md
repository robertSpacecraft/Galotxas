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
- criterios de uso de Resources.

## Orden recomendado de lectura

1. [Dominio](01-domain.md)
2. [Arquitectura](02-architecture.md)
3. [Contrato API actual y objetivo](03-api-contract.md)
4. [Panel administrativo Blade](04-admin-panel.md)
5. [Entorno y pruebas](05-testing.md)
6. [Roadmap y deuda técnica](06-roadmap.md)
7. [Decisiones arquitectónicas](07-decisions.md)
8. [Resources y serialización](08-resources.md)

## Relación con AGENTS.md

Los archivos `AGENTS.md` no son documentación histórica ni roadmap.

Su función es dar instrucciones estables a agentes y desarrolladores:

- `/AGENTS.md`: reglas globales del monorepo.
- `/backend/AGENTS.md`: reglas específicas de Laravel, API, Blade y dominio backend.
- `/frontend/AGENTS.md`: reglas específicas de React, Vite, consumo API y componentes.

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
- deuda técnica aceptada explícitamente.

## Estado de la documentación

Esta documentación describe el estado real del proyecto en su fase actual.

Cuando exista diferencia entre:

1. código real;
2. documentación;
3. objetivo futuro;

Debe indicarse de forma explícita. No se debe presentar un objetivo futuro como si ya estuviera implementado.