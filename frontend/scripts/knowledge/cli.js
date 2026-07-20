#!/usr/bin/env node

import {
  DEFAULT_KNOWLEDGE_ROOT,
  DEFAULT_OUTPUT_PATH,
  DEFAULT_PUBLIC_OUTPUT_PATH,
} from './config.js'
import {
  buildKnowledge,
  compileKnowledgeArtifacts,
  serializeArtifact,
} from './compiler.js'
import { KnowledgeValidationError } from './errors.js'

async function run() {
  const command = process.argv[2]

  if (command === 'check') {
    const first = await compileKnowledgeArtifacts(DEFAULT_KNOWLEDGE_ROOT)
    const second = await compileKnowledgeArtifacts(DEFAULT_KNOWLEDGE_ROOT)
    const bytes = serializeArtifact(first.artifact)
    const publicBytes = serializeArtifact(first.publicArtifact)

    if (
      bytes !== serializeArtifact(second.artifact)
      || publicBytes !== serializeArtifact(second.publicArtifact)
    ) {
      throw new KnowledgeValidationError(
        'los artefactos no son deterministas entre dos compilaciones consecutivas.',
        { code: 'KNOWLEDGE_NON_DETERMINISTIC' },
      )
    }

    console.log(
      `Knowledge válido: ${first.artifact.documents.length} documentos canónicos, ${first.artifact.collections.length} colecciones, ${Buffer.byteLength(bytes)} bytes deterministas.`,
    )
    console.log(
      `Proyección pública válida: ${first.publicArtifact.documents.length} documentos, ${first.publicArtifact.collections.length} colecciones, ${Buffer.byteLength(publicBytes)} bytes deterministas.`,
    )
    console.log(`Excluidos de forma explícita: ${first.discovery.excluded.length}.`)
    return
  }

  if (command === 'build') {
    const {
      artifact,
      publicArtifact,
      bytes,
      publicBytes,
      outputPath,
      publicOutputPath,
    } = await buildKnowledge(
      DEFAULT_KNOWLEDGE_ROOT,
      DEFAULT_OUTPUT_PATH,
      DEFAULT_PUBLIC_OUTPUT_PATH,
    )

    console.log(
      `Artefacto canónico generado: ${artifact.documents.length} documentos, ${Buffer.byteLength(bytes)} bytes.`,
    )
    console.log(outputPath)
    console.log(
      `Proyección pública generada: ${publicArtifact.documents.length} documentos, ${Buffer.byteLength(publicBytes)} bytes.`,
    )
    console.log(publicOutputPath)
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
