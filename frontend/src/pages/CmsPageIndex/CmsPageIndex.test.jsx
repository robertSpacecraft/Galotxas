import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { cmsService } from '../../api/cms';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CmsPageIndex } from './CmsPageIndex';

vi.mock('../../api/cms', () => ({
  cmsService: {
    getPublishedPages: vi.fn(),
  },
}));

describe('CmsPageIndex', () => {
  it('keeps a single application main landmark while loading legacy contents', () => {
    cmsService.getPublishedPages.mockReturnValue(new Promise(() => {}));

    renderWithProviders(
      <main>
        <CmsPageIndex />
      </main>,
      { route: '/contenidos' },
    );

    expect(screen.getAllByRole('main')).toHaveLength(1);
    expect(screen.getByText('Cargando contenidos...')).toBeInTheDocument();
  });
});
