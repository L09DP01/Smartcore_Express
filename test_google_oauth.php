<?php
/**
 * Test de la configuration Google OAuth
 * Ce fichier permet de vérifier que la configuration Google OAuth est correcte
 * À supprimer après les tests
 */

// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test de la configuration Google OAuth</h1>";

// Test 1: Inclusion des fichiers de configuration
echo "<h2>1. Test d'inclusion des fichiers</h2>";

try {
    require_once 'config/oauth_config.php';
    echo "✅ oauth_config.php inclus avec succès<br>";
} catch (Exception $e) {
    echo "❌ Erreur lors de l'inclusion d'oauth_config.php: " . $e->getMessage() . "<br>";
}

try {
    require_once 'config/database.php';
    echo "✅ database.php inclus avec succès<br>";
} catch (Exception $e) {
    echo "❌ Erreur lors de l'inclusion de database.php: " . $e->getMessage() . "<br>";
}

// Test 2: Vérification des constantes
echo "<h2>2. Vérification des constantes Google OAuth</h2>";

if (defined('GOOGLE_CLIENT_ID')) {
    echo "✅ GOOGLE_CLIENT_ID défini: " . (strlen(GOOGLE_CLIENT_ID) > 10 ? substr(GOOGLE_CLIENT_ID, 0, 20) . "..." : "[VIDE]") . "<br>";
} else {
    echo "❌ GOOGLE_CLIENT_ID non défini<br>";
}

if (defined('GOOGLE_CLIENT_SECRET')) {
    echo "✅ GOOGLE_CLIENT_SECRET défini: " . (strlen(GOOGLE_CLIENT_SECRET) > 10 ? "[CONFIGURÉ]" : "[VIDE]") . "<br>";
} else {
    echo "❌ GOOGLE_CLIENT_SECRET non défini<br>";
}

if (defined('GOOGLE_REDIRECT_URI')) {
    echo "✅ GOOGLE_REDIRECT_URI défini: " . GOOGLE_REDIRECT_URI . "<br>";
} else {
    echo "❌ GOOGLE_REDIRECT_URI non défini<br>";
}

// Test 3: Test des fonctions
echo "<h2>3. Test des fonctions OAuth</h2>";

if (function_exists('isGoogleConfigured')) {
    $isConfigured = isGoogleConfigured();
    echo "✅ Fonction isGoogleConfigured() disponible: " . ($isConfigured ? "CONFIGURÉ" : "NON CONFIGURÉ") . "<br>";
} else {
    echo "❌ Fonction isGoogleConfigured() non disponible<br>";
}

if (function_exists('getGoogleAuthUrl')) {
    try {
        $authUrl = getGoogleAuthUrl();
        echo "✅ Fonction getGoogleAuthUrl() disponible<br>";
        echo "📋 URL d'autorisation générée: <a href='" . htmlspecialchars($authUrl) . "' target='_blank'>" . htmlspecialchars(substr($authUrl, 0, 80)) . "...</a><br>";
    } catch (Exception $e) {
        echo "❌ Erreur lors de la génération de l'URL: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Fonction getGoogleAuthUrl() non disponible<br>";
}

// Test 4: Test de connexion à la base de données
echo "<h2>4. Test de connexion à la base de données</h2>";

try {
    $conn = getDBConnection();
    echo "✅ Connexion à la base de données réussie<br>";
    
    // Vérifier la table users
    $stmt = $conn->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "✅ Table 'users' trouvée<br>";
        
        // Vérifier la structure de la table
        $stmt = $conn->prepare("DESCRIBE users");
        $stmt->execute();
        $columns = $stmt->fetchAll();
        echo "📋 Colonnes de la table users: ";
        foreach ($columns as $column) {
            echo $column['Field'] . " ";
        }
        echo "<br>";
    } else {
        echo "❌ Table 'users' non trouvée<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur de connexion à la base de données: " . $e->getMessage() . "<br>";
}

// Test 5: Test des sessions
echo "<h2>5. Test des sessions</h2>";

if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session active<br>";
} else {
    session_start();
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "✅ Session démarrée avec succès<br>";
    } else {
        echo "❌ Impossible de démarrer la session<br>";
    }
}

// Test 6: Test de curl
echo "<h2>6. Test de curl (requis pour OAuth)</h2>";

if (function_exists('curl_init')) {
    echo "✅ Extension curl disponible<br>";
    
    // Test de connectivité vers Google
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Connectivité vers Google APIs OK<br>";
    } else {
        echo "⚠️ Problème de connectivité vers Google APIs (Code: $httpCode)<br>";
    }
} else {
    echo "❌ Extension curl non disponible<br>";
}

// Test 7: Test de la fonction d'email de bienvenue
echo "<h2>7. Test de la fonction d'email de bienvenue</h2>";

try {
    require_once 'includes/welcome_email_functions.php';
    echo "✅ welcome_email_functions.php inclus avec succès<br>";
    
    if (function_exists('sendWelcomeEmailOAuth')) {
        echo "✅ Fonction sendWelcomeEmailOAuth() disponible<br>";
        echo "📋 La fonction d'envoi d'email automatique est prête<br>";
    } else {
        echo "❌ Fonction sendWelcomeEmailOAuth() non disponible<br>";
    }
    
    if (function_exists('generateWelcomeEmailOAuthTemplate')) {
        echo "✅ Fonction generateWelcomeEmailOAuthTemplate() disponible<br>";
    } else {
        echo "❌ Fonction generateWelcomeEmailOAuthTemplate() non disponible<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test des fonctions d'email: " . $e->getMessage() . "<br>";
}

// Test 8: Test de la fonctionnalité de completion de profil
echo "<h2>8. Test de la fonctionnalité de completion de profil</h2>";

// Vérifier l'existence des fichiers de completion de profil
if (file_exists('auth/complete-profile.php')) {
    echo "✅ Fichier complete-profile.php trouvé<br>";
} else {
    echo "❌ Fichier complete-profile.php manquant<br>";
}

if (file_exists('auth/skip_profile.php')) {
    echo "✅ Fichier skip_profile.php trouvé<br>";
} else {
    echo "❌ Fichier skip_profile.php manquant<br>";
}

// Vérifier la colonne profile_completed dans la base de données
try {
    if (isset($conn)) {
        $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'profile_completed'");
        $stmt->execute();
        $columnExists = $stmt->fetch();
        
        if ($columnExists) {
            echo "✅ Colonne 'profile_completed' trouvée dans la table users<br>";
            
            // Afficher les détails de la colonne
            echo "📋 Type: " . htmlspecialchars($columnExists['Type']) . "<br>";
            echo "📋 Défaut: " . htmlspecialchars($columnExists['Default'] ?? 'NULL') . "<br>";
        } else {
            echo "❌ Colonne 'profile_completed' manquante dans la table users<br>";
            echo "⚠️ → Exécutez le script de migration: database/migrate_profile_completed.php<br>";
        }
    } else {
        echo "⚠️ Connexion à la base de données non disponible pour ce test<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la vérification de la colonne: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// Vérifier les fichiers de migration
if (file_exists('database/add_profile_completed.sql')) {
    echo "✅ Script SQL de migration trouvé<br>";
} else {
    echo "⚠️ Script SQL de migration manquant<br>";
}

if (file_exists('database/migrate_profile_completed.php')) {
    echo "✅ Script PHP de migration trouvé<br>";
} else {
    echo "⚠️ Script PHP de migration manquant<br>";
}

// Résumé
echo "<h2>📋 Résumé</h2>";
echo "<p>Si tous les tests sont verts (✅), votre configuration Google OAuth devrait fonctionner.</p>";
echo "<p><strong>✨ Nouvelles fonctionnalités :</strong></p>";
echo "<ul>";
echo "<li>📧 <strong>Email automatique</strong> : Les nouveaux clients reçoivent un email de bienvenue</li>";
echo "<li>🔐 <strong>Connexion sécurisée</strong> : Authentification via Google OAuth</li>";
echo "<li>👤 <strong>Création automatique</strong> : Comptes créés automatiquement lors de la première connexion</li>";
echo "<li>📝 <strong>Completion de profil</strong> : Possibilité de compléter ou ignorer le profil après connexion</li>";
echo "</ul>";
echo "<p><strong>Étapes suivantes :</strong></p>";
echo "<ol>";
echo "<li>Configurez vos identifiants Google dans config/oauth_config.php</li>";
echo "<li>Exécutez la migration de base de données si nécessaire</li>";
echo "<li>Testez la connexion sur auth/login.php</li>";
echo "<li>Vérifiez la réception d'email pour un nouveau compte</li>";
echo "<li>Testez le processus de completion de profil</li>";
echo "<li>Supprimez ce fichier de test après vérification</li>";
echo "</ol>";

echo "<hr>";
echo "<p><em>Fichier de test créé le " . date('Y-m-d H:i:s') . "</em></p>";
echo "<p><strong>⚠️ Important :</strong> Supprimez ce fichier après les tests pour des raisons de sécurité.</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #333; }
h2 { color: #666; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
</style>