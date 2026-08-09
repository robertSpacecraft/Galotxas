import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Link, MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { RouteAccessibility } from './RouteAccessibility';
import { SeoProvider } from './SeoProvider';

const AccessibleRouter = () => (
  <MemoryRouter initialEntries={['/']}>
    <SeoProvider>
      <RouteAccessibility />
      <main id="main-content" tabIndex="-1">
        <Routes>
          <Route path="/" element={<Link to="/competicion">Ir a Competición</Link>} />
          <Route path="/competicion" element={<h1>Competición</h1>} />
        </Routes>
      </main>
    </SeoProvider>
  </MemoryRouter>
);

describe('RouteAccessibility', () => {
  it('moves focus and announces one safe title after SPA navigation', async () => {
    const user = userEvent.setup();
    render(<AccessibleRouter />);

    const link = screen.getByRole('link', { name: 'Ir a Competición' });
    link.focus();
    await user.click(link);

    await waitFor(() => expect(screen.getByRole('main')).toHaveFocus());
    expect(screen.getAllByText('Competición')).toHaveLength(2);
    expect(document.querySelectorAll('[aria-live="polite"].route-announcer')).toHaveLength(1);
  });
});
