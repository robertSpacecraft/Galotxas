import { render } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { AuthContext } from '../context/authContext';

export const renderWithProviders = (
  ui,
  {
    route = '/',
    routePath = null,
    authValue = null,
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
      {content}
    </MemoryRouter>,
  );
};
