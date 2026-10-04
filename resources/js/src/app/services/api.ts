import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true, // Required for Sanctum SPA authentication
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    }
});

// Request interceptor for CSRF token if needed (Sanctum usually handles this automatically if using cookie-based auth, but we might need to fetch the csrf cookie first)
api.interceptors.request.use(async (config) => {
    // If not a GET request, ensure CSRF token is present
    if (config.method !== 'get') {
        const token = document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='));
        if (!token) {
            try {
                await axios.get('/sanctum/csrf-cookie', { baseURL: '' });
            } catch (error) {
                console.error('Could not fetch CSRF token', error);
            }
        }
    }
    return config;
});

// Response interceptor for handling common errors
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Unauthorized, redirect to login or clear auth state
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;
