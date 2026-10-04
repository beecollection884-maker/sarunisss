import React, { createContext, useContext, useState, useEffect } from 'react';
import api from '../services/api';

export type Role = "administrator" | "guru-mapel" | "wali-kelas" | "siswa" | "orang-tua" | "guru-bk" | "wakil-kepala";

export interface User {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    avatar?: string;
    activePortal?: string;
}

interface AuthContextType {
    user: User | null;
    isLoading: boolean;
    login: (userData: User) => void;
    logout: () => Promise<void>;
    checkAuth: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const checkAuth = async () => {
        try {
            const response = await api.get('/auth/me');
            if (response.data?.data) {
                // Map the backend role to frontend role (assuming the backend gives us an array of roles or a primary role)
                const backendUser = response.data.data;
                // The backend buildPayload returns { user: {...}, logged_in_user: {...}, roles: [...] }
                const mappedUser: User = {
                    id: backendUser.logged_in_user?.id || backendUser.user?.id,
                    name: backendUser.logged_in_user?.name || backendUser.user?.name || "User",
                    email: backendUser.logged_in_user?.email || backendUser.user?.email || "",
                    roles: backendUser.roles || ["administrator"], // fallback mapping logic
                    avatar: backendUser.user?.avatar,
                    activePortal: backendUser.active_portal,
                };
                setUser(mappedUser);
            }
        } catch (error) {
            setUser(null);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        checkAuth();
    }, []);

    const login = (userData: User) => {
        setUser(userData);
    };

    const logout = async () => {
        try {
            await api.post('/auth/logout');
        } finally {
            setUser(null);
            window.location.href = '/login';
        }
    };

    return (
        <AuthContext.Provider value={{ user, isLoading, login, logout, checkAuth }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};
