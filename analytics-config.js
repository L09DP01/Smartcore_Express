// Configuration Google Analytics et Tag Manager pour Smartcore Express
// Ce fichier doit être inclus dans toutes les pages pour le suivi SEO

// Google Analytics 4 Configuration
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());

// Remplacez 'GA_MEASUREMENT_ID' par votre vrai ID Google Analytics
// gtag('config', 'GA_MEASUREMENT_ID', {
//   page_title: document.title,
//   page_location: window.location.href,
//   custom_map: {
//     'dimension1': 'service_type',
//     'dimension2': 'user_type'
//   }
// });

// Événements personnalisés pour le suivi SEO
function trackServiceView(serviceName) {
  gtag('event', 'view_service', {
    'service_name': serviceName,
    'page_title': document.title,
    'page_location': window.location.href
  });
}

function trackQuoteRequest(serviceType) {
  gtag('event', 'quote_request', {
    'service_type': serviceType,
    'value': 1,
    'currency': 'USD'
  });
}

function trackContactForm() {
  gtag('event', 'contact_form_submit', {
    'form_type': 'contact',
    'page_location': window.location.href
  });
}

function trackRegistration() {
  gtag('event', 'sign_up', {
    'method': 'website',
    'value': 1
  });
}

function trackLogin() {
  gtag('event', 'login', {
    'method': 'website'
  });
}

// Suivi des clics sur les liens externes
document.addEventListener('DOMContentLoaded', function() {
  // Suivi des clics sur les services
  const serviceCards = document.querySelectorAll('.service-card');
  serviceCards.forEach(card => {
    card.addEventListener('click', function() {
      const serviceName = this.querySelector('h3').textContent;
      trackServiceView(serviceName);
    });
  });
  
  // Suivi des clics sur les boutons de devis
  const quoteButtons = document.querySelectorAll('[href*="calculate"], [href*="devis"]');
  quoteButtons.forEach(button => {
    button.addEventListener('click', function() {
      trackQuoteRequest('general');
    });
  });
  
  // Suivi des soumissions de formulaire de contact
  const contactForms = document.querySelectorAll('form[action*="contact"]');
  contactForms.forEach(form => {
    form.addEventListener('submit', function() {
      trackContactForm();
    });
  });
});

// Configuration pour le suivi des performances Core Web Vitals
function trackWebVitals() {
  // Largest Contentful Paint (LCP)
  new PerformanceObserver((entryList) => {
    for (const entry of entryList.getEntries()) {
      gtag('event', 'LCP', {
        'value': Math.round(entry.startTime),
        'custom_parameter': 'core_web_vitals'
      });
    }
  }).observe({entryTypes: ['largest-contentful-paint']});
  
  // First Input Delay (FID)
  new PerformanceObserver((entryList) => {
    for (const entry of entryList.getEntries()) {
      gtag('event', 'FID', {
        'value': Math.round(entry.processingStart - entry.startTime),
        'custom_parameter': 'core_web_vitals'
      });
    }
  }).observe({entryTypes: ['first-input']});
}

// Initialiser le suivi des Web Vitals si supporté
if ('PerformanceObserver' in window) {
  trackWebVitals();
}

// Instructions pour l'implémentation :
/*
1. Créez un compte Google Analytics 4
2. Obtenez votre Measurement ID (format: G-XXXXXXXXXX)
3. Remplacez 'GA_MEASUREMENT_ID' par votre vrai ID
4. Décommentez la configuration gtag
5. Ajoutez ce script dans toutes vos pages HTML :
   <script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
   <script src="analytics-config.js"></script>

6. Pour Google Tag Manager (optionnel) :
   - Créez un compte GTM
   - Ajoutez le code GTM dans le <head> et après <body>
   - Configurez les tags dans l'interface GTM
*/