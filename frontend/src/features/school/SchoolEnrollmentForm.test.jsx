import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { schoolService } from './schoolService';
import { SchoolEnrollmentForm } from './SchoolEnrollmentForm';

vi.mock('./schoolService', () => ({
  schoolService: {
    createEnrollment: vi.fn(),
  },
}));

const levels = [
  { id: 8, name: 'Iniciación' },
  { id: 12, name: 'Adultos' },
];

const reloadOverview = vi.fn();

const fillAdult = async (user) => {
  await user.type(screen.getByLabelText(/Nombre completo del participante/), 'Persona Adulta');
  await user.type(screen.getByLabelText(/Fecha de nacimiento/), '1990-01-01');
  await user.type(screen.getByLabelText(/Teléfono de contacto/), '611 000 000');
  await user.type(screen.getByLabelText(/Correo electrónico de contacto/), 'adulto@example.test');
  await user.click(screen.getByLabelText(/He leído la información de privacidad/));
};

describe('SchoolEnrollmentForm', () => {
  beforeEach(() => {
    schoolService.createEnrollment.mockReset();
    reloadOverview.mockReset();
  });

  it('shows representative fields only for a minor and clears them after changing to adult', async () => {
    const user = userEvent.setup();
    schoolService.createEnrollment.mockResolvedValue({ message: 'Recibida', data: null });
    render(<SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />);

    await user.type(screen.getByLabelText(/Fecha de nacimiento/), '2015-01-01');
    expect(screen.getByRole('group', { name: 'Representante' })).toBeInTheDocument();

    await user.type(screen.getByLabelText(/Nombre completo del representante/), 'Persona Tutora');
    await user.type(screen.getByLabelText(/Relación con el participante/), 'Madre');
    await user.clear(screen.getByLabelText(/Fecha de nacimiento/));
    await user.type(screen.getByLabelText(/Fecha de nacimiento/), '1990-01-01');

    expect(screen.queryByRole('group', { name: 'Representante' })).not.toBeInTheDocument();
  });

  it('validates basic fields, links inline errors and focuses the first invalid field', async () => {
    const user = userEvent.setup();
    render(<SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />);

    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    const participant = screen.getByLabelText(/Nombre completo del participante/);
    expect(participant).toHaveFocus();
    expect(participant).toHaveAttribute('aria-invalid', 'true');
    expect(participant).toHaveAttribute('aria-describedby', 'participant_name-error');
    expect(screen.getByText('Indica el nombre completo del participante.')).toBeInTheDocument();
    expect(schoolService.createEnrollment).not.toHaveBeenCalled();
  });

  it('prevents a double submission, sends the adult payload and focuses the neutral result', async () => {
    const user = userEvent.setup();
    let resolveRequest;
    schoolService.createEnrollment.mockReturnValue(new Promise((resolve) => {
      resolveRequest = resolve;
    }));
    render(<SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />);

    await fillAdult(user);
    await user.selectOptions(screen.getByLabelText(/Nivel solicitado/), '12');
    const button = screen.getByRole('button', { name: 'Enviar solicitud' });
    await user.click(button);
    fireEvent.submit(button.closest('form'));

    expect(screen.getByRole('button', { name: 'Enviando solicitud…' })).toBeDisabled();
    expect(schoolService.createEnrollment).toHaveBeenCalledOnce();
    expect(schoolService.createEnrollment).toHaveBeenCalledWith({
      participant_name: 'Persona Adulta',
      participant_birth_date: '1990-01-01',
      contact_phone: '611 000 000',
      contact_email: 'adulto@example.test',
      school_level_id: 12,
      privacy_acknowledged: true,
      privacy_notice_id: 'NOTICE-SCHOOL-ENROLLMENT',
      privacy_notice_version: '1.0.0',
      website: '',
    });

    resolveRequest({ message: 'Recibida', data: null });

    expect(await screen.findByText('La solicitud de inscripción se ha recibido correctamente.'))
      .toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Solicitud recibida');
    expect(screen.getByRole('status')).toHaveFocus();
    expect(screen.queryByDisplayValue('Persona Adulta')).not.toBeInTheDocument();
  });

  it('rejects an invalid phone before sending', async () => {
    const user = userEvent.setup();
    render(<SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />);

    await fillAdult(user);
    await user.clear(screen.getByLabelText(/Teléfono de contacto/));
    await user.type(screen.getByLabelText(/Teléfono de contacto/), 'teléfono inválido');
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    expect(screen.getByText('Indica un teléfono de contacto válido.')).toBeInTheDocument();
    expect(screen.getByLabelText(/Teléfono de contacto/)).toHaveFocus();
    expect(schoolService.createEnrollment).not.toHaveBeenCalled();
  });

  it('keeps the honeypot untabbable and never writes personal fields to web storage', async () => {
    const user = userEvent.setup();
    localStorage.clear();
    sessionStorage.clear();
    const { container } = render(
      <SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />,
    );

    const honeypot = container.querySelector('input[name="website"]');
    expect(honeypot).toHaveAttribute('tabindex', '-1');
    expect(honeypot.closest('[aria-hidden="true"]')).toBeInTheDocument();
    await user.type(screen.getByLabelText(/Nombre completo del participante/), 'Dato temporal');
    expect(localStorage).toHaveLength(0);
    expect(sessionStorage).toHaveLength(0);
  });

  it('sends required representative data for a minor and no internal fields', async () => {
    const user = userEvent.setup();
    schoolService.createEnrollment.mockResolvedValue({ message: 'Recibida', data: null });
    render(<SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />);

    await user.type(screen.getByLabelText(/Nombre completo del participante/), 'Participante Menor');
    await user.type(screen.getByLabelText(/Fecha de nacimiento/), '2015-01-01');
    await user.type(screen.getByLabelText(/Teléfono de contacto/), '600 123 123');
    await user.type(screen.getByLabelText(/Correo electrónico de contacto/), 'familia@example.test');
    await user.type(screen.getByLabelText(/Nombre completo del representante/), 'Persona Tutora');
    await user.type(screen.getByLabelText(/Relación con el participante/), 'Madre');
    await user.click(screen.getByLabelText(/He leído la información de privacidad/));
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    await waitFor(() => expect(schoolService.createEnrollment).toHaveBeenCalledOnce());
    expect(schoolService.createEnrollment).toHaveBeenCalledWith({
      participant_name: 'Participante Menor',
      participant_birth_date: '2015-01-01',
      contact_phone: '600 123 123',
      contact_email: 'familia@example.test',
      guardian_name: 'Persona Tutora',
      guardian_relationship: 'Madre',
      privacy_acknowledged: true,
      privacy_notice_id: 'NOTICE-SCHOOL-ENROLLMENT',
      privacy_notice_version: '1.0.0',
      website: '',
    });
  });

  it('keeps public identity optional and sends a separate versioned request when selected', async () => {
    const user = userEvent.setup();
    schoolService.createEnrollment.mockResolvedValue({ message: 'Recibida', data: null });
    render(
      <SchoolEnrollmentForm
        levels={levels}
        reloadOverview={reloadOverview}
        identityAuthorization={{
          enabled: true,
          notice_id: 'NOTICE-PUBLIC-IDENTITY-MINORS',
          notice_version: '1.0.0',
          scope: 'public_competition_identity',
          modes: ['alias', 'name_initial', 'anonymous'],
        }}
      />,
    );

    await user.type(screen.getByLabelText(/Nombre completo del participante/), 'Menor Autorizable');
    await user.type(screen.getByLabelText(/Fecha de nacimiento/), '2015-01-01');
    await user.type(screen.getByLabelText(/Teléfono de contacto/), '600 000 000');
    await user.type(screen.getByLabelText(/Correo electrónico de contacto/), 'tutora@example.test');
    await user.type(screen.getByLabelText(/Nombre completo del representante/), 'Persona Tutora');
    await user.type(screen.getByLabelText(/Relación con el participante/), 'Tutora');
    expect(screen.getByLabelText(/No autorizar identidad individual/)).toBeChecked();
    await user.click(screen.getByLabelText(/Autorizar sólo el alias deportivo/));
    await user.click(screen.getByLabelText(/Declaro que ejerzo la patria potestad/));
    await user.click(screen.getByLabelText(/He leído la información de privacidad/));
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    await waitFor(() => expect(schoolService.createEnrollment).toHaveBeenCalledOnce());
    expect(schoolService.createEnrollment).toHaveBeenCalledWith(expect.objectContaining({
      public_identity_authorization: {
        mode: 'alias',
        notice_version: '1.0.0',
        guardian_authority_declared: true,
      },
      privacy_acknowledged: true,
      privacy_notice_id: 'NOTICE-SCHOOL-ENROLLMENT',
      privacy_notice_version: '1.0.0',
    }));
  });

  it.each([
    ['anonymous', /No autorizar identidad individual/, false],
    ['name_initial', /Autorizar nombres de pila e inicial/, true],
  ])('submits the %s minor identity mode without mixing privacy', async (
    mode,
    optionName,
    requiresAuthority,
  ) => {
    const user = userEvent.setup();
    schoolService.createEnrollment.mockResolvedValue({ message: 'Recibida', data: null });
    render(
      <SchoolEnrollmentForm
        levels={levels}
        reloadOverview={reloadOverview}
        identityAuthorization={{ enabled: true, notice_version: '1.0.0' }}
      />,
    );

    await user.type(screen.getByLabelText(/Nombre completo del participante/), 'Menor Modalidad');
    await user.type(screen.getByLabelText(/Fecha de nacimiento/), '2015-01-01');
    await user.type(screen.getByLabelText(/Teléfono de contacto/), '600 000 001');
    await user.type(screen.getByLabelText(/Correo electrónico de contacto/), 'familia@example.test');
    await user.type(screen.getByLabelText(/Nombre completo del representante/), 'Persona Tutora');
    await user.type(screen.getByLabelText(/Relación con el participante/), 'Tutora');
    await user.click(screen.getByLabelText(optionName));
    if (requiresAuthority) {
      await user.click(screen.getByLabelText(/Declaro que ejerzo la patria potestad/));
    }
    await user.click(screen.getByLabelText(/He leído la información de privacidad/));
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    await waitFor(() => expect(schoolService.createEnrollment).toHaveBeenCalledOnce());
    expect(schoolService.createEnrollment).toHaveBeenCalledWith(expect.objectContaining({
      privacy_acknowledged: true,
      privacy_notice_id: 'NOTICE-SCHOOL-ENROLLMENT',
      privacy_notice_version: '1.0.0',
      public_identity_authorization: {
        mode,
        notice_version: '1.0.0',
        ...(requiresAuthority ? { guardian_authority_declared: true } : {}),
      },
    }));
  });

  it('associates and focuses the authority error without blocking anonymous enrollment', async () => {
    const user = userEvent.setup();
    schoolService.createEnrollment.mockResolvedValue({ message: 'Recibida', data: null });
    render(
      <SchoolEnrollmentForm
        levels={levels}
        reloadOverview={reloadOverview}
        identityAuthorization={{ enabled: true, notice_version: '1.0.0' }}
      />,
    );

    await user.type(screen.getByLabelText(/Nombre completo del participante/), 'Menor Validación');
    await user.type(screen.getByLabelText(/Fecha de nacimiento/), '2015-01-01');
    await user.type(screen.getByLabelText(/Teléfono de contacto/), '600 000 002');
    await user.type(screen.getByLabelText(/Correo electrónico de contacto/), 'validacion@example.test');
    await user.type(screen.getByLabelText(/Nombre completo del representante/), 'Persona Tutora');
    await user.type(screen.getByLabelText(/Relación con el participante/), 'Tutora');
    await user.click(screen.getByLabelText(/Autorizar sólo el alias deportivo/));
    await user.click(screen.getByLabelText(/He leído la información de privacidad/));
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    const authority = screen.getByLabelText(/Declaro que ejerzo la patria potestad/);
    expect(authority).toHaveFocus();
    expect(authority).toHaveAttribute('aria-describedby', 'guardian_authority_declared-error');
    expect(schoolService.createEnrollment).not.toHaveBeenCalled();

    await user.click(screen.getByLabelText(/No autorizar identidad individual/));
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));
    await waitFor(() => expect(schoolService.createEnrollment).toHaveBeenCalledOnce());
  });

  it('maps backend 422 errors, preserves fields and focuses the first invalid field', async () => {
    const user = userEvent.setup();
    schoolService.createEnrollment.mockRejectedValue({
      response: {
        status: 422,
        data: {
          errors: {
            participant_name: ['El nombre no es válido.'],
            contact_email: ['El correo no es válido.'],
          },
        },
      },
    });
    render(<SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />);

    await fillAdult(user);
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    expect(await screen.findByText('El nombre no es válido.')).toBeInTheDocument();
    expect(screen.getByText('El correo no es válido.')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Persona Adulta')).toBeInTheDocument();
    expect(screen.getByLabelText(/Nombre completo del participante/)).toHaveFocus();
    expect(screen.getByRole('alert')).toHaveTextContent('Revisa los campos indicados.');
  });

  it('handles a 409 as closed, blocks another submit and offers a public refresh', async () => {
    const user = userEvent.setup();
    schoolService.createEnrollment.mockRejectedValue({
      response: {
        status: 409,
        data: { message: 'Las inscripciones no están disponibles en este momento.' },
      },
    });
    reloadOverview.mockResolvedValue({
      ok: true,
      data: { enrollment_status: 'open' },
    });
    render(<SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />);

    await fillAdult(user);
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Las inscripciones no están disponibles en este momento.',
    );
    expect(screen.getByDisplayValue('Persona Adulta')).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Enviar solicitud' })).toBeDisabled();
    await user.click(screen.getByRole('button', { name: 'Volver a comprobar disponibilidad' }));
    expect(reloadOverview).toHaveBeenCalledOnce();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Enviar solicitud' })).toBeEnabled());
  });

  it.each([
    [
      429,
      'Se han realizado demasiados intentos. Espera un momento antes de volver a intentarlo.',
    ],
    [
      500,
      'No se ha podido enviar la solicitud. Comprueba tu conexión e inténtalo de nuevo.',
    ],
  ])('keeps values and permits a manual retry after HTTP %s', async (status, message) => {
    const user = userEvent.setup();
    schoolService.createEnrollment.mockRejectedValue({ response: { status } });
    render(<SchoolEnrollmentForm levels={levels} reloadOverview={reloadOverview} />);

    await fillAdult(user);
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(message);
    expect(screen.getByDisplayValue('Persona Adulta')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Enviar solicitud' })).toBeEnabled();
  });
});
