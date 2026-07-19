import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { Layout } from './Layout';

describe('Layout', () => {
  it('does not create a nested main landmark inside the application main', () => {
    renderWithProviders(
      <main>
        <Layout>
          <h1>Inicio de prueba</h1>
        </Layout>
      </main>,
    );

    expect(screen.getAllByRole('main')).toHaveLength(1);
  });
});
