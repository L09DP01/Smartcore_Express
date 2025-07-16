// Optimisations de performance pour améliorer le SEO
// Ce fichier améliore les Core Web Vitals et la vitesse de chargement

(function() {
    'use strict';
    
    // Préchargement des ressources critiques
    function preloadCriticalResources() {
        const criticalResources = [
            { href: 'css/styles.css', as: 'style' },
            { href: 'img/Logo.png', as: 'image' },
            { href: 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap', as: 'style' }
        ];
        
        criticalResources.forEach(resource => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = resource.href;
            link.as = resource.as;
            if (resource.as === 'style') {
                link.onload = function() {
                    this.onload = null;
                    this.rel = 'stylesheet';
                };
            }
            document.head.appendChild(link);
        });
    }
    
    // Lazy loading pour les images
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        } else {
            // Fallback pour les navigateurs plus anciens
            document.querySelectorAll('img[data-src]').forEach(img => {
                img.src = img.dataset.src;
            });
        }
    }
    
    // Optimisation du chargement des polices
    function optimizeFontLoading() {
        if ('fonts' in document) {
            // Préchargement des polices critiques
            const fontPromises = [
                new FontFace('Poppins', 'url(https://fonts.gstatic.com/s/poppins/v20/pxiEyp8kv8JHgFVrJJfecg.woff2)', {
                    weight: '400',
                    display: 'swap'
                }),
                new FontFace('Poppins', 'url(https://fonts.gstatic.com/s/poppins/v20/pxiByp8kv8JHgFVrLCz7Z1xlFQ.woff2)', {
                    weight: '600',
                    display: 'swap'
                })
            ];
            
            fontPromises.forEach(font => {
                font.load().then(loadedFont => {
                    document.fonts.add(loadedFont);
                }).catch(err => {
                    console.warn('Font loading failed:', err);
                });
            });
        }
    }
    
    // Optimisation des animations et transitions
    function optimizeAnimations() {
        // Réduire les animations si l'utilisateur préfère moins de mouvement
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            const style = document.createElement('style');
            style.textContent = `
                *, *::before, *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Optimisation du défilement
    function optimizeScrolling() {
        // Smooth scrolling pour les liens d'ancrage
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
    
    // Optimisation des formulaires
    function optimizeForms() {
        // Validation côté client pour réduire les allers-retours serveur
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('error');
                    } else {
                        field.classList.remove('error');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        });
    }
    
    // Mise en cache intelligente
    function setupIntelligentCaching() {
        if ('serviceWorker' in navigator) {
            // Enregistrement du service worker pour la mise en cache
            navigator.serviceWorker.register('/sw.js').then(registration => {
                console.log('Service Worker enregistré:', registration);
            }).catch(error => {
                console.log('Échec de l\'enregistrement du Service Worker:', error);
            });
        }
    }
    
    // Optimisation des ressources externes
    function optimizeExternalResources() {
        // Chargement différé de Tailwind CSS si pas critique
        const tailwindScript = document.querySelector('script[src*="tailwindcss"]');
        if (tailwindScript && !document.querySelector('.tailwind-critical')) {
            tailwindScript.defer = true;
        }
        
        // Chargement différé de Font Awesome
        const fontAwesome = document.querySelector('link[href*="font-awesome"]');
        if (fontAwesome) {
            fontAwesome.media = 'print';
            fontAwesome.onload = function() {
                this.media = 'all';
            };
        }
    }
    
    // Mesure et rapport des Core Web Vitals
    function measureWebVitals() {
        // Largest Contentful Paint (LCP)
        new PerformanceObserver((entryList) => {
            for (const entry of entryList.getEntries()) {
                console.log('LCP:', entry.startTime);
                // Envoyer à Google Analytics si configuré
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'web_vitals', {
                        'metric_name': 'LCP',
                        'metric_value': Math.round(entry.startTime),
                        'metric_rating': entry.startTime < 2500 ? 'good' : entry.startTime < 4000 ? 'needs_improvement' : 'poor'
                    });
                }
            }
        }).observe({entryTypes: ['largest-contentful-paint']});
        
        // First Input Delay (FID)
        new PerformanceObserver((entryList) => {
            for (const entry of entryList.getEntries()) {
                const fid = entry.processingStart - entry.startTime;
                console.log('FID:', fid);
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'web_vitals', {
                        'metric_name': 'FID',
                        'metric_value': Math.round(fid),
                        'metric_rating': fid < 100 ? 'good' : fid < 300 ? 'needs_improvement' : 'poor'
                    });
                }
            }
        }).observe({entryTypes: ['first-input']});
        
        // Cumulative Layout Shift (CLS)
        let clsValue = 0;
        new PerformanceObserver((entryList) => {
            for (const entry of entryList.getEntries()) {
                if (!entry.hadRecentInput) {
                    clsValue += entry.value;
                }
            }
            console.log('CLS:', clsValue);
            if (typeof gtag !== 'undefined') {
                gtag('event', 'web_vitals', {
                    'metric_name': 'CLS',
                    'metric_value': Math.round(clsValue * 1000),
                    'metric_rating': clsValue < 0.1 ? 'good' : clsValue < 0.25 ? 'needs_improvement' : 'poor'
                });
            }
        }).observe({entryTypes: ['layout-shift']});
    }
    
    // Initialisation de toutes les optimisations
    function init() {
        // Exécuter immédiatement
        preloadCriticalResources();
        optimizeExternalResources();
        optimizeAnimations();
        
        // Exécuter au chargement du DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initLazyLoading();
                optimizeScrolling();
                optimizeForms();
                setupIntelligentCaching();
            });
        } else {
            initLazyLoading();
            optimizeScrolling();
            optimizeForms();
            setupIntelligentCaching();
        }
        
        // Exécuter après le chargement complet
        window.addEventListener('load', function() {
            optimizeFontLoading();
            if ('PerformanceObserver' in window) {
                measureWebVitals();
            }
        });
    }
    
    // Démarrer les optimisations
    init();
    
})();

// CSS pour les images lazy loading
const lazyLoadingCSS = `
    .lazy {
        opacity: 0;
        transition: opacity 0.3s;
    }
    .lazy.loaded {
        opacity: 1;
    }
    .error {
        border: 2px solid #ff6b6b !important;
        background-color: #ffe0e0 !important;
    }
`;

const style = document.createElement('style');
style.textContent = lazyLoadingCSS;
document.head.appendChild(style);