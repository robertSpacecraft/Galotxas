import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from './api/championships';
import App from './App';

vi.mock('./api/championships', () => ({
  championshipsService: {
    getSeasons: vi.fn(),
    getChampionships: vi.fn(),
  },
}));

describe('App tournament route', () => {
  beforeEach(() => {
    localStorage.clear();
    window.history.replaceState({}, '', '/torneos');
    championshipsService.getSeasons.mockResolvedValue([]);
    championshipsService.getChampionships.mockResolvedValue([]);
  });

  it('renders the functional tournament list without the legacy placeholder', async () => {
    render(<App />);

    expect(await screen.findByRole('heading', { name: 'Torneos', level: 1 })).toBeInTheDocument();
    expect(screen.queryByText(/En construcción/)).not.toBeInTheDocument();
  });
});
