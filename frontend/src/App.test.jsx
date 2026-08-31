import { lazy } from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from './api/championships';
import { cmsService } from './api/cms';
import { contactService } from './features/contact/contactService';
import { newsService } from './features/news/newsService';
import { schoolService } from './features/school/schoolService';
import { sponsorService } from './features/sponsors/sponsorService';
import App, {
  ClubRoute,
  CupRoute,
  KnowledgeRoute,
  LegalRoute,
  NewsRoute,
  SchoolRoute,
} from './App';

vi.mock('./api/championships', () => ({
  championshipsService: {
    getSeasons: vi.fn(),
    getChampionships: vi.fn(),
    getAllTimeRanking: vi.fn(),
    getSeasonRanking: vi.fn(),
    getCategory: vi.fn(),
    getCategorySchedule: vi.fn(),
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

vi.mock('./features/news/newsService', () => ({
  newsService: {
    getList: vi.fn(),
    getBySlug: vi.fn(),
  },
}));

vi.mock('./features/sponsors/sponsorService', () => ({
  sponsorService: {
    getAll: vi.fn(),
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
    championshipsService.getCategory.mockResolvedValue({
      id: 12,
      name: 'Categoría de prueba',
      championship: { name: 'Campeonato de prueba', season: { name: 'Temporada de prueba' } },
    });
    championshipsService.getCategorySchedule.mockResolvedValue([]);
    cmsService.getPublishedPages.mockResolvedValue([]);
    cmsService.getPageBySlug.mockImplementation((slug) => Promise.resolve({
      slug,
      title: slug === 'nosotros' ? 'Nosotros' : `CMS ${slug}`,
      blocks: [],
    }));
    schoolService.getOverview.mockResolvedValue(null);
    contactService.getConfig.mockResolvedValue({ enabled: false });
    newsService.getList.mockResolvedValue({
      articles: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 12,
        total: 0,
        has_more: false,
      },
    });
    newsService.getBySlug.mockResolvedValue({
      slug: 'cronica-final',
      title: 'Crónica de la final',
      excerpt: 'Resumen manual.',
      body: 'Cuerpo de la noticia.',
      published_at: '2026-08-21T10:00:00+00:00',
      seo_title: null,
      seo_description: null,
      image: {
        url: 'https://api.example.test/api/v1/news/cronica-final/image',
        width: 1600,
        height: 900,
        alt: 'Pelota sobre una pista vacía.',
        credit: null,
      },
    });
    sponsorService.getAll.mockResolvedValue([]);
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

  it('uses the accessible Legal fallback without a false H1 or 404', () => {
    const PendingPage = lazy(() => new Promise(() => {}));

    render(
      <LegalRoute>
        <PendingPage />
      </LegalRoute>,
    );

    expect(screen.getByRole('status')).toHaveTextContent('Cargando información legal');
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
    expect(screen.queryByText('Página no encontrada')).not.toBeInTheDocument();
  });

  it('uses the accessible News fallback without a false H1 or 404', () => {
    const PendingPage = lazy(() => new Promise(() => {}));

    render(
      <NewsRoute>
        <PendingPage />
      </NewsRoute>,
    );

    expect(screen.getByRole('status')).toHaveTextContent('Cargando Noticias');
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
    expect(screen.queryByText('Página no encontrada')).not.toBeInTheDocument();
  });

  it('uses the accessible Cup fallback without a false H1 or 404', () => {
    const PendingPage = lazy(() => new Promise(() => {}));

    render(
      <CupRoute>
        <PendingPage />
      </CupRoute>,
    );

    expect(screen.getByRole('status')).toHaveTextContent('Cargando Copa');
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
    expect(screen.queryByText('Página no encontrada')).not.toBeInTheDocument();
  });

  it('registers the lazy category Cup route with its neutral empty state', async () => {
    openAppAt('/categories/12/cup');

    expect(await screen.findByRole('heading', { name: 'Copa de Categoría de prueba', level: 1 }))
      .toBeInTheDocument();
    expect(screen.getByText('Todavía no hay una Copa generada para esta categoría.'))
      .toBeInTheDocument();
    expect(championshipsService.getCategory).toHaveBeenCalledWith('12');
    expect(championshipsService.getCategorySchedule).toHaveBeenCalledWith('12');
  });

  it('renders the functional tournament list without the legacy placeholder', async () => {
    openAppAt('/torneos');

    expect(await screen.findByRole('heading', { name: 'Campeonatos', level: 1 })).toBeInTheDocument();
    expect(screen.queryByText(/En construcción/)).not.toBeInTheDocument();
  });

  it('registers the competition landing with one application main landmark', async () => {
    openAppAt('/competicion');

    expect(await screen.findByRole('heading', { name: 'Competición', level: 1 })).toBeInTheDocument();
    expect(screen.getAllByRole('main')).toHaveLength(1);
  });

  it('registers the lazy News index route', async () => {
    openAppAt('/noticias');

    expect(await screen.findByRole('heading', { name: 'Noticias', level: 1 })).toBeInTheDocument();
    expect(await screen.findByText('No hay noticias publicadas en este momento.'))
      .toBeInTheDocument();
    expect(newsService.getList).toHaveBeenCalledOnce();
  });

  it('registers the lazy News detail route', async () => {
    openAppAt('/noticias/cronica-final');

    expect(await screen.findByRole('heading', { name: 'Crónica de la final', level: 1 }))
      .toBeInTheDocument();
    expect(newsService.getBySlug).toHaveBeenCalledWith(
      'cronica-final',
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
  });

  it('keeps Home inside a single main landmark', async () => {
    openAppAt('/');

    expect(await screen.findByRole('heading', { name: 'Galotxes en Monóvar' }))
      .toBeInTheDocument();
    expect(screen.getAllByRole('main')).toHaveLength(1);
    expect(screen.getByRole('main')).toHaveAttribute('id', 'main-content');
    expect(screen.getByRole('contentinfo')).toHaveTextContent('Club Galotxes Monòver');
  });

  it('renders collaborators between public content and footer without altering Home', async () => {
    sponsorService.getAll.mockResolvedValue([{
      id: 1,
      name: 'Empresa colaboradora',
      logo: {
        url: 'https://api.example.test/api/v1/sponsors/1/logo',
        width: 600,
        height: 300,
      },
      website_url: null,
    }]);

    const { container } = openAppAt('/');

    expect(await screen.findByRole('heading', { name: 'Colaboradores', level: 2 }))
      .toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Galotxes en Monóvar', level: 1 }))
      .toBeInTheDocument();
    const sponsorSection = screen.getByRole('heading', { name: 'Colaboradores' }).closest('section');
    expect(sponsorSection.nextElementSibling).toBe(screen.getByRole('contentinfo'));
    expect(container.scrollWidth).toBeLessThanOrEqual(container.clientWidth);
  });

  it.each(['/player', '/login', '/public-identity/confirm', '/ruta-inexistente'])(
    'does not request or render collaborators on excluded route %s',
    async (pathname) => {
      openAppAt(pathname);

      if (pathname === '/ruta-inexistente') {
        expect(await screen.findByRole('heading', { name: 'Página no encontrada' }))
          .toBeInTheDocument();
      }

      expect(sponsorService.getAll).not.toHaveBeenCalled();
      expect(screen.queryByRole('heading', { name: 'Colaboradores' })).not.toBeInTheDocument();
    },
  );

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

  it('isolates the public identity decision route from global navigation and footer', async () => {
    openAppAt('/public-identity/confirm');

    expect(await screen.findByRole('heading', { name: 'Identidad pública en competición' }))
      .toBeInTheDocument();
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
    expect(screen.queryByRole('contentinfo')).not.toBeInTheDocument();
    expect(screen.getAllByRole('main')).toHaveLength(1);
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

  it.each([
    ['/legal/aviso-legal', 'Aviso legal'],
    ['/legal/privacidad', 'Política de privacidad'],
    ['/legal/cookies', 'Política de cookies y almacenamiento local'],
  ])('registers the exact lazy legal route %s', async (pathname, heading) => {
    openAppAt(pathname);

    expect(await screen.findByRole('heading', { name: heading, level: 1 })).toBeInTheDocument();
    expect(screen.getAllByRole('main')).toHaveLength(1);
  });

  it.each(['/legal', '/legal/desconocido', '/legal/privacidad/otra-ruta'])(
    'keeps the unregistered legal route %s on the accessible 404',
    async (pathname) => {
      openAppAt(pathname);
      expect(await screen.findByRole('heading', { name: 'Página no encontrada', level: 1 }))
        .toBeInTheDocument();
      expect(window.location.pathname).toBe(pathname);
    },
  );

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
