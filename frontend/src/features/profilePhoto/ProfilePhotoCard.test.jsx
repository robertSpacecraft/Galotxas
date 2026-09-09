import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { profilePhotoService } from './profilePhotoService';
import { ProfilePhotoCard } from './ProfilePhotoCard';

vi.mock('./profilePhotoService', () => ({
  profilePhotoService: {
    upload: vi.fn(),
    remove: vi.fn(),
    download: vi.fn(),
  },
}));

const existingPhoto = {
  url: 'https://api.example.test/api/v1/me/profile-photo/image',
};

const responsivePhoto = {
  ...existingPhoto,
  width: 512,
  height: 512,
  variants: [
    {
      url: 'https://api.example.test/api/v1/me/profile-photo/image/128',
      width: 128,
      height: 128,
      mime_type: 'image/webp',
    },
    {
      url: 'https://api.example.test/api/v1/me/profile-photo/image/256',
      width: 256,
      height: 256,
      mime_type: 'image/webp',
    },
  ],
};

const userWithoutPhoto = {
  name: 'Nombre',
  lastname: 'Apellido',
  profile_photo: null,
};

describe('ProfilePhotoCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    Object.defineProperty(window, 'devicePixelRatio', {
      configurable: true,
      value: 1,
    });
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      writable: true,
      value: vi.fn().mockReturnValueOnce('blob:photo-1').mockReturnValue('blob:photo-2'),
    });
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      writable: true,
      value: vi.fn(),
    });
  });

  it('renders approved initials and a neutral fallback without remote data', () => {
    const { rerender } = render(
      <ProfilePhotoCard user={userWithoutPhoto} onProfilePhotoChange={vi.fn()} />,
    );

    expect(screen.getByRole('img', { name: 'Sin foto de perfil' })).toHaveTextContent('NA');
    expect(screen.getByText(/Es privada y no se publica en competición/i)).toBeInTheDocument();
    expect(profilePhotoService.download).not.toHaveBeenCalled();

    rerender(
      <ProfilePhotoCard
        user={{ name: ' ', lastname: null, profile_photo: null }}
        onProfilePhotoChange={vi.fn()}
      />,
    );
    expect(screen.getByRole('img', { name: 'Sin foto de perfil' })).toHaveTextContent('●');
  });

  it('downloads the existing photo as a blob and revokes it on unmount', async () => {
    profilePhotoService.download.mockResolvedValue(new Blob(['photo'], { type: 'image/png' }));
    const { unmount } = render(
      <ProfilePhotoCard
        user={{ ...userWithoutPhoto, profile_photo: existingPhoto }}
        onProfilePhotoChange={vi.fn()}
      />,
    );

    const image = await screen.findByRole('img', { name: 'Foto de perfil de Nombre Apellido' });
    expect(image).toHaveAttribute('src', 'blob:photo-1');
    expect(profilePhotoService.download).toHaveBeenCalledWith({ signal: expect.any(AbortSignal) });

    unmount();
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:photo-1');
  });

  it('selects an authenticated avatar variant for the rendered size and DPR', async () => {
    Object.defineProperty(window, 'devicePixelRatio', {
      configurable: true,
      value: 2,
    });
    profilePhotoService.download.mockResolvedValue(new Blob(['photo'], { type: 'image/webp' }));
    render(
      <ProfilePhotoCard
        user={{ ...userWithoutPhoto, profile_photo: responsivePhoto }}
        onProfilePhotoChange={vi.fn()}
      />,
    );

    await screen.findByRole('img', { name: 'Foto de perfil de Nombre Apellido' });
    expect(profilePhotoService.download).toHaveBeenCalledOnce();
    expect(profilePhotoService.download).toHaveBeenCalledWith({
      signal: expect.any(AbortSignal),
      width: 256,
    });
  });

  it('uses the authenticated master when high DPR exceeds every available variant', async () => {
    Object.defineProperty(window, 'devicePixelRatio', {
      configurable: true,
      value: 3,
    });
    profilePhotoService.download.mockResolvedValue(new Blob(['master'], { type: 'image/webp' }));
    render(
      <ProfilePhotoCard
        user={{ ...userWithoutPhoto, profile_photo: responsivePhoto }}
        onProfilePhotoChange={vi.fn()}
      />,
    );

    await screen.findByRole('img', { name: 'Foto de perfil de Nombre Apellido' });
    expect(profilePhotoService.download).toHaveBeenCalledOnce();
    expect(profilePhotoService.download).toHaveBeenCalledWith({
      signal: expect.any(AbortSignal),
    });
  });

  it('falls back from a failed authenticated variant request to the authenticated master', async () => {
    profilePhotoService.download
      .mockRejectedValueOnce(new Error('variant unavailable'))
      .mockResolvedValueOnce(new Blob(['master'], { type: 'image/webp' }));
    render(
      <ProfilePhotoCard
        user={{ ...userWithoutPhoto, profile_photo: responsivePhoto }}
        onProfilePhotoChange={vi.fn()}
      />,
    );

    expect(await screen.findByRole('img', { name: 'Foto de perfil de Nombre Apellido' }))
      .toHaveAttribute('src', 'blob:photo-1');
    expect(profilePhotoService.download).toHaveBeenNthCalledWith(1, {
      signal: expect.any(AbortSignal),
      width: 128,
    });
    expect(profilePhotoService.download).toHaveBeenNthCalledWith(2, {
      signal: expect.any(AbortSignal),
    });
  });

  it('falls back from an undecodable variant blob to master, then stops on master failure', async () => {
    profilePhotoService.download
      .mockResolvedValueOnce(new Blob(['variant'], { type: 'image/webp' }))
      .mockResolvedValueOnce(new Blob(['master'], { type: 'image/webp' }));
    render(
      <ProfilePhotoCard
        user={{ ...userWithoutPhoto, profile_photo: responsivePhoto }}
        onProfilePhotoChange={vi.fn()}
      />,
    );

    fireEvent.error(await screen.findByRole('img', { name: 'Foto de perfil de Nombre Apellido' }));
    await waitFor(() => expect(screen.getByRole('img', {
      name: 'Foto de perfil de Nombre Apellido',
    })).toHaveAttribute('src', 'blob:photo-2'));
    expect(profilePhotoService.download).toHaveBeenNthCalledWith(2, {
      signal: expect.any(AbortSignal),
    });

    fireEvent.error(screen.getByRole('img', { name: 'Foto de perfil de Nombre Apellido' }));
    expect(screen.getByRole('img', { name: 'Sin foto de perfil' })).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('No se pudo mostrar');
    expect(profilePhotoService.download).toHaveBeenCalledTimes(2);
  });

  it('previews, uploads and immediately refreshes without persisting photo data', async () => {
    const browserUser = userEvent.setup();
    const onProfilePhotoChange = vi.fn();
    const selected = new File(['selected'], 'avatar.webp', { type: 'image/webp' });
    localStorage.setItem('token', 'private-token');
    profilePhotoService.upload.mockResolvedValue(existingPhoto);
    profilePhotoService.download.mockResolvedValue(new Blob(['stored'], { type: 'image/webp' }));
    render(
      <ProfilePhotoCard user={userWithoutPhoto} onProfilePhotoChange={onProfilePhotoChange} />,
    );

    const input = screen.getByLabelText('Subir foto');
    await browserUser.upload(input, selected);

    expect(screen.getByText('Vista previa pendiente de guardar.')).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Foto de perfil de Nombre Apellido' }))
      .toHaveAttribute('src', 'blob:photo-1');

    await browserUser.click(screen.getByRole('button', { name: 'Guardar foto' }));

    await waitFor(() => expect(onProfilePhotoChange).toHaveBeenCalledWith(existingPhoto));
    expect(profilePhotoService.upload).toHaveBeenCalledWith(selected);
    expect(profilePhotoService.download).toHaveBeenCalledWith({ signal: undefined });
    expect(await screen.findByRole('status')).toHaveTextContent('actualizada correctamente');
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:photo-1');
    expect(localStorage.getItem('token')).toBe('private-token');
    expect(localStorage).toHaveLength(1);
  });

  it('removes an existing photo after confirmation and returns to fallback', async () => {
    const browserUser = userEvent.setup();
    const onProfilePhotoChange = vi.fn();
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    profilePhotoService.download.mockResolvedValue(new Blob(['photo'], { type: 'image/jpeg' }));
    profilePhotoService.remove.mockResolvedValue(null);
    render(
      <ProfilePhotoCard
        user={{ ...userWithoutPhoto, profile_photo: existingPhoto }}
        onProfilePhotoChange={onProfilePhotoChange}
      />,
    );

    await screen.findByRole('img', { name: 'Foto de perfil de Nombre Apellido' });
    await browserUser.click(screen.getByRole('button', { name: 'Eliminar foto' }));

    expect(window.confirm).toHaveBeenCalledOnce();
    expect(profilePhotoService.remove).toHaveBeenCalledOnce();
    expect(onProfilePhotoChange).toHaveBeenCalledWith(null);
    expect(screen.getByRole('img', { name: 'Sin foto de perfil' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('eliminada correctamente');
  });

  it.each([
    [422, { errors: { photo: ['La imagen no es válida.'] } }, 'La imagen no es válida.'],
    [429, {}, 'demasiados intentos'],
    [503, {}, 'no está disponible temporalmente'],
  ])('shows a safe mutation error for HTTP %s', async (status, data, expected) => {
    const browserUser = userEvent.setup();
    profilePhotoService.upload.mockRejectedValue({ response: { status, data } });
    render(<ProfilePhotoCard user={userWithoutPhoto} onProfilePhotoChange={vi.fn()} />);

    await browserUser.upload(
      screen.getByLabelText('Subir foto'),
      new File(['photo'], 'avatar.png', { type: 'image/png' }),
    );
    await browserUser.click(screen.getByRole('button', { name: 'Guardar foto' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(expected);
  });

  it('falls back after an image failure and supports keyboard focus and retry', async () => {
    const browserUser = userEvent.setup();
    profilePhotoService.download.mockResolvedValue(new Blob(['photo'], { type: 'image/png' }));
    render(
      <ProfilePhotoCard
        user={{ ...userWithoutPhoto, profile_photo: existingPhoto }}
        onProfilePhotoChange={vi.fn()}
      />,
    );

    fireEvent.error(await screen.findByRole('img', { name: 'Foto de perfil de Nombre Apellido' }));
    expect(screen.getByRole('img', { name: 'Sin foto de perfil' })).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('No se pudo mostrar');

    await browserUser.click(screen.getByRole('button', { name: 'Reintentar carga' }));
    await waitFor(() => expect(profilePhotoService.download).toHaveBeenCalledTimes(2));

    const input = screen.getByLabelText('Cambiar foto');
    await browserUser.tab();
    expect(input).toHaveFocus();
  });
});
