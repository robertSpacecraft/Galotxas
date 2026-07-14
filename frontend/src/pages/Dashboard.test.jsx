import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuth } from '../hooks/useAuth';
import Dashboard from './Dashboard';

vi.mock('../hooks/useAuth', () => ({
  useAuth: vi.fn(),
}));

describe('Dashboard session refresh', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not log again when AuthContext has already handled a failed initial refresh', async () => {
    const refreshUser = vi.fn().mockRejectedValue({ response: { status: 401 } });
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    useAuth.mockReturnValue({
      user: {
        name: 'Player',
        lastname: 'E2E',
        email: 'player@example.test',
        role: 'user',
      },
      createPlayerProfile: vi.fn(),
      refreshUser,
    });

    render(
      <MemoryRouter>
        <Dashboard />
      </MemoryRouter>,
    );

    expect(screen.getByRole('heading', { name: 'Panel de Control', level: 1 })).toBeInTheDocument();
    await waitFor(() => expect(refreshUser).toHaveBeenCalledOnce());
    expect(consoleError).not.toHaveBeenCalled();
  });
});
