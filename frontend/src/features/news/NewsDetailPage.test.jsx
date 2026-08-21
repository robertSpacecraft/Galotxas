import { fireEvent, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import NewsDetailPage from './NewsDetailPage';
import { useNewsArticle } from './useNewsArticle';

vi.mock('./useNewsArticle', () => ({
  useNewsArticle: vi.fn(),
}));

const article = {
  slug: 'cronica-final',
  title: 'Crónica de la final',
  excerpt: 'Resumen de la jornada.',
  body: 'Primer párrafo con <script>alert(1)</script>.\n\nSegundo párrafo.',
  published_at: '2026-08-21T10:00:00+00:00',
  seo_title: null,
  seo_description: null,
  image: {
    url: 'https://api.example.test/api/v1/news/cronica-final/image',
    width: 1600,
    height: 900,
    alt: 'Pelota sobre una pista vacía.',
    credit: 'Club Galotxes Monòver',
  },
};

describe('NewsDetailPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders semantic detail, escaped paragraphs, date, credit and contextual links', () => {
    useNewsArticle.mockReturnValue({ article, status: 'content', error: null, reload: vi.fn() });
    const { container } = renderWithProviders(<NewsDetailPage />, {
      route: '/noticias/cronica-final',
      routePath: '/noticias/:slug',
    });

    expect(screen.getByRole('article')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Crónica de la final', level: 1 }))
      .toBeInTheDocument();
    expect(screen.getByText('Primer párrafo con <script>alert(1)</script>.'))
      .toBeInTheDocument();
    expect(container.querySelector('script')).not.toBeInTheDocument();
    expect(screen.getByText('Segundo párrafo.')).toBeInTheDocument();
    expect(screen.getByText('Club Galotxes Monòver')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Volver a Noticias' }))
      .toHaveAttribute('href', '/noticias');
    expect(screen.getByRole('time')).toHaveAttribute('datetime', article.published_at);
  });

  it('uses the existing 404 for a missing public article', () => {
    useNewsArticle.mockReturnValue({
      article: null,
      status: 'not-found',
      error: null,
      reload: vi.fn(),
    });
    renderWithProviders(<NewsDetailPage />, {
      route: '/noticias/inexistente',
      routePath: '/noticias/:slug',
    });

    expect(screen.getByRole('heading', { name: 'Página no encontrada' })).toBeInTheDocument();
  });

  it.each([
    ['loading', null, 'Cargando noticia…'],
    ['error', 'No se ha podido cargar la noticia.', 'No se ha podido cargar la noticia.'],
    ['invalid', 'La respuesta de la noticia no tiene un formato válido.', 'La respuesta de la noticia no tiene un formato válido.'],
  ])('renders the %s remote state', (status, error, message) => {
    useNewsArticle.mockReturnValue({ article: null, status, error, reload: vi.fn() });
    renderWithProviders(<NewsDetailPage />, {
      route: '/noticias/cronica-final',
      routePath: '/noticias/:slug',
    });

    expect(screen.getByText(message)).toBeInTheDocument();
  });

  it('offers retry and a neutral image fallback', async () => {
    const user = userEvent.setup();
    const reload = vi.fn();
    useNewsArticle.mockReturnValue({
      article: null,
      status: 'error',
      error: 'No se ha podido cargar la noticia.',
      reload,
    });
    const { unmount } = renderWithProviders(<NewsDetailPage />, {
      route: '/noticias/cronica-final',
      routePath: '/noticias/:slug',
    });
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(reload).toHaveBeenCalledOnce();

    unmount();
    useNewsArticle.mockReturnValue({ article, status: 'content', error: null, reload });
    renderWithProviders(<NewsDetailPage />, {
      route: '/noticias/cronica-final',
      routePath: '/noticias/:slug',
    });
    fireEvent.error(screen.getByRole('img', { name: article.image.alt }));
    expect(screen.getByRole('img', { name: /Pelota sobre una pista vacía.*no disponible/i }))
      .toBeInTheDocument();
  });
});
