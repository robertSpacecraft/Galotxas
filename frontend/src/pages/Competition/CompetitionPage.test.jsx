import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CompetitionPage } from './CompetitionPage';

describe('CompetitionPage', () => {
  it('provides a minimal accessible hub for the existing competition destinations', () => {
    const getChampionships = vi.spyOn(championshipsService, 'getChampionships');
    const getSeasons = vi.spyOn(championshipsService, 'getSeasons');
    const { container } = renderWithProviders(<CompetitionPage />, { route: '/competicion' });

    expect(screen.getByRole('heading', { name: 'Competición', level: 1 })).toBeInTheDocument();
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expect(screen.getByRole('heading', { name: 'Torneos y rankings', level: 2 }))
      .toHaveAttribute('id', 'competition-destinations-title');
    expect(screen.getByRole('region', { name: 'Torneos y rankings' }))
      .toHaveAttribute('aria-labelledby', 'competition-destinations-title');
    expect(screen.getByRole('navigation', { name: 'Opciones de competición' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Torneos/ })).toHaveAttribute('href', '/torneos');
    expect(screen.getByRole('link', { name: /Rankings/ })).toHaveAttribute('href', '/rankings');
    expect(screen.getByText(/Consulta campeonatos, categorías, calendarios/)).toBeInTheDocument();
    expect(screen.queryByText(/próximamente|en construcción/i)).not.toBeInTheDocument();
    expect(getChampionships).not.toHaveBeenCalled();
    expect(getSeasons).not.toHaveBeenCalled();
    expect(document.title).toBe('Competición | Galotxas');
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta campeonatos, categorías, calendarios, resultados y clasificaciones de Galotxas.',
    );
  });
});
