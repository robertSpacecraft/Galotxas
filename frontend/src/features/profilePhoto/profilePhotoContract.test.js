import { describe, expect, it } from 'vitest';
import { InvalidProfilePhotoResponseError, normalizeProfilePhoto } from './profilePhotoContract';

const existingPhoto = {
  url: 'https://api.example.test/api/v1/me/profile-photo/image',
};

describe('profilePhotoContract', () => {
  it('accepts private responsive endpoints without exposing credentials', () => {
    const profilePhoto = {
      url: 'https://api.example.test/api/v1/me/profile-photo/image',
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

    expect(normalizeProfilePhoto(profilePhoto)).toEqual(profilePhoto);
  });

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

  it.each([
    [{
      url: 'https://api.example.test/api/v1/me/profile-photo/image/320',
      width: 320,
      height: 320,
      mime_type: 'image/webp',
    }],
    [{
      url: 'https://api.example.test/api/v1/me/profile-photo/image/128?token=secret',
      width: 128,
      height: 128,
      mime_type: 'image/webp',
    }],
  ])('ignores an unsafe responsive extension and keeps the private master %#', (variants) => {
    expect(normalizeProfilePhoto({ ...existingPhoto, variants })).toEqual(existingPhoto);
  });
});
