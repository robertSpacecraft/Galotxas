# Galotxas

Plataforma web para la gestión y visualización de competiciones de Galotxas.

## Arquitectura

- backend/: Laravel, API y lógica de negocio.
- frontend/: React y Vite.
- backend/docker/: PHP-FPM, Nginx y MariaDB 11.4.
- docs/: documentación técnica y funcional.

MariaDB es el único motor de base de datos soportado. Laravel utiliza la conexión mariadb y PHP accede al servidor mediante la extensión pdo_mysql.

## Entorno de desarrollo

1. Copiar backend/.env.example a backend/.env si el archivo local no existe.
2. Levantar los servicios desde backend/docker:

~~~bash
docker compose up -d --build
~~~

La aplicación queda disponible en http://localhost:8080 y MariaDB expone el puerto local 3307 para herramientas de administración.

## Pruebas

Las pruebas de integración usan una instancia MariaDB 11.4 independiente, con credenciales propias y almacenamiento temporal. Nunca deben ejecutarse contra la base de desarrollo galotxas.

Desde backend/docker:

~~~bash
docker compose --profile test run --rm test
~~~

El comando inicia test-db, espera a que esté disponible y ejecuta migraciones y PHPUnit sobre la base aislada galotxas_testing.
