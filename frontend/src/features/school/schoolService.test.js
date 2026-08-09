import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../../api/client';
import { schoolService } from './schoolService';

vi.mock('../../api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe('schoolService', () => {
  beforeEach(() => {
    api.get.mockReset();
    api.post.mockReset();
  });

  it('loads and safely normalizes the public aggregate without reordering it', async () => {
    const signal = new AbortController().signal;
    api.get.mockResolvedValue({
      data: {
        message: null,
        data: {
          name: 'Programa público',
          description: 'Descripción pública',
          enrollment_information: 'Proceso público',
          enrollment_status: 'open',
          enrollments_open: true,
          privacy_notice: {
            id: 'NOTICE-SCHOOL-ENROLLMENT',
            version: '1.0.0',
            privacy_url: '/legal/privacidad',
          },
          default_location: null,
          levels: [
            {
              id: 9,
              name: 'Segundo en respuesta',
              minimum_age: null,
              maximum_age: 16,
              schedules: [],
            },
            {
              id: 2,
              name: 'Primero administrativo',
              minimum_age: 8,
              maximum_age: null,
              schedules: [
                {
                  id: 12,
                  day_of_week: 2,
                  starts_at: '18:00',
                  ends_at: '19:00',
                  location: { id: 4, name: 'Pista', locality: 'Monóvar', address: null },
                },
              ],
            },
          ],
        },
      },
    });

    const result = await schoolService.getOverview({ signal });

    expect(api.get).toHaveBeenCalledWith('/school', { signal });
    expect(result.levels.map((level) => level.id)).toEqual([9, 2]);
    expect(result.levels[1].schedules[0]).toEqual({
      id: 12,
      day_of_week: 2,
      starts_at: '18:00',
      ends_at: '19:00',
      location: { id: 4, name: 'Pista', locality: 'Monóvar', address: null },
    });
  });

  it('returns null for the valid absence contract', async () => {
    api.get.mockResolvedValue({ data: { message: null, data: null } });

    await expect(schoolService.getOverview()).resolves.toBeNull();
  });

  it('rejects an invalid aggregate instead of exposing unsafe render data', async () => {
    api.get.mockResolvedValue({ data: { data: 'invalid' } });

    await expect(schoolService.getOverview()).rejects.toThrow('Invalid school overview response');
  });

  it('posts only the payload supplied by the form and returns the API envelope', async () => {
    const payload = {
      participant_name: 'Participante',
      participant_birth_date: '1990-01-01',
      contact_phone: '600 000 000',
      contact_email: 'contacto@example.test',
    };
    const envelope = { message: 'Recibida.', data: null };
    api.post.mockResolvedValue({ data: envelope });

    await expect(schoolService.createEnrollment(payload)).resolves.toEqual(envelope);
    expect(api.post).toHaveBeenCalledWith('/school/enrollments', payload, { signal: undefined });
  });
});
