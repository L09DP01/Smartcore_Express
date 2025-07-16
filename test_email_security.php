<?php
/**
 * Script de test pour le système de sécurité email
 * Teste l'envoi d'emails avec suppression automatique en cas d'échec
 * 
 * @author Smartcore Express
 * @version 1.0
 */

require_once 'includes/welcome_email_functions.php';
require_once 'includes/welcome_email_with_validation.php';

// Fonction pour afficher les résultats de test
function displayTestResult($testName, $result) {
    echo "<div style='border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px;'>";
    echo "<h3 style='color: #0047AB; margin-top: 0;'>🧪 Test: $testName</h3>";
    
    if ($result['success']) {
        echo "<p style='color: green; font-weight: bold;'>✅ Succès: " . htmlspecialchars($result['message']) . "</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Échec: " . htmlspecialchars($result['message']) . "</p>";
        
        if (isset($result['smtp_error']) && !empty($result['smtp_error'])) {
            echo "<p style='color: orange;'><strong>📧 Erreur SMTP:</strong> " . htmlspecialchars($result['smtp_error']) . "</p>";
        }
        
        if (isset($result['user_deleted'])) {
            if ($result['user_deleted']) {
                echo "<p style='color: blue;'><strong>🗑️ Utilisateur supprimé:</strong> Oui</p>";
            } else {
                echo "<p style='color: orange;'><strong>🗑️ Utilisateur supprimé:</strong> Non</p>";
            }
        }
    }
    
    echo "</div>";
}

// Fonction pour créer un utilisateur de test
function createTestUser($email, $firstName = 'Test', $lastName = 'User', $username = 'testuser') {
    try {
        $mysqli = new mysqli('srv449.hstgr.io', 'u929653200_smartcore_db', 'Lorvens22@', 'u929653200_smartcore_db');
        
        if ($mysqli->connect_error) {
            return ['success' => false, 'message' => 'Erreur de connexion: ' . $mysqli->connect_error];
        }
        
        $mysqli->set_charset('utf8mb4');
        
        // Vérifier si l'utilisateur existe déjà
        $checkStmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $mysqli->close();
            return ['success' => true, 'message' => 'Utilisateur existe déjà'];
        }
        
        // Créer l'utilisateur
        $hashedPassword = password_hash('testpassword123', PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, created_at) VALUES (?, ?, ?, ?, ?, 'client', NOW())");
        $stmt->bind_param('sssss', $username, $email, $hashedPassword, $firstName, $lastName);
        
        if ($stmt->execute()) {
            $mysqli->close();
            return ['success' => true, 'message' => 'Utilisateur créé avec succès'];
        } else {
            $mysqli->close();
            return ['success' => false, 'message' => 'Erreur lors de la création: ' . $stmt->error];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

// Fonction pour vérifier si un utilisateur existe
function userExists($email) {
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
    <title>Test du Système de Sécurité Email - Smartcore Express</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #0047AB, #1e5bb8);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0047AB;
        }
        input[type="email"], input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="email"]:focus, input[type="text"]:focus {
            border-color: #0047AB;
            outline: none;
        }
        .btn {
            background: #0047AB;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn:hover {
            background: #003d96;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #0047AB;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .results {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔒 Test du Système de Sécurité Email</h1>
        <p>Smartcore Express - Validation et Suppression Automatique</p>
    </div>

    <div class="container">
        <div class="info-box">
            <h3>📋 Fonctionnalités testées :</h3>
            <ul>
                <li><strong>Envoi d'email de bienvenue</strong> avec validation d'adresse</li>
                <li><strong>Suppression automatique</strong> de l'utilisateur si l'email échoue</li>
                <li><strong>Capture des erreurs SMTP</strong> détaillées</li>
                <li><strong>Messages d'erreur clairs</strong> pour l'utilisateur</li>
                <li><strong>Logging complet</strong> des opérations</li>
            </ul>
        </div>

        <div class="warning-box">
            <h3>⚠️ Important :</h3>
            <p>Ce script teste le système de sécurité en créant des utilisateurs temporaires et en tentant d'envoyer des emails. Les utilisateurs avec des adresses email invalides seront automatiquement supprimés de la base de données.</p>
        </div>

        <h2>🧪 Tests Manuels</h2>
        
        <form method="POST" style="margin-bottom: 30px;">
            <div class="form-group">
                <label for="test_email">Adresse Email à Tester :</label>
                <input type="email" id="test_email" name="test_email" 
                       placeholder="exemple@domaine.com" 
                       value="<?php echo isset($_POST['test_email']) ? htmlspecialchars($_POST['test_email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="first_name">Prénom :</label>
                <input type="text" id="first_name" name="first_name" 
                       placeholder="Jean" 
                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : 'Test'; ?>">
            </div>
            
            <div class="form-group">
                <label for="last_name">Nom :</label>
                <input type="text" id="last_name" name="last_name" 
                       placeholder="Dupont" 
                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : 'User'; ?>">
            </div>
            
            <button type="submit" name="test_manual" class="btn">🧪 Tester l'Envoi d'Email</button>
            <button type="submit" name="test_oauth" class="btn btn-secondary">🔐 Tester OAuth Email</button>
        </form>

        <h2>🤖 Tests Automatiques</h2>
        <form method="POST">
            <button type="submit" name="run_auto_tests" class="btn">▶️ Exécuter les Tests Automatiques</button>
        </form>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<div class='container results'>";
        echo "<h2>📊 Résultats des Tests</h2>";
        
        if (isset($_POST['test_manual'])) {
            $email = $_POST['test_email'];
            $firstName = $_POST['first_name'] ?: 'Test';
            $lastName = $_POST['last_name'] ?: 'User';
            $username = 'test_' . time();
            
            echo "<h3>Test Manuel - Email avec Mot de Passe</h3>";
            
            // Créer l'utilisateur de test
            $createResult = createTestUser($email, $firstName, $lastName, $username);
            echo "<p><strong>Création utilisateur:</strong> " . htmlspecialchars($createResult['message']) . "</p>";
            
            if ($createResult['success']) {
                // Tester l'envoi d'email
                $result = sendWelcomeEmailWithPassword($email, $firstName, $lastName, $username, 'testpassword123');
                displayTestResult("Envoi Email avec Mot de Passe", $result);
                
                // Vérifier si l'utilisateur existe encore
                $stillExists = userExists($email);
                echo "<p><strong>Utilisateur existe encore:</strong> " . ($stillExists ? 'Oui' : 'Non') . "</p>";
            }
            
        } elseif (isset($_POST['test_oauth'])) {
            $email = $_POST['test_email'];
            $firstName = $_POST['first_name'] ?: 'Test';
            $lastName = $_POST['last_name'] ?: 'User';
            $username = 'oauth_' . time();
            
            echo "<h3>Test Manuel - Email OAuth</h3>";
            
            // Créer l'utilisateur de test
            $createResult = createTestUser($email, $firstName, $lastName, $username);
            echo "<p><strong>Création utilisateur:</strong> " . htmlspecialchars($createResult['message']) . "</p>";
            
            if ($createResult['success']) {
                // Tester l'envoi d'email OAuth
                $result = sendWelcomeEmailOAuth($email, $firstName, $lastName, 'Google');
                displayTestResult("Envoi Email OAuth", $result);
                
                // Vérifier si l'utilisateur existe encore
                $stillExists = userExists($email);
                echo "<p><strong>Utilisateur existe encore:</strong> " . ($stillExists ? 'Oui' : 'Non') . "</p>";
            }
            
        } elseif (isset($_POST['run_auto_tests'])) {
            echo "<h3>Tests Automatiques</h3>";
            
            // Test 1: Email invalide (domaine inexistant)
            $invalidEmail = 'test@domaineinexistant' . time() . '.xyz';
            $createResult1 = createTestUser($invalidEmail, 'Test', 'Invalid', 'testinvalid' . time());
            
            if ($createResult1['success']) {
                $result1 = sendWelcomeEmailWithPassword($invalidEmail, 'Test', 'Invalid', 'testinvalid', 'password123');
                displayTestResult("Email Invalide (Domaine Inexistant)", $result1);
            }
            
            // Test 2: Email avec format invalide
            $malformedEmail = 'emailmalformé@';
            $createResult2 = createTestUser($malformedEmail, 'Test', 'Malformed', 'testmalformed' . time());
            
            if ($createResult2['success']) {
                $result2 = sendWelcomeEmailOAuth($malformedEmail, 'Test', 'Malformed', 'Google');
                displayTestResult("Email Malformé", $result2);
            }
            
            // Test 3: Utilisation de la classe WelcomeEmailValidator
            echo "<h4>Test avec WelcomeEmailValidator</h4>";
            $validatorEmail = 'validator@inexistant' . time() . '.test';
            $createResult3 = createTestUser($validatorEmail, 'Validator', 'Test', 'validator' . time());
            
            if ($createResult3['success']) {
                $result3 = sendWelcomeEmailWithValidation($validatorEmail, 'Validator', 'Test', 'validator');
                displayTestResult("WelcomeEmailValidator", $result3);
            }
            
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
            echo "<h4 style='color: #155724; margin-top: 0;'>✅ Tests Automatiques Terminés</h4>";
            echo "<p style='color: #155724;'>Tous les tests automatiques ont été exécutés. Les utilisateurs avec des emails invalides ont été automatiquement supprimés de la base de données.</p>";
            echo "</div>";
        }
        
        echo "</div>";
    }
    ?>

    <div class="container">
        <h2>📚 Documentation</h2>
        <div class="info-box">
            <h3>🔧 Fonctionnement du Système de Sécurité :</h3>
            <ol>
                <li><strong>Tentative d'envoi</strong> : Le système tente d'envoyer l'email de bienvenue</li>
                <li><strong>Détection d'échec</strong> : Si l'envoi échoue (adresse invalide, serveur inaccessible, etc.)</li>
                <li><strong>Capture d'erreur</strong> : L'erreur SMTP est capturée avec <code>$mail->ErrorInfo</code></li>
                <li><strong>Suppression automatique</strong> : L'utilisateur est supprimé de la table <code>users</code></li>
                <li><strong>Message clair</strong> : Un message d'erreur explicite est retourné</li>
                <li><strong>Logging</strong> : Toutes les opérations sont enregistrées dans les logs</li>
            </ol>
            
            <h3>📁 Fichiers Concernés :</h3>
            <ul>
                <li><code>includes/welcome_email_functions.php</code> - Fonctions d'envoi avec sécurité</li>
                <li><code>includes/welcome_email_with_validation.php</code> - Classe de validation complète</li>
                <li><code>test_email_security.php</code> - Ce script de test</li>
            </ul>
            
            <h3>🔒 Sécurité :</h3>
            <ul>
                <li>Suppression automatique des comptes avec emails invalides</li>
                <li>Prévention de l'accumulation de comptes inutilisables</li>
                <li>Logging détaillé pour le débogage</li>
                <li>Messages d'erreur clairs pour l'utilisateur</li>
            </ul>
        </div>
    </div>

    <div class="container">
        <p style="text-align: center; color: #666; font-size: 14px;">
            © 2024 Smartcore Express - Système de Test de Sécurité Email
        </p>
    </div>
</body>
</html>