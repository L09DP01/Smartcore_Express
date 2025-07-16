/**
 * Gestionnaire d'activité de session
 * Maintient la session active et gère la déconnexion automatique
 */

class SessionManager {
    constructor() {
        this.activityTimer = null;
        this.warningTimer = null;
        this.lastActivity = Date.now();
        
        // Vérifier si le cookie remember_token existe
        const hasRememberToken = document.cookie.split(';').some(item => item.trim().startsWith('remember_token='));
        
        // 15 jours en millisecondes si remember_token existe, sinon 1 heure
        this.inactivityLimit = hasRememberToken ? 1296000000 : 3600000;
        
        // Avertir 15 minutes avant l'expiration
        this.warningTime = hasRememberToken ? (this.inactivityLimit - 900000) : 3300000;
        
        // Mettre à jour toutes les 5 minutes
        this.updateInterval = 300000;
        
        this.init();
    }
    
    init() {
        // Écouter les événements d'activité utilisateur
        this.bindActivityEvents();
        
        // Démarrer le timer de vérification d'inactivité
        this.startInactivityTimer();
        
        // Démarrer les mises à jour périodiques du serveur
        this.startPeriodicUpdates();
    }
    
    bindActivityEvents() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.updateActivity();
            }, true);
        });
    }
    
    updateActivity() {
        this.lastActivity = Date.now();
        
        // Supprimer l'avertissement s'il existe
        this.removeWarning();
        
        // Redémarrer le timer d'inactivité
        this.startInactivityTimer();
    }
    
    startInactivityTimer() {
        // Nettoyer les timers existants
        if (this.activityTimer) {
            clearTimeout(this.activityTimer);
        }
        if (this.warningTimer) {
            clearTimeout(this.warningTimer);
        }
        
        // Timer pour l'avertissement
        this.warningTimer = setTimeout(() => {
            this.showWarning();
        }, this.warningTime);
        
        // Timer pour la déconnexion
        this.activityTimer = setTimeout(() => {
            this.handleInactivity();
        }, this.inactivityLimit);
    }
    
    showWarning() {
        // Créer une notification d'avertissement
        const warning = document.createElement('div');
        warning.id = 'session-warning';
        warning.className = 'fixed top-4 right-4 bg-yellow-500 text-white p-4 rounded-lg shadow-lg z-50';
        warning.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>Votre session expirera dans 15 secondes...</span>
                <button onclick="sessionManager.extendSession()" class="ml-4 bg-white text-yellow-500 px-2 py-1 rounded text-sm">
                    Rester connecté
                </button>
            </div>
        `;
        
        document.body.appendChild(warning);
    }
    
    removeWarning() {
        const warning = document.getElementById('session-warning');
        if (warning) {
            warning.remove();
        }
    }
    
    extendSession() {
        this.updateActivity();
        this.updateServerActivity();
    }
    
    handleInactivity() {
        // Créer une modal pour l'expiration de session
        const modal = document.createElement('div');
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
        modal.style.display = 'flex';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'center';
        modal.style.zIndex = '9999';
        
        const modalContent = document.createElement('div');
        modalContent.style.backgroundColor = 'white';
        modalContent.style.padding = '30px';
        modalContent.style.borderRadius = '8px';
        modalContent.style.maxWidth = '450px';
        modalContent.style.width = '90%';
        modalContent.style.textAlign = 'center';
        modalContent.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
        modalContent.style.animation = 'fadeIn 0.3s ease-out';
        
        // Ajouter une animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
        
        // Icône d'avertissement
        const icon = document.createElement('div');
        icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;
        icon.style.marginBottom = '15px';
        icon.style.animation = 'pulse 2s infinite';
        
        const title = document.createElement('h3');
        title.textContent = 'Session expirée';
        title.style.marginBottom = '15px';
        title.style.color = '#e53e3e';
        title.style.fontSize = '24px';
        title.style.fontWeight = 'bold';
        
        const message = document.createElement('p');
        message.textContent = 'Votre session a expiré en raison d\'inactivité. Vous allez être redirigé vers la page de connexion.';
        message.style.marginBottom = '25px';
        message.style.fontSize = '16px';
        message.style.lineHeight = '1.5';
        message.style.color = '#4a5568';
        
        const button = document.createElement('button');
        button.textContent = 'OK';
        button.style.padding = '12px 30px';
        button.style.backgroundColor = '#3182ce';
        button.style.color = 'white';
        button.style.border = 'none';
        button.style.borderRadius = '6px';
        button.style.cursor = 'pointer';
        button.style.fontSize = '16px';
        button.style.fontWeight = 'bold';
        button.style.transition = 'background-color 0.2s';
        button.onmouseover = function() {
            this.style.backgroundColor = '#2c5282';
        };
        button.onmouseout = function() {
            this.style.backgroundColor = '#3182ce';
        };
        button.onclick = function() {
            window.location.href = '/Smartcore_Express/auth/login.php?';
        };
        
        // Compteur de redirection
        const counter = document.createElement('p');
        counter.style.marginTop = '15px';
        counter.style.fontSize = '14px';
        counter.style.color = '#718096';
        counter.textContent = 'Redirection automatique dans 5 secondes...';
        
        modalContent.appendChild(icon);
        modalContent.appendChild(title);
        modalContent.appendChild(message);
        modalContent.appendChild(button);
        modalContent.appendChild(counter);
        modal.appendChild(modalContent);
        
        document.body.appendChild(modal);
        
        // Mise à jour du compteur
        let secondsLeft = 5;
        const countdownInterval = setInterval(() => {
            secondsLeft--;
            if (secondsLeft > 0) {
                counter.textContent = `Redirection automatique dans ${secondsLeft} seconde${secondsLeft > 1 ? 's' : ''}...`;
            } else {
                counter.textContent = 'Redirection en cours...';
                clearInterval(countdownInterval);
            }
        }, 1000);
        
        // Rediriger après 5 secondes si l'utilisateur ne clique pas sur le bouton
        setTimeout(() => {
            window.location.href = '/Smartcore_Express/auth/login.php?expired=1';
        }, 5000);
    }
    
    startPeriodicUpdates() {
        // Mettre à jour l'activité sur le serveur toutes les 30 secondes
        setInterval(() => {
            if (Date.now() - this.lastActivity < this.updateInterval) {
                this.updateServerActivity();
            }
        }, this.updateInterval);
    }
    
    updateServerActivity() {
        fetch('/Smartcore_Express/auth/session_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'update_activity=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'error') {
                console.log('Erreur de session:', data.message);
                window.location.href = '/Smartcore_Express/auth/login.php';
            }
        })
        .catch(error => {
            console.error('Erreur de mise à jour de session:', error);
        });
    }
}

// Initialiser le gestionnaire de session quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    window.sessionManager = new SessionManager();
});

// Gérer la fermeture de l'onglet/fenêtre
window.addEventListener('beforeunload', function() {
    // Optionnel: informer le serveur que l'utilisateur quitte
    navigator.sendBeacon('/Smartcore_Express/auth/session_manager.php', 'user_leaving=1');
});