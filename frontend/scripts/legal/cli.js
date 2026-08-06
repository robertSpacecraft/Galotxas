#!/usr/bin/env node

import {
  DEFAULT_BACKEND_FORM_NOTICE_OUTPUT_PATH,
  DEFAULT_FORM_NOTICE_OUTPUT_PATH,
  DEFAULT_LEGAL_OUTPUT_PATH,
  DEFAULT_LEGAL_ROOT,
} from './config.js'
import {
  assertFormNoticeArtifactCurrent,
  assertLegalArtifactCurrent,
  buildFormNoticeArtifacts,
  buildLegalArtifact,
  compileFormNoticeArtifact,
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
    const noticeFirst = await compileFormNoticeArtifact(DEFAULT_LEGAL_ROOT)
    const noticeSecond = await compileFormNoticeArtifact(DEFAULT_LEGAL_ROOT)
    const noticeBytes = serializeLegalArtifact(noticeFirst.artifact)
    if (noticeBytes !== serializeLegalArtifact(noticeSecond.artifact)) {
      throw new LegalValidationError('la salida de avisos no es determinista.', { code: 'NOTICE_NON_DETERMINISTIC' })
    }
    await assertFormNoticeArtifactCurrent(DEFAULT_LEGAL_ROOT, [
      DEFAULT_FORM_NOTICE_OUTPUT_PATH,
      DEFAULT_BACKEND_FORM_NOTICE_OUTPUT_PATH,
    ])
    console.log(
      `Legal válido: ${first.artifact.documents.length} documentos, ${Buffer.byteLength(firstBytes)} bytes deterministas y artefacto actualizado.`,
    )
    console.log(`Excluidos de forma explícita: ${first.discovery.excluded.length}.`)
    console.log(`Avisos válidos: ${noticeFirst.artifact.notices.length}, ${Buffer.byteLength(noticeBytes)} bytes deterministas.`)
    return
  }

  if (command === 'build') {
    const result = await buildLegalArtifact(DEFAULT_LEGAL_ROOT, DEFAULT_LEGAL_OUTPUT_PATH)
    const noticeResult = await buildFormNoticeArtifacts(DEFAULT_LEGAL_ROOT, [
      DEFAULT_FORM_NOTICE_OUTPUT_PATH,
      DEFAULT_BACKEND_FORM_NOTICE_OUTPUT_PATH,
    ])
    console.log(
      `Proyección legal generada: ${result.artifact.documents.length} documentos, ${Buffer.byteLength(result.bytes)} bytes.`,
    )
    console.log(result.outputPath)
    console.log(
      `Proyección de avisos generada: ${noticeResult.artifact.notices.length} avisos, ${Buffer.byteLength(noticeResult.bytes)} bytes.`,
    )
    noticeResult.outputPaths.forEach((outputPath) => console.log(outputPath))
    return
  }

  throw new LegalValidationError('uso: node scripts/legal/cli.js <check|build>', { code: 'CLI_USAGE' })
}

run().catch((error) => {
  const code = error instanceof LegalValidationError ? error.code : 'LEGAL_ERROR'
  console.error(`[${code}] ${error.message}`)
  process.exitCode = 1
})
