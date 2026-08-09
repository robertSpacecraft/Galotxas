import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadEnv } from 'vite';
import publicKnowledge from '../../src/generated/knowledge/public-knowledge.json' with { type: 'json' };
import publicLegal from '../../src/generated/legal/public-legal.json' with { type: 'json' };
import { checkPublicSeo } from './check.js';

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const environment = {
  ...loadEnv('production', frontendRoot, ''),
  ...process.env,
};

try {
  const result = checkPublicSeo({
    environment,
    knowledgeArtifact: publicKnowledge,
    legalArtifact: publicLegal,
  });

  console.log(
    `SEO válido: ${result.declaredRoutes} rutas declaradas, `
      + `${result.sitemapEntries} URLs canónicas, `
      + `indexación ${result.indexingEnabled ? 'habilitada' : 'deshabilitada'}.`,
  );
} catch (error) {
  console.error(`SEO inválido: ${error.message}`);
  process.exitCode = 1;
}
