import { fireEvent, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import NewsIndexPage from './NewsIndexPage';
import { useNewsList } from './useNewsList';

vi.mock('./useNewsList', () => ({
  useNewsList: vi.fn(),
}));

const newsArticle = (slug, title) => ({
  slug,
  title,
  excerpt: `Resumen de ${title}.`,
  published_at: '2026-08-21T10:00:00+00:00',
  image: {
    url: `https://api.example.test/api/v1/news/${slug}/image`,
    width: 1600,
    height: 900,
    alt: `Imagen de ${title}.`,
    credit: null,
  },
});

const contentState = (overrides = {}) => ({
  articles: [
    newsArticle('segunda', 'Segunda noticia'),
    newsArticle('primera', 'Primera noticia'),
  ],
  meta: { current_page: 1, has_more: true },
  status: 'content',
  loadMoreStatus: 'idle',
  error: null,
  loadMoreError: null,
  reload: vi.fn(),
  loadMore: vi.fn(),
  ...overrides,
});

describe('NewsIndexPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders the latest item as the eager featured article and the rest as lazy cards', () => {
    useNewsList.mockReturnValue(contentState());
    const { container } = renderWithProviders(<NewsIndexPage />, { route: '/noticias' });

    expect(screen.getByRole('heading', { name: 'Noticias', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('Última noticia').closest('article'))
      .toHaveTextContent('Segunda noticia');
    expect(screen.getByRole('link', { name: 'Leer noticia: Segunda noticia' }))
      .toHaveAttribute('href', '/noticias/segunda');
    const images = screen.getAllByRole('img');
    expect(images[0]).toHaveAttribute('loading', 'eager');
    expect(images[1]).toHaveAttribute('loading', 'lazy');
    expect(container.scrollWidth).toBeLessThanOrEqual(container.clientWidth);
  });

  it('loads more and preserves a local recoverable error', async () => {
    const user = userEvent.setup();
    const loadMore = vi.fn();
    useNewsList.mockReturnValue(contentState({
      loadMore,
      loadMoreError: 'No se han podido cargar más noticias.',
    }));
    renderWithProviders(<NewsIndexPage />);

    expect(screen.getAllByRole('article')).toHaveLength(2);
    await user.click(screen.getByRole('button', { name: 'Reintentar cargar más' }));
    await user.click(screen.getByRole('button', { name: 'Cargar más' }));
    expect(loadMore).toHaveBeenCalledTimes(2);
  });

  it.each([
    ['loading', 'Cargando noticias…'],
    ['empty', 'No hay noticias publicadas en este momento.'],
    ['error', 'No se han podido cargar las noticias.'],
    ['invalid', 'La respuesta de Noticias no tiene un formato válido.'],
  ])('renders the %s state', (status, message) => {
    useNewsList.mockReturnValue(contentState({
      articles: [],
      meta: null,
      status,
      error: message,
    }));
    renderWithProviders(<NewsIndexPage />);

    expect(screen.getByText(message)).toBeInTheDocument();
  });

  it('replaces a failed image with a neutral accessible fallback', () => {
    useNewsList.mockReturnValue(contentState({
      articles: [newsArticle('segunda', 'Segunda noticia')],
      meta: { current_page: 1, has_more: false },
    }));
    renderWithProviders(<NewsIndexPage />);

    fireEvent.error(screen.getByRole('img', { name: 'Imagen de Segunda noticia.' }));
    expect(screen.getByRole('img', { name: /Imagen de Segunda noticia.*no disponible/i }))
      .toBeInTheDocument();
  });
});
