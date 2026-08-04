import { MemoryRouter } from 'react-router-dom';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { cmsService } from '../../api/cms';
import { contactService } from '../contact/contactService';
import { ClubPage } from './ClubPage';

vi.mock('../../api/cms', () => ({
  cmsService: {
    getPageBySlug: vi.fn(),
  },
}));

vi.mock('../contact/contactService', () => ({
  contactService: {
    getConfig: vi.fn(),
    submit: vi.fn(),
  },
}));

const page = (overrides = {}) => ({
  slug: 'nosotros',
  title: 'Club de prueba',
  seo_title: 'Club técnico',
  seo_description: 'Descripción técnica desde CMS.',
  blocks: [
    { type: 'heading', order: 10, data: { text: 'Bloque CMS', level: 2 } },
    { type: 'text', order: 20, data: { text: 'Contenido exclusivo del CMS.' } },
  ],
  ...overrides,
});

const renderPage = (pageId = 'about') => render(
  <MemoryRouter>
    <ClubPage pageId={pageId} />
  </MemoryRouter>,
);

describe('ClubPage', () => {
  beforeEach(() => {
    cmsService.getPageBySlug.mockReset();
    contactService.getConfig.mockReset();
    contactService.submit.mockReset();
    contactService.getConfig.mockResolvedValue({ enabled: false });
  });

  it('announces loading and requests only the configured slug', () => {
    cmsService.getPageBySlug.mockReturnValue(new Promise(() => {}));

    renderPage();

    expect(screen.getByRole('status')).toHaveTextContent('Cargando contenido');
    expect(cmsService.getPageBySlug).toHaveBeenCalledWith('nosotros');
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
  });

  it('renders CMS blocks and metadata without duplicating editorial content', async () => {
    cmsService.getPageBySlug.mockResolvedValue(page());

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Club de prueba', level: 1 }))
      .toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Bloque CMS', level: 2 })).toBeInTheDocument();
    expect(screen.getByText('Contenido exclusivo del CMS.')).toBeInTheDocument();
    expect(document.title).toBe('Club técnico | Galotxas');
    expect(document.head.querySelector('meta[name="description"]'))
      .toHaveAttribute('content', 'Descripción técnica desde CMS.');
  });

  it('uses the closed route metadata only as a fallback', async () => {
    cmsService.getPageBySlug.mockResolvedValue(page({ seo_title: null, seo_description: null }));

    renderPage();

    await screen.findByRole('heading', { name: 'Club de prueba', level: 1 });
    expect(document.title).toBe('Club de prueba | Galotxas');
    expect(document.head.querySelector('meta[name="description"]'))
      .toHaveAttribute('content', 'Información institucional del Club Galotxes Monòver.');
  });

  it('uses the accessible application 404 for a missing or unpublished CMS page', async () => {
    cmsService.getPageBySlug.mockRejectedValue(Object.assign(new Error('Not found'), { status: 404 }));

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Página no encontrada', level: 1 }))
      .toBeInTheDocument();
    expect(document.head.querySelector('meta[name="robots"]')).toHaveAttribute('content', 'noindex');
  });

  it('offers a retry after a recoverable error', async () => {
    const user = userEvent.setup();
    cmsService.getPageBySlug
      .mockRejectedValueOnce(new Error('Network'))
      .mockResolvedValueOnce(page());

    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido cargar');
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByRole('heading', { name: 'Club de prueba', level: 1 }))
      .toBeInTheDocument();
    expect(cmsService.getPageBySlug).toHaveBeenCalledTimes(2);
  });

  it.each([
    [{ slug: 'contacto' }, 'a page for another slug'],
    [{ title: '' }, 'a response without a title'],
  ])('rejects %s as an invalid response instead of showing foreign data', async (override) => {
    cmsService.getPageBySlug.mockResolvedValue(page(override));

    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido cargar');
    expect(screen.queryByText('Contenido exclusivo del CMS.')).not.toBeInTheDocument();
  });

  it('handles a partial response with no block list as an empty CMS page', async () => {
    cmsService.getPageBySlug.mockResolvedValue(page({ blocks: undefined }));

    renderPage();

    expect(await screen.findByText('Esta página no tiene contenido publicado.'))
      .toBeInTheDocument();
  });

  it('keeps Contact CMS content visible when the form is disabled', async () => {
    cmsService.getPageBySlug.mockResolvedValue(page({ slug: 'contacto', title: 'Contacto' }));
    contactService.getConfig.mockResolvedValue({ enabled: false });

    renderPage('contact');

    expect(await screen.findByText('Contenido exclusivo del CMS.')).toBeInTheDocument();
    expect(await screen.findByText(/El formulario no está disponible actualmente/))
      .toBeInTheDocument();
    expect(screen.queryByLabelText(/^Nombre/)).not.toBeInTheDocument();
  });

  it('keeps Contact CMS content visible and retries an independent config failure', async () => {
    const user = userEvent.setup();
    cmsService.getPageBySlug.mockResolvedValue(page({ slug: 'contacto', title: 'Contacto' }));
    contactService.getConfig
      .mockRejectedValueOnce(new Error('Network'))
      .mockResolvedValueOnce({ enabled: false });

    renderPage('contact');

    expect(await screen.findByText('Contenido exclusivo del CMS.')).toBeInTheDocument();
    expect(await screen.findByText(/No se ha podido comprobar/)).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(await screen.findByText(/El formulario no está disponible actualmente/))
      .toBeInTheDocument();
    expect(contactService.getConfig).toHaveBeenCalledTimes(2);
  });

  it('renders the contact fields only for an exact enabled boolean', async () => {
    cmsService.getPageBySlug.mockResolvedValue(page({ slug: 'contacto', title: 'Contacto' }));
    contactService.getConfig.mockResolvedValue({ enabled: true });

    renderPage('contact');

    expect(await screen.findByLabelText(/^Nombre/)).toBeInTheDocument();
    expect(screen.getByLabelText(/^Correo electrónico/)).toBeInTheDocument();
    expect(screen.getByLabelText(/^Asunto/)).toBeInTheDocument();
    expect(screen.getByLabelText(/^Mensaje/)).toBeInTheDocument();
    expect(screen.getByRole('checkbox')).toBeInTheDocument();
    expect(screen.queryByLabelText(/teléfono/i)).not.toBeInTheDocument();
  });

  it('does not break when an unknown CMS block is returned', async () => {
    cmsService.getPageBySlug.mockResolvedValue(page({
      blocks: [{ type: 'future_block', order: 10, data: { text: 'No renderizar' } }],
    }));

    renderPage();

    await waitFor(() => expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Club'));
    expect(screen.queryByText('No renderizar')).not.toBeInTheDocument();
  });
});
