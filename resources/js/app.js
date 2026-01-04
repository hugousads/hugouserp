import './bootstrap';
import * as Turbo from '@hotwired/turbo';
import { erpPosTerminal } from './pos';
import Swal from 'sweetalert2';
import Chart from 'chart.js/auto';

// Initialize Turbo.js for SPA-like navigation
try {
    Turbo.start();
    window.Turbo = Turbo;
    window.TurboLoaded = true;
} catch (e) {
    console.warn('Turbo.js initialization failed:', e);
    window.TurboLoaded = false;
}

window.erpPosTerminal = erpPosTerminal;
window.Swal = Swal;
window.Chart = Chart;

const NotificationSound = {
    audio: null,
    enabled: true,
    
    init() {
        try {
            this.audio = new Audio('/sounds/notification.mp3');
            this.audio.preload = 'auto';
            this.audio.volume = 0.5;
            const savedPref = localStorage.getItem('erp_notification_sound');
            this.enabled = savedPref !== '0';
        } catch (e) {
            console.warn('Notification sound initialization failed:', e);
        }
    },
    
    play() {
        if (!this.enabled || !this.audio) return;
        try {
            this.audio.currentTime = 0;
            this.audio.play().catch(() => {});
        } catch (e) {}
    },
    
    toggle() {
        this.enabled = !this.enabled;
        localStorage.setItem('erp_notification_sound', this.enabled ? '1' : '0');
        return this.enabled;
    },
    
    setVolume(vol) {
        if (this.audio) {
            this.audio.volume = Math.max(0, Math.min(1, vol));
        }
    },
    
    isEnabled() {
        return this.enabled;
    }
};

NotificationSound.init();
window.erpNotificationSound = NotificationSound;

if (window.Echo && window.Laravel && window.Laravel.userId) {
    window.Echo.private(`App.Models.User.${window.Laravel.userId}`)
        .listen('.notification.created', (e) => {
            if (window.Livewire) {
                window.Livewire.dispatch('notification-received', {
                    type: e.type ?? 'info',
                    message: e.message ?? '',
                });
            }
            window.erpShowNotification(e.message, e.type, true);
        });
}

if (typeof window !== 'undefined') {
    window.erpApplyTheme = function () {
        try {
            const saved = localStorage.getItem('erp_dark');
            const isDark = saved === '1';
            document.documentElement.classList.toggle('dark', isDark);
        } catch (e) {}
    };

    window.erpToggleDarkMode = function () {
        try {
            const isDark = document.documentElement.classList.contains('dark');
            const next = !isDark;
            document.documentElement.classList.toggle('dark', next);
            localStorage.setItem('erp_dark', next ? '1' : '0');
        } catch (e) {}
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.erpApplyTheme();
    });
}

window.erpShowToast = function (message, type = 'success') {
    try {
        const root = document.getElementById('erp-toast-root');
        if (!root) return;
        const el = document.createElement('div');
        el.className = 'pointer-events-auto mb-2 inline-flex items-center rounded-2xl px-4 py-2 text-sm shadow-lg bg-white/90 text-slate-900 border border-slate-200';
        if (type === 'success') {
            el.className += ' border-emerald-300 shadow-emerald-200';
        } else if (type === 'error') {
            el.className += ' border-rose-300 shadow-rose-200';
        }
        el.innerText = message || 'Saved';
        root.appendChild(el);
        setTimeout(() => {
            el.classList.add('opacity-0', 'translate-y-1');
            setTimeout(() => el.remove(), 200);
        }, 2200);
    } catch (e) {}
};

window.erpShowNotification = function (message, type = 'info', playSound = false) {
    if (playSound && window.erpNotificationSound) {
        window.erpNotificationSound.play();
    }
    
    const Toast = Swal.mixin({
        toast: true,
        position: document.documentElement.dir === 'rtl' ? 'top-start' : 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });
    
    const icons = {
        success: 'success',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };
    
    Toast.fire({
        icon: icons[type] || 'info',
        title: message
    });
};

window.erpPlayNotificationSound = function () {
    if (window.erpNotificationSound) {
        window.erpNotificationSound.play();
    }
};

window.erpToggleNotificationSound = function () {
    if (window.erpNotificationSound) {
        return window.erpNotificationSound.toggle();
    }
    return false;
};

window.erpSetNotificationVolume = function (volume) {
    if (window.erpNotificationSound) {
        window.erpNotificationSound.setVolume(volume);
    }
};

window.erpConfirm = function (options = {}) {
    const defaults = {
        title: 'Are you sure?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'Cancel'
    };
    
    return Swal.fire({ ...defaults, ...options });
};

window.erpAlert = function (title, text = '', icon = 'info') {
    return Swal.fire({
        title,
        text,
        icon,
        confirmButtonColor: '#10b981'
    });
};

window.erpLoading = function (title = 'Loading...') {
    Swal.fire({
        title,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
};

window.erpCloseLoading = function () {
    Swal.close();
};

window.erpCreateChart = function (ctx, config) {
    return new Chart(ctx, config);
};

document.addEventListener('livewire:navigated', () => {
    window.erpApplyTheme && window.erpApplyTheme();
});

window.addEventListener('swal:success', event => {
    const playSound = event.detail.playSound ?? false;
    window.erpShowNotification(event.detail.message || 'Success!', 'success', playSound);
});

window.addEventListener('swal:error', event => {
    const playSound = event.detail.playSound ?? false;
    window.erpShowNotification(event.detail.message || 'Error occurred!', 'error', playSound);
});

window.addEventListener('play-notification-sound', () => {
    window.erpPlayNotificationSound();
});

window.addEventListener('swal:confirm', event => {
    window.erpConfirm({
        title: event.detail.title || 'Are you sure?',
        text: event.detail.text || '',
        confirmButtonText: event.detail.confirmText || 'Yes',
        cancelButtonText: event.detail.cancelText || 'Cancel'
    }).then((result) => {
        if (result.isConfirmed && event.detail.callback) {
            window.Livewire.dispatch(event.detail.callback, event.detail.params || {});
        }
    });
});

// Service Worker Registration for Offline Support
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('[ERP] Service Worker registered:', registration.scope);
                
                // Listen for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New content available, prompt user to refresh
                                if (window.Swal) {
                                    Swal.fire({
                                        title: 'Update Available',
                                        text: 'A new version of HugousERP is available. Would you like to refresh?',
                                        icon: 'info',
                                        showCancelButton: true,
                                        confirmButtonText: 'Refresh',
                                        cancelButtonText: 'Later',
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            newWorker.postMessage({ type: 'SKIP_WAITING' });
                                            window.location.reload();
                                        }
                                    });
                                }
                            }
                        });
                    }
                });
            })
            .catch((error) => {
                console.warn('[ERP] Service Worker registration failed:', error);
            });
        
        // Handle messages from service worker
        navigator.serviceWorker.addEventListener('message', (event) => {
            const { type, timestamp, url } = event.data || {};
            
            if (type === 'SYNC_OFFLINE_SALES') {
                // Trigger offline sales sync
                if (window.Livewire) {
                    window.Livewire.dispatch('sync-offline-sales');
                }
            }
            
            if (type === 'SYNC_OFFLINE_DATA') {
                // Trigger general offline data sync
                if (window.Livewire) {
                    window.Livewire.dispatch('sync-offline-data');
                }
            }
            
            if (type === 'NAVIGATE' && url) {
                // Handle navigation request from service worker
                window.location.href = url;
            }
        });
    });
}

// Offline/Online status indicators
window.addEventListener('online', () => {
    document.body.classList.remove('is-offline');
    if (window.erpShowNotification) {
        window.erpShowNotification('Connection restored', 'success');
    }
    // Trigger sync if registration supports it
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.ready.then((registration) => {
            if (registration.sync) {
                registration.sync.register('sync-offline-data').catch(() => {});
            }
        });
    }
});

window.addEventListener('offline', () => {
    document.body.classList.add('is-offline');
    if (window.erpShowNotification) {
        window.erpShowNotification('You are offline. Some features may be limited.', 'warning');
    }
});
