import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { NotFoundPage } from './NotFoundPage';

describe('NotFoundPage', () => {
  it('offers accessible recovery links without redirecting', () => {
    const { container } = renderWithProviders(<NotFoundPage />, { route: '/ruta-inexistente' });

    expect(screen.getByRole('heading', { name: 'Página no encontrada', level: 1 })).toBeInTheDocument();
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expect(screen.getByRole('link', { name: 'Volver a Inicio' })).toHaveAttribute('href', '/');
    expect(screen.getByRole('link', { name: 'Ir a Competición' })).toHaveAttribute('href', '/competicion');
  });
});
