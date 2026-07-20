#!/usr/bin/env node

import {
  DEFAULT_KNOWLEDGE_ROOT,
  DEFAULT_OUTPUT_PATH,
} from './config.js'
import { buildKnowledge, compileKnowledge, serializeArtifact } from './compiler.js'
import { KnowledgeValidationError } from './errors.js'

async function run() {
  const command = process.argv[2]

  if (command === 'check') {
    const { artifact, discovery } = await compileKnowledge(DEFAULT_KNOWLEDGE_ROOT)
    const bytes = serializeArtifact(artifact)

    console.log(
      `Knowledge válido: ${artifact.documents.length} documentos, ${artifact.collections.length} colecciones, ${Buffer.byteLength(bytes)} bytes deterministas.`,
    )
    console.log(`Excluidos de forma explícita: ${discovery.excluded.length}.`)
    return
  }

  if (command === 'build') {
    const { artifact, bytes, outputPath } = await buildKnowledge(
      DEFAULT_KNOWLEDGE_ROOT,
      DEFAULT_OUTPUT_PATH,
    )

    console.log(
      `Artefacto generado: ${artifact.documents.length} documentos, ${Buffer.byteLength(bytes)} bytes.`,
    )
    console.log(outputPath)
    return
  }

  throw new KnowledgeValidationError(
    'uso: node scripts/knowledge/cli.js <check|build>',
    { code: 'CLI_USAGE' },
  )
}

run().catch((error) => {
  const prefix = error instanceof KnowledgeValidationError ? error.code : 'KNOWLEDGE_ERROR'
  console.error(`[${prefix}] ${error.message}`)
  process.exitCode = 1
})
