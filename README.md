# 📦 Smartcore Express

**Plateforme de gestion de livraison et d'expédition internationale**

Smartcore Express est une application web complète pour la gestion des colis, le suivi des expéditions et l'administration des services de livraison internationale.

## 🚀 Fonctionnalités

### 👥 Gestion des Utilisateurs
- **Authentification sécurisée** avec sessions PHP
- **Inscription et connexion** avec validation email
- **Réinitialisation de mot de passe** sécurisée
- **Profils utilisateurs** avec photos
- **Gestion des rôles** (Client, Admin)

### 📦 Gestion des Colis
- **Suivi en temps réel** des expéditions
- **Statuts détaillés** (En attente, En transit, Livré, etc.)
- **Historique complet** des mouvements
- **Notifications automatiques** par email
- **Calcul des frais** de livraison

### 🛠️ Administration
- **Dashboard responsive** avec statistiques
- **Gestion des utilisateurs** et des colis
- **Rapports détaillés** et analytics
- **Paramètres système** configurables
- **Interface mobile-friendly**

### 📱 Progressive Web App (PWA)
- **Installation sur mobile** et desktop
- **Fonctionnement hors ligne**
- **Notifications push**
- **Mise à jour automatique**

### 🎨 Interface Responsive
- **Design moderne** avec Tailwind CSS
- **Compatible mobile** et tablette
- **Thème adaptatif**
- **Animations fluides**

## 🛠️ Technologies Utilisées

### Backend
- **PHP 8.3+** - Langage principal
- **MySQL** - Base de données
- **Composer** - Gestionnaire de dépendances
- **PHPMailer** - Envoi d'emails

### Frontend
- **HTML5/CSS3** - Structure et style
- **JavaScript ES6+** - Interactivité
- **Tailwind CSS** - Framework CSS
- **Alpine.js** - Framework JavaScript léger
- **Chart.js** - Graphiques et statistiques
- **Font Awesome** - Icônes

### PWA
- **Service Worker** - Cache et offline
- **Web App Manifest** - Installation
- **Push Notifications** - Notifications

## 📋 Prérequis

- **PHP 8.0+** avec extensions :
  - `mysqli`
  - `pdo_mysql`
  - `mbstring`
  - `openssl`
  - `curl`
  - `gd`
- **MySQL 5.7+** ou **MariaDB 10.3+**
- **Composer** pour les dépendances
- **Serveur web** (Apache, Nginx, ou PHP built-in)

## 🚀 Installation

### 1. Cloner le projet
```bash
git clone https://github.com/votre-username/smartcore-express.git
cd smartcore-express
```

### 2. Installer les dépendances
```bash
composer install
```

### 3. Configuration de la base de données

1. Créer une base de données MySQL :
```sql
CREATE DATABASE smartcore_express;
```

2. Configurer la connexion dans `config/database.php` :
```php
<?php
$host = 'localhost';
$dbname = 'smartcore_express';
$username = 'votre_username';
$password = 'votre_password';
```

3. Importer la structure de base de données (fichier SQL à fournir)

### 4. Configuration des emails

Configurer les paramètres SMTP dans les fichiers appropriés pour l'envoi d'emails.

### 5. Permissions

Donner les permissions d'écriture aux dossiers :
```bash
chmod 755 uploads/
chmod 755 logs/
chmod 755 img/profiles/
```

### 6. Lancer le serveur

**Développement :**
```bash
php -S localhost:8000
```

**Production :** Configurer Apache/Nginx

## 📁 Structure du Projet

```
smartcore-express/
├── admin/                  # Interface d'administration
│   ├── dashboard.php      # Tableau de bord
│   ├── users.php          # Gestion utilisateurs
│   ├── colis_management.php # Gestion colis
│   └── ...
├── auth/                   # Authentification
│   ├── login.php          # Connexion
│   ├── register.php       # Inscription
│   └── ...
├── client/                 # Interface client
│   ├── dashboard.php      # Tableau de bord client
│   ├── mes_colis.php      # Mes colis
│   └── ...
├── config/                 # Configuration
│   └── database.php       # Base de données
├── css/                    # Styles CSS
│   ├── styles.css         # Styles principaux
│   ├── admin-responsive.css # Styles admin responsive
│   └── theme.css          # Thème
├── js/                     # Scripts JavaScript
│   ├── main.js            # Script principal
│   ├── admin-responsive.js # Scripts admin responsive
│   └── ...
├── includes/               # Fonctions PHP
├── img/                    # Images
├── uploads/                # Fichiers uploadés
└── vendor/                 # Dépendances Composer
```

## 🔧 Configuration

### Variables d'environnement

Créer un fichier `.env` pour les configurations sensibles :
```env
DB_HOST=localhost
DB_NAME=smartcore_express
DB_USER=username
DB_PASS=password

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=email@gmail.com
SMTP_PASS=password
```

### Tâches automatisées

Configurer les tâches cron pour :
- Nettoyage des tokens expirés
- Envoi d'emails de bienvenue
- Notifications automatiques

```bash
# Exemple de crontab
0 2 * * * php /path/to/cleanup_expired_tokens.php
*/15 * * * * php /path/to/send_welcome_emails.php
```

## 📱 PWA - Installation

L'application peut être installée comme une app native :

1. **Sur mobile :** Ouvrir dans le navigateur → "Ajouter à l'écran d'accueil"
2. **Sur desktop :** Chrome → Menu → "Installer Smartcore Express"

## 🔒 Sécurité

- **Validation des données** côté serveur
- **Protection CSRF** sur les formulaires
- **Hashage sécurisé** des mots de passe
- **Sessions sécurisées** avec timeout
- **Validation des uploads** de fichiers
- **Échappement des données** pour éviter XSS

## 🧪 Tests

Pour tester l'application :

1. **Interface publique :** `http://localhost:8000/`
2. **Connexion client :** `http://localhost:8000/auth/login.php`
3. **Administration :** `http://localhost:8000/admin/dashboard.php`
4. **Suivi colis :** `http://localhost:8000/track.php`

## 📊 Fonctionnalités Avancées

### Analytics
- Suivi des performances
- Statistiques d'utilisation
- Rapports personnalisés

### Notifications
- Emails automatiques
- Notifications push PWA
- Alertes administrateur

### API (Future)
- Endpoints REST
- Authentification par token
- Documentation Swagger

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit les changements (`git commit -am 'Ajouter nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 👨‍💻 Auteur

**Smartcore Express Team**
- Email : contact@smartcore-express.com
- Website : https://smartcore-express.com

## 🆘 Support

Pour obtenir de l'aide :

1. **Documentation :** Consulter ce README
2. **Issues :** Créer une issue sur GitHub
3. **Email :** contact@smartcore-express.com

## 🔄 Changelog

### Version 1.0.0 (2025)
- ✅ Interface d'administration responsive
- ✅ PWA fonctionnelle
- ✅ Système d'authentification complet
- ✅ Gestion des colis et suivi
- ✅ Notifications par email
- ✅ Dashboard avec statistiques

---

**Smartcore Express** - Votre partenaire pour la livraison internationale 🌍📦