import { createContext, useContext, useState, useEffect } from 'react';
import api from '../api/client';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        try {
            const storedToken = localStorage.getItem('token');
            const storedUser = localStorage.getItem('user');
            
            if (storedToken && storedUser) {
                setToken(storedToken);
                setUser(JSON.parse(storedUser));
            }
        } catch (error) {
            console.error("Error loading auth from localStorage", error);
            // Clear corrupted data
            localStorage.removeItem('token');
            localStorage.removeItem('user');
        } finally {
            setLoading(false);
        }
    }, []);

    const login = async (email, password) => {
        const response = await api.post('/auth/login', { email, password });
        const rawData = response.data.data;
        
        let userData = rawData.user ? { ...rawData.user } : { ...rawData };
        if (rawData.player) {
            userData.player = rawData.player;
        }
        
        localStorage.setItem('token', rawData.token);
        localStorage.setItem('user', JSON.stringify(userData));
        
        setToken(rawData.token);
        setUser(userData);
        return userData;
    };

    const register = async (userDataInput) => {
        const response = await api.post('/auth/register', userDataInput);
        const rawData = response.data.data;
        
        let userData = rawData.user ? { ...rawData.user } : { ...rawData };
        if (rawData.player) {
            userData.player = rawData.player;
        }
        
        localStorage.setItem('token', rawData.token);
        localStorage.setItem('user', JSON.stringify(userData));
        
        setToken(rawData.token);
        setUser(userData);
        return userData;
    };

    const createPlayerProfile = async (playerData) => {
        const response = await api.post('/me/player-profile', playerData);
        const newPlayer = response.data.data;
        setUser(prev => {
            // Merge with prev or fallback to localStorage if state is lost
            const currentBase = prev || JSON.parse(localStorage.getItem('user')) || {};
            const updatedUser = { ...currentBase, player: newPlayer };
            localStorage.setItem('user', JSON.stringify(updatedUser));
            return updatedUser;
        });
        return newPlayer;
    };

    const refreshUser = async () => {
        try {
            const response = await api.get('/me');
            const rawData = response.data.data;
            
            let userData = rawData.user ? { ...rawData.user } : { ...rawData };
            if (rawData.player) {
                userData.player = rawData.player;
            }
            
            localStorage.setItem('user', JSON.stringify(userData));
            setUser(userData);
            return userData;
        } catch (error) {
            console.error("Error refreshing user data:", error);
            if (error.response?.status === 401) {
                logout();
            }
            throw error;
        }
    };

    const forgotPassword = async (email) => {
        const response = await api.post('/auth/forgot-password', { email });
        return response.data;
    };

    const resetPassword = async (data) => {
        const response = await api.post('/auth/reset-password', data);
        return response.data;
    };

    const logout = () => {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        setToken(null);
        setUser(null);
    };

    const value = {
        user,
        token,
        login,
        register,
        logout,
        createPlayerProfile,
        forgotPassword,
        resetPassword,
        refreshUser,
        isAuthenticated: !!token,
        isAdmin: user?.role === 'admin'
    };

    return (
        <AuthContext.Provider value={value}>
            {!loading && children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};
