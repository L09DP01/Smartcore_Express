/**
 * Fichier JavaScript principal pour Smartcore Express
 * Gère les interactions et fonctionnalités du site
 */

// Attendre que le DOM soit complètement chargé
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du menu mobile
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            // Toggle la classe 'hidden' pour afficher/masquer le menu
            mobileMenu.classList.toggle('hidden');
            
            // Changer l'icône du bouton (hamburger <-> croix)
            const icon = mobileMenuButton.querySelector('i');
            if (icon) {
                if (mobileMenu.classList.contains('hidden')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            }
        });
    }
    
    // Animation des compteurs pour les statistiques
    function animateCounter(element, target, duration) {
        let start = 0;
        const increment = target > 0 ? Math.ceil(target / (duration / 16)) : 0;
        const timer = setInterval(function() {
            start += increment;
            element.textContent = start;
            if (start >= target) {
                element.textContent = target;
                clearInterval(timer);
            }
        }, 16);
    }
    
    // Animer les compteurs lorsqu'ils sont visibles
    const counters = document.querySelectorAll('.counter');
    if (counters.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = parseInt(entry.target.getAttribute('data-target'));
                    animateCounter(entry.target, target, 2000);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        counters.forEach(counter => {
            observer.observe(counter);
        });
    }
    
    // Gestion des formulaires
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Empêcher l'envoi du formulaire par défaut pour la démo
            e.preventDefault();
            
            // Simuler l'envoi du formulaire
            const submitButton = form.querySelector('[type="submit"]');
            if (submitButton) {
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Envoi en cours...';
                
                // Simuler un délai de traitement
                setTimeout(() => {
                    // Afficher un message de succès
                    const formMessage = document.createElement('div');
                    formMessage.className = 'alert alert-success mt-4';
                    formMessage.textContent = 'Votre message a été envoyé avec succès! Nous vous répondrons dans les plus brefs délais.';
                    form.appendChild(formMessage);
                    
                    // Réinitialiser le formulaire
                    form.reset();
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                    
                    // Supprimer le message après quelques secondes
                    setTimeout(() => {
                        formMessage.remove();
                    }, 5000);
                }, 1500);
            }
        });
    });
    
    // Gestion des accordéons (FAQ)
    const accordionButtons = document.querySelectorAll('.accordion-button');
    accordionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const content = this.nextElementSibling;
            const icon = this.querySelector('i');
            
            // Toggle le contenu
            content.classList.toggle('hidden');
            
            // Changer l'icône
            if (icon) {
                if (content.classList.contains('hidden')) {
                    icon.classList.remove('fa-minus');
                    icon.classList.add('fa-plus');
                } else {
                    icon.classList.remove('fa-plus');
                    icon.classList.add('fa-minus');
                }
            }
        });
    });
    
    // Animation au défilement pour les éléments
    function animateOnScroll() {
        const elements = document.querySelectorAll('.animate-on-scroll');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        elements.forEach(element => {
            observer.observe(element);
        });
    }
    
    // Initialiser l'animation au défilement
    animateOnScroll();
    
    // Gestion des tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltipElement = document.createElement('div');
            tooltipElement.className = 'tooltip-text';
            tooltipElement.textContent = tooltipText;
            this.appendChild(tooltipElement);
        });
        
        tooltip.addEventListener('mouseleave', function() {
            const tooltipElement = this.querySelector('.tooltip-text');
            if (tooltipElement) {
                tooltipElement.remove();
            }
        });
    });
});