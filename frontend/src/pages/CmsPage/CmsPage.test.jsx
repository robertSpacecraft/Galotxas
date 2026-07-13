import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { cmsService } from '../../api/cms';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CmsPage } from './CmsPage';

vi.mock('../../api/cms', () => ({
  cmsService: {
    getPageBySlug: vi.fn(),
  },
}));

const renderCmsPage = () => renderWithProviders(<CmsPage />, {
  route: '/contenidos/nosotros',
  routePath: '/contenidos/:slug',
});

describe('CmsPage', () => {
  beforeEach(() => {
    cmsService.getPageBySlug.mockReset();
  });

  it('renders the loading state while the request is pending', () => {
    cmsService.getPageBySlug.mockReturnValue(new Promise(() => {}));

    renderCmsPage();

    expect(screen.getByText('Cargando contenido...')).toBeInTheDocument();
  });

  it('renders a controlled error state', async () => {
    cmsService.getPageBySlug.mockRejectedValue(new Error('Network error'));

    renderCmsPage();

    expect(await screen.findByRole('heading', { name: 'Error de carga' })).toBeInTheDocument();
    expect(screen.getByText('No se ha podido cargar la página.')).toBeInTheDocument();
  });

  it('renders the not-found state for a missing page', async () => {
    cmsService.getPageBySlug.mockRejectedValue({ status: 404 });

    renderCmsPage();

    expect(await screen.findByRole('heading', { name: 'Página no encontrada' })).toBeInTheDocument();
    expect(screen.getByText('El contenido solicitado no está disponible.')).toBeInTheDocument();
  });

  it('renders valid blocks and ignores an unknown block type', async () => {
    cmsService.getPageBySlug.mockResolvedValue({
      title: 'Nosotros',
      seo_title: 'Sobre Galotxas',
      seo_description: 'Información institucional.',
      blocks: [
        { type: 'heading', order: 1, data: { text: 'Bienvenida', level: 2 } },
        { type: 'text', order: 2, data: { text: 'Contenido público válido.' } },
        { type: 'unknown', order: 3, data: { text: 'No debe mostrarse' } },
      ],
    });

    renderCmsPage();

    expect(await screen.findByRole('heading', { name: 'Nosotros', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Bienvenida', level: 2 })).toBeInTheDocument();
    expect(screen.getByText('Contenido público válido.')).toBeInTheDocument();
    expect(screen.queryByText('No debe mostrarse')).not.toBeInTheDocument();
    expect(document.title).toBe('Sobre Galotxas | Galotxas');
  });
});
