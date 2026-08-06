import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PublicIdentityConfirmationPage } from './PublicIdentityConfirmationPage';
import { publicIdentityService } from './publicIdentityService';

vi.mock('./publicIdentityService', () => ({
  publicIdentityService: {
    lookup: vi.fn(),
    confirm: vi.fn(),
    deny: vi.fn(),
  },
}));

const validToken = 'a'.repeat(64);

describe('PublicIdentityConfirmationPage', () => {
  beforeEach(() => {
    publicIdentityService.lookup.mockReset();
    publicIdentityService.confirm.mockReset();
    publicIdentityService.deny.mockReset();
    localStorage.clear();
    sessionStorage.clear();
    window.history.replaceState(null, '', `/public-identity/confirm#token=${validToken}`);
  });

  it('loads only safe context, removes the fragment and confirms once', async () => {
    const user = userEvent.setup();
    const logSpies = ['log', 'warn', 'error'].map((method) => (
      vi.spyOn(console, method).mockImplementation(() => {})
    ));
    publicIdentityService.lookup.mockImplementation(() => {
      expect(window.location.hash).toBe('');

      return Promise.resolve({
        mode: 'alias',
        scope: 'public_competition_identity',
        notice_version: '1.0.0',
        expires_at: '2026-08-08T10:00:00+02:00',
      });
    });
    publicIdentityService.confirm.mockResolvedValue({
      message: 'Registrada',
      data: { received: true },
    });

    render(<PublicIdentityConfirmationPage />);

    expect(window.location.hash).toBe('');
    expect(await screen.findByText(/Se ha solicitado el modo/)).toHaveTextContent('Alias deportivo');
    expect(publicIdentityService.lookup).toHaveBeenCalledWith(
      validToken,
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
    expect(window.location.hash).toBe('');
    expect(document.querySelector('meta[name="robots"]')).toHaveAttribute(
      'content',
      'noindex, nofollow, noarchive',
    );

    await user.click(screen.getByRole('button', { name: 'Confirmar autorización' }));
    expect(await screen.findByRole('heading', { name: 'Confirmación registrada' }))
      .toBeInTheDocument();
    expect(publicIdentityService.confirm).toHaveBeenCalledOnce();
    expect(screen.getByText(/seguirá como “Participante”/)).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveFocus();
    expect(localStorage).toHaveLength(0);
    expect(sessionStorage).toHaveLength(0);
    logSpies.forEach((spy) => {
      expect(JSON.stringify(spy.mock.calls)).not.toContain(validToken);
      spy.mockRestore();
    });
  });

  it('offers an explicit rejection without exposing a minor or guardian', async () => {
    const user = userEvent.setup();
    publicIdentityService.lookup.mockResolvedValue({
      mode: 'name_initial',
      scope: 'public_competition_identity',
      notice_version: '1.0.0',
    });
    publicIdentityService.deny.mockResolvedValue({ data: { received: true } });

    render(<PublicIdentityConfirmationPage />);
    await screen.findByText(/Nombres de pila e inicial/);
    await user.click(screen.getByRole('button', { name: /Rechazar/ }));

    expect(await screen.findByRole('heading', { name: 'Rechazo registrado' }))
      .toBeInTheDocument();
    expect(document.body).not.toHaveTextContent('correo');
    expect(document.body).not.toHaveTextContent('fecha de nacimiento');
  });

  it.each(['invalid', 'used', 'expired'])(
    'uses the same neutral state for an %s token',
    async () => {
    publicIdentityService.lookup.mockRejectedValue({ response: { status: 404 } });

    render(<PublicIdentityConfirmationPage />);

    expect(await screen.findByRole('heading', { name: 'Enlace no disponible' }))
      .toBeInTheDocument();
    expect(screen.getByText('El enlace no es válido, ha caducado o ya fue utilizado.'))
      .toBeInTheDocument();
    await waitFor(() => expect(window.location.hash).toBe(''));
    expect(screen.getByRole('alert')).toHaveFocus();
    },
  );

  it('does not restore the token when navigating back and does not retry after reload', async () => {
    publicIdentityService.lookup.mockResolvedValue({
      mode: 'alias',
      scope: 'public_competition_identity',
      notice_version: '1.0.0',
    });
    window.history.replaceState(null, '', '/previous-page');
    window.history.pushState(null, '', `/public-identity/confirm#token=${validToken}`);

    const firstRender = render(<PublicIdentityConfirmationPage />);
    expect(window.location.hash).toBe('');
    await screen.findByText(/Se ha solicitado el modo/);
    expect(publicIdentityService.lookup).toHaveBeenCalledOnce();

    window.history.back();
    await waitFor(() => expect(window.location.pathname).toBe('/previous-page'));
    expect(window.location.hash).toBe('');

    firstRender.unmount();
    window.history.pushState(null, '', '/public-identity/confirm');
    render(<PublicIdentityConfirmationPage />);
    expect(await screen.findByRole('heading', { name: 'Enlace no disponible' }))
      .toBeInTheDocument();
    expect(publicIdentityService.lookup).toHaveBeenCalledOnce();
    expect(localStorage).toHaveLength(0);
    expect(sessionStorage).toHaveLength(0);
  });

  it('prevents a double confirmation while the first decision is pending', async () => {
    const user = userEvent.setup();
    let resolveConfirmation;
    publicIdentityService.lookup.mockResolvedValue({
      mode: 'alias',
      scope: 'public_competition_identity',
      notice_version: '1.0.0',
    });
    publicIdentityService.confirm.mockReturnValue(new Promise((resolve) => {
      resolveConfirmation = resolve;
    }));

    render(<PublicIdentityConfirmationPage />);
    const button = await screen.findByRole('button', { name: 'Confirmar autorización' });
    await user.dblClick(button);

    expect(publicIdentityService.confirm).toHaveBeenCalledOnce();
    resolveConfirmation({ data: { received: true } });
    expect(await screen.findByRole('heading', { name: 'Confirmación registrada' }))
      .toBeInTheDocument();
  });
});
