<?php
/**
 * Test simple pour vérifier le système de sécurité email amélioré
 */

require_once 'includes/welcome_email_functions.php';

// Fonction pour créer un utilisateur de test
function createSimpleTestUser($email) {
    try {
        $mysqli = new mysqli('srv449.hstgr.io', 'u929653200_smartcore_db', 'Lorvens22@', 'u929653200_smartcore_db');
        
        if ($mysqli->connect_error) {
            return false;
        }
        
        $mysqli->set_charset('utf8mb4');
        
        // Supprimer l'utilisateur s'il existe déjà
        $deleteStmt = $mysqli->prepare("DELETE FROM users WHERE email = ?");
        $deleteStmt->bind_param('s', $email);
        $deleteStmt->execute();
        
        // Créer l'utilisateur
        $hashedPassword = password_hash('testpass123', PASSWORD_DEFAULT);
        $username = 'test_' . time() . '_' . rand(1000, 9999);
        $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, created_at) VALUES (?, ?, ?, 'Test', 'User', 'client', NOW())");
        $stmt->bind_param('sss', $username, $email, $hashedPassword);
        
        $success = $stmt->execute();
        $mysqli->close();
        
        return $success ? $username : false;
        
    } catch (Exception $e) {
        return false;
    }
}

// Fonction pour vérifier si un utilisateur existe
function checkSimpleUserExists($email) {
    try {
        $mysqli = new mysqli('srv449.hstgr.io', 'u929653200_smartcore_db', 'Lorvens22@', 'u929653200_smartcore_db');
        
        if ($mysqli->connect_error) {
            return false;
        }
        
        $mysqli->set_charset('utf8mb4');
        
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $exists = $result->num_rows > 0;
        $mysqli->close();
        
        return $exists;
        
    } catch (Exception $e) {
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Simple - Système Email Sécurisé</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header {
            background: #0047AB;
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .btn {
            background: #0047AB;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover { background: #003d96; }
        input[type="email"] {
            width: 300px;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
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
        <h1>🧪 Test Simple - Système Email Sécurisé</h1>
        <p>Test rapide de la suppression automatique d'utilisateurs</p>
    </div>

    <div class="container">
        <h2>📧 Test avec Email Invalide</h2>
        <p>Ce test va :</p>
        <ol>
            <li>Créer un utilisateur avec un email invalide</li>
            <li>Tenter d'envoyer un email (qui devrait échouer)</li>
            <li>Vérifier que l'utilisateur a été supprimé automatiquement</li>
        </ol>
        
        <form method="POST">
            <label for="test_email">Email de test (utilisez un format invalide) :</label><br>
            <input type="email" name="test_email" value="test@domaineinvalide.xyz" required><br>
            <button type="submit" name="run_test" class="btn">🚀 Lancer le Test</button>
        </form>
    </div>

    <?php
    if (isset($_POST['run_test'])) {
        $testEmail = $_POST['test_email'];
        
        echo "<div class='container'>";
        echo "<h2>📊 Résultats du Test pour: " . htmlspecialchars($testEmail) . "</h2>";
        
        // Étape 1: Créer l'utilisateur
        echo "<h3>Étape 1: Création de l'utilisateur</h3>";
        $username = createSimpleTestUser($testEmail);
        
        if ($username) {
            echo "<div class='success'>✅ Utilisateur créé avec succès (Username: $username)</div>";
        } else {
            echo "<div class='error'>❌ Erreur lors de la création de l'utilisateur</div>";
            echo "</div>";
            exit;
        }
        
        // Étape 2: Vérifier que l'utilisateur existe
        echo "<h3>Étape 2: Vérification de l'existence (avant envoi)</h3>";
        $existsBefore = checkSimpleUserExists($testEmail);
        
        if ($existsBefore) {
            echo "<div class='success'>✅ Utilisateur confirmé dans la base de données</div>";
        } else {
            echo "<div class='error'>❌ Utilisateur non trouvé dans la base de données</div>";
        }
        
        // Étape 3: Tenter l'envoi d'email
        echo "<h3>Étape 3: Tentative d'envoi d'email</h3>";
        echo "<div class='info'>📧 Envoi en cours vers: " . htmlspecialchars($testEmail) . "...</div>";
        
        $startTime = microtime(true);
        $result = sendWelcomeEmailWithPassword($testEmail, 'Test', 'User', $username, 'testpass123');
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime), 2);
        
        echo "<div class='info'>⏱️ Durée du test: {$duration} secondes</div>";
        
        // Afficher le résultat détaillé
        echo "<h4>📋 Résultat de l'envoi d'email:</h4>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        if ($result['success']) {
            echo "<div class='warning'>⚠️ ATTENTION: L'email a été envoyé avec succès. Cela peut indiquer que l'adresse n'est pas vraiment invalide.</div>";
        } else {
            echo "<div class='success'>✅ L'envoi d'email a échoué comme attendu</div>";
            
            if (!empty($result['smtp_error'])) {
                echo "<div class='info'><strong>🔧 Erreur SMTP capturée:</strong><br>" . htmlspecialchars($result['smtp_error']) . "</div>";
            }
        }
        
        // Étape 4: Vérifier la suppression
        echo "<h3>Étape 4: Vérification de la suppression (après envoi)</h3>";
        $existsAfter = checkSimpleUserExists($testEmail);
        
        if (!$existsAfter && !$result['success']) {
            echo "<div class='success'>✅ SUCCÈS: L'utilisateur a été correctement supprimé après l'échec de l'email</div>";
        } elseif ($existsAfter && !$result['success']) {
            echo "<div class='error'>❌ PROBLÈME: L'utilisateur existe encore malgré l'échec de l'email</div>";
            
            if (isset($result['user_deleted'])) {
                echo "<div class='warning'>📝 Statut de suppression rapporté: " . ($result['user_deleted'] ? 'Supprimé' : 'Non supprimé') . "</div>";
            }
        } elseif ($result['success']) {
            echo "<div class='warning'>⚠️ L'email a été envoyé avec succès, donc l'utilisateur n'a pas été supprimé (comportement normal)</div>";
        }
        
        // Résumé final
        echo "<h3>📋 Résumé</h3>";
        if (!$result['success'] && !$existsAfter) {
            echo "<div class='success'><strong>🎉 SYSTÈME FONCTIONNE CORRECTEMENT</strong><br>";
            echo "L'email a échoué et l'utilisateur a été supprimé automatiquement.</div>";
        } elseif (!$result['success'] && $existsAfter) {
            echo "<div class='error'><strong>🚨 PROBLÈME DÉTECTÉ</strong><br>";
            echo "L'email a échoué mais l'utilisateur n'a pas été supprimé.</div>";
        } else {
            echo "<div class='warning'><strong>⚠️ RÉSULTAT INATTENDU</strong><br>";
            echo "L'email a été envoyé avec succès. Essayez avec une adresse plus clairement invalide.</div>";
        }
        
        echo "</div>";
    }
    ?>

    <div class="container">
        <h2>💡 Conseils pour le Test</h2>
        <div class="info">
            <h3>📧 Emails à tester :</h3>
            <ul>
                <li><code>test@domaineinexistant.xyz</code> - Domaine inexistant</li>
                <li><code>invalid@.com</code> - Format invalide</li>
                <li><code>test@localhost</code> - Domaine local</li>
                <li><code>user@example.invalid</code> - TLD invalide</li>
            </ul>
            
            <h3>🔍 Ce qui est testé :</h3>
            <ul>
                <li>Validation du format d'email avec <code>filter_var()</code></li>
                <li>Configuration SMTP stricte avec timeout</li>
                <li>Capture des erreurs PHPMailer</li>
                <li>Suppression automatique en cas d'échec</li>
                <li>Logging détaillé des opérations</li>
            </ul>
        </div>
    </div>

    <div class="container">
        <p style="text-align: center; color: #666; font-size: 14px;">
            © 2024 Smartcore Express - Test Simple du Système Email
        </p>
    </div>
</body>
</html>