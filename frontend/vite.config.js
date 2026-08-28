import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import { configDefaults } from 'vitest/config'
import publicKnowledge from './src/generated/knowledge/public-knowledge.json' with { type: 'json' }
import publicLegal from './src/generated/legal/public-legal.json' with { type: 'json' }
import { createSeoAssets, renderInitialHtmlSeo } from './scripts/seo/assets.js'
import { createPublicSiteConfig } from './src/seo/seoConfig.js'

const frontendRoot = path.dirname(fileURLToPath(import.meta.url))
const artifacts = {
  knowledgeArtifact: publicKnowledge,
  legalArtifact: publicLegal,
}

const publicSeoAssetsPlugin = (config) => {
  const assets = createSeoAssets(config, artifacts)

  return {
    name: 'galotxas-public-seo-assets',
    transformIndexHtml(html) {
      return renderInitialHtmlSeo(html, config)
    },
    configureServer(server) {
      server.middlewares.use((request, response, next) => {
        const pathname = new URL(request.url ?? '/', 'http://vite.local').pathname

        if (pathname === '/robots.txt') {
          response.statusCode = 200
          response.setHeader('Content-Type', 'text/plain; charset=utf-8')
          response.end(assets.robots)
          return
        }

        if (pathname === '/sitemap.xml') {
          if (!assets.sitemap) {
            response.statusCode = 404
            response.setHeader('Content-Type', 'text/plain; charset=utf-8')
            response.end('Sitemap no disponible.\n')
            return
          }

          response.statusCode = 200
          response.setHeader('Content-Type', 'application/xml; charset=utf-8')
          response.end(assets.sitemap)
          return
        }

        next()
      })
    },
    generateBundle() {
      this.emitFile({ type: 'asset', fileName: 'robots.txt', source: assets.robots })

      if (assets.sitemap) {
        this.emitFile({ type: 'asset', fileName: 'sitemap.xml', source: assets.sitemap })
      }
    },
  }
}

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const environment = loadEnv(mode, frontendRoot, '')
  const publicSiteConfig = createPublicSiteConfig({
    ...environment,
    ...(globalThis.process?.env ?? {}),
  })

  return {
    plugins: [react(), publicSeoAssetsPlugin(publicSiteConfig)],
    test: {
      environment: 'jsdom',
      setupFiles: './src/test/setup.js',
      clearMocks: true,
      restoreMocks: true,
      exclude: [...configDefaults.exclude, 'e2e/**'],
    },
  }
})
