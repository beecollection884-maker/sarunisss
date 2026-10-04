import React from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export const AuthLayout = () => {
    const { user, isLoading } = useAuth();

    if (isLoading) {
        return <div className="min-h-screen flex items-center justify-center bg-slate-50">Memuat...</div>;
    }

    if (user) {
        return <Navigate to="/" replace />;
    }

    return (
        <Outlet />
    );
};
