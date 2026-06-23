# Arquitectura — Galotxas

## Propósito

Este documento describe la arquitectura técnica del proyecto Galotxas y la relación entre sus distintas capas.

Complementa a `01-domain.md`, que describe el funcionamiento del negocio. Aquí se explica cómo dicho dominio se implementa técnicamente.

---

# 1. Visión general

Galotxas es un monorepo compuesto por dos aplicaciones que comparten un mismo dominio y una única base de datos.

Componentes principales:

- Frontend React.
- Backend Laravel.
- MariaDB.
- Docker como entorno de desarrollo y pruebas.

El backend constituye la fuente de verdad del sistema.

---

# 2. Arquitectura actual

Actualmente el proyecto se encuentra en una fase de consolidación.

Conviven componentes ya adaptados al patrón arquitectónico objetivo con otros que todavía mantienen una implementación heredada.

Esto es una decisión consciente para priorizar la estabilidad del proyecto y evitar reescrituras innecesarias.

Ejemplos:

- coexistencia de endpoints ya serializados mediante Resources con otros pendientes de normalización;
- controladores con distintos niveles de extracción hacia Services;
- panel administrativo Blade y frontend React funcionando de forma paralela.

La arquitectura documentada debe reflejar este estado real.

---

# 3. Arquitectura objetivo

La evolución prevista del proyecto persigue:

- separación clara entre Controllers, Services y Resources;
- utilización sistemática de Resources en la API;
- reducción progresiva de lógica en controladores;
- contrato API homogéneo;
- reutilización de Services;
- mantenimiento de compatibilidad con los consumidores existentes.

La arquitectura objetivo constituye una guía para el desarrollo futuro y no implica que todas las áreas del proyecto hayan alcanzado todavía ese nivel de homogeneidad.

---

# 4. Capas de la aplicación

## Frontend React

Responsabilidades:

- interfaz pública;
- zona privada del participante;
- navegación;
- experiencia de usuario;
- consumo de la API.

No implementa reglas deportivas.

## Backend Laravel

Responsabilidades:

- dominio;
- autenticación;
- autorización;
- API REST;
- panel administrativo Blade;
- persistencia;
- generación de competiciones;
- rankings;
- validación de resultados.

Toda decisión deportiva pertenece al backend.

## Base de datos

MariaDB es el único motor soportado por el proyecto.

No existe compatibilidad con SQLite.

Las pruebas utilizan una instancia MariaDB completamente aislada.

## Arquitectura prevista CMS (Futuro)

Cuando se implemente la gestión de contenidos, la arquitectura seguirá el mismo patrón:

- **Panel Blade**: CRUD administrativo para gestores (Noticias, Documentos, Páginas, Formularios).
- **API Pública**: Endpoints de solo lectura para entregar los contenidos.
- **React**: Consumo de la API pública y renderizado de los componentes de contenido (mediante bloques estructurados, sin HTML libre).

---

# 5. Interfaces del sistema

Actualmente existen dos interfaces oficiales.

## Panel administrativo

Tecnología:

- Laravel Blade
- Bootstrap

Gestiona el dominio mediante controladores web.

## Frontend React

Consume exclusivamente la API REST.

No accede directamente a la base de datos.

---

# 6. Flujo de comunicación

Panel Blade

Administrador
→ Blade
→ Controllers Web
→ Services
→ Eloquent
→ MariaDB

Frontend

Usuario
→ React
→ API REST
→ Resources
→ Services
→ Eloquent
→ MariaDB

---

# 7. Organización del backend

La arquitectura backend se basa en responsabilidades separadas.

- Controllers coordinan peticiones.
- Form Requests validan entradas.
- Services implementan lógica de negocio.
- Resources serializan la API.
- Middleware controla la autorización y otras responsabilidades transversales.
- Models representan entidades persistentes.

Actualmente la autorización del proyecto se basa principalmente en Middleware y comprobaciones explícitas.

Las Policies forman parte de la arquitectura objetivo y podrán incorporarse progresivamente cuando aporten una mejora clara respecto a la implementación existente.

En el área privada, `BuildMyRankingsService` coordina el caso de uso de rankings del participante. Localiza sus categorías, reutiliza `BuildCategoryRankingService` para el cálculo deportivo y entrega una estructura explícita al Resource, evitando que el controlador gestione consultas, búsqueda de posición o modelos Eloquent.

Esta extracción no sustituye ni modifica el servicio de ranking de categoría.

---

# 8. Organización del frontend

La arquitectura del frontend evoluciona hacia una organización por funcionalidades.

Los elementos principales son:

- páginas;
- componentes;
- hooks;
- contextos;
- servicios API;
- CSS Modules.

Cada capa debe tener una responsabilidad clara.

---

# 9. Contrato API

La API constituye el contrato entre backend y consumidores.

Los consumidores nunca deben depender de modelos Eloquent.

Las respuestas relevantes deben serializarse mediante Resources.

La normalización completa del contrato se documenta en `03-api-contract.md`.

---

# 10. Seguridad

La arquitectura incorpora actualmente:

- Sanctum;
- Middleware;
- comprobación de usuarios activos;
- rate limiting;
- recuperación segura de contraseña;
- separación entre recursos públicos y privados.

La incorporación de Policies forma parte de la arquitectura objetivo, pero actualmente la autorización se implementa mediante Middleware y comprobaciones explícitas.

---

# 11. Testing

Se distinguen claramente:

- base de desarrollo;
- base de pruebas.

Las pruebas nunca deben ejecutarse sobre la base de desarrollo.

La ejecución oficial utiliza Docker y una instancia MariaDB temporal.

---

# 12. Principios arquitectónicos

- Backend como fuente de verdad.
- React sin lógica deportiva.
- Un contexto funcional ⇒ un Resource.
- Blade y React son interfaces oficiales.
- Services para la lógica de dominio.
- MariaDB como único motor soportado.
- Evolución incremental sin reescrituras masivas.

---

# Relación con el resto de la documentación

- `00-glossary.md`
- `01-domain.md`
- `03-api-contract.md`
- `04-admin-panel.md`
- `08-resources.md`

## Mantenimiento

Cuando cambie la arquitectura del proyecto o se adopte un nuevo patrón estructural, este documento deberá actualizarse.