# Système de Gestion de Session avec "Se Souvenir de Moi"

## Fonctionnalités Implémentées

### 1. Déconnexion Automatique par Inactivité
- **Durée d'inactivité** : 1 minute
- **Détection** : Surveillance des événements utilisateur (clics, mouvements de souris, frappe clavier, etc.)
- **Avertissement** : Notification à 45 secondes d'inactivité
- **Action** : Déconnexion automatique et redirection vers la page de connexion

### 2. Fonction "Se Souvenir de Moi"
- **Durée** : 15 jours
- **Sécurité** : Token unique généré pour chaque session
- **Stockage** : Cookie sécurisé + base de données
- **Nettoyage** : Suppression automatique des anciens tokens

## Fichiers Créés/Modifiés

### 1. `auth/login.php` (Modifié)
- Ajout de la gestion du checkbox "Se souvenir de moi"
- Vérification de l'inactivité de session
- Gestion des tokens de connexion persistante
- Mise à jour automatique de l'activité

### 2. `auth/session_manager.php` (Nouveau)
- Gestionnaire centralisé de session
- À inclure dans toutes les pages protégées
- Fonctions de vérification et de nettoyage

### 3. `js/session_activity.js` (Nouveau)
- Surveillance côté client de l'activité utilisateur
- Notifications d'avertissement
- Mise à jour périodique du serveur
- Gestion de la déconnexion automatique

### 4. `database/remember_tokens.sql` (Nouveau)
- Structure de la table pour les tokens "Se souvenir de moi"
- Index optimisés pour les performances

## Installation

### 1. Créer la Table de Base de Données
```sql
-- Exécuter le contenu du fichier database/remember_tokens.sql
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);
```

### 2. Inclure le Gestionnaire de Session
Dans chaque page protégée, ajouter au début :
```php
<?php
require_once 'auth/session_manager.php';
// Le reste de votre code...
?>
```

### 3. Inclure le Script JavaScript
Dans le `<head>` de vos pages protégées :
```html
<script src="/Smartcore_Express/js/session_activity.js"></script>
```

## Utilisation

### Pour les Utilisateurs
1. **Connexion normale** : Session expire après 1 minute d'inactivité
2. **"Se souvenir de moi"** : Connexion maintenue pendant 15 jours
3. **Avertissement** : Notification 15 secondes avant expiration
4. **Extension** : Bouton "Rester connecté" dans l'avertissement

### Pour les Développeurs

#### Vérifier l'État de Session
```php
// Inclure le gestionnaire
require_once 'auth/session_manager.php';

// La session est automatiquement vérifiée
// L'utilisateur est redirigé si non connecté
```

#### Déconnecter Manuellement
```php
require_once 'auth/session_manager.php';
logout();
header('Location: /Smartcore_Express/auth/login.php');
```

#### Mettre à Jour l'Activité
```php
// Automatique avec session_manager.php
// Ou manuellement :
updateActivity();
```

## Sécurité

### Mesures Implémentées
1. **Tokens uniques** : Chaque session génère un token cryptographiquement sécurisé
2. **Expiration automatique** : Tokens supprimés après 15 jours
3. **Nettoyage** : Suppression des anciens tokens à chaque nouvelle connexion
4. **Cookies sécurisés** : HttpOnly pour prévenir les attaques XSS
5. **Validation côté serveur** : Vérification de l'état utilisateur et du token

### Recommandations
1. **HTTPS** : Utiliser HTTPS en production pour sécuriser les cookies
2. **Nettoyage périodique** : Programmer une tâche cron pour supprimer les tokens expirés
3. **Logs** : Surveiller les tentatives de connexion avec des tokens invalides

## Maintenance

### Nettoyage des Tokens Expirés
Exécuter périodiquement (par exemple, via cron) :
```sql
DELETE FROM remember_tokens WHERE expires_at < NOW();
```

### Surveillance
- Vérifier les logs d'erreur pour les tentatives de tokens invalides
- Surveiller la taille de la table `remember_tokens`
- Analyser les patterns de connexion pour détecter des anomalies

## Dépannage

### Problèmes Courants
1. **Session expire immédiatement** : Vérifier que `session_activity.js` est bien inclus
2. **"Se souvenir de moi" ne fonctionne pas** : Vérifier que la table `remember_tokens` existe
3. **Erreurs de base de données** : Vérifier les permissions et la structure de la table
4. **JavaScript non fonctionnel** : Vérifier la console du navigateur pour les erreurs

### Logs à Vérifier
- Logs d'erreur PHP pour les problèmes de base de données
- Console du navigateur pour les erreurs JavaScript
- Logs du serveur web pour les problèmes de cookies