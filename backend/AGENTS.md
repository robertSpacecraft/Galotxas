# Backend AGENTS — Galotxas

## Propósito

Este documento contiene las reglas específicas para el desarrollo del backend del proyecto Galotxas.

Complementa al archivo `/AGENTS.md` situado en la raíz del proyecto.

Cuando un archivo del backend esté afectado por ambos documentos, prevalecerán las instrucciones de este archivo por ser más específicas.

No debe contener roadmap ni estado del proyecto.

---

# Stack tecnológico

- Laravel 12
- PHP 8.2+
- MariaDB 11.x
- Laravel Sanctum
- Docker
- PHPUnit

MariaDB es el único motor soportado.

No introducir compatibilidad con SQLite.

---

# Arquitectura

El backend tiene dos responsabilidades claramente diferenciadas:

## API REST

Ubicación principal:

- `app/Http/Controllers/Api/V1`

Responsabilidades:

- autenticación
- consumo por React
- futura aplicación móvil
- futuras integraciones

## Panel administrativo

Basado en:

- Blade
- Bootstrap

Ubicación:

- Controladores Web
- Resources cuando corresponda
- Views Blade

La existencia del panel Blade forma parte de la arquitectura oficial.

No convertir funcionalidades Blade en React salvo decisión explícita.

El backend debe distinguir explícitamente entre:

- datos y reglas del dominio deportivo;
- contenido editorial administrable mediante CMS.

No crear endpoints para compensar una separación incorrecta de responsabilidades o lógica que pertenezca a la estructura de presentación del frontend.

---

# Dominio

Toda la lógica deportiva reside exclusivamente en backend.

Ejemplos:

- rankings
- puntuaciones
- generación de ligas
- generación de copa
- validación de resultados
- conflictos
- permisos deportivos
- inscripciones

Nunca trasladar esta lógica al frontend.

---

# Organización del código

Siempre priorizar:

1. Domain Services
2. Form Requests
3. Resources
4. Policies
5. Controllers pequeños

Los controladores deben coordinar.

No deben contener lógica de negocio compleja.

---

# Resources

Los Resources constituyen el contrato de serialización.

Regla general:

Un contexto → un Resource.

Siempre que el contexto funcional cambie significativamente, valorar crear un Resource específico.

Ejemplos:

Implementado actualmente:

- PublicMatchResource

Ejemplos de futuros Resources por contexto:

- ParticipantMatchResource
- AdminMatchResource

Evitar grandes bloques condicionales dentro de un único Resource.

No devolver modelos Eloquent directamente en nuevos endpoints.

---

# API

Mantener el contrato existente salvo decisión expresa.

No modificar estructuras consumidas por React sin revisar previamente los consumidores.

No introducir cambios globales del contrato API durante implementaciones funcionales pequeñas.

Los endpoints públicos de contenido deben filtrar en backend cualquier borrador, publicación futura o registro no visible. El frontend no es una barrera de seguridad editorial.

El acceso directo por `slug` a contenido no publicado debe quedar protegido igual que los listados públicos.

---

# Contenido administrable

Blade es la interfaz administrativa oficial para contenidos editables por administradores.

Una nueva sección administrable debe resolverse como un flujo vertical completo cuando cada pieza sea aplicable:

1. modelo y migración;
2. Form Requests;
3. Actions o Services conforme a la arquitectura existente;
4. CRUD Blade siguiendo `backend/BACKEND_STYLE.md`;
5. autorización administrativa;
6. estados de publicación y visibilidad;
7. slugs estables y únicos;
8. API Resource específico;
9. endpoint o controlador público;
10. exclusión de borradores y contenido no publicable en backend;
11. integración React;
12. tests Feature y pruebas de las capas consumidoras;
13. documentación.

No declarar modelos, endpoints, pantallas o capacidades como existentes sin verificarlos. La gobernanza y la matriz de fuentes se documentan en `/docs/10-content-governance.md`.

Los Form Requests deben validar las entradas y la autorización debe comprobarse en servidor. Los Resources delimitan el contrato público y no deben exponer estado editorial o trazabilidad administrativa salvo que el contexto lo requiera.

Las cargas administrativas de producción necesitan almacenamiento persistente y desacoplado del filesystem efímero del despliegue. Antes de habilitarlas se deben definir permisos, propiedad, procedencia, texto alternativo, reemplazo, borrado y limpieza de huérfanos. Las imágenes de menores requieren controles específicos de autorización, privacidad y uso.

---

# Base de datos

- MariaDB únicamente.
- No utilizar SQL específico de otro motor.
- Las migraciones deben ser compatibles con MariaDB.

No asumir compatibilidad con SQLite.

---

# Testing

Las pruebas de integración se ejecutan exclusivamente mediante el entorno Docker de pruebas.

Comando recomendado:

docker compose --profile test run --rm test

Nunca ejecutar RefreshDatabase sobre la base de desarrollo.

Todo cambio relevante debe acompañarse de tests cuando sea razonable.

Los flujos de contenido administrable deben cubrir con tests Feature, según corresponda:

- autorización administrativa;
- validación y unicidad de slugs;
- estados y fechas de publicación;
- exclusión de borradores y publicaciones futuras;
- acceso directo a contenido no publicable;
- contrato de los Resources públicos.

---

# Seguridad

Mantener siempre:

- Sanctum
- usuarios activos
- rate limiting
- validaciones mediante Form Requests
- autorización mediante Middleware.

Las Policies forman parte de la arquitectura objetivo y podrán incorporarse cuando aporten una mejora clara frente a la autorización actual.

No introducir bypass de seguridad para simplificar implementaciones.

---

# Blade

Las reglas visuales del panel administrativo se documentan en:

`backend/BACKEND_STYLE.md`

No introducir nuevos patrones visuales sin seguir esa guía.

---

# Validaciones mínimas antes de finalizar un bloque

Siempre que sea posible:

- ejecutar la suite oficial:

```bash
cd backend/docker
docker compose --profile test run --rm test
```

- php -l sobre los archivos modificados.
- git diff --check.

Indicar siempre:

- archivos modificados;
- decisiones tomadas;
- limitaciones detectadas.

Cuando cambien un contrato público, un flujo editorial o una regla con consumidores, coordinar la revisión de `frontend/`, `/docs` y, si existe conocimiento canónico implicado, `knowledge/`.
