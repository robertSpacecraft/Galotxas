import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../test/renderWithProviders';
import ForgotPassword from './ForgotPassword';

describe('ForgotPassword', () => {
  it('associates the email label and exposes one main heading', () => {
    renderWithProviders(<ForgotPassword />, {
      authValue: { forgotPassword: vi.fn() },
    });

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { name: 'Recuperar Contraseña', level: 1 })).toBeInTheDocument();
    expect(screen.getByLabelText('Correo Electrónico')).toHaveAttribute('autocomplete', 'email');
  });

  it('submits the existing email payload and keeps one h1 in the success state', async () => {
    const user = userEvent.setup();
    const forgotPassword = vi.fn().mockResolvedValue({});
    renderWithProviders(<ForgotPassword />, { authValue: { forgotPassword } });

    await user.type(screen.getByLabelText('Correo Electrónico'), 'player@example.test');
    await user.click(screen.getByRole('button', { name: 'Enviar enlace' }));

    expect(forgotPassword).toHaveBeenCalledWith('player@example.test');
    expect(await screen.findByRole('heading', { name: 'Revisa tu correo', level: 1 })).toBeInTheDocument();
    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
  });
});
