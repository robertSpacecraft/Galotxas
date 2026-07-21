import { lazy } from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from './api/championships';
import { cmsService } from './api/cms';
import App, { KnowledgeRoute } from './App';

vi.mock('./api/championships', () => ({
  championshipsService: {
    getSeasons: vi.fn(),
    getChampionships: vi.fn(),
    getAllTimeRanking: vi.fn(),
    getSeasonRanking: vi.fn(),
  },
}));

vi.mock('./api/cms', () => ({
  cmsService: {
    getPublishedPages: vi.fn(),
    getPageBySlug: vi.fn(),
  },
}));

const openAppAt = (pathname) => {
  window.history.replaceState({}, '', pathname);
  return render(<App />);
};

describe('App public routes', () => {
  beforeEach(() => {
    localStorage.clear();
    championshipsService.getSeasons.mockResolvedValue([]);
    championshipsService.getChampionships.mockResolvedValue([]);
    championshipsService.getAllTimeRanking.mockResolvedValue([]);
    championshipsService.getSeasonRanking.mockResolvedValue([]);
    cmsService.getPublishedPages.mockResolvedValue([]);
    cmsService.getPageBySlug.mockResolvedValue({
      title: 'Nosotros',
      blocks: [],
    });
  });

  it('muestra un fallback accesible sin main, H1 ni 404 mientras carga una ruta diferida', () => {
    const PendingPage = lazy(() => new Promise(() => {}));

    render(
      <KnowledgeRoute>
        <PendingPage />
      </KnowledgeRoute>,
    );

    expect(screen.getByRole('status')).toHaveTextContent('Cargando Aprende a jugar');
    expect(screen.queryByRole('main')).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
    expect(screen.queryByText('Página no encontrada')).not.toBeInTheDocument();
  });

  it('renders the functional tournament list without the legacy placeholder', async () => {
    openAppAt('/torneos');

    expect(await screen.findByRole('heading', { name: 'Torneos', level: 1 })).toBeInTheDocument();
    expect(screen.queryByText(/En construcción/)).not.toBeInTheDocument();
  });

  it('registers the competition landing with one application main landmark', async () => {
    openAppAt('/competicion');

    expect(await screen.findByRole('heading', { name: 'Competición', level: 1 })).toBeInTheDocument();
    expect(screen.getAllByRole('main')).toHaveLength(1);
  });

  it('keeps Home inside a single main landmark', async () => {
    openAppAt('/');

    expect(await screen.findByRole('heading', { name: 'La emoción de las Galotxas' }))
      .toBeInTheDocument();
    expect(screen.getAllByRole('main')).toHaveLength(1);
  });

  it('renders the wildcard page without automatically redirecting', async () => {
    openAppAt('/cuenta/ruta-inexistente');

    expect(await screen.findByRole('heading', { name: 'Página no encontrada', level: 1 }))
      .toBeInTheDocument();
    expect(window.location.pathname).toBe('/cuenta/ruta-inexistente');
    expect(screen.getAllByRole('main')).toHaveLength(1);
  });

  it.each(['/escuela', '/club'])(
    'does not publish the future route %s as a placeholder',
    async (pathname) => {
      openAppAt(pathname);

      expect(await screen.findByRole('heading', { name: 'Página no encontrada', level: 1 }))
        .toBeInTheDocument();
      expect(window.location.pathname).toBe(pathname);
    },
  );

  it.each([
    ['/aprende-a-jugar', 'Aprende a jugar'],
    ['/aprende-a-jugar/manual', 'Manual'],
    ['/aprende-a-jugar/manual/reglamento/saque', 'El saque'],
    ['/aprende-a-jugar/manual/conceptos/elementos/pilota', 'Pilota'],
  ])('registers the functional Knowledge route %s', async (pathname, heading) => {
    openAppAt(pathname);

    expect(await screen.findByRole('heading', { name: heading, level: 1 })).toBeInTheDocument();
    expect(screen.getAllByRole('main')).toHaveLength(1);
  });

  it.each([
    '/aprende-a-jugar/manual/reglamento/inexistente',
    '/aprende-a-jugar/manual/conceptos/instalaciones/cancha',
    '/aprende-a-jugar/manual/conceptos/juego/inexistente',
    '/aprende-a-jugar/manual/ruta-mal-formada',
  ])('uses the existing 404 without redirecting for %s', async (pathname) => {
    openAppAt(pathname);

    expect(await screen.findByRole('heading', { name: 'Página no encontrada', level: 1 }))
      .toBeInTheDocument();
    expect(window.location.pathname).toBe(pathname);
  });

  it('does not intercept a valid dynamic CMS route with the wildcard', async () => {
    openAppAt('/contenidos/nosotros');

    expect(await screen.findByRole('heading', { name: 'Nosotros', level: 1 })).toBeInTheDocument();
    expect(cmsService.getPageBySlug).toHaveBeenCalledWith('nosotros');
    expect(screen.queryByRole('heading', { name: 'Página no encontrada' })).not.toBeInTheDocument();
  });

  it.each([
    ['/login', 'Acceso Jugadores'],
    ['/register', 'Registro de Usuario'],
    ['/forgot-password', 'Recuperar Contraseña'],
    ['/rankings', 'Rankings de Galotxas'],
    ['/contenidos', 'Contenidos'],
    ['/nosotros', 'Mucho más que un juego: la tradición viva de Monóvar.'],
  ])('preserves the representative route %s', async (pathname, heading) => {
    openAppAt(pathname);

    expect(await screen.findByRole('heading', { name: heading, level: 1 })).toBeInTheDocument();
  });

  it('preserves the protected player route and its anonymous login outcome', async () => {
    openAppAt('/player');

    expect(await screen.findByRole('heading', { name: 'Acceso Jugadores', level: 1 }))
      .toBeInTheDocument();
    expect(window.location.pathname).toBe('/login');
  });
});
