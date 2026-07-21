# Galotxas

Plataforma web para la gestión y visualización de competiciones de Galotxas.

## Estado del candidato

El MVP funcional está preparado como candidato propuesto `v0.1.0-rc.1`, todavía sin tag ni publicación. La evidencia, alcance, limitaciones y checklist se encuentran en [docs/09-release-candidate.md](docs/09-release-candidate.md).

El candidato no equivale a un despliegue de producción. HTTPS, proxy inverso, backups, monitorización, correo real y configuración operativa siguen pendientes.

## Arquitectura

- `backend/`: Laravel, API REST, dominio y panel administrativo Blade.
- `frontend/`: React, Vite y zona pública/privada del jugador.
- `backend/docker/`: PHP-FPM, Nginx y MariaDB 11.4.
- `docs/`: documentación técnica y funcional.
- `knowledge/`: fuente canónica del reglamento, los conceptos y el conocimiento estable del deporte.

MariaDB es el único motor de base de datos soportado. Laravel utiliza la conexión mariadb y PHP accede al servidor mediante la extensión pdo_mysql.

## Entorno de desarrollo

Requisitos: Docker con Compose y Node.js 22.

1. Preparar Laravel e instalar Composer desde el contenedor oficial del proyecto:

~~~bash
cp backend/.env.example backend/.env
docker compose -f backend/docker/docker-compose.yml run --rm --no-deps --user "$(id -u):$(id -g)" app composer install --no-interaction --prefer-dist
~~~

2. Levantar los servicios, generar la clave de una instalación nueva y ejecutar las migraciones:

~~~bash
docker compose -f backend/docker/docker-compose.yml up -d --build
docker compose -f backend/docker/docker-compose.yml exec app php artisan key:generate --force
docker compose -f backend/docker/docker-compose.yml exec app php artisan migrate --force
~~~

La aplicación queda disponible en http://localhost:8080 y MariaDB expone el puerto local 3307 para herramientas de administración.

No volver a ejecutar `key:generate --force` sobre un entorno existente. `backend/storage` y `backend/bootstrap/cache` deben ser escribibles por el proceso PHP.

Los datos base opcionales y no destructivos de desarrollo se crean de forma explícita:

~~~bash
docker compose -f backend/docker/docker-compose.yml exec app php artisan db:seed --class=DefaultVenueSeeder
docker compose -f backend/docker/docker-compose.yml exec app php artisan db:seed --class=InstitutionalCmsPageSeeder
~~~

`DatabaseSeeder` contiene datos y credenciales de demostración y no debe ejecutarse en producción.

El frontend se ejecuta por separado:

~~~bash
cd frontend
cp .env.example .env
npm ci
npm run dev
~~~

Por defecto Vite queda disponible en http://localhost:5173 y consume `http://localhost:8080/api/v1`. En otros entornos, `VITE_API_BASE_URL` permite configurar la URL de la API durante el build; sin variable, producción utiliza `/api/v1`.

Para generar el artefacto productivo bajo el mismo dominio:

~~~bash
cd frontend
VITE_API_BASE_URL=/api/v1 npm run build
~~~

El conocimiento canónico dispone de una validación y compilación independiente. La proyección pública generada se versiona y alimenta las rutas de Aprende a jugar y Manual mediante carga diferida en React:

~~~bash
cd frontend
npm run knowledge:check
npm run knowledge:build
~~~

El servidor de producción deberá servir `frontend/dist` con fallback SPA a `index.html` y enrutar `/api/v1` y `/admin` hacia Laravel.

## Pruebas

Las pruebas de integración usan una instancia MariaDB 11.4 independiente, con credenciales propias y almacenamiento temporal. Nunca deben ejecutarse contra la base de desarrollo galotxas.

Desde la raíz del repositorio:

~~~bash
docker compose -f backend/docker/docker-compose.yml --profile test run --rm test
~~~

El comando inicia test-db, espera a que esté disponible y ejecuta migraciones y PHPUnit sobre la base aislada galotxas_testing.

Validaciones frontend:

~~~bash
cd frontend
npm run test:run
npm run lint
npm run build
~~~

Smoke E2E del MVP con Chromium, API y MariaDB temporales:

~~~bash
cd frontend
npm run e2e
~~~

El stack E2E es desechable y no utiliza la base de desarrollo.

## Documentación

- [Índice técnico y funcional](docs/README.md)
- [Gobernanza de contenidos y arquitectura pública](docs/10-content-governance.md)
- [Canalización build-time de Knowledge](docs/11-knowledge-pipeline.md)
- [Conocimiento canónico del deporte](knowledge/README.md)
- [Roadmap y estado del MVP](docs/06-roadmap.md)
- [Candidato MVP y publicación](docs/09-release-candidate.md)
- [Historial de cambios](CHANGELOG.md)
