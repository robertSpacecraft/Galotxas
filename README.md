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
- `legal/`: fuente canónica versionada de los tres textos legales públicos y
  de los avisos de formulario allowlisted de identidad, Contacto y Escuela.

MariaDB es el único motor de base de datos soportado. Laravel utiliza la conexión mariadb y PHP accede al servidor mediante la extensión pdo_mysql.

## Entorno de desarrollo

Requisitos: Docker con Compose y Node.js 22.

1. Preparar Laravel e instalar Composer desde el contenedor oficial del proyecto:

~~~bash
cp backend/.env.example backend/.env
docker compose --project-name galotxas -f backend/docker/docker-compose.yml run --rm --no-deps --user "$(id -u):$(id -g)" app composer install --no-interaction --prefer-dist
~~~

2. Levantar los servicios, generar la clave de una instalación nueva y ejecutar las migraciones:

~~~bash
docker compose --project-name galotxas -f backend/docker/docker-compose.yml up -d --build
docker compose --project-name galotxas -f backend/docker/docker-compose.yml exec app php artisan key:generate --force
docker compose --project-name galotxas -f backend/docker/docker-compose.yml exec app php artisan migrate --force
~~~

La aplicación queda disponible en http://localhost:8080 y MariaDB expone el puerto local 3307 para herramientas de administración.

No volver a ejecutar `key:generate --force` sobre un entorno existente. `backend/storage` y `backend/bootstrap/cache` deben ser escribibles por el proceso PHP.

Los datos base opcionales y no destructivos de desarrollo se crean de forma explícita:

~~~bash
docker compose --project-name galotxas -f backend/docker/docker-compose.yml exec app php artisan db:seed --class=DefaultVenueSeeder
docker compose --project-name galotxas -f backend/docker/docker-compose.yml exec app php artisan db:seed --class=InstitutionalCmsPageSeeder
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

Los textos legales disponen de un pipeline independiente. El build exige que
su proyección pública versionada esté sincronizada:

~~~bash
cd frontend
npm run legal:check
npm run legal:build
~~~

La fuente vive en `legal/`; las salidas son
`frontend/src/generated/legal/public-legal.json`,
`frontend/src/generated/legal/form-notices.json` y la copia backend del aviso.
Las rutas públicas son
`/legal/aviso-legal`, `/legal/privacidad` y `/legal/cookies`. No usan CMS, API
ni Knowledge. Los avisos de identidad de menores y Contacto no crean rutas
legales nuevas.

El servidor de producción deberá servir `frontend/dist` con fallback SPA a `index.html` y enrutar `/api/v1` y `/admin` hacia Laravel.

La capacidad técnica del formulario de contacto está desactivada por defecto.
Laravel aplica aviso versionado, configuración fail-closed, persistencia como
recepción, correo auxiliar, retención y operación Blade. No debe habilitarse en
producción hasta configurar y probar destinatario, remitente, proveedor,
entrega, logs, scheduler, backups y rollback. La carga de las
páginas Club continúa siendo manual mediante el CMS Blade. React expone las
fachadas diferidas `/club/quienes-somos`, `/club/contacto`, `/club/federarse` y
`/club/documentos`; el formulario sólo se monta cuando la configuración pública
devuelve `enabled: true`. Fase 7D.1 incorpora el Navbar agrupado Aprende/Club,
Home orientada a recorridos reales y footer global. 7D.2A consolida identidad,
tratamientos, cookies, terceros y borradores internos; 7D.2B minimiza la
identidad deportiva pública, conserva en el navegador sólo el token Bearer,
restaura el perfil mediante `/me` y elimina las cargas automáticas de fuentes
y recursos externos. 7D.2C1 publica los tres textos legales desde Git mediante
compilación build-time y los enlaza en el footer. 7D.2C2A añade la autorización
verificable y revocable de identidad deportiva de menores, integrada con
Escuela, revisión Blade y proyección fail-closed de Competición; sus flags y el
correo saliente continúan desactivados por defecto. 7D.2C2B completa la primera
capa y operación de Contacto sin proveedor ni activación productiva. 7D.3
añade la política SEO y de accesibilidad transversal, canonicaliza aliases y
genera robots/sitemap de forma fail-closed y completa 61 escenarios E2E sobre
el stack aislado. 7D queda cerrada. Las imágenes permanecen como un frente
independiente y el despliegue corresponde a 7F.

La inscripción de Escuela también permanece cerrada por defecto mediante
`SCHOOL_ENROLLMENT_ENABLED=false`. Laravel exige configuración operativa
completa, contenido administrable y `NOTICE-SCHOOL-ENROLLMENT` vigente antes de
declararla disponible; React sólo consume `open`, `closed` o `unavailable`.
Los datos reales, el correo y la activación productiva siguen siendo gates de
7F. El contrato está en
[docs/26-school-operational-readiness.md](docs/26-school-operational-readiness.md).

La indexación pública está desactivada por defecto. Un build indexable exige
configurar explícitamente una URL HTTPS no local. El dominio real no se incluye
en el repositorio y su activación pertenece a 7F:

~~~bash
cd frontend
VITE_PUBLIC_SITE_URL=https://dominio-confirmado.example \
VITE_PUBLIC_INDEXING_ENABLED=true \
npm run build
~~~

El host anterior es únicamente ilustrativo. `npm run seo:check` valida el
contrato sin red y el build normal sigue funcionando en modo fail-closed.

## Pruebas

Las pruebas de integración usan una instancia MariaDB 11.4 independiente, con credenciales propias y almacenamiento temporal. Nunca deben ejecutarse contra la base de desarrollo galotxas.

Desde la raíz del repositorio:

~~~bash
backend/scripts/run-tests.sh
~~~

El runner usa el proyecto explícito `galotxas-test` y el archivo exclusivo `docker-compose.test.yml`: inicia `test-db`, espera a que esté disponible, ejecuta migraciones y PHPUnit sobre `galotxas_testing`, y desmonta sólo sus recursos temporales. Desarrollo, tests backend y E2E no comparten proyecto, red, volumen ni base. El contrato completo y los comandos seguros se documentan en [docs/13-docker-environment-isolation.md](docs/13-docker-environment-isolation.md).

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
- [Preparación técnica de Club y contacto](docs/17-club-technical-preparation-and-contact.md)
- [Navegación agrupada, Home y footer](docs/19-navigation-home-and-footer.md)
- [Preparación legal, privacidad y cookies](docs/20-legal-privacy-and-cookies-readiness.md)
- [Páginas legales públicas versionadas](docs/22-versioned-legal-pages.md)
- [SEO, accesibilidad e indexación pública](docs/25-public-seo-accessibility-and-indexing.md)
- [Candidato MVP y publicación](docs/09-release-candidate.md)
- [Historial de cambios](CHANGELOG.md)
