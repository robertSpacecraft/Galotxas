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
import Dashboard from './pages/Dashboard';
import { Rankings } from './pages/Rankings/Rankings';
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
              <Route path="/nosotros" element={<Nosotros />} />
              <Route path="/torneos" element={<TournamentList />} />
              <Route path="/torneos/:championshipId" element={<TournamentDetail />} />
              <Route path="/categories/:categoryId" element={<CategoryDetail />} />
              <Route path="/categories/:categoryId/standings" element={<StandingsPage />} />
              <Route path="/categories/:categoryId/schedule" element={<SchedulePage />} />
              <Route path="/matches/:matchId" element={<MatchDetails />} />
              <Route path="/login" element={<Login />} />
              <Route
                path="/player"
                element={
                  <ProtectedRoute>
                    <Dashboard />
                  </ProtectedRoute>
                }
              />
              {/* Optional: Add placeholder routes for new sections if needed */}
              <Route path="/torneos" element={<h2 style={{padding: '5rem', textAlign: 'center'}}>Sección Torneos (En construcción)</h2>} />
              <Route path="/rankings" element={<Rankings />} />
            </Routes>
          </main>
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;