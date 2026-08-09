import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { SchoolPage } from './SchoolPage';
import { useSchoolOverview } from './useSchoolOverview';

vi.mock('./useSchoolOverview', () => ({
  useSchoolOverview: vi.fn(),
}));

const reload = vi.fn();

const publicSchool = {
  name: 'Programa Escuela E2E',
  enrollments_open: true,
  contact: {
    phone: '600 100 200',
    email: 'escuela@example.test',
  },
  default_location: {
    id: 7,
    name: 'Pista habitual',
    locality: 'Monóvar',
    address: 'Calle Mayor, 1',
  },
  levels: [
    {
      id: 4,
      name: 'Iniciación',
      minimum_age: 8,
      maximum_age: 12,
      schedules: [
        {
          id: 9,
          day_of_week: 2,
          starts_at: '18:00',
          ends_at: '19:00',
          location: {
            id: 8,
            name: 'Pista secundaria',
            locality: 'Monóvar',
            address: null,
          },
        },
      ],
    },
  ],
};

const renderPage = () => renderWithProviders(<SchoolPage />, { route: '/escuela' });

describe('SchoolPage', () => {
  beforeEach(() => {
    reload.mockReset();
  });

  it('renders the public aggregate, metadata, Manual access and open form semantically', () => {
    useSchoolOverview.mockReturnValue({
      data: publicSchool,
      status: 'content',
      error: null,
      reload,
    });

    const { container } = renderPage();

    expect(screen.getByRole('heading', { name: 'Escuela de Galotxas', level: 1 }))
      .toBeInTheDocument();
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expect(screen.getByRole('link', { name: 'Consultar el Manual' }))
      .toHaveAttribute('href', '/aprende-a-jugar/manual');
    expect(screen.getByText('Programa Escuela E2E')).toBeInTheDocument();
    expect(screen.getByText('Inscripciones abiertas.')).toHaveAttribute('role', 'status');
    expect(screen.getByRole('heading', { name: 'Ubicación habitual', level: 3 }))
      .toBeInTheDocument();
    expect(screen.getByText('Pista habitual')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Iniciación', level: 3 })).toBeInTheDocument();
    expect(screen.getByText('De 8 a 12 años')).toBeInTheDocument();
    expect(screen.getByText('Martes')).toBeInTheDocument();
    expect(screen.getByText('18:00–19:00')).toBeInTheDocument();
    expect(screen.getByText('Pista secundaria')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: '600 100 200' }))
      .toHaveAttribute('href', 'tel:600 100 200');
    expect(screen.getByRole('link', { name: 'escuela@example.test' }))
      .toHaveAttribute('href', 'mailto:escuela@example.test');
    expect(screen.getByRole('button', { name: 'Enviar solicitud' })).toBeInTheDocument();
    expect(container).not.toHaveTextContent('school_program_id');
    expect(container).not.toHaveTextContent('admin_notes');
    expect(document.title).toBe('Escuela de Galotxas | Club Galotxes Monòver');
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta niveles, horarios, ubicaciones e inscripciones de la Escuela de Galotxas.',
    );
  });

  it('keeps partial data useful without empty contact or location boxes', () => {
    useSchoolOverview.mockReturnValue({
      data: {
        name: null,
        enrollments_open: false,
        contact: { phone: null, email: null },
        default_location: null,
        levels: [
          {
            id: 5,
            name: 'Adultos',
            minimum_age: 18,
            maximum_age: null,
            schedules: [],
          },
        ],
      },
      status: 'content',
      error: null,
      reload,
    });

    renderPage();

    expect(screen.getByText('No se admiten solicitudes de inscripción en este momento.'))
      .toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Adultos', level: 3 })).toBeInTheDocument();
    expect(screen.getByText('Desde 18 años')).toBeInTheDocument();
    expect(screen.getByText('Horario todavía no disponible.')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Contacto' })).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Ubicación habitual' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Enviar solicitud' })).not.toBeInTheDocument();
  });

  it('shows a valid data-null page and keeps the Manual available', () => {
    useSchoolOverview.mockReturnValue({
      data: null,
      status: 'empty',
      error: null,
      reload,
    });

    renderPage();

    expect(screen.getByText('La información de la Escuela no está disponible actualmente.'))
      .toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Consultar el Manual' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Enviar solicitud' })).not.toBeInTheDocument();
    expect(screen.queryByText('Página no encontrada')).not.toBeInTheDocument();
  });

  it('announces loading and retries a safe read error', async () => {
    const user = userEvent.setup();
    useSchoolOverview.mockReturnValue({
      data: null,
      status: 'error',
      error: 'No se ha podido cargar la información de la Escuela.',
      reload,
    });

    renderPage();

    expect(screen.getByRole('alert')).toHaveTextContent(
      'No se ha podido cargar la información de la Escuela.',
    );
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(reload).toHaveBeenCalledOnce();
  });

  it('uses an accessible local loading state', () => {
    useSchoolOverview.mockReturnValue({
      data: null,
      status: 'loading',
      error: null,
      reload,
    });

    renderPage();

    expect(screen.getByRole('status')).toHaveTextContent('Cargando información de la Escuela');
  });
});
