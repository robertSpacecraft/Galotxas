# CLAUDE.md

Este archivo proporciona una guía de arranque para Claude Code al trabajar en este repositorio. No sustituye las instrucciones canónicas del proyecto.

## Precedencia documental

Antes de modificar cualquier archivo, leer las instrucciones aplicables en este orden:

1. `/AGENTS.md`
2. El `AGENTS.md` específico de la zona afectada:
   - `/backend/AGENTS.md`
   - `/frontend/AGENTS.md`
   - `/knowledge/AGENTS.md`
3. Las guías y documentación técnica aplicables, como:
   - `/backend/BACKEND_STYLE.md`
   - `/frontend/FRONTEND_STYLE.md`
   - `/docs/`

En caso de conflicto, prevalece la instrucción más específica.

No duplicar aquí reglas ya definidas en esas fuentes. Este archivo debe mantenerse estable, breve y sin roadmap, estado puntual, SHA, tareas pendientes ni información efímera.

Respetar las convenciones, idioma y nomenclatura ya existentes en cada zona del repositorio. No renombrar identificadores de código por motivos lingüísticos.

## Arquitectura esencial

Galotxas es un monorepo con:

- frontend React;
- backend Laravel con API REST y panel administrativo Blade;
- MariaDB como único motor de base de datos soportado;
- Docker para desarrollo y pruebas locales.

Principios que deben preservarse:

- el backend es la fuente de verdad del dominio;
- React no calcula reglas deportivas;
- el panel Blade `/admin` forma parte de la arquitectura oficial;
- React no es fuente editorial para contenido administrable;
- dominio, API, administración y presentación deben mantenerse separados.

Consultar los `AGENTS.md` y `/docs/` para el detalle antes de implementar.

## Comandos habituales

### Backend local

Desarrollo:

```bash
docker compose --project-name galotxas -f backend/docker/docker-compose.yml up -d --build
docker compose --project-name galotxas -f backend/docker/docker-compose.yml exec app php artisan migrate --force
```

Tests backend sobre MariaDB aislada:

```bash
backend/scripts/run-tests.sh
backend/scripts/run-tests.sh --filter=NombreDelTest
backend/scripts/run-tests.sh tests/Feature/ArchivoTest.php
```

Nunca ejecutar `RefreshDatabase` ni la suite de integración contra la base de desarrollo.

### Frontend

```bash
cd frontend
npm ci
npm run dev
npm run test:run
npm run lint
npm run build
npm run e2e
```

Para un test concreto:

```bash
npm run test:run -- ruta/al/test
npm run e2e -- e2e/archivo.spec.js
```

### Knowledge y legal

Cuando el cambio afecte a estas fuentes o a sus artefactos generados:

```bash
cd frontend
npm run knowledge:check
npm run knowledge:build
npm run legal:check
npm run legal:build
```

Usar `npm run deploy:check` cuando el alcance requiera validar gates de despliegue.

## Aislamiento local

Los entornos Docker de desarrollo, tests y E2E son independientes. Consultar `/docs/13-docker-environment-isolation.md` y los scripts guard antes de manipularlos.

No ejecutar `docker compose down` sin `--project-name` explícito.

El uso de Docker indicado aquí aplica a desarrollo y pruebas locales. No generalizarlo a staging o producción ni a operaciones explícitas realizadas dentro de Railway.

## Operativa con agentes

Codex y Claude son implementadores equivalentes.

Reglas de trabajo:

- un bloque iniciado por un agente debe terminar preferentemente con ese mismo agente;
- no cambiar de agente a mitad de una implementación salvo instrucción explícita y un checkpoint suficiente para transferir el trabajo con seguridad;
- el siguiente bloque puede hacerlo cualquiera de los dos según disponibilidad de tokens;
- Git lo controla el usuario;
- no ejecutar `git add`, `git commit`, `git push`, `git merge`, `git reset`, `git restore`, `git stash` ni deploys salvo instrucción explícita;
- al terminar una implementación, dejar los cambios locales sin preparar y reportar de forma concisa archivos modificados, decisiones, validaciones ejecutadas y limitaciones detectadas.

El otro agente puede utilizarse como auditor independiente cuando el riesgo del bloque lo justifique.

## Validación antes de cerrar un bloque

Seguir siempre las validaciones específicas indicadas en los `AGENTS.md` aplicables.

Como mínimo:

Backend:
- tests focalizados cuando proceda;
- suite oficial cuando el riesgo o alcance lo justifique;
- `php -l` sobre PHP modificado;
- Pint sobre el alcance afectado cuando corresponda;
- `git diff --check`.

Frontend:
- tests focalizados cuando proceda;
- `npm run test:run`;
- `npm run lint`;
- `npm run build`;
- E2E cuando el cambio afecte a flujos cubiertos por Playwright.

No repetir pruebas ya superadas si no ha habido cambios posteriores que puedan afectarlas.

Evitar salidas innecesariamente extensas: no volcar diffs completos, archivos completos ni logs masivos salvo que se soliciten o sean necesarios para diagnosticar un fallo.

## Cierre

Antes de declarar un bloque terminado:

- verificar el alcance real de los cambios;
- revisar impacto coordinado entre `backend/`, `frontend/`, `knowledge/` y `docs/` cuando corresponda;
- actualizar documentación si cambia el comportamiento;
- informar de deuda técnica o limitaciones detectadas;
- no afirmar PASS de una validación que no se haya ejecutado o verificado.
