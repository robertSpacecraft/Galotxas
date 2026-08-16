import { describe, expect, it } from 'vitest';
import { InvalidProfilePhotoResponseError, normalizeProfilePhoto } from './profilePhotoContract';

describe('profilePhotoContract', () => {
  it('accepts null or the stable private image endpoint', () => {
    expect(normalizeProfilePhoto(null)).toBeNull();
    expect(normalizeProfilePhoto({
      url: 'https://api.example.test/api/v1/me/profile-photo/image',
    })).toEqual({
      url: 'https://api.example.test/api/v1/me/profile-photo/image',
    });
  });

  it.each([
    undefined,
    {},
    [],
    { url: '/api/v1/me/profile-photo/image' },
    { url: 'https://objects.example.test/avatars/private.jpg' },
    { url: 'https://api.example.test/api/v1/me/profile-photo/image?token=secret' },
    { url: 'https://user:secret@api.example.test/api/v1/me/profile-photo/image' },
    { url: 'https://api.example.test/api/v1/me/profile-photo/image', profile_photo_path: 'avatars/private.jpg' },
  ])('rejects invalid, temporary or key-bearing values %#', (value) => {
    expect(() => normalizeProfilePhoto(value)).toThrow(InvalidProfilePhotoResponseError);
  });
});
