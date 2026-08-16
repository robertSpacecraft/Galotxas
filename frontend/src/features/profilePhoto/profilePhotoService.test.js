import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../../api/client';
import { InvalidProfilePhotoResponseError } from './profilePhotoContract';
import { profilePhotoService } from './profilePhotoService';

vi.mock('../../api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

const profilePhoto = {
  url: 'https://api.example.test/api/v1/me/profile-photo/image',
};

describe('profilePhotoService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('uploads FormData without setting a multipart boundary manually', async () => {
    const file = new File(['photo'], 'avatar.png', { type: 'image/png' });
    api.post.mockResolvedValue({ data: { data: { profile_photo: profilePhoto } } });

    await expect(profilePhotoService.upload(file)).resolves.toEqual(profilePhoto);

    expect(api.post).toHaveBeenCalledOnce();
    expect(api.post.mock.calls[0][0]).toBe('/me/profile-photo');
    expect(api.post.mock.calls[0][1]).toBeInstanceOf(FormData);
    expect(api.post.mock.calls[0][1].get('photo')).toBe(file);
    expect(api.post.mock.calls[0]).toHaveLength(2);
  });

  it('deletes the private photo and requires a null contract', async () => {
    api.delete.mockResolvedValue({ data: { data: { profile_photo: null } } });

    await expect(profilePhotoService.remove()).resolves.toBeNull();
    expect(api.delete).toHaveBeenCalledWith('/me/profile-photo');

    api.delete.mockResolvedValue({
      data: { data: { profile_photo: { url: 'https://objects.example.test/private.jpg' } } },
    });
    await expect(profilePhotoService.remove()).rejects.toThrow(InvalidProfilePhotoResponseError);
  });

  it('downloads only an image blob through the stable authenticated endpoint', async () => {
    const controller = new AbortController();
    const blob = new Blob(['photo'], { type: 'image/webp' });
    api.get.mockResolvedValue({ data: blob });

    await expect(profilePhotoService.download({ signal: controller.signal })).resolves.toBe(blob);
    expect(api.get).toHaveBeenCalledWith('/me/profile-photo/image', {
      responseType: 'blob',
      signal: controller.signal,
    });

    api.get.mockResolvedValue({ data: new Blob(['html'], { type: 'text/html' }) });
    await expect(profilePhotoService.download()).rejects.toThrow(InvalidProfilePhotoResponseError);
  });
});
