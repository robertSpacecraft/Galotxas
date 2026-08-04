import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ContactServiceError, contactService } from '../contact/contactService';
import { ContactForm } from './ContactForm';

vi.mock('../contact/contactService', async (importOriginal) => {
  const original = await importOriginal();

  return {
    ...original,
    contactService: {
      getConfig: vi.fn(),
      submit: vi.fn(),
    },
  };
});

const fillValidForm = async (user) => {
  await user.type(screen.getByLabelText(/^Nombre/), 'Persona interesada');
  await user.type(screen.getByLabelText(/^Correo electrónico/), 'persona@example.test');
  await user.type(screen.getByLabelText(/^Asunto/), 'Consulta técnica');
  await user.type(screen.getByLabelText(/^Mensaje/), 'Este es un mensaje suficientemente largo.');
  await user.click(screen.getByRole('checkbox'));
};

describe('ContactForm', () => {
  beforeEach(() => {
    contactService.submit.mockReset();
  });

  it('validates required fields and focuses the first invalid control', async () => {
    const user = userEvent.setup();
    render(<ContactForm />);

    await user.click(screen.getByRole('button', { name: 'Enviar mensaje' }));

    expect(screen.getByLabelText(/^Nombre/)).toHaveFocus();
    expect(screen.getByLabelText(/^Nombre/)).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('checkbox')).toHaveAttribute('aria-invalid', 'true');
    expect(contactService.submit).not.toHaveBeenCalled();
  });

  it('requires consent and associates its error with the checkbox', async () => {
    const user = userEvent.setup();
    render(<ContactForm />);
    await user.type(screen.getByLabelText(/^Nombre/), 'Persona interesada');
    await user.type(screen.getByLabelText(/^Correo electrónico/), 'persona@example.test');
    await user.type(screen.getByLabelText(/^Asunto/), 'Consulta');
    await user.type(screen.getByLabelText(/^Mensaje/), 'Mensaje suficientemente largo.');

    await user.click(screen.getByRole('button', { name: 'Enviar mensaje' }));

    expect(screen.getByRole('checkbox')).toHaveFocus();
    expect(screen.getByRole('checkbox')).toHaveAccessibleDescription(
      'Debes confirmar el envío de los datos introducidos.',
    );
  });

  it('submits the closed payload, clears fields and focuses the 201 result', async () => {
    const user = userEvent.setup();
    contactService.submit.mockResolvedValue({ data: { received: true } });
    render(<ContactForm />);
    await fillValidForm(user);

    await user.click(screen.getByRole('button', { name: 'Enviar mensaje' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Tu mensaje se ha recibido');
    expect(screen.getByRole('status')).toHaveFocus();
    expect(contactService.submit).toHaveBeenCalledWith({
      name: 'Persona interesada',
      email: 'persona@example.test',
      subject: 'Consulta técnica',
      message: 'Este es un mensaje suficientemente largo.',
      privacy_accepted: true,
      website: '',
    });
    expect(screen.queryByLabelText('Nombre')).not.toBeInTheDocument();
  });

  it('maps 422 errors, retains correctable data and focuses the first field', async () => {
    const user = userEvent.setup();
    contactService.submit.mockRejectedValue(new ContactServiceError('Datos inválidos.', {
      status: 422,
      kind: 'api',
      errors: { email: ['El correo ya no es válido.'] },
    }));
    render(<ContactForm />);
    await fillValidForm(user);

    await user.click(screen.getByRole('button', { name: 'Enviar mensaje' }));

    expect(await screen.findByText('El correo ya no es válido.')).toBeInTheDocument();
    expect(screen.getByLabelText(/^Correo electrónico/)).toHaveFocus();
    expect(screen.getByLabelText(/^Nombre/)).toHaveValue('Persona interesada');
    expect(screen.getByLabelText(/^Mensaje/)).toHaveValue(
      'Este es un mensaje suficientemente largo.',
    );
  });

  it.each([
    [429, 'Demasiadas solicitudes. Inténtalo de nuevo más tarde.'],
    [503, 'El formulario de contacto no está disponible.'],
    [null, 'No se ha podido conectar con el servicio de contacto.'],
    [500, 'No se ha podido procesar la solicitud.'],
  ])('keeps fields and allows retry after a %s failure', async (status, message) => {
    const user = userEvent.setup();
    contactService.submit.mockRejectedValue(new ContactServiceError(message, {
      status,
      kind: status === null ? 'network' : 'api',
    }));
    render(<ContactForm />);
    await fillValidForm(user);

    await user.click(screen.getByRole('button', { name: 'Enviar mensaje' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(message);
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(screen.getByLabelText(/^Nombre/)).toHaveValue('Persona interesada');
    expect(screen.getByRole('button', { name: 'Enviar mensaje' })).toBeEnabled();
  });

  it('prevents duplicate submissions while a request is pending', async () => {
    const user = userEvent.setup();
    let resolveRequest;
    contactService.submit.mockReturnValue(new Promise((resolve) => {
      resolveRequest = resolve;
    }));
    render(<ContactForm />);
    await fillValidForm(user);

    const button = screen.getByRole('button', { name: 'Enviar mensaje' });
    await user.click(button);
    fireEvent.submit(button.closest('form'));

    expect(contactService.submit).toHaveBeenCalledOnce();
    expect(screen.getByRole('button', { name: 'Enviando mensaje…' })).toBeDisabled();
    resolveRequest({ data: { received: true } });
    expect(await screen.findByText('Mensaje recibido')).toBeInTheDocument();
  });

  it('keeps the honeypot invisible, untabbable and out of storage', async () => {
    const user = userEvent.setup();
    localStorage.clear();
    sessionStorage.clear();
    const { container } = render(<ContactForm />);

    const honeypot = container.querySelector('input[name="website"]');
    expect(honeypot).toHaveAttribute('tabindex', '-1');
    expect(honeypot.closest('[aria-hidden="true"]')).toBeInTheDocument();
    await user.type(screen.getByLabelText(/^Nombre/), 'Persona');
    expect(localStorage).toHaveLength(0);
    expect(sessionStorage).toHaveLength(0);
  });

  it('exposes semantic autocomplete and visible labels for keyboard use', () => {
    render(<ContactForm />);

    expect(screen.getByLabelText(/^Nombre/)).toHaveAttribute('autocomplete', 'name');
    expect(screen.getByLabelText(/^Correo electrónico/)).toHaveAttribute('autocomplete', 'email');
    expect(screen.getByRole('button', { name: 'Enviar mensaje' })).toHaveAttribute('type', 'submit');
  });
});
