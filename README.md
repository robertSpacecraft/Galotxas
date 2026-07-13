# Galotxas

Plataforma web para la gestión y visualización de competiciones de Galotxas.

## Arquitectura

- `backend/`: Laravel, API REST, dominio y panel administrativo Blade.
- `frontend/`: React, Vite y zona pública/privada del jugador.
- `backend/docker/`: PHP-FPM, Nginx y MariaDB 11.4.
- `docs/`: documentación técnica y funcional.
- `knowledge/`: fuente documental del deporte y su reglamento.

MariaDB es el único motor de base de datos soportado. Laravel utiliza la conexión mariadb y PHP accede al servidor mediante la extensión pdo_mysql.

## Entorno de desarrollo

1. Copiar backend/.env.example a backend/.env si el archivo local no existe.
2. Levantar los servicios desde backend/docker:

~~~bash
docker compose up -d --build
~~~

La aplicación queda disponible en http://localhost:8080 y MariaDB expone el puerto local 3307 para herramientas de administración.

El frontend se ejecuta por separado:

~~~bash
cd frontend
npm ci
npm run dev
~~~

Por defecto Vite queda disponible en http://localhost:5173 y consume `http://localhost:8080/api/v1`. En otros entornos, `VITE_API_BASE_URL` permite configurar la URL de la API durante el build; sin variable, producción utiliza `/api/v1`.

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
- [Reglas de dominio deportivo](knowledge/README.md)
- [Roadmap y estado del MVP](docs/06-roadmap.md)
