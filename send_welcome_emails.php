<?php
/**
 * Script automatisé d'envoi d'emails de bienvenue
 * Smartcore Express - Système de livraison
 */

// Charger l'autoloader Composer pour les dépendances
require_once 'vendor/autoload.php';

require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Configuration SMTP
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'noreply@smartcoreexpress.com');
define('SMTP_PASSWORD', 'Lorvens22@');
define('FROM_EMAIL', 'noreply@smartcoreexpress.com');
define('FROM_NAME', 'Smartcore Express');

/**
 * Fonction pour créer le template HTML de l'email de bienvenue
 */
function getWelcomeEmailTemplate($firstName, $lastName) {
    $template = '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bienvenue chez Smartcore Express</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 30px 20px;
                text-align: center;
            }
            .logo {
                max-width: 200px;
                height: auto;
            }
            .content {
                padding: 40px 30px;
                text-align: center;
            }
            .welcome-title {
                color: #333;
                font-size: 28px;
                margin-bottom: 20px;
                font-weight: bold;
            }
            .welcome-text {
                color: #666;
                font-size: 16px;
                line-height: 1.6;
                margin-bottom: 30px;
            }
            .cta-button {
                display: inline-block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 25px;
                font-weight: bold;
                font-size: 16px;
                margin: 20px 0;
                transition: transform 0.3s ease;
            }
            .cta-button:hover {
                transform: translateY(-2px);
            }
            .features {
                background-color:rgb(8, 53, 98);
                padding: 30px;
                margin: 30px 0;
                border-radius: 10px;
            }
            .feature-item {
                margin: 15px 0;
                color: #555;
            }
            .footer {
                background-color: #333;
                color: white;
                padding: 20px;
                text-align: center;
                font-size: 14px;
            }
            .social-links {
                margin: 20px 0;
            }
            .social-links a {
                color: #667eea;
                text-decoration: none;
                margin: 0 10px;
            }
            @media (max-width: 600px) {
                .container {
                    width: 100% !important;
                }
                .content {
                    padding: 20px !important;
                }
                .welcome-title {
                    font-size: 24px !important;
                }
            }
        </style>
        <link rel="icon" type="image/png" href="client/logo.png">
</head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://smartcoreexpress.com/img/Logo.png" alt="Smartcore Express" class="logo">
            </div>
            
            <div class="content">
                <h1 class="welcome-title">Bienvenue ' . htmlspecialchars($firstName . ' ' . $lastName) . ' !</h1>
                
                <p class="welcome-text">
                    Nous sommes ravis de vous accueillir dans la famille Smartcore Express ! 
                    Votre compte a été créé avec succès et vous pouvez maintenant profiter 
                    de nos services de livraison rapide et sécurisée.
                </p>
                
                <a href="https://smartcoreexpress.com/auth/login.php" class="cta-button">
                    Accéder à mon compte
                </a>
                
                <div class="features">
                    <h3 style="color: #333; margin-bottom: 20px;">Nos services à votre disposition :</h3>
                    <div class="feature-item">📦 Suivi en temps réel de vos colis</div>
                    <div class="feature-item">🚚 Livraison rapide et sécurisée</div>
                    <div class="feature-item">💰 Tarifs compétitifs</div>
                    <div class="feature-item">🛒 Service d\'achat assisté</div>
                    <div class="feature-item">📱 Interface moderne et intuitive</div>
                </div>
                
                <p class="welcome-text">
                    Si vous avez des questions, n\'hésitez pas à nous contacter. 
                    Notre équipe est là pour vous accompagner !
                </p>
            </div>
            
            <div class="footer">
                <p><strong>Smartcore Express</strong></p>
                <p>Votre partenaire de confiance pour vos livraisons</p>
                <div class="social-links">
                    <a href="#">Facebook</a> |
                    <a href="#">Instagram</a> |
                    <a href="#">Twitter</a>
                </div>
                <p style="font-size: 12px; color: #999; margin-top: 20px;">
                    Cet email a été envoyé automatiquement. Merci de ne pas y répondre.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $template;
}

/**
 * Fonction pour envoyer un email de bienvenue
 */
function sendWelcomeEmail($email, $firstName, $lastName) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = 'ssl';
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Destinataires
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $firstName . ' ' . $lastName);
        $mail->addReplyTo(FROM_EMAIL, FROM_NAME);
        
        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = 'Bienvenue chez Smartcore Express ! 🎉';
        $mail->Body = getWelcomeEmailTemplate($firstName, $lastName);
        $mail->AltBody = "Bienvenue $firstName $lastName !\n\nNous sommes ravis de vous accueillir chez Smartcore Express. Votre compte a été créé avec succès.\n\nConnectez-vous à votre compte : https://smartcoreexpress.com/auth/login.php\n\nCordialement,\nL'équipe Smartcore Express";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email de bienvenue à $email: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Fonction principale pour traiter les emails de bienvenue
 */
function processWelcomeEmails() {
    try {
        $conn = getDBConnection();
        
        // Récupérer tous les utilisateurs qui n'ont pas encore reçu l'email de bienvenue
        $stmt = $conn->prepare("
            SELECT id, email, first_name, last_name 
            FROM users 
            WHERE welcome_email_sent = FALSE 
            AND role = 'client' 
            AND is_active = TRUE
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($users as $user) {
            echo "Envoi de l'email de bienvenue à: {$user['email']}...\n";
            
            if (sendWelcomeEmail($user['email'], $user['first_name'], $user['last_name'])) {
                // Marquer l'email comme envoyé
                $updateStmt = $conn->prepare("
                    UPDATE users 
                    SET welcome_email_sent = TRUE 
                    WHERE id = ?
                ");
                $updateStmt->execute([$user['id']]);
                
                echo "✅ Email envoyé avec succès à {$user['email']}\n";
                $successCount++;
                
                // Pause pour éviter de surcharger le serveur SMTP
                sleep(1);
                
            } else {
                echo "❌ Erreur lors de l'envoi à {$user['email']}\n";
                $errorCount++;
            }
        }
        
        echo "\n=== RÉSUMÉ ===\n";
        echo "Emails traités: " . count($users) . "\n";
        echo "Succès: $successCount\n";
        echo "Erreurs: $errorCount\n";
        
    } catch (PDOException $e) {
        echo "Erreur de base de données: " . $e->getMessage() . "\n";
        error_log("Erreur processWelcomeEmails: " . $e->getMessage());
    }
}

// Exécution du script
if (php_sapi_name() === 'cli') {
    // Exécution en ligne de commande
    echo "=== SMARTCORE EXPRESS - ENVOI D'EMAILS DE BIENVENUE ===\n";
    echo "Démarrage du processus...\n\n";
    processWelcomeEmails();
} else {
    // Exécution via navigateur (pour les tests)
    echo "<h2>Smartcore Express - Envoi d'emails de bienvenue</h2>";
    echo "<pre>";
    processWelcomeEmails();
    echo "</pre>";
}
?>