import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import { Home } from './pages/Home/Home';
import { Nosotros } from './pages/Nosotros/Nosotros';
import { TournamentList } from './pages/Torneos/TournamentList';
import { TournamentDetail } from './pages/Torneos/TournamentDetail';
import { CategoryDetail } from './pages/Torneos/CategoryDetail';
import { Navbar } from './components/Navbar/Navbar';
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
import { LearnPage } from './pages/Learn/LearnPage';
import { ManualPage } from './pages/Learn/ManualPage';
import { KnowledgeDocumentPage } from './pages/Learn/KnowledgeDocumentPage';
import './index.css';

function App() {
  return (
    <AuthProvider>
      <Router>
        <div className="app-layout">
          <Navbar />

          <main className="main-content">
            <Routes>
              <Route path="/" element={<Home />} />
              <Route path="/competicion" element={<CompetitionPage />} />
              <Route path="/aprende-a-jugar" element={<LearnPage />} />
              <Route path="/aprende-a-jugar/manual" element={<ManualPage />} />
              <Route
                path="/aprende-a-jugar/manual/reglamento/:slug"
                element={<KnowledgeDocumentPage type="regulation" />}
              />
              <Route
                path="/aprende-a-jugar/manual/conceptos/:group/:slug"
                element={<KnowledgeDocumentPage type="concept" />}
              />
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
                element={
                  <ProtectedRoute>
                    <Dashboard />
                  </ProtectedRoute>
                }
              />
              <Route path="/rankings" element={<Rankings />} />
              <Route path="*" element={<NotFoundPage />} />
            </Routes>
          </main>
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;
