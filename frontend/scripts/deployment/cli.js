import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadEnv } from 'vite';
import { checkDeploymentEnvironment } from './check.js';

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const environment = {
  ...loadEnv('production', frontendRoot, ''),
  ...process.env,
};
const result = checkDeploymentEnvironment(environment);

for (const check of result.checks) {
  console.log(`${check.passed ? 'OK' : 'BLOQUEO'} | ${check.name} | ${check.detail}`);
}

if (!result.passed) {
  console.error('Preflight frontend bloqueado. No se ha realizado ningún despliegue.');
  process.exitCode = 1;
} else {
  console.log('Preflight frontend válido. No se ha realizado ningún despliegue.');
}
