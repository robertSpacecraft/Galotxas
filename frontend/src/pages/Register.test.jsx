import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../test/renderWithProviders';
import Register from './Register';

const register = vi.fn();
const createPlayerProfile = vi.fn();

const renderRegister = () => renderWithProviders(<Register />, {
  route: '/register',
  authValue: { register, createPlayerProfile },
});

describe('Register', () => {
  beforeEach(() => {
    register.mockReset();
    createPlayerProfile.mockReset();
    register.mockResolvedValue({});
    createPlayerProfile.mockResolvedValue({});
  });

  it('has one main heading and exposes the player selector as a checkbox', () => {
    renderRegister();

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { name: 'Registro de Usuario', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Soy jugador' })).not.toBeChecked();
  });

  it('shows and hides the associated player fields with click interaction', async () => {
    const user = userEvent.setup();
    renderRegister();
    const playerToggle = screen.getByRole('checkbox', { name: 'Soy jugador' });

    await user.click(playerToggle);

    expect(playerToggle).toBeChecked();
    for (const label of [
      'Apodo (Nickname)',
      /DNI \/ NIE/,
      'Fecha de Nacimiento',
      'Género',
      /Nivel de juego/,
      'Nº Licencia',
      'Mano Dominante',
      'Notas / Observaciones',
    ]) {
      expect(screen.getByLabelText(label)).toBeInTheDocument();
    }

    await user.click(playerToggle);
    expect(screen.queryByLabelText('Apodo (Nickname)')).not.toBeInTheDocument();
  });

  it('can activate the player fields with the keyboard', async () => {
    const user = userEvent.setup();
    renderRegister();
    const playerToggle = screen.getByRole('checkbox', { name: 'Soy jugador' });

    playerToggle.focus();
    await user.keyboard(' ');

    expect(playerToggle).toBeChecked();
    expect(screen.getByLabelText('Apodo (Nickname)')).toBeInTheDocument();
  });

  it('keeps the existing account and player payloads', async () => {
    const user = userEvent.setup();
    renderRegister();

    await user.type(screen.getByLabelText('Nombre *'), 'Ada');
    await user.type(screen.getByLabelText('Apellidos *'), 'Lovelace');
    await user.type(screen.getByLabelText('Correo Electrónico *'), 'ada@example.test');
    await user.type(screen.getByLabelText('Confirmar Correo *'), 'ada@example.test');
    await user.type(screen.getByLabelText(/Contraseña \* \(min/), 'password123');
    await user.type(screen.getByLabelText('Confirmar Contraseña *'), 'password123');
    await user.click(screen.getByRole('checkbox', { name: 'Soy jugador' }));
    await user.type(screen.getByLabelText('Apodo (Nickname)'), 'Ada');
    await user.type(screen.getByLabelText(/Nivel de juego/), '5');
    await user.click(screen.getByRole('button', { name: 'Registrarse' }));

    await waitFor(() => {
      expect(register).toHaveBeenCalledWith({
        name: 'Ada',
        lastname: 'Lovelace',
        email: 'ada@example.test',
        password: 'password123',
        password_confirmation: 'password123',
      });
      expect(createPlayerProfile).toHaveBeenCalledWith({ nickname: 'Ada', level: 5 });
    });
  });
});
