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