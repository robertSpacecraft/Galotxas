import { render } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { AuthContext } from '../context/authContext';
import { SeoProvider } from '../seo/SeoProvider';

export const renderWithProviders = (
  ui,
  {
    route = '/',
    routePath = null,
    authValue = null,
    seoConfig = undefined,
  } = {},
) => {
  const routedUi = routePath ? (
    <Routes>
      <Route path={routePath} element={ui} />
    </Routes>
  ) : ui;

  const content = authValue ? (
    <AuthContext.Provider value={authValue}>{routedUi}</AuthContext.Provider>
  ) : routedUi;

  return render(
    <MemoryRouter initialEntries={[route]}>
      <SeoProvider config={seoConfig}>
        {content}
      </SeoProvider>
    </MemoryRouter>,
  );
};
