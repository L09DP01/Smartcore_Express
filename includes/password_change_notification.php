<?php
/**
 * Fonctions pour l'envoi de notifications de changement de mot de passe
 * Smartcore Express - Système de livraison
 */

// Charger l'autoloader Composer pour les dépendances
require_once __DIR__ . '/../vendor/autoload.php';

require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoie un email de notification de changement de mot de passe
 * @param string $email Email du destinataire
 * @param string $firstName Prénom de l'utilisateur
 * @param string $lastName Nom de l'utilisateur
 * @return bool True si l'email a été envoyé avec succès
 */
function sendPasswordChangeNotification($email, $firstName, $lastName) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'noreply@smartcoreexpress.com';
        $mail->Password   = 'Lorvens22@'; // Mot de passe Hostinger
        $mail->SMTPSecure = 'ssl';
         $mail->Port = 465;
        $mail->CharSet    = 'UTF-8';
        
        // Expéditeur et destinataire
        $mail->setFrom('noreply@smartcoreexpress.com', 'Smartcore Express');
        $mail->addAddress($email, $firstName . ' ' . $lastName);
        
        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = 'Confirmation de changement de mot de passe - Smartcore Express';
        
        // Template HTML
        $htmlBody = generatePasswordChangeEmailTemplate($firstName, $lastName);
        $mail->Body = $htmlBody;
        
        // Version texte alternative
        $mail->AltBody = generatePasswordChangeTextTemplate($firstName, $lastName);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email changement mot de passe: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Génère le template HTML pour l'email de notification de changement de mot de passe
 * @param string $firstName Prénom de l'utilisateur
 * @param string $lastName Nom de l'utilisateur
 * @return string Template HTML
 */
function generatePasswordChangeEmailTemplate($firstName, $lastName) {
    $currentDate = date('d/m/Y à H:i');
    
    return '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Changement de mot de passe - Smartcore Express</title>
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
            .logo-text {
                color: #0047AB;
                font-weight: bold;
                font-size: 14px;
                text-align: center;
            }
            .content {
                padding: 40px 30px;
                background-color: #ffffff;
        }
        .security-notice {
            background: #e8f5e8;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info-box {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 14px;
        }
        .contact-info {
            margin-top: 20px;
            padding: 15px;
            background: #f1f3f4;
            border-radius: 5px;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, #0047AB, #1e5bb8);
                color: white;
                text-decoration: none;
                border-radius: 25px;
                font-weight: bold;
                margin: 20px 0;
                text-align: center;
            }
            .btn:hover {
                background: linear-gradient(135deg, #003d96, #1a4fa0);
            }
            .footer {
                background-color: #f8f9fa;
                padding: 30px;
                text-align: center;
                color: #666;
                font-size: 14px;
            }
            .footer a {
                color: #0047AB;
                text-decoration: none;
            }
            .footer a:hover {
                text-decoration: underline;
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
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Votre partenaire logistique de confiance</p>
            </div>
            
            <div class="content">
        <h2 style="color: #28a745; margin-top: 0;">🔐 Mot de passe modifié avec succès</h2>
        
        <p>Bonjour <strong>' . htmlspecialchars($firstName . ' ' . $lastName) . '</strong>,</p>
        
        <div class="security-notice">
            <h3 style="margin-top: 0; color: #28a745;">✅ Confirmation de sécurité</h3>
            <p><strong>Votre mot de passe a été modifié avec succès</strong> le <strong>' . $currentDate . '</strong>.</p>
        </div>
        
        <div class="info-box">
            <h3 style="margin-top: 0; color: #17a2b8;">📋 Détails de la modification</h3>
            <ul style="margin: 10px 0;">
                <li><strong>Date et heure :</strong> ' . $currentDate . '</li>
                <li><strong>Action :</strong> Changement de mot de passe</li>
                <li><strong>Statut :</strong> Réussi</li>
                <li><strong>Origine :</strong> Paramètres du compte</li>
            </ul>
        </div>
        
        <div class="warning-box">
            <h3 style="margin-top: 0; color: #856404;">⚠️ Ce changement n\'était pas de vous ?</h3>
            <p>Si vous n\'avez pas effectué cette modification, <strong>contactez-nous immédiatement</strong> :</p>
            <ul>
                <li>📧 Email : <a href="mailto:support@smartcoreexpress.com">support@smartcoreexpress.com</a></li>
                <li>📞 Téléphone : 50940035664</li>
            </ul>
        </div>
        
        <h3 style="color: #333;">🛡️ Conseils de sécurité</h3>
        <ul>
            <li>Ne partagez jamais votre mot de passe avec personne</li>
            <li>Utilisez un mot de passe unique et complexe</li>
            <li>Déconnectez-vous toujours après utilisation</li>
            <li>Vérifiez régulièrement l\'activité de votre compte</li>
        </ul>
        
                <div class="contact-info">
                    <h3 style="margin-top: 0;">📞 Besoin d\'aide ?</h3>
                    <p>Notre équipe support est disponible 24h/7j pour vous assister :</p>
                    <p>
                        📧 <a href="mailto:support@smartcoreexpress.com">support@smartcoreexpress.com</a><br>
                        🌐 <a href="https://smartcoreexpress.com">https://smartcoreexpress.com</a>
                    </p>
                </div>
            </div>
            
            <div class="footer">
                <p><strong>SMARTCORE EXPRESS</strong></p>
                <p>Votre partenaire de confiance pour l\'expédition internationale</p>
                <p style="margin: 20px 0;">📧 <a href="mailto:support@smartcoreexpress.com">support@smartcoreexpress.com</a> | 🌐 <a href="https://smartcoreexpress.com">smartcoreexpress.com</a></p>
                <p style="font-size: 12px; color: #999; margin-top: 20px;">Cet email a été envoyé automatiquement, merci de ne pas y répondre.<br>© 2025 Smartcore Express. Tous droits réservés.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

/**
 * Génère le template texte pour l'email de notification de changement de mot de passe
 * @param string $firstName Prénom de l'utilisateur
 * @param string $lastName Nom de l'utilisateur
 * @return string Template texte
 */
function generatePasswordChangeTextTemplate($firstName, $lastName) {
    $currentDate = date('d/m/Y à H:i');
    
    return "
SMARTCORE EXPRESS - Confirmation de changement de mot de passe

Bonjour " . $firstName . " " . $lastName . ",

Votre mot de passe a été modifié avec succès le " . $currentDate . ".

Détails de la modification :
- Date et heure : " . $currentDate . "
- Action : Changement de mot de passe
- Statut : Réussi
- Origine : Paramètres du compte

Ce changement n'était pas de vous ?
Si vous n'avez pas effectué cette modification, contactez-nous immédiatement :
- Email : support@smartcoreexpress.com
- Téléphone : +1 (555) 123-4567

Conseils de sécurité :
- Ne partagez jamais votre mot de passe
- Utilisez un mot de passe unique et complexe
- Déconnectez-vous toujours après utilisation
- Vérifiez régulièrement l'activité de votre compte

Besoin d'aide ?
Notre équipe support : support@smartcoreexpress.com
Site web : https://smartcoreexpress.com

---
Smartcore Express - Votre partenaire de confiance
Cet email a été envoyé automatiquement.
© 2025 Smartcore Express. Tous droits réservés.
";
}

/**
 * Log les tentatives de changement de mot de passe pour la sécurité
 * @param int $userId ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 * @param bool $success Succès ou échec
 * @param string $errorMessage Message d'erreur si échec
 */
function logPasswordChangeAttempt($userId, $email, $success, $errorMessage = '') {
    $logFile = '../logs/password_changes.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $logEntry = "[{$timestamp}] PASSWORD_CHANGE: User ID: {$userId}, Email: {$email}, Status: {$status}, IP: {$ip}";
    if (!$success && $errorMessage) {
        $logEntry .= ", Error: {$errorMessage}";
    }
    $logEntry .= PHP_EOL;
    
    // Créer le dossier logs s'il n'existe pas
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>