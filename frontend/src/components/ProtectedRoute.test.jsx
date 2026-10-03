import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../test/renderWithProviders';
import ProtectedRoute from './ProtectedRoute';

const renderRoute = (authValue, props = {}) => renderWithProviders(
  <Routes>
    <Route path="/login" element={<p>Pantalla de acceso</p>} />
    <Route path="/dashboard" element={<p>Panel de usuario</p>} />
    <Route path="/area" element={<ProtectedRoute {...props}><p>Contenido protegido</p></ProtectedRoute>} />
  </Routes>,
  { route: '/area', authValue },
);

describe('ProtectedRoute', () => {
  it('waits without redirecting while the session is being restored', () => {
    renderRoute({ authStatus: 'restoring', isAuthenticated: false });

    expect(screen.getByRole('status')).toHaveTextContent('Comprobando tu sesión');
    expect(screen.queryByText('Pantalla de acceso')).not.toBeInTheDocument();
    expect(screen.queryByText('Contenido protegido')).not.toBeInTheDocument();
  });

  it('redirects to login once anonymity is confirmed', () => {
    renderRoute({ authStatus: 'anonymous', isAuthenticated: false });

    expect(screen.getByText('Pantalla de acceso')).toBeInTheDocument();
  });

  it('renders protected content for an authenticated user', () => {
    renderRoute({ authStatus: 'authenticated', isAuthenticated: true });

    expect(screen.getByText('Contenido protegido')).toBeInTheDocument();
  });

  it('keeps the non-admin redirect', () => {
    renderRoute({ authStatus: 'authenticated', isAuthenticated: true, isAdmin: false }, { requireAdmin: true });

    expect(screen.getByText('Panel de usuario')).toBeInTheDocument();
  });

  it('offers a retry instead of redirecting when the restore failed', async () => {
    const retrySessionRestore = vi.fn();
    renderRoute({ authStatus: 'failed', isAuthenticated: false, retrySessionRestore });

    expect(screen.getByRole('alert')).toHaveTextContent('No se ha podido comprobar tu sesión.');
    expect(screen.queryByText('Pantalla de acceso')).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(retrySessionRestore).toHaveBeenCalledOnce();
  });
});
