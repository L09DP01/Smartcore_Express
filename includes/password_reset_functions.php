<?php
// Charger l'autoloader Composer pour les dépendances
require_once __DIR__ . '/../vendor/autoload.php';



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoie un email de réinitialisation de mot de passe
 */
function sendPasswordResetEmail($email, $first_name, $last_name, $reset_token) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP Hostinger
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@smartcoreexpress.com';
        $mail->Password   = 'Lorvens22@';
        $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0; // Mode debug désactivé
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataire
        $mail->setFrom('noreply@smartcoreexpress.com', 'Smartcore Express');
        $mail->addAddress($email, $first_name . ' ' . $last_name);
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation de votre mot de passe - Smartcore Express';
        $mail->Body = generatePasswordResetEmailTemplate($first_name, $last_name, $reset_token);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email de réinitialisation: " . $e->getMessage());
        error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
        // Afficher l'erreur pour le debug (à retirer en production)
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px;'>";
        echo "<strong>Erreur de debug:</strong><br>";
        echo "Message: " . $e->getMessage() . "<br>";
        echo "PHPMailer ErrorInfo: " . $mail->ErrorInfo;
        echo "</div>";
        return false;
    }
}

/**
 * Génère le template HTML pour l'email de réinitialisation de mot de passe
 */
function generatePasswordResetEmailTemplate($first_name, $last_name, $reset_token) {
    $resetUrl = 'https://smartcoreexpress.com/auth/reset_password.php?token=' . $reset_token;
    
    return '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Réinitialisation de mot de passe - Smartcore Express</title>
        <style>
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #0047AB, #1e5bb8);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .logo {
                width: 80px;
                height: 80px;
                background-color: white;
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }
            .logo img {
                width: 70px;
                height: 70px;
                object-fit: contain;
            }
            .content {
                padding: 40px 30px;
            }
            .reset-message {
                font-size: 24px;
                font-weight: bold;
                color: #0047AB;
                margin-bottom: 20px;
                text-align: center;
            }
            .reset-button {
                display: inline-block;
                background-color: #0047AB;
                color: white !important;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                margin: 20px 0;
                transition: background-color 0.3s;
                text-align: center;
            }
            .reset-button:hover {
                background-color: #003d96;
            }
            .security-notice {
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 8px;
                padding: 20px;
                margin: 30px 0;
            }
            .security-title {
                font-weight: bold;
                color: #856404;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .footer {
                background-color: #f8f9fa;
                padding: 30px;
                text-align: center;
                color: #666;
                border-top: 1px solid #e0e0e0;
            }
            .company-info {
                margin-top: 20px;
                font-size: 14px;
            }
            .expiry-notice {
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 8px;
                padding: 15px;
                margin: 20px 0;
                color: #721c24;
                text-align: center;
            }
            @media (max-width: 600px) {
                .container {
                    margin: 10px;
                    border-radius: 5px;
                }
                .content {
                    padding: 20px 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">
                    <img src="https://smartcoreexpress.com/img/Logo.png" alt="Smartcore Express Logo">
                </div>
                <h1 style="margin: 0; font-size: 28px;">SMARTCORE EXPRESS</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Réinitialisation de mot de passe</p>
            </div>
            
            <div class="content">
                <div class="reset-message">
                    🔐 Réinitialisation de mot de passe
                </div>
                
                <p>Bonjour <strong>' . htmlspecialchars($first_name . ' ' . $last_name) . '</strong>,</p>
                
                <p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte <strong>Smartcore Express</strong>.</p>
                
                <p>Si vous êtes à l\'origine de cette demande, cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe :</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $resetUrl . '" class="reset-button">
                        🔑 Réinitialiser mon mot de passe
                    </a>
                </div>
                
                <div class="expiry-notice">
                    ⏰ <strong>Important :</strong> Ce lien expire dans 1 heure pour votre sécurité.
                </div>
                
                <div class="security-notice">
                    <div class="security-title">
                        🛡️ Conseils de sécurité
                    </div>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email</li>
                        <li>Ne partagez jamais ce lien avec qui que ce soit</li>
                        <li>Choisissez un mot de passe fort et unique</li>
                        <li>Contactez-nous immédiatement si vous suspectez une activité suspecte</li>
                    </ul>
                </div>
                
                <p style="margin-top: 30px;">Si le bouton ne fonctionne pas, vous pouvez copier et coller ce lien dans votre navigateur :</p>
                <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 14px;">' . $resetUrl . '</p>
                
                <p style="margin-top: 30px;">Si vous avez des questions, n\'hésitez pas à nous contacter.</p>
                
                <p style="margin-top: 20px;">Cordialement,<br>
                <strong>L\'équipe Smartcore Express</strong></p>
            </div>
            
            <div class="footer">
                <p><strong>Smartcore Express</strong></p>
                <p>Email: contact@smartcoreexpress.com | Téléphone: 40035664</p>
                <div class="company-info">
                    <p>© 2024 Smartcore Express. Tous droits réservés.</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Vérifie si un token de réinitialisation est valide
 */
function validateResetToken($token) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT prt.user_id, u.email, u.first_name, u.last_name 
            FROM password_reset_tokens prt 
            JOIN users u ON prt.user_id = u.id 
            WHERE prt.token = ? AND prt.expires_at > NOW() AND u.is_active = 1
        ");
        $stmt->execute([$token]);
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un token de réinitialisation après utilisation
 */
function deleteResetToken($token) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Nettoie automatiquement tous les tokens de réinitialisation expirés
 * Cette fonction doit être appelée périodiquement (par exemple via un cron job)
 */
function cleanExpiredPasswordResetTokens() {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Supprimer tous les tokens expirés
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        
        $deleted_count = $stmt->rowCount();
        
        // Log du nettoyage
        if ($deleted_count > 0) {
            error_log("Nettoyage automatique des tokens de réinitialisation: {$deleted_count} tokens expirés supprimés.");
        }
        
        return $deleted_count;
    } catch (PDOException $e) {
        error_log("Erreur lors du nettoyage des tokens expirés: " . $e->getMessage());
        return false;
    }
}

/**
 * Nettoie les tokens expirés lors de chaque réinitialisation de mot de passe
 * Cette fonction est appelée automatiquement pour maintenir la base de données propre
 */
function cleanExpiredTokensOnPasswordReset() {
    // Nettoyer les tokens expirés à chaque réinitialisation
    cleanExpiredPasswordResetTokens();
}
?>