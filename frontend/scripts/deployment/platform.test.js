import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const vercel = JSON.parse(fs.readFileSync(path.join(frontendRoot, 'vercel.json'), 'utf8'));

describe('contrato Vercel', () => {
  it('compila el frontend con preflight, Node declarado y salida dist', () => {
    const packageJson = JSON.parse(
      fs.readFileSync(path.join(frontendRoot, 'package.json'), 'utf8'),
    );

    expect(vercel.framework).toBe('vite');
    expect(vercel.installCommand).toBe('npm ci');
    expect(vercel.buildCommand).toBe('npm run deploy:build');
    expect(vercel.outputDirectory).toBe('dist');
    expect(packageJson.engines.node).toBe('22.x');
  });

  it('mantiene deep links SPA sin capturar recursos con extensión', () => {
    const spaFallback = vercel.rewrites.find(({ destination }) => destination === '/index.html');

    expect(spaFallback).toEqual({
      source: '/((?!.*\\.[^/]+$).*)',
      destination: '/index.html',
    });

    const spaFallbackPattern = new RegExp(`^${spaFallback.source}$`);

    for (const pathname of [
      '/',
      '/noticias',
      '/noticias/cronica-de-la-final',
      '/categories/2',
      '/contenidos/nosotros',
      '/aprende-a-jugar/manual/reglamento/reglamento',
      '/ruta-react-inexistente',
    ]) {
      expect(spaFallbackPattern.test(pathname), pathname).toBe(true);
    }

    for (const pathname of [
      '/sitemap.xml',
      '/robots.txt',
      '/nonexistent.xml',
      '/favicon.ico',
      '/assets/recurso-inexistente.js',
      '/imagen.png',
    ]) {
      expect(spaFallbackPattern.test(pathname), pathname).toBe(false);
    }
  });

  it('redirige www al apex permanentemente', () => {
    expect(vercel.redirects).toContainEqual(expect.objectContaining({
      destination: 'https://galotxesmonover.es/:path*',
      permanent: true,
    }));
    expect(vercel.redirects[0].has).toContainEqual({
      type: 'host',
      value: 'www.galotxesmonover.es',
    });
  });

  it('declara cabeceras mínimas sin activar HSTS prematuramente', () => {
    const headers = Object.fromEntries(
      vercel.headers.flatMap((entry) => entry.headers.map((header) => [header.key, header.value])),
    );

    expect(headers['X-Content-Type-Options']).toBe('nosniff');
    expect(headers['X-Frame-Options']).toBe('SAMEORIGIN');
    expect(headers['Referrer-Policy']).toBe('strict-origin-when-cross-origin');
    expect(headers['Permissions-Policy']).toContain('camera=()');
    expect(headers['Strict-Transport-Security']).toBeUndefined();
  });
});
