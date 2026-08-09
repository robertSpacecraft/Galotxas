import { lazy, Suspense } from 'react';
import { BrowserRouter as Router, Routes, Route, useLocation } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import { Home } from './pages/Home/Home';
import { Nosotros } from './pages/Nosotros/Nosotros';
import { TournamentList } from './pages/Torneos/TournamentList';
import { TournamentDetail } from './pages/Torneos/TournamentDetail';
import { CategoryDetail } from './pages/Torneos/CategoryDetail';
import { Navbar } from './components/Navbar/Navbar';
import { Footer } from './components/Footer/Footer';
import StandingsPage from './pages/Standings';
import SchedulePage from './pages/Schedule';
import MatchDetails from './pages/MatchDetails';
import Login from './pages/Login';
import Register from './pages/Register';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import Dashboard from './pages/Dashboard';
import { Rankings } from './pages/Rankings/Rankings';
import { CmsPageIndex } from './pages/CmsPageIndex/CmsPageIndex';
import { CmsPage } from './pages/CmsPage/CmsPage';
import { CompetitionPage } from './pages/Competition/CompetitionPage';
import { NotFoundPage } from './pages/NotFound/NotFoundPage';
import { RouteLoading } from './components/RouteLoading/RouteLoading';
import { clubPages } from './features/club/clubRoutes';
import { legalPages } from './features/legal/legalRoutes';
import { schoolPath } from './features/school/schoolRoutes';
import { RouteAccessibility } from './seo/RouteAccessibility';
import { SeoProvider } from './seo/SeoProvider';
import './index.css';

const LearnPage = lazy(() => import('./pages/Learn/LearnPage'));
const ManualPage = lazy(() => import('./pages/Learn/ManualPage'));
const KnowledgeDocumentPage = lazy(() => import('./pages/Learn/KnowledgeDocumentPage'));
const SchoolPage = lazy(() => import('./features/school/SchoolPage'));
const ClubPage = lazy(() => import('./features/club/ClubPage'));
const LegalPage = lazy(() => import('./features/legal/LegalPage'));
const PublicIdentityConfirmationPage = lazy(
  () => import('./features/publicIdentity/PublicIdentityConfirmationPage'),
);

export const KnowledgeRoute = ({ children }) => (
  <Suspense fallback={<RouteLoading label="Cargando Aprende a jugar" />}>
    {children}
  </Suspense>
);

export const SchoolRoute = ({ children }) => (
  <Suspense fallback={<RouteLoading label="Cargando Escuela de Galotxas" />}>
    {children}
  </Suspense>
);

export const ClubRoute = ({ children }) => (
  <Suspense fallback={<RouteLoading label="Cargando Club" />}>
    {children}
  </Suspense>
);

export const LegalRoute = ({ children }) => (
  <Suspense fallback={<RouteLoading label="Cargando información legal" />}>
    {children}
  </Suspense>
);

const AppContent = () => {
  const location = useLocation();
  const isPublicIdentityConfirmation = location.pathname === '/public-identity/confirm';

  return (
    <div className="app-layout">
      {!isPublicIdentityConfirmation ? <Navbar /> : null}

      <main id="main-content" className="main-content" tabIndex="-1">
        <Routes>
          <Route
            path="/public-identity/confirm"
            element={(
              <Suspense fallback={<RouteLoading label="Comprobando autorización" />}>
                <PublicIdentityConfirmationPage />
              </Suspense>
            )}
          />
          <Route path="/" element={<Home />} />
          <Route path="/competicion" element={<CompetitionPage />} />
          <Route
            path="/aprende-a-jugar"
            element={<KnowledgeRoute><LearnPage /></KnowledgeRoute>}
          />
          <Route
            path="/aprende-a-jugar/manual"
            element={<KnowledgeRoute><ManualPage /></KnowledgeRoute>}
          />
          <Route
            path="/aprende-a-jugar/manual/reglamento/:slug"
            element={(
              <KnowledgeRoute>
                <KnowledgeDocumentPage type="regulation" />
              </KnowledgeRoute>
            )}
          />
          <Route
            path="/aprende-a-jugar/manual/conceptos/:group/:slug"
            element={(
              <KnowledgeRoute>
                <KnowledgeDocumentPage type="concept" />
              </KnowledgeRoute>
            )}
          />
          <Route
            path={schoolPath()}
            element={<SchoolRoute><SchoolPage /></SchoolRoute>}
          />
          {Object.values(clubPages).map((clubPage) => (
            <Route
              key={clubPage.id}
              path={clubPage.path}
              element={(
                <ClubRoute>
                  <ClubPage pageId={clubPage.id} />
                </ClubRoute>
              )}
            />
          ))}
          {Object.values(legalPages).map((legalPage) => (
            <Route
              key={legalPage.id}
              path={legalPage.path}
              element={(
                <LegalRoute>
                  <LegalPage pageId={legalPage.id} />
                </LegalRoute>
              )}
            />
          ))}
          <Route path="/nosotros" element={<Nosotros />} />
          <Route path="/torneos" element={<TournamentList />} />
          <Route path="/torneos/:championshipId" element={<TournamentDetail />} />
          <Route path="/categories/:categoryId" element={<CategoryDetail />} />
          <Route path="/categories/:categoryId/standings" element={<StandingsPage />} />
          <Route path="/categories/:categoryId/schedule" element={<SchedulePage />} />
          <Route path="/matches/:matchId" element={<MatchDetails />} />
          <Route path="/contenidos" element={<CmsPageIndex />} />
          <Route path="/contenidos/:slug" element={<CmsPage />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/forgot-password" element={<ForgotPassword />} />
          <Route path="/reset-password" element={<ResetPassword />} />
          <Route
            path="/player"
            element={(
              <ProtectedRoute>
                <Dashboard />
              </ProtectedRoute>
            )}
          />
          <Route path="/rankings" element={<Rankings />} />
          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </main>
      {!isPublicIdentityConfirmation ? <Footer /> : null}
    </div>
  );
};

function App() {
  return (
    <AuthProvider>
      <Router>
        <SeoProvider>
          <RouteAccessibility />
          <AppContent />
        </SeoProvider>
      </Router>
    </AuthProvider>
  );
}

export default App;
