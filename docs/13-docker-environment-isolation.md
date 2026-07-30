# Aislamiento de entornos Docker

## 1. Propósito

Este documento define la separación obligatoria entre desarrollo, tests backend y E2E. Su objetivo es impedir que una ejecución o limpieza de pruebas alcance contenedores, redes, volúmenes o datos de otro entorno.

La identidad de un entorno no depende de `APP_ENV` por sí sola. Queda determinada conjuntamente por el proyecto Compose, el archivo Compose, la red, el almacenamiento y la base.

## 2. Entornos

| Entorno | Proyecto Compose | Archivo | Contenedores generados | Red | Almacenamiento de base | Base | Limpieza |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Desarrollo | `galotxas` | `backend/docker/docker-compose.yml` | `galotxas-app-1`, `galotxas-web-1`, `galotxas-db-1` | `galotxas_galotxas_net` | volumen lógico `galotxas_db_data`, físico `galotxas_galotxas_db_data` | `galotxas` | exclusivamente manual, con proyecto y archivo explícitos; nunca desde runners de test |
| Tests backend | `galotxas-test` | `backend/docker/docker-compose.test.yml` | `galotxas-test-test-db-1` y runner efímero prefijado | `galotxas-test_test` | `tmpfs`, sin volumen Docker | `galotxas_testing` | automática mediante `safe-compose-down.sh` |
| E2E | `galotxas-e2e` | `backend/docker/docker-compose.e2e.yml` | `galotxas-e2e-app-1`, `galotxas-e2e-web-1`, `galotxas-e2e-db-1` y runner efímero prefijado | `galotxas-e2e_e2e` | `tmpfs`, sin volumen Docker | `galotxas_e2e` | automática mediante `safe-compose-down.sh` |

Los nombres físicos reflejan el comportamiento actual de Compose. La propiedad `name:` de cada archivo y los runners con `--project-name` fijan la misma identidad de forma redundante.

## 3. Proyecto Compose de desarrollo

`docker-compose.yml` contiene únicamente `app`, `web` y `db`. Declara `name: galotxas` y ya no contiene perfiles ni servicios de test. Los comandos documentados pasan además `--project-name galotxas`.

Durante 6C.1 no se levantó este proyecto, no se creó su volumen y no se ejecutaron migraciones o seeders sobre él.

## 4. Proyecto de tests backend

`docker-compose.test.yml` contiene únicamente `test-db` y `test`, declara `name: galotxas-test` y utiliza una MariaDB temporal. El punto de entrada oficial desde la raíz es:

```bash
backend/scripts/run-tests.sh
backend/scripts/run-tests.sh --filter=School
```

El runner valida el entorno, limpia sólo residuos previos de su propio proyecto, ejecuta PHPUnit y desmonta sus recursos incluso si la suite falla.

## 5. Proyecto E2E

`docker-compose.e2e.yml` declara `name: galotxas-e2e`. `frontend/scripts/run-e2e.sh` fija el mismo proyecto, valida la configuración, levanta el stack, migra y siembra sólo la base efímera, ejecuta Playwright y desmonta exclusivamente ese proyecto.

```bash
cd frontend
npm run e2e
```

## 6. Contenedores

Ningún archivo Compose usa `container_name`. Compose genera todos los nombres con el prefijo del proyecto, lo que permite ejecutar los tres entornos sin colisiones de nombre.

La guarda rechaza cualquier `container_name` en las configuraciones de test y, de forma expresa, los nombres locales históricos `galotxas_app`, `galotxas_web` y `galotxas_db`.

## 7. Redes

Cada archivo declara una red lógica distinta:

- desarrollo: `galotxas_net`;
- tests backend: `test`;
- E2E: `e2e`.

Compose antepone el proyecto al nombre físico. Ningún test referencia `galotxas_net`, y la guarda aborta si esa referencia aparece en una configuración resuelta.

## 8. Volúmenes

Sólo desarrollo declara un volumen Docker persistente, `galotxas_db_data`. Tests backend y E2E montan `/var/lib/mysql` mediante `tmpfs` y no declaran volúmenes nombrados.

La guarda rechaza tanto el nombre lógico de desarrollo como el nombre físico histórico `docker_galotxas_db_data`. La limpieza de pruebas puede solicitar `--volumes`, pero en esos proyectos no existe un volumen de base persistente que compartir.

## 9. Bases de datos

Las bases son distintas:

- desarrollo: `galotxas`;
- tests backend: `galotxas_testing`;
- E2E: `galotxas_e2e`.

La configuración PHPUnit fuerza MariaDB, el host `test-db` y `galotxas_testing`. El E2E exige simultáneamente `APP_ENV=e2e` y `galotxas_e2e`; `E2ESmokeSeeder` conserva además su propia defensa. Ninguna de estas comprobaciones sustituye el aislamiento del proyecto Compose.

## 10. Comandos permitidos

Desarrollo, sólo cuando el operador autorice trabajar con la instalación local:

```bash
docker compose --project-name galotxas --file backend/docker/docker-compose.yml up -d --build
docker compose --project-name galotxas --file backend/docker/docker-compose.yml exec app php artisan migrate --force
```

Tests:

```bash
backend/scripts/run-tests.sh
cd frontend && npm run e2e
```

Inspección y regresión de guardas:

```bash
docker compose --project-name galotxas-e2e --file backend/docker/docker-compose.e2e.yml config
backend/docker/test-compose-isolation-guard.sh
```

## 11. Comandos prohibidos

No se admiten:

```text
docker compose down --volumes
docker compose down -v
docker system prune
docker volume prune
```

Tampoco se permite ejecutar `down`, `rm -v`, migraciones destructivas o seeders sin proyecto, archivo y entorno resueltos explícitamente. No deben usarse coincidencias amplias de nombres para eliminar recursos.

## 12. Guardas

`compose-isolation-guard.sh` usa `docker compose config` y verifica antes de limpiar:

- proyecto exacto `galotxas-test` o `galotxas-e2e`;
- archivo Compose canónico del entorno;
- `name:` resuelto coincidente;
- `APP_ENV` esperado;
- base esperada;
- almacenamiento `tmpfs`;
- ausencia del volumen y red de desarrollo;
- ausencia de nombres fijos de contenedor;
- prefijo correcto de cualquier contenedor, red o volumen ya etiquetado para el proyecto.

`safe-compose-down.sh` exige entorno, proyecto y archivo como tres argumentos obligatorios, vuelve a ejecutar la guarda y sólo entonces llama a `down --volumes --remove-orphans`.

`test-compose-isolation-guard.sh` acredita configuraciones seguras y fallos ante proyecto de desarrollo, volumen local, base incorrecta, entorno incorrecto, nombres fijos, archivo inesperado y cleanup sin proyecto.

## 13. Flujo E2E

1. Resolver rutas absolutas y el proyecto `galotxas-e2e`.
2. Validar configuración y recursos existentes.
3. Limpiar, mediante el helper seguro, sólo residuos del mismo proyecto.
4. Levantar y esperar app, web y MariaDB E2E.
5. Ejecutar migraciones y `E2ESmokeSeeder` en la base temporal.
6. Ejecutar Playwright desde el runner oficial.
7. Validar de nuevo antes del cleanup.
8. Desmontar sólo `galotxas-e2e`.
9. Eliminar informes únicamente tras un resultado correcto.

## 14. Flujo de tests backend

1. Resolver `docker-compose.test.yml` y `galotxas-test`.
2. Validar configuración y recursos.
3. Limpiar residuos del mismo proyecto.
4. Iniciar `test-db` sobre `tmpfs`.
5. Ejecutar `php artisan test` con los argumentos recibidos.
6. Validar de nuevo y desmontar sólo `galotxas-test`.

## 15. Limpieza segura

Los runners no invocan `docker compose down` directamente. La única ruta automática es:

```bash
backend/docker/safe-compose-down.sh e2e galotxas-e2e backend/docker/docker-compose.e2e.yml
backend/docker/safe-compose-down.sh test galotxas-test backend/docker/docker-compose.test.yml
```

El helper resuelve la ruta real antes de actuar. Un archivo alternativo, un proyecto modificado o una guarda fallida impiden el `down`.

## 16. Verificación previa

Antes de una regresión integral se inspeccionan, sin credenciales:

```bash
docker compose ls --all
docker ps -a
docker volume ls
docker network ls
```

En la revalidación de 6C.1 no apareció ningún proyecto o recurso Galotxas antes de las pruebas. El volumen de desarrollo perdido no había reaparecido.

## 17. Verificación posterior

Después de PHPUnit y E2E deben quedar vacías las consultas por etiquetas de `galotxas-test` y `galotxas-e2e` para contenedores, redes y volúmenes. También deben faltar `frontend/test-results`, `frontend/playwright-report` y `frontend/blob-report` después de una ejecución correcta.

La prueba 6C.1 observó durante E2E cuatro contenedores prefijados, la red `galotxas-e2e_e2e`, cero volúmenes y `tmpfs` en la base. Después del cleanup no quedó ningún recurso de test.

## 18. Incidente de 6C

Durante la limpieza posterior a una validación de 6C se ejecutó:

```bash
docker compose --file backend/docker/docker-compose.yml --profile test down --volumes --remove-orphans
```

El archivo reunía entonces desarrollo y tests backend. Como el comando no fijaba proyecto, Compose derivó `docker` del directorio del archivo y consideró todos los servicios y volúmenes del mismo archivo parte del mismo proyecto.

## 19. Recursos eliminados

La operación detuvo y eliminó:

- `galotxas_web`;
- `galotxas_app`;
- `galotxas_db`;
- el contenedor temporal `docker-test-db-1`;
- la red `docker_galotxas_net`;
- el volumen `docker_galotxas_db_data`.

Los nombres fijos de los contenedores de desarrollo ocultaban visualmente el prefijo real del proyecto, pero no cambiaban la propiedad Compose del volumen y la red.

## 20. Impacto

Se perdió el volumen persistente de la base local de desarrollo. No se demostró impacto en el código fuente ni en los cambios pendientes de 6C. El repositorio y los artefactos de Knowledge permanecieron intactos.

## 21. Recuperación

No se intentó reconstruir la base durante 6C.1.

- **Datos reproducibles:** migraciones y seeders explícitos podrían regenerarlos en una reconstrucción futura autorizada.
- **Datos únicos sin backup:** se consideran no recuperables mientras no aparezca una copia; no deben inventarse.
- **Dump encontrado:** debe inventariarse, verificarse y restaurarse sólo con autorización humana sobre un volumen nuevo.
- **Reconstrucción limpia:** requiere autorización explícita para crear el proyecto de desarrollo, el volumen, migrar y ejecutar únicamente los seeders acordados.

## 22. Prevención

La prevención combina:

- archivos separados;
- nombres de proyecto declarados y pasados por CLI;
- redes y bases diferentes;
- `tmpfs` en pruebas;
- ausencia de `container_name`;
- runners únicos;
- configuración resuelta validada;
- cleanup encapsulado;
- regresiones negativas;
- inventario antes, durante y después.

No se vuelve a atribuir seguridad a una sola variable de entorno.

## 23. Criterios de aceptación

DOCKER-ENVIRONMENT-ISOLATION-1 queda aceptado cuando:

- las tres configuraciones resueltas son disjuntas;
- las guardas positivas y negativas pasan;
- un recurso centinela ajeno sobrevive al cleanup E2E;
- las suites backend, frontend y E2E finalizan correctamente;
- Knowledge conserva sus hashes;
- no se reconstruye desarrollo;
- no quedan recursos o informes temporales.

La revalidación del 30 de julio de 2026 cumplió estos criterios. Una red centinela temporal sin volumen ni datos permaneció presente después del cleanup E2E y se eliminó después de verificar la no destrucción.
