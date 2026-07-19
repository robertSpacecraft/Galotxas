import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Link, Route, Routes } from 'react-router-dom';
import { renderWithProviders } from '../../test/renderWithProviders';
import { PageMetadata } from './PageMetadata';

const removeTestMetadata = () => {
  document.head.querySelectorAll('meta[name="description"], meta[name="robots"]')
    .forEach((element) => element.remove());
};

describe('PageMetadata', () => {
  beforeEach(() => {
    removeTestMetadata();
    document.title = 'Galotxas base';
  });

  afterEach(() => {
    removeTestMetadata();
    document.title = 'frontend';
  });

  it('updates the title and description and restores the previous document state', () => {
    const { unmount } = renderWithProviders(
      <PageMetadata title="Ruta de prueba | Galotxas" description="Descripción de prueba." />,
    );

    expect(document.title).toBe('Ruta de prueba | Galotxas');
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Descripción de prueba.',
    );

    unmount();

    expect(document.title).toBe('Galotxas base');
    expect(document.head.querySelector('meta[name="description"]')).toBeNull();
  });

  it('updates one existing description without creating duplicates and restores its content', () => {
    const existingDescription = document.createElement('meta');
    existingDescription.setAttribute('name', 'description');
    existingDescription.setAttribute('content', 'Descripción anterior.');
    document.head.appendChild(existingDescription);

    const { unmount } = renderWithProviders(
      <PageMetadata title="Nueva ruta | Galotxas" description="Descripción nueva." />,
    );

    expect(document.head.querySelectorAll('meta[name="description"]')).toHaveLength(1);
    expect(existingDescription).toHaveAttribute('content', 'Descripción nueva.');

    unmount();

    expect(existingDescription).toHaveAttribute('content', 'Descripción anterior.');
  });

  it('substitutes metadata during SPA navigation and prevents 404 inheritance', async () => {
    const user = userEvent.setup();

    renderWithProviders(
      <Routes>
        <Route
          path="/competicion"
          element={(
            <>
              <PageMetadata
                title="Competición | Galotxas"
                description="Descripción de Competición."
              />
              <Link to="/ruta-inexistente">Abrir ruta inexistente</Link>
            </>
          )}
        />
        <Route
          path="*"
          element={(
            <>
              <PageMetadata
                title="Página no encontrada | Galotxas"
                description="Descripción de error."
                robots="noindex"
              />
              <Link to="/competicion">Volver a Competición</Link>
            </>
          )}
        />
      </Routes>,
      { route: '/competicion' },
    );

    expect(document.title).toBe('Competición | Galotxas');
    await user.click(screen.getByRole('link', { name: 'Abrir ruta inexistente' }));

    expect(document.title).toBe('Página no encontrada | Galotxas');
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Descripción de error.',
    );
    expect(document.head.querySelector('meta[name="robots"]')).toHaveAttribute(
      'content',
      'noindex',
    );
    expect(document.head.querySelectorAll('meta[name="description"]')).toHaveLength(1);

    await user.click(screen.getByRole('link', { name: 'Volver a Competición' }));

    expect(document.title).toBe('Competición | Galotxas');
    expect(document.head.querySelector('meta[name="robots"]')).toBeNull();
  });
});
