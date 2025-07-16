/**
 * JavaScript pour la gestion responsive des pages d'administration
 * Gère le menu mobile, les interactions et l'adaptabilité
 */

// Variables globales
let sidebarOpen = false;
let isMobile = window.innerWidth <= 768;

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    initResponsiveAdmin();
    setupEventListeners();
    handleResize();
});

// Gestion du redimensionnement de la fenêtre
window.addEventListener('resize', handleResize);

/**
 * Initialise les fonctionnalités responsive de l'admin
 */
function initResponsiveAdmin() {
    // Créer le bouton menu mobile s'il n'existe pas
    createMobileMenuButton();
    
    // Créer l'overlay pour mobile s'il n'existe pas
    createSidebarOverlay();
    
    // Ajouter les classes CSS nécessaires
    addResponsiveClasses();
    
    // Initialiser les tables responsives
    initResponsiveTables();
    
    // Initialiser les modals responsives
    initResponsiveModals();
    
    console.log('Admin responsive initialized');
}

/**
 * Crée le bouton menu mobile
 */
function createMobileMenuButton() {
    const header = document.querySelector('.admin-header') || document.querySelector('header');
    if (!header) return;
    
    // Vérifier si le bouton existe déjà
    if (document.querySelector('.mobile-menu-btn')) return;
    
    const menuBtn = document.createElement('button');
    menuBtn.className = 'mobile-menu-btn';
    menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    menuBtn.setAttribute('aria-label', 'Toggle menu');
    
    // Insérer au début du header
    header.insertBefore(menuBtn, header.firstChild);
}

/**
 * Crée l'overlay pour la sidebar mobile
 */
function createSidebarOverlay() {
    // Vérifier si l'overlay existe déjà
    if (document.querySelector('.sidebar-overlay')) return;
    
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
}

/**
 * Ajoute les classes CSS nécessaires aux éléments existants
 */
function addResponsiveClasses() {
    // Layout principal
    const body = document.body;
    if (!body.classList.contains('admin-layout')) {
        body.classList.add('admin-layout');
    }
    
    // Sidebar
    const sidebar = document.querySelector('aside') || document.querySelector('.sidebar') || document.querySelector('nav');
    if (sidebar && !sidebar.classList.contains('admin-sidebar')) {
        sidebar.classList.add('admin-sidebar');
    }
    
    // Main content
    const main = document.querySelector('main') || document.querySelector('.main-content');
    if (main && !main.classList.contains('admin-main')) {
        main.classList.add('admin-main');
    }
    
    // Header
    const header = document.querySelector('header') || document.querySelector('.header');
    if (header && !header.classList.contains('admin-header')) {
        header.classList.add('admin-header');
    }
}

/**
 * Configure les event listeners
 */
function setupEventListeners() {
    // Bouton menu mobile
    const menuBtn = document.querySelector('.mobile-menu-btn');
    if (menuBtn) {
        menuBtn.addEventListener('click', toggleSidebar);
    }
    
    // Overlay
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Fermer la sidebar avec Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebarOpen) {
            closeSidebar();
        }
    });
    
    // Liens de la sidebar sur mobile
    const sidebarLinks = document.querySelectorAll('.admin-sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (isMobile) {
                closeSidebar();
            }
        });
    });
}

/**
 * Gère le redimensionnement de la fenêtre
 */
function handleResize() {
    const wasMobile = isMobile;
    isMobile = window.innerWidth <= 768;
    
    // Si on passe de mobile à desktop
    if (wasMobile && !isMobile) {
        closeSidebar();
    }
    
    // Réinitialiser les tables responsives
    initResponsiveTables();
}

/**
 * Toggle la sidebar mobile
 */
function toggleSidebar() {
    if (sidebarOpen) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

/**
 * Ouvre la sidebar mobile
 */
function openSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.add('open');
    }
    
    if (overlay) {
        overlay.classList.add('show');
    }
    
    document.body.style.overflow = 'hidden';
    sidebarOpen = true;
}

/**
 * Ferme la sidebar mobile
 */
function closeSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.remove('open');
    }
    
    if (overlay) {
        overlay.classList.remove('show');
    }
    
    document.body.style.overflow = '';
    sidebarOpen = false;
}

/**
 * Initialise les tables responsives
 */
function initResponsiveTables() {
    const tables = document.querySelectorAll('table');
    
    tables.forEach(table => {
        // Ajouter la classe responsive si elle n'existe pas
        if (!table.classList.contains('responsive-table')) {
            table.classList.add('responsive-table');
        }
        
        // Wrapper pour le scroll horizontal
        if (!table.parentElement.classList.contains('table-container')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-container';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
        
        // Ajouter des classes pour cacher certaines colonnes sur mobile
        addMobileTableClasses(table);
    });
}

/**
 * Ajoute des classes pour cacher certaines colonnes sur mobile
 */
function addMobileTableClasses(table) {
    const headers = table.querySelectorAll('th');
    const rows = table.querySelectorAll('tbody tr');
    
    // Colonnes à cacher sur mobile (basé sur le contenu)
    const mobileHideColumns = [];
    const smallHideColumns = [];
    
    headers.forEach((header, index) => {
        const text = header.textContent.toLowerCase();
        
        // Colonnes à cacher sur tablette/mobile
        if (text.includes('description') || text.includes('détails') || text.includes('commentaire')) {
            mobileHideColumns.push(index);
        }
        
        // Colonnes à cacher sur très petit écran
        if (text.includes('date') || text.includes('créé') || text.includes('modifié')) {
            smallHideColumns.push(index);
        }
    });
    
    // Appliquer les classes
    mobileHideColumns.forEach(colIndex => {
        if (headers[colIndex]) {
            headers[colIndex].classList.add('hide-mobile');
        }
        rows.forEach(row => {
            const cell = row.children[colIndex];
            if (cell) {
                cell.classList.add('hide-mobile');
            }
        });
    });
    
    smallHideColumns.forEach(colIndex => {
        if (headers[colIndex]) {
            headers[colIndex].classList.add('hide-small');
        }
        rows.forEach(row => {
            const cell = row.children[colIndex];
            if (cell) {
                cell.classList.add('hide-small');
            }
        });
    });
}

/**
 * Initialise les modals responsives
 */
function initResponsiveModals() {
    const modals = document.querySelectorAll('.modal, [id*="modal"], [class*="modal"]');
    
    modals.forEach(modal => {
        // Fermer modal avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                closeModal(modal);
            }
        });
        
        // Fermer modal en cliquant à l'extérieur
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });
}

/**
 * Ferme une modal
 */
function closeModal(modal) {
    modal.style.display = 'none';
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

/**
 * Ouvre une modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Utilitaires pour les formulaires responsives
 */
function initResponsiveForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Ajouter des classes responsive aux groupes de champs
        const fieldGroups = form.querySelectorAll('.form-group, .field-group, .input-group');
        fieldGroups.forEach(group => {
            if (!group.classList.contains('form-group')) {
                group.classList.add('form-group');
            }
        });
        
        // Wrapper pour les formulaires en grille
        const formGrid = form.querySelector('.form-grid');
        if (!formGrid && fieldGroups.length > 2) {
            const wrapper = document.createElement('div');
            wrapper.className = 'form-grid';
            
            fieldGroups.forEach(group => {
                wrapper.appendChild(group);
            });
            
            form.appendChild(wrapper);
        }
    });
}

/**
 * Utilitaires pour les cartes de statistiques
 */
function initStatsCards() {
    const statsContainers = document.querySelectorAll('.stats, .statistics, .dashboard-stats');
    
    statsContainers.forEach(container => {
        if (!container.classList.contains('stats-grid')) {
            container.classList.add('stats-grid');
        }
        
        const cards = container.querySelectorAll('.card, .stat-card, .metric');
        cards.forEach(card => {
            if (!card.classList.contains('stat-card')) {
                card.classList.add('stat-card');
            }
        });
    });
}

/**
 * Fonction pour afficher des notifications responsives
 */
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} fade-in`;
    notification.textContent = message;
    
    // Ajouter au début du contenu principal
    const main = document.querySelector('.admin-main') || document.body;
    main.insertBefore(notification, main.firstChild);
    
    // Supprimer automatiquement
    setTimeout(() => {
        notification.remove();
    }, duration);
}

/**
 * Fonction pour confirmer les actions (responsive)
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Utilitaires d'export pour les autres scripts
 */
window.AdminResponsive = {
    toggleSidebar,
    openSidebar,
    closeSidebar,
    openModal,
    closeModal,
    showNotification,
    confirmAction,
    initResponsiveTables,
    initResponsiveForms,
    initStatsCards
};

// Auto-initialisation des composants au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Délai pour s'assurer que le DOM est complètement chargé
    setTimeout(() => {
        initResponsiveForms();
        initStatsCards();
    }, 100);
});

console.log('Admin Responsive JS loaded successfully');