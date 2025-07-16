<?php
/**
 * Script de débogage pour le système de sécurité email
 * Teste spécifiquement la suppression d'utilisateurs et l'affichage des messages
 */

require_once 'includes/welcome_email_functions.php';

// Fonction pour créer un utilisateur de test
function createDebugTestUser($email, $firstName = 'Debug', $lastName = 'Test') {
    try {
        $mysqli = new mysqli('srv449.hstgr.io', 'u929653200_smartcore_db', 'Lorvens22@', 'u929653200_smartcore_db');
        
        if ($mysqli->connect_error) {
            return ['success' => false, 'message' => 'Erreur de connexion: ' . $mysqli->connect_error];
        }
        
        $mysqli->set_charset('utf8mb4');
        
        // Supprimer l'utilisateur s'il existe déjà
        $deleteStmt = $mysqli->prepare("DELETE FROM users WHERE email = ?");
        $deleteStmt->bind_param('s', $email);
        $deleteStmt->execute();
        
        // Créer l'utilisateur
        $hashedPassword = password_hash('debugpassword123', PASSWORD_DEFAULT);
        $username = 'debug_' . time();
        $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, created_at) VALUES (?, ?, ?, ?, ?, 'client', NOW())");
        $stmt->bind_param('sssss', $username, $email, $hashedPassword, $firstName, $lastName);
        
        if ($stmt->execute()) {
            $userId = $mysqli->insert_id;
            $mysqli->close();
            return ['success' => true, 'message' => 'Utilisateur créé avec succès', 'user_id' => $userId, 'username' => $username];
        } else {
            $mysqli->close();
            return ['success' => false, 'message' => 'Erreur lors de la création: ' . $stmt->error];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

// Fonction pour vérifier si un utilisateur existe
function checkUserExists($email) {
    try {
        $mysqli = new mysqli('srv449.hstgr.io', 'u929653200_smartcore_db', 'Lorvens22@', 'u929653200_smartcore_db');
        
        if ($mysqli->connect_error) {
            return ['exists' => false, 'error' => 'Erreur de connexion: ' . $mysqli->connect_error];
        }
        
        $mysqli->set_charset('utf8mb4');
        
        $stmt = $mysqli->prepare("SELECT id, username, first_name, last_name FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $mysqli->close();
            return ['exists' => true, 'user' => $user];
        } else {
            $mysqli->close();
            return ['exists' => false];
        }
        
    } catch (Exception $e) {
        return ['exists' => false, 'error' => 'Erreur: ' . $e->getMessage()];
    }
}

// Fonction pour tester la suppression directe
function testDirectDeletion($email) {
    echo "<h4>🧪 Test de suppression directe pour: $email</h4>";
    
    $result = deleteUserByEmail($email);
    
    echo "<div style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Résultat de deleteUserByEmail():</strong><br>";
    echo "Success: " . ($result['success'] ? 'true' : 'false') . "<br>";
    echo "Message: " . htmlspecialchars($result['message']) . "<br>";
    echo "</div>";
    
    return $result;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Débogage Système de Sécurité Email - Smartcore Express</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .result-box {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover { background: #c82333; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🐛 Débogage Système de Sécurité Email</h1>
        <p>Diagnostic des problèmes de suppression et d'affichage des messages</p>
    </div>

    <div class="container">
        <h2>🔍 Test de Diagnostic</h2>
        <form method="POST">
            <label for="test_email">Email de test (utilisez un email invalide) :</label><br>
            <input type="email" name="test_email" value="test@domaineinvalide.xyz" style="width: 300px; padding: 8px; margin: 10px 0;" required><br>
            <button type="submit" name="run_debug" class="btn">🚀 Lancer le Test de Débogage</button>
        </form>
    </div>

    <?php
    if (isset($_POST['run_debug'])) {
        $testEmail = $_POST['test_email'];
        
        echo "<div class='container'>";
        echo "<h2>📊 Résultats du Débogage pour: " . htmlspecialchars($testEmail) . "</h2>";
        
        // Étape 1: Créer l'utilisateur de test
        echo "<h3>Étape 1: Création de l'utilisateur de test</h3>";
        $createResult = createDebugTestUser($testEmail, 'Debug', 'Test');
        
        if ($createResult['success']) {
            echo "<div class='result-box success'>";
            echo "✅ Utilisateur créé avec succès<br>";
            echo "ID: " . $createResult['user_id'] . "<br>";
            echo "Username: " . $createResult['username'] . "<br>";
            echo "</div>";
            
            $username = $createResult['username'];
        } else {
            echo "<div class='result-box error'>";
            echo "❌ Erreur lors de la création: " . htmlspecialchars($createResult['message']);
            echo "</div>";
            echo "</div>";
            exit;
        }
        
        // Étape 2: Vérifier que l'utilisateur existe
        echo "<h3>Étape 2: Vérification de l'existence de l'utilisateur</h3>";
        $checkBefore = checkUserExists($testEmail);
        
        if ($checkBefore['exists']) {
            echo "<div class='result-box success'>";
            echo "✅ Utilisateur trouvé dans la base de données<br>";
            echo "Détails: " . json_encode($checkBefore['user'], JSON_PRETTY_PRINT);
            echo "</div>";
        } else {
            echo "<div class='result-box error'>";
            echo "❌ Utilisateur non trouvé: " . (isset($checkBefore['error']) ? $checkBefore['error'] : 'Aucune erreur spécifiée');
            echo "</div>";
        }
        
        // Étape 3: Tenter l'envoi d'email (qui devrait échouer)
        echo "<h3>Étape 3: Tentative d'envoi d'email (attendu: échec)</h3>";
        
        // Activer l'affichage des erreurs pour voir les détails
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        
        echo "<div class='result-box info'>";
        echo "📧 Tentative d'envoi d'email à: " . htmlspecialchars($testEmail) . "<br>";
        echo "Fonction utilisée: sendWelcomeEmailWithPassword()<br>";
        echo "</div>";
        
        $emailResult = sendWelcomeEmailWithPassword($testEmail, 'Debug', 'Test', $username, 'debugpassword123');
        
        echo "<div class='result-box'>";
        echo "<strong>📋 Résultat complet de l'envoi d'email:</strong><br>";
        echo "<pre>" . json_encode($emailResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        echo "</div>";
        
        // Étape 4: Vérifier si l'utilisateur a été supprimé
        echo "<h3>Étape 4: Vérification de la suppression de l'utilisateur</h3>";
        $checkAfter = checkUserExists($testEmail);
        
        if ($checkAfter['exists']) {
            echo "<div class='result-box error'>";
            echo "❌ PROBLÈME: L'utilisateur existe encore dans la base de données !<br>";
            echo "Détails: " . json_encode($checkAfter['user'], JSON_PRETTY_PRINT);
            echo "</div>";
            
            // Test de suppression manuelle
            echo "<h4>🔧 Test de suppression manuelle</h4>";
            $manualDelete = testDirectDeletion($testEmail);
            
            // Vérifier à nouveau
            $checkAfterManual = checkUserExists($testEmail);
            if (!$checkAfterManual['exists']) {
                echo "<div class='result-box success'>";
                echo "✅ Suppression manuelle réussie";
                echo "</div>";
            } else {
                echo "<div class='result-box error'>";
                echo "❌ La suppression manuelle a également échoué";
                echo "</div>";
            }
            
        } else {
            echo "<div class='result-box success'>";
            echo "✅ Utilisateur correctement supprimé de la base de données";
            echo "</div>";
        }
        
        // Étape 5: Analyse des logs d'erreur
        echo "<h3>Étape 5: Analyse des messages et erreurs</h3>";
        
        if (!$emailResult['success']) {
            echo "<div class='result-box info'>";
            echo "<strong>📝 Message d'erreur affiché:</strong><br>";
            echo htmlspecialchars($emailResult['message']) . "<br><br>";
            
            echo "<strong>🔧 Erreur SMTP capturée:</strong><br>";
            echo htmlspecialchars($emailResult['smtp_error']) . "<br><br>";
            
            echo "<strong>🗑️ Statut de suppression:</strong><br>";
            echo ($emailResult['user_deleted'] ? 'Utilisateur supprimé' : 'Utilisateur NON supprimé') . "<br>";
            echo "</div>";
        }
        
        // Résumé final
        echo "<h3>📋 Résumé du Diagnostic</h3>";
        echo "<div class='result-box warning'>";
        echo "<strong>Problèmes identifiés:</strong><br>";
        
        $problems = [];
        
        if ($emailResult['success']) {
            $problems[] = "L'email a été envoyé avec succès alors qu'il devrait échouer avec une adresse invalide";
        }
        
        if (!$emailResult['user_deleted'] && !$emailResult['success']) {
            $problems[] = "L'utilisateur n'a pas été supprimé malgré l'échec de l'envoi d'email";
        }
        
        if (empty($emailResult['smtp_error']) && !$emailResult['success']) {
            $problems[] = "Aucune erreur SMTP capturée malgré l'échec";
        }
        
        if (empty($problems)) {
            echo "✅ Aucun problème détecté - Le système fonctionne correctement";
        } else {
            foreach ($problems as $problem) {
                echo "❌ " . $problem . "<br>";
            }
        }
        
        echo "</div>";
        echo "</div>";
    }
    ?>

    <div class="container">
        <h2>📚 Informations de Débogage</h2>
        <div class="result-box info">
            <h3>🔍 Ce que ce script teste :</h3>
            <ul>
                <li>Création d'un utilisateur de test dans la base de données</li>
                <li>Tentative d'envoi d'email à une adresse invalide</li>
                <li>Vérification de la suppression automatique de l'utilisateur</li>
                <li>Capture et affichage des erreurs SMTP</li>
                <li>Validation des messages d'erreur retournés</li>
            </ul>
            
            <h3>🛠️ Fonctions testées :</h3>
            <ul>
                <li><code>sendWelcomeEmailWithPassword()</code></li>
                <li><code>deleteUserByEmail()</code></li>
                <li>Gestion des exceptions PHPMailer</li>
                <li>Connexion et requêtes MySQL</li>
            </ul>
        </div>
    </div>

    <div class="container">
        <p style="text-align: center; color: #666; font-size: 14px;">
            © 2024 Smartcore Express - Script de Débogage Système Email
        </p>
    </div>
</body>
</html>