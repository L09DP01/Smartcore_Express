// PWA Global - Script universel pour toutes les pages
class GlobalPWA {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.isOnline = navigator.onLine;
        this.init();
    }

    async init() {
        console.log('🚀 Initialisation PWA Global');
        
        // Enregistrer le service worker
        await this.registerServiceWorker();
        
        // Configurer les événements PWA
        this.setupInstallPrompt();
        this.setupConnectionStatus();
        this.checkInstallStatus();
        
        // Créer le bouton d'installation
        this.createInstallButton();
        
        console.log('✅ PWA Global initialisé avec succès');
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                // Déterminer le bon chemin du service worker selon la page
                const swPath = this.getServiceWorkerPath();
                const registration = await navigator.serviceWorker.register(swPath, {
                    scope: '/'
                });
                
                console.log('✅ Service Worker enregistré:', registration.scope);
                
                // Vérifier les mises à jour
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });
                
                return registration;
            } catch (error) {
                console.error('❌ Erreur d\'enregistrement du Service Worker:', error);
            }
        } else {
            console.warn('⚠️ Service Worker non supporté par ce navigateur');
        }
    }

    getServiceWorkerPath() {
        // Utiliser toujours le service worker global
        return '/sw.js';
    }

    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('📱 Événement beforeinstallprompt détecté');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallButton();
        });

        window.addEventListener('appinstalled', () => {
            console.log('🎉 Application installée avec succès');
            this.isInstalled = true;
            this.hideInstallButton();
            this.showInstallSuccessMessage();
            this.deferredPrompt = null;
        });
    }

    setupConnectionStatus() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateConnectionStatus();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateConnectionStatus();
        });
    }

    checkInstallStatus() {
        // Vérifier si l'app est déjà installée
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            this.isInstalled = true;
            console.log('📱 Application déjà installée (mode standalone)');
        }
        
        // Vérifier via navigator.standalone (iOS)
        if (window.navigator.standalone === true) {
            this.isInstalled = true;
            console.log('📱 Application déjà installée (iOS standalone)');
        }
    }

    createInstallButton() {
        // Vérifier si le bouton existe déjà
        if (document.getElementById('global-pwa-install-button')) {
            return;
        }

        const button = document.createElement('button');
        button.id = 'global-pwa-install-button';
        button.className = 'fixed bottom-4 right-4 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-lg transition-all duration-300 transform translate-y-20 opacity-0 z-50 flex items-center space-x-2 text-sm';
        button.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>Install app</span>
        `;
        
        button.addEventListener('click', () => this.installApp());
        document.body.appendChild(button);
        
        // Masquer le bouton par défaut
        button.style.display = 'none';
    }

    showInstallButton() {
        if (this.isInstalled) return;
        
        const button = document.getElementById('global-pwa-install-button');
        if (button) {
            button.style.display = 'flex';
            // Animation d'apparition
            setTimeout(() => {
                button.classList.remove('translate-y-20', 'opacity-0');
                button.classList.add('translate-y-0', 'opacity-100');
            }, 100);
        }
    }

    hideInstallButton() {
        const button = document.getElementById('global-pwa-install-button');
        if (button) {
            button.classList.add('translate-y-20', 'opacity-0');
            button.classList.remove('translate-y-0', 'opacity-100');
            setTimeout(() => {
                button.style.display = 'none';
            }, 300);
        }
    }

    async installApp() {
        if (!this.deferredPrompt) {
            console.warn('⚠️ Aucun prompt d\'installation disponible');
            this.showToast('Installation non disponible', 'warning');
            return;
        }

        try {
            // Afficher le prompt d'installation
            this.deferredPrompt.prompt();
            
            // Attendre la réponse de l'utilisateur
            const { outcome } = await this.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('✅ Utilisateur a accepté l\'installation');
                this.showToast('Installation en cours...', 'success');
            } else {
                console.log('❌ Utilisateur a refusé l\'installation');
                this.showToast('Installation annulée', 'info');
            }
            
            this.deferredPrompt = null;
        } catch (error) {
            console.error('❌ Erreur lors de l\'installation:', error);
            this.showToast('Erreur lors de l\'installation', 'error');
        }
    }

    showInstallSuccessMessage() {
        this.showToast('🎉 Application installée avec succès!', 'success');
    }

    showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-blue-600 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
        notification.innerHTML = `
            <div class="flex items-center space-x-3">
                <svg class="w-6 h-6 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <div>
                    <p class="font-medium">Mise à jour disponible</p>
                    <p class="text-sm text-blue-200">Rechargez pour obtenir la dernière version</p>
                </div>
                <button onclick="window.location.reload()" class="bg-white text-blue-600 px-3 py-1 rounded text-sm font-medium hover:bg-blue-50">
                    Recharger
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Supprimer automatiquement après 10 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 10000);
    }

    updateConnectionStatus() {
        const statusElement = document.getElementById('global-connection-status');
        
        if (!statusElement) {
            // Créer l'indicateur de statut s'il n'existe pas
            const status = document.createElement('div');
            status.id = 'global-connection-status';
            status.className = 'fixed top-4 left-4 px-3 py-1 rounded-full text-sm font-medium z-40 transition-all duration-300';
            document.body.appendChild(status);
        }
        
        const status = document.getElementById('global-connection-status');
        
        if (this.isOnline) {
            status.className = 'fixed top-4 left-4 px-3 py-1 rounded-full text-sm font-medium z-40 transition-all duration-300 bg-green-100 text-green-800';
            status.innerHTML = '🟢 En ligne';
            
            // Masquer après 3 secondes
            setTimeout(() => {
                status.style.opacity = '0';
            }, 3000);
        } else {
            status.className = 'fixed top-4 left-4 px-3 py-1 rounded-full text-sm font-medium z-40 transition-all duration-300 bg-red-100 text-red-800';
            status.innerHTML = '🔴 Hors ligne';
            status.style.opacity = '1';
        }
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            warning: 'bg-yellow-600',
            info: 'bg-blue-600'
        };
        
        toast.className = `fixed bottom-20 left-1/2 transform -translate-x-1/2 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 opacity-0 translate-y-4`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // Animation d'apparition
        setTimeout(() => {
            toast.classList.remove('opacity-0', 'translate-y-4');
            toast.classList.add('opacity-100', 'translate-y-0');
        }, 100);
        
        // Suppression automatique
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-4');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }, 3000);
    }
}

// Initialiser le PWA global quand le DOM est chargé
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.globalPWA = new GlobalPWA();
    });
} else {
    window.globalPWA = new GlobalPWA();
}

// Exporter pour utilisation globale
window.GlobalPWA = GlobalPWA;