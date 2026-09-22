import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { accountProfileNotice } from '../legal/formNoticeRepository';
import { PlayerProfileEditor } from './PlayerProfileEditor';

const player = {
  nickname: 'Pilotari',
  dominant_hand: 'right',
  license_number: 'LIC-7',
  birth_date: '1990-01-01',
  public_identity: {
    display_name: 'Pilotari',
    status: 'adult_alias',
  },
};

const renderEditor = ({
  profile = player,
  generalDeclarationRequired = false,
  onSave = vi.fn().mockResolvedValue(profile),
} = {}) => {
  render(
    <MemoryRouter>
      <PlayerProfileEditor
        player={profile}
        generalDeclarationRequired={generalDeclarationRequired}
        onSave={onSave}
      />
    </MemoryRouter>,
  );

  return { onSave };
};

describe('PlayerProfileEditor', () => {
  it('shows the private diagnostic and exposes only the four approved edit controls', async () => {
    const user = userEvent.setup();
    renderEditor();

    expect(screen.getByText(/Se muestra tu apodo deportivo/).closest('div')).toHaveTextContent(
      'Identidad pública efectiva: Pilotari. Se muestra tu apodo deportivo.',
    );
    await user.click(screen.getByRole('button', { name: 'Editar perfil' }));

    expect(screen.getByLabelText('Apodo deportivo')).toHaveValue('Pilotari');
    expect(screen.getByLabelText('Mano dominante')).toHaveValue('right');
    expect(screen.getByLabelText('Número de licencia')).toHaveValue('LIC-7');
    expect(screen.getByLabelText('Fecha de nacimiento')).toHaveValue('1990-01-01');
    expect(screen.getByText(/No uses tu nombre habitual/)).toBeInTheDocument();
    for (const forbidden of ['DNI', 'Correo electrónico', 'Nivel', 'Género', 'Rol', 'Slug']) {
      expect(screen.queryByLabelText(forbidden)).not.toBeInTheDocument();
    }
  });

  it('restores the server snapshot on cancel and returns focus to Editar perfil', async () => {
    const user = userEvent.setup();
    renderEditor();

    await user.click(screen.getByRole('button', { name: 'Editar perfil' }));
    const nickname = screen.getByLabelText('Apodo deportivo');
    await user.clear(nickname);
    await user.type(nickname, 'Cambio sin guardar');
    await user.click(screen.getByRole('button', { name: 'Cancelar' }));

    const editButton = screen.getByRole('button', { name: 'Editar perfil' });
    await waitFor(() => expect(editButton).toHaveFocus());
    await user.click(editButton);
    expect(screen.getByLabelText('Apodo deportivo')).toHaveValue('Pilotari');
  });

  it('shows declarations only when their corresponding evidence is required', async () => {
    const user = userEvent.setup();
    renderEditor({ generalDeclarationRequired: true });

    await user.click(screen.getByRole('button', { name: 'Editar perfil' }));
    expect(screen.getByRole('checkbox', { name: /declaro que los datos facilitados son exactos y veraces/i }))
      .toBeInTheDocument();
    expect(screen.queryByRole('checkbox', { name: /fecha de nacimiento indicada/i }))
      .not.toBeInTheDocument();

    await user.clear(screen.getByLabelText('Fecha de nacimiento'));
    await user.type(screen.getByLabelText('Fecha de nacimiento'), '1991-02-03');
    expect(screen.getByRole('checkbox', { name: /fecha de nacimiento indicada/i }))
      .toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Política de Privacidad' }))
      .toHaveAttribute('href', '/legal/privacidad');
  });

  it('submits once with the versioned declarations and disables actions while saving', async () => {
    const user = userEvent.setup();
    let finishSaving;
    const onSave = vi.fn(() => new Promise((resolve) => {
      finishSaving = resolve;
    }));
    renderEditor({ generalDeclarationRequired: true, onSave });

    await user.click(screen.getByRole('button', { name: 'Editar perfil' }));
    await user.clear(screen.getByLabelText('Apodo deportivo'));
    await user.type(screen.getByLabelText('Apodo deportivo'), 'Alias nuevo');
    await user.clear(screen.getByLabelText('Fecha de nacimiento'));
    await user.type(screen.getByLabelText('Fecha de nacimiento'), '1991-02-03');
    await user.click(screen.getByRole('checkbox', { name: /declaro que los datos facilitados son exactos y veraces/i }));
    await user.click(screen.getByRole('checkbox', { name: /fecha de nacimiento indicada/i }));
    await user.click(screen.getByRole('button', { name: 'Guardar' }));

    expect(screen.getByRole('button', { name: 'Guardando…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeDisabled();
    expect(onSave).toHaveBeenCalledOnce();
    expect(onSave).toHaveBeenCalledWith({
      nickname: 'Alias nuevo',
      dominant_hand: 'right',
      license_number: 'LIC-7',
      birth_date: '1991-02-03',
      profile_declaration_accepted: true,
      birth_date_confirmed: true,
      profile_notice_id: accountProfileNotice.id,
      profile_notice_version: accountProfileNotice.version,
    });

    finishSaving(player);
    expect(await screen.findByRole('status')).toHaveTextContent('Perfil actualizado correctamente.');
  });

  it.each([
    ['nickname', 'Apodo deportivo', 'Alias ocupado', 'El apodo ya está en uso.'],
    ['license_number', 'Número de licencia', 'LIC-DUP', 'La licencia ya está en uso.'],
    ['birth_date', 'Fecha de nacimiento', '2014-01-01', 'Una persona menor no puede cambiar su fecha.'],
  ])('preserves values and focuses %s after a controlled 422 response', async (
    field,
    label,
    changedValue,
    message,
  ) => {
    const user = userEvent.setup();
    const onSave = vi.fn().mockRejectedValue({
      response: { status: 422, data: { errors: { [field]: [message] } } },
    });
    renderEditor({ onSave });

    await user.click(screen.getByRole('button', { name: 'Editar perfil' }));
    const control = screen.getByLabelText(label);
    await user.clear(control);
    await user.type(control, changedValue);
    if (field === 'birth_date') {
      await user.click(screen.getByRole('checkbox', { name: /fecha de nacimiento indicada/i }));
    }
    await user.click(screen.getByRole('button', { name: 'Guardar' }));

    expect(await screen.findAllByText(message)).toHaveLength(2);
    expect(control).toHaveValue(changedValue);
    expect(control).toHaveAttribute('aria-invalid', 'true');
    expect(control.getAttribute('aria-describedby')).toContain(`profile-${field}-error`);
    await waitFor(() => expect(control).toHaveFocus());
    expect(screen.getByRole('alert')).toHaveTextContent('Revisa los campos indicados.');
  });
});
