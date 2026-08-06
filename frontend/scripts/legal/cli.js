#!/usr/bin/env node

import {
  DEFAULT_LEGAL_OUTPUT_PATH,
  DEFAULT_LEGAL_ROOT,
} from './config.js'
import {
  assertLegalArtifactCurrent,
  buildLegalArtifact,
  compileLegalArtifact,
  serializeLegalArtifact,
} from './compiler.js'
import { LegalValidationError } from './errors.js'

const run = async () => {
  const command = process.argv[2]

  if (command === 'check') {
    const first = await compileLegalArtifact(DEFAULT_LEGAL_ROOT)
    const second = await compileLegalArtifact(DEFAULT_LEGAL_ROOT)
    const firstBytes = serializeLegalArtifact(first.artifact)
    if (firstBytes !== serializeLegalArtifact(second.artifact)) {
      throw new LegalValidationError('la salida no es determinista.', { code: 'LEGAL_NON_DETERMINISTIC' })
    }
    await assertLegalArtifactCurrent(DEFAULT_LEGAL_ROOT, DEFAULT_LEGAL_OUTPUT_PATH)
    console.log(
      `Legal válido: ${first.artifact.documents.length} documentos, ${Buffer.byteLength(firstBytes)} bytes deterministas y artefacto actualizado.`,
    )
    console.log(`Excluidos de forma explícita: ${first.discovery.excluded.length}.`)
    return
  }

  if (command === 'build') {
    const result = await buildLegalArtifact(DEFAULT_LEGAL_ROOT, DEFAULT_LEGAL_OUTPUT_PATH)
    console.log(
      `Proyección legal generada: ${result.artifact.documents.length} documentos, ${Buffer.byteLength(result.bytes)} bytes.`,
    )
    console.log(result.outputPath)
    return
  }

  throw new LegalValidationError('uso: node scripts/legal/cli.js <check|build>', { code: 'CLI_USAGE' })
}

run().catch((error) => {
  const code = error instanceof LegalValidationError ? error.code : 'LEGAL_ERROR'
  console.error(`[${code}] ${error.message}`)
  process.exitCode = 1
})
