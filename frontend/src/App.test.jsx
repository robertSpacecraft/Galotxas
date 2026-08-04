import { lazy } from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from './api/championships';
import { cmsService } from './api/cms';
import { contactService } from './features/contact/contactService';
import { schoolService } from './features/school/schoolService';
import App, { ClubRoute, KnowledgeRoute, SchoolRoute } from './App';

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

vi.mock('./features/school/schoolService', () => ({
  schoolService: {
    getOverview: vi.fn(),
    createEnrollment: vi.fn(),
  },
}));

vi.mock('./features/contact/contactService', () => ({
  contactService: {
    getConfig: vi.fn(),
    submit: vi.fn(),
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
    cmsService.getPageBySlug.mockImplementation((slug) => Promise.resolve({
      slug,
      title: slug === 'nosotros' ? 'Nosotros' : `CMS ${slug}`,
      blocks: [],
    }));
    schoolService.getOverview.mockResolvedValue(null);
    contactService.getConfig.mockResolvedValue({ enabled: false });
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

  it('uses the accessible School fallback without a false H1 or 404', () => {
    const PendingPage = lazy(() => new Promise(() => {}));

    render(
      <SchoolRoute>
        <PendingPage />
      </SchoolRoute>,
    );

    expect(screen.getByRole('status')).toHaveTextContent('Cargando Escuela de Galotxas');
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
    expect(screen.queryByText('Página no encontrada')).not.toBeInTheDocument();
  });

  it('uses the accessible Club fallback without a false H1 or 404', () => {
    const PendingPage = lazy(() => new Promise(() => {}));

    render(
      <ClubRoute>
        <PendingPage />
      </ClubRoute>,
    );

    expect(screen.getByRole('status')).toHaveTextContent('Cargando Club');
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

  it.each([
    '/club',
    '/club/quienes-somos/detalle',
    '/club/contacto/otra-ruta',
    '/club/federarse/otra-ruta',
    '/club/documentos/otra-ruta',
  ])(
    'keeps the unregistered Club route %s on the accessible 404',
    async (pathname) => {
      openAppAt(pathname);

      expect(await screen.findByRole('heading', { name: 'Página no encontrada', level: 1 }))
        .toBeInTheDocument();
      expect(window.location.pathname).toBe(pathname);
    },
  );

  it.each([
    ['/club/quienes-somos', 'nosotros', 'Nosotros'],
    ['/club/contacto', 'contacto', 'CMS contacto'],
    ['/club/federarse', 'federarse', 'CMS federarse'],
    ['/club/documentos', 'documentos', 'CMS documentos'],
  ])('registers the exact lazy Club facade %s', async (pathname, slug, heading) => {
    openAppAt(pathname);

    expect(await screen.findByRole('heading', { name: heading, level: 1 })).toBeInTheDocument();
    expect(cmsService.getPageBySlug).toHaveBeenCalledWith(slug);
    expect(window.location.pathname).toBe(pathname);
  });

  it('registers the lazy School landing and preserves data null as a valid page', async () => {
    openAppAt('/escuela');

    expect(await screen.findByRole('heading', { name: 'Escuela de Galotxas', level: 1 }))
      .toBeInTheDocument();
    expect(await screen.findByText('La información de la Escuela no está disponible actualmente.'))
      .toBeInTheDocument();
    expect(screen.queryByText('Página no encontrada')).not.toBeInTheDocument();
    expect(screen.getAllByRole('main')).toHaveLength(1);
    expect(schoolService.getOverview).toHaveBeenCalledOnce();
  });

  it.each(['/escuela/alumno', '/school', '/academy'])(
    'keeps the unapproved School-like route %s on the existing 404',
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
