import React from 'react';
import Swal from 'sweetalert2';

// Custom CSS styling for SweetAlert2 to match modern Premium Design System (Inter / Outfit typography, rounded corners, soft shadows)
const swalCustomClass = {
    popup: 'rounded-2xl border border-slate-100 shadow-xl bg-white font-sans',
    title: 'text-lg font-bold text-slate-800 font-sans',
    htmlContainer: 'text-sm text-slate-600 font-sans',
    confirmButton: 'px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold transition-colors outline-none focus:ring-2 focus:ring-blue-100',
    cancelButton: 'px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-semibold transition-colors outline-none focus:ring-2 focus:ring-slate-100',
    actions: 'gap-2 pt-2'
};

// Premium Toast Mixin for successes and short alerts
const swalToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    background: '#ffffff',
    color: '#1e293b',
    iconColor: '#3b82f6',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
        // Apply custom fonts/styles to the toast
        toast.style.fontFamily = 'Outfit, Inter, system-ui, sans-serif';
        toast.style.borderRadius = '16px';
        toast.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)';
        toast.style.border = '1px solid #f1f5f9';
    }
});

// Toast implementation supporting function-style calls: toast("message")
export const toast = (message: any, options?: any) => {
    swalToast.fire({
        icon: 'info',
        title: typeof message === 'string' ? message : (message?.title || 'Informasi'),
    });
};

// Success alerts: use Toast style for seamless, non-intrusive confirmation
toast.success = (message: any, options?: any) => {
    const text = typeof message === 'string' ? message : (message?.title || 'Sukses!');
    swalToast.fire({
        icon: 'success',
        title: text,
        iconColor: '#10b981', // Emerald green
    });
};

// Error alerts: use full blocking modal overlay with premium styling so admin gets explicit instructions
toast.error = (message: any, options?: any) => {
    const text = typeof message === 'string' ? message : (message?.title || 'Terjadi Kesalahan!');
    
    // Check if the message contains schedule conflict or import error warnings to format it beautifully
    const isConflict = text.includes('TABRAKAN') || text.includes('Konflik');

    Swal.fire({
        icon: 'error',
        title: isConflict ? 'Tabrakan Jadwal Terdeteksi' : 'Terjadi Kesalahan',
        html: `<div class="text-left font-medium leading-relaxed">${text.replace(/\n/g, '<br/>')}</div>`,
        buttonsStyling: false,
        customClass: swalCustomClass,
        iconColor: '#ef4444', // Red
        confirmButtonText: 'Mengerti',
    });
};

// Warning alerts: blocking modal or toast depending on the severity
toast.warning = (message: any, options?: any) => {
    const text = typeof message === 'string' ? message : (message?.title || 'Peringatan!');
    Swal.fire({
        icon: 'warning',
        title: 'Peringatan',
        html: `<div class="text-left font-medium leading-relaxed">${text.replace(/\n/g, '<br/>')}</div>`,
        buttonsStyling: false,
        customClass: swalCustomClass,
        iconColor: '#f59e0b', // Amber
        confirmButtonText: 'Oke',
    });
};

// Info alerts: Toast style
toast.info = (message: any, options?: any) => {
    const text = typeof message === 'string' ? message : (message?.title || 'Info');
    swalToast.fire({
        icon: 'info',
        title: text,
        iconColor: '#3b82f6', // Blue
    });
};

// Dummy Toaster component to ensure existing imports <Toaster /> in routes/index.tsx and sonner.tsx do not crash
export const Toaster = () => {
    return null;
};

export default toast;
