# Configuration Google OAuth - Smartcore Express

## Vue d'ensemble

Ce guide explique comment configurer l'authentification Google OAuth pour Smartcore Express. Le système a été simplifié pour se concentrer uniquement sur Google OAuth.

## Prérequis

1. Un projet Google Cloud Platform
2. Les API Google+ et Google OAuth2 activées
3. Un domaine configuré (smartcoreexpress.com)

## Configuration Google Cloud Console

### 1. Créer un projet Google Cloud

1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créez un nouveau projet ou sélectionnez un projet existant
3. Notez l'ID du projet

### 2. Activer les APIs nécessaires

1. Dans le menu de navigation, allez à "APIs & Services" > "Library"
2. Recherchez et activez :
   - Google+ API
   - Google OAuth2 API

### 3. Configurer l'écran de consentement OAuth

1. Allez à "APIs & Services" > "OAuth consent screen"
2. Choisissez "External" pour les utilisateurs publics
3. Remplissez les informations requises :
   - Nom de l'application : "Smartcore Express"
   - Email de support utilisateur
   - Domaines autorisés : `smartcoreexpress.com`
   - Email de contact développeur

### 4. Créer les identifiants OAuth

1. Allez à "APIs & Services" > "Credentials"
2. Cliquez sur "Create Credentials" > "OAuth 2.0 Client IDs"
3. Choisissez "Web application"
4. Configurez :
   - **Nom** : Smartcore Express Web Client
   - **URIs de redirection autorisés** :
     - `https://smartcoreexpress.com/auth/google-callback.php`
     - `http://localhost/Smartcore_Express/auth/google-callback.php` (pour le développement)

5. Sauvegardez et notez :
   - Client ID
   - Client Secret

## Configuration du fichier oauth_config.php

Mettez à jour le fichier `config/oauth_config.php` avec vos identifiants :

```php
<?php
// Configuration Google OAuth
define('GOOGLE_CLIENT_ID', 'votre-client-id-google.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'votre-client-secret-google');
define('GOOGLE_REDIRECT_URI', 'https://smartcoreexpress.com/auth/google-callback.php');
define('BASE_URL', 'https://smartcoreexpress.com');
```

## Structure des fichiers

### Fichiers principaux :

- `config/oauth_config.php` - Configuration OAuth
- `auth/google-callback.php` - Gestionnaire de callback Google
- `auth/login.php` - Page de connexion avec bouton Google
- `auth/register.php` - Page d'inscription avec bouton Google

### Fonctions disponibles :

- `isGoogleConfigured()` - Vérifie si Google OAuth est configuré
- `getGoogleAuthUrl()` - Génère l'URL d'autorisation Google

## Flux d'authentification

1. **Connexion** : L'utilisateur clique sur "Continuer avec Google"
2. **Redirection** : Redirection vers Google avec les paramètres OAuth
3. **Autorisation** : L'utilisateur autorise l'application
4. **Callback** : Google redirige vers `google-callback.php`
5. **Traitement** : 
   - Échange du code contre un token d'accès
   - Récupération des informations utilisateur
   - Création ou connexion de l'utilisateur
   - **Envoi automatique d'un email de bienvenue** (nouveaux clients uniquement)
   - Création de la session
6. **Redirection finale** : Vers le dashboard approprié selon le rôle

## Fonctionnalité d'email automatique

### Email de bienvenue pour nouveaux clients

Lorsqu'un nouvel utilisateur se connecte pour la première fois via Google OAuth :

- **Déclenchement automatique** : Un email de bienvenue est envoyé immédiatement après la création du compte
- **Template spécialisé** : Email personnalisé pour les connexions OAuth (sans mot de passe)
- **Contenu de l'email** :
  - Message de bienvenue personnalisé
  - Information sur la connexion via Google
  - Liens vers le dashboard et la page de connexion
  - Guide des fonctionnalités disponibles
  - Informations de contact du support

### Configuration email

Les emails sont envoyés via :
- **Serveur SMTP** : smtp.hostinger.com
- **Expéditeur** : noreply@smartcoreexpress.com
- **Sécurité** : SSL sur le port 465

### Gestion des erreurs

- Les erreurs d'envoi d'email sont enregistrées dans les logs PHP
- L'échec d'envoi d'email n'interrompt pas le processus de connexion
- Les tentatives d'envoi sont tracées pour le débogage

## Completion de Profil pour Nouveaux Utilisateurs

### Fonctionnalité
Après la première connexion via Google OAuth, les nouveaux utilisateurs sont automatiquement redirigés vers une page de completion de profil où ils peuvent :
- **Modifier leur nom et prénom** récupérés depuis Google
- **Confirmer leurs informations** avant d'accéder au dashboard
- **Passer cette étape** s'ils le souhaitent (modifiable plus tard)

### Fichiers Impliqués
- `auth/complete-profile.php` : Page de completion de profil
- `auth/skip_profile.php` : Gestion de l'option "passer cette étape"
- `database/add_profile_completed.sql` : Script SQL pour ajouter la colonne
- `database/migrate_profile_completed.php` : Script de migration

### Base de Données
Une nouvelle colonne `profile_completed` a été ajoutée à la table `users` :
```sql
profile_completed TINYINT(1) DEFAULT 0 COMMENT 'Indique si l\'utilisateur a complété son profil (0=non, 1=oui)'
```

### Flux Utilisateur
1. **Nouvelle inscription OAuth** → Redirection vers `complete-profile.php`
2. **Completion du profil** → Redirection vers le dashboard avec message de succès
3. **Passer l'étape** → Redirection vers le dashboard avec message informatif
4. **Utilisateurs existants** → Redirection directe vers le dashboard

### Messages de Confirmation
- **Profil mis à jour** : Alerte verte avec message de succès
- **Étape passée** : Alerte bleue informative
- **Auto-fermeture** : Les alertes se ferment automatiquement après 5 secondes

## Test de la configuration

### 1. Vérification des prérequis

```bash
# Vérifiez que curl est installé
php -m | grep curl

# Vérifiez que les sessions fonctionnent
php -i | grep session
```

### 2. Test de connexion

1. Allez sur `https://smartcoreexpress.com/auth/login.php`
2. Cliquez sur "Continuer avec Google"
3. Autorisez l'application
4. Vérifiez la redirection vers le dashboard

### 3. Vérification des logs

Consultez les logs d'erreur PHP pour identifier les problèmes :

```bash
tail -f /var/log/apache2/error.log
```

## Dépannage

### Erreurs courantes

1. **"Configuration Google OAuth manquante"**
   - Vérifiez que `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` sont définis
   - Vérifiez que les valeurs ne sont pas vides

2. **"Paramètres OAuth manquants"**
   - Vérifiez l'URL de redirection dans Google Cloud Console
   - Vérifiez que l'URL correspond exactement

3. **"État OAuth invalide"**
   - Problème de session ou de sécurité CSRF
   - Vérifiez que les sessions fonctionnent correctement

4. **"Erreur lors de l'échange du token"**
   - Vérifiez les identifiants Google
   - Vérifiez la connectivité réseau
   - Vérifiez que curl est installé et configuré

### Logs de débogage

Les erreurs sont enregistrées dans les logs PHP. Pour activer le débogage temporaire :

```php
// Ajouter au début de google-callback.php pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

**Important** : Désactivez l'affichage des erreurs en production !

## Sécurité

### Bonnes pratiques :

1. **Environnement de production** :
   - Désactivez l'affichage des erreurs
   - Utilisez HTTPS uniquement
   - Gardez les secrets sécurisés

2. **Validation** :
   - Vérification de l'état CSRF
   - Validation des données utilisateur
   - Nettoyage des sessions

3. **Base de données** :
   - Utilisez des requêtes préparées
   - Validez les entrées utilisateur
   - Chiffrez les données sensibles

## Support

Pour obtenir de l'aide :

1. Consultez les logs d'erreur
2. Vérifiez la configuration Google Cloud Console
3. Testez avec les outils de développement du navigateur
4. Consultez la documentation Google OAuth 2.0

## Changelog

- **Version 2.0** : Simplification complète, suppression d'Apple OAuth, focus sur Google uniquement
- **Version 1.0** : Implémentation initiale avec support multi-providers