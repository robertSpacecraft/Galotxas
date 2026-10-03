import { Navigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { AuthRestoreFeedback } from './AuthRestoreFeedback/AuthRestoreFeedback';

export default function ProtectedRoute({ children, requireAdmin = false }) {
    const { isAuthenticated, isAdmin, authStatus, retrySessionRestore } = useAuth();

    if (authStatus === 'restoring' || authStatus === 'failed') {
        return <AuthRestoreFeedback status={authStatus} onRetry={() => retrySessionRestore()} />;
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    if (requireAdmin && !isAdmin) {
        return <Navigate to="/dashboard" replace />; // Redirect non-admins to their dashboard
    }

    return children;
}
