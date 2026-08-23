import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CategoryNavigation } from './CategoryNavigation';

describe('CategoryNavigation', () => {
  it('keeps the four category views in their approved order and marks only Copa as current', () => {
    renderWithProviders(<CategoryNavigation categoryId="copa/12" currentView="cup" />);

    const links = screen.getAllByRole('link');

    expect(links.map((link) => link.textContent)).toEqual([
      'Resumen',
      'Clasificación',
      'Calendario y resultados',
      'Copa',
    ]);
    expect(links.map((link) => link.getAttribute('href'))).toEqual([
      '/categories/copa%2F12',
      '/categories/copa%2F12/standings',
      '/categories/copa%2F12/schedule',
      '/categories/copa%2F12/cup',
    ]);
    expect(screen.getByRole('link', { name: 'Copa' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getAllByRole('link').filter((link) => link.hasAttribute('aria-current')))
      .toHaveLength(1);
  });

  it('preserves the active state of an existing category view', () => {
    renderWithProviders(<CategoryNavigation categoryId={12} currentView="schedule" />);

    expect(screen.getByRole('link', { name: 'Calendario y resultados' }))
      .toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Copa' })).not.toHaveAttribute('aria-current');
  });
});
