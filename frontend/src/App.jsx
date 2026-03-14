import { BrowserRouter as Router, Routes, Route, Link } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Home from './pages/Home';
import Standings from './pages/Standings';
import Schedule from './pages/Schedule';
import MatchDetails from './pages/MatchDetails';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import './index.css';

function Navigation() {
  const { isAuthenticated, logout } = useAuth();

  return (
      <nav className="navbar">
        <div className="navbar-brand">
          <Link to="/">Galotxas</Link>
        </div>

        <div className="navbar-links">
          <Link to="/">Inicio</Link>

          {isAuthenticated ? (
              <>
                <Link to="/player">Mi panel</Link>
                <button
                    onClick={logout}
                    style={{
                      background: 'transparent',
                      border: 'none',
                      color: 'var(--text-secondary)',
                      cursor: 'pointer',
                      fontSize: '0.95rem',
                      fontWeight: '500'
                    }}
                >
                  Salir
                </button>
              </>
          ) : (
              <Link to="/login">Acceso jugadores</Link>
          )}
        </div>
      </nav>
  );
}

function App() {
  return (
      <AuthProvider>
        <Router>
          <div className="app-layout">
            <Navigation />

            <main className="main-content">
              <Routes>
                <Route path="/" element={<Home />} />
                <Route path="/categories/:categoryId/standings" element={<Standings />} />
                <Route path="/categories/:categoryId/schedule" element={<Schedule />} />
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
              </Routes>
            </main>
          </div>
        </Router>
      </AuthProvider>
  );
}

export default App;