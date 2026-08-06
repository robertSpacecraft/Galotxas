export class LegalValidationError extends Error {
  constructor(message, { code = 'LEGAL_INVALID', sourcePath = null } = {}) {
    super(sourcePath ? `${sourcePath}: ${message}` : message)
    this.name = 'LegalValidationError'
    this.code = code
    this.sourcePath = sourcePath
  }
}
