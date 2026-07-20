export class KnowledgeValidationError extends Error {
  constructor(message, { code = 'KNOWLEDGE_INVALID', sourcePath = null } = {}) {
    super(sourcePath ? `${sourcePath}: ${message}` : message)
    this.name = 'KnowledgeValidationError'
    this.code = code
    this.sourcePath = sourcePath
  }
}
