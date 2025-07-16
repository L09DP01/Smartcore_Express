<?php
// Charger l'autoloader Composer pour les dépendances
require_once __DIR__ . '/../vendor/autoload.php';

require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Fonction pour envoyer l'email de bienvenue OAuth (sans mot de passe) avec sécurité
 */
function sendWelcomeEmailOAuth($email, $first_name, $last_name, $provider) {
    $mail = new PHPMailer(true);
    
    try {
        // Validation préalable de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format d'email invalide: $email");
        }
        
        // Configuration SMTP avec options strictes
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@smartcoreexpress.com';
        $mail->Password = 'Lorvens22@';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Configuration stricte pour détecter les erreurs
        $mail->SMTPDebug = 0; // Désactiver le debug en production
        $mail->Timeout = 30; // Timeout de 30 secondes
        
        // Configuration de l'email
        $mail->setFrom('noreply@smartcoreexpress.com', 'SMARTCORE EXPRESS');
        $mail->addAddress($email, $first_name . ' ' . $last_name);
        
        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = 'Bienvenue chez Smartcore Express';
        $mail->Body = generateWelcomeEmailOAuthTemplate($first_name, $last_name, $provider);
        
        // Envoi de l'email avec vérification stricte
        if (!$mail->send()) {
            throw new Exception("Échec de l'envoi: " . $mail->ErrorInfo);
        }
        
        return [
            'success' => true,
            'message' => 'Email envoyé avec succès',
            'user_deleted' => false,
            'smtp_error' => ''
        ];
        
    } catch (Exception $e) {
        // Capturer l'erreur SMTP
        $smtp_error = $mail->ErrorInfo;
        
        // Log de l'erreur
        error_log("Erreur envoi email OAuth à $email: " . $e->getMessage() . " | SMTP Error: " . $smtp_error);
        
        // Supprimer l'utilisateur de la base de données
        $deleteResult = deleteUserByEmail($email);
        
        $result = [
            'success' => false,
            'message' => 'Adresse email invalide. Veuillez en entrer une autre.',
            'user_deleted' => $deleteResult['success'],
            'smtp_error' => $smtp_error
        ];
        
        if ($deleteResult['success']) {
            $result['message'] .= ' L\'utilisateur a été supprimé de la base de données.';
            error_log("Utilisateur OAuth supprimé de la base de données: $email");
        } else {
            $result['message'] .= ' Erreur lors de la suppression de l\'utilisateur: ' . $deleteResult['message'];
            error_log("Erreur suppression utilisateur OAuth $email: " . $deleteResult['message']);
        }
        
        return $result;
    }
}

/**
 * Fonction principale pour envoyer l'email de bienvenue avec mot de passe et sécurité
 */
function sendWelcomeEmailWithPassword($email, $first_name, $last_name, $username, $password) {
    $mail = new PHPMailer(true);
    
    try {
        // Validation préalable de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format d'email invalide: $email");
        }
        
        // Configuration SMTP avec options strictes
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@smartcoreexpress.com';
        $mail->Password = 'Lorvens22@';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Configuration stricte pour détecter les erreurs
        $mail->SMTPDebug = 0; // Désactiver le debug en production
        $mail->Timeout = 30; // Timeout de 30 secondes
        
        // Configuration de l'email
        $mail->setFrom('noreply@smartcoreexpress.com', 'SMARTCORE EXPRESS');
        $mail->addAddress($email, $first_name . ' ' . $last_name);
        
        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = 'Bienvenue chez Smartcore Express - Vos identifiants de connexion';
        $mail->Body = generateWelcomeEmailTemplate($first_name, $last_name, $username, $email, $password);
        
        // Envoi de l'email avec vérification stricte
        if (!$mail->send()) {
            throw new Exception("Échec de l'envoi: " . $mail->ErrorInfo);
        }
        
        return [
            'success' => true,
            'message' => 'Email envoyé avec succès',
            'user_deleted' => false,
            'smtp_error' => ''
        ];
        
    } catch (Exception $e) {
        // Capturer l'erreur SMTP
        $smtp_error = $mail->ErrorInfo;
        
        // Log de l'erreur
        error_log("Erreur envoi email à $email: " . $e->getMessage() . " | SMTP Error: " . $smtp_error);
        
        // Supprimer l'utilisateur de la base de données
        $deleteResult = deleteUserByEmail($email);
        
        $result = [
            'success' => false,
            'message' => 'Adresse email invalide. Veuillez en entrer une autre.',
            'user_deleted' => $deleteResult['success'],
            'smtp_error' => $smtp_error
        ];
        
        if ($deleteResult['success']) {
            $result['message'] .= ' L\'utilisateur a été supprimé de la base de données.';
            error_log("Utilisateur supprimé de la base de données: $email");
        } else {
            $result['message'] .= ' Erreur lors de la suppression de l\'utilisateur: ' . $deleteResult['message'];
            error_log("Erreur suppression utilisateur $email: " . $deleteResult['message']);
        }
        
        return $result;
    }
}

/**
 * Génère le template HTML pour l'email de bienvenue avec mot de passe
 */
function generateWelcomeEmailTemplate($first_name, $last_name, $username, $email, $password) {
    $loginUrl = 'https://smartcoreexpress.com/auth/login.php';
    
    return '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bienvenue chez Smartcore Express</title>
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
            .welcome-message {
                font-size: 24px;
                font-weight: bold;
                color: #0047AB;
                margin-bottom: 20px;
                text-align: center;
            }
            .credentials-box {
                background-color: #f8f9fa;
                border: 2px solid #0047AB;
                border-radius: 10px;
                padding: 25px;
                margin: 30px 0;
                text-align: center;
            }
            .credentials-title {
                font-size: 18px;
                font-weight: bold;
                color: #0047AB;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            .credential-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 15px 0;
                padding: 15px;
                background-color: white;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
            }
            .credential-label {
                font-weight: bold;
                color: #555;
                flex: 1;
            }
            .credential-value {
                font-family: monospace;
                background-color: #f1f3f4;
                padding: 8px 12px;
                border-radius: 5px;
                border: 1px solid #d0d0d0;
                flex: 2;
                margin: 0 10px;
                word-break: break-all;
            }

            .login-button {
                display: inline-block;
                background-color: #0047AB;
                color: white !important;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                margin: 20px 0;
                transition: background-color 0.3s;
            }
            .login-button:hover {
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
            @media (max-width: 600px) {
                .container {
                    margin: 10px;
                    border-radius: 5px;
                }
                .content {
                    padding: 20px 15px;
                }
                .credential-item {
                    flex-direction: column;
                    gap: 10px;
                }
                .credential-value {
                    margin: 0;
                    width: 100%;
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
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Votre partenaire logistique de confiance</p>
            </div>
            
            <div class="content">
                <div class="welcome-message">
                    🎉 Bienvenue ' . htmlspecialchars($first_name) . ' !
                </div>
                
                <p>Cher(e) <strong>' . htmlspecialchars($first_name . ' ' . $last_name) . '</strong>,</p>
                
                <p>Nous sommes ravis de vous accueillir dans la famille <strong>Smartcore Express</strong> ! Votre compte a été créé avec succès et vous pouvez maintenant profiter de tous nos services de livraison internationale.</p>
                
                <div class="credentials-box">
                    <div class="credentials-title">
                        🔐 Vos identifiants de connexion
                    </div>
                    
                    <div class="credential-item">
                        <div class="credential-label">Email :</div>
                        <div class="credential-value">' . htmlspecialchars($email) . '</div>
                    </div>
                    
                    <div class="credential-item">
                        <div class="credential-label">Mot de passe :</div>
                        <div class="credential-value">' . htmlspecialchars($password) . '</div>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <a href="' . $loginUrl . '" class="login-button">
                        🚀 Se connecter maintenant
                    </a>
                </div>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="https://smartcoreexpress.com/client/dashboard.php" class="cta-button" style="display: inline-block; background-color: #28a745; color: white !important; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; transition: background-color 0.3s;">
                        Accéder à mon espace
                    </a>
                </div>
                
                <div class="security-notice">
                    <div class="security-title">
                        🛡️ Conseils de sécurité
                    </div>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Changez votre mot de passe lors de votre première connexion</li>
                        <li>Ne partagez jamais vos identifiants avec qui que ce soit</li>
                        <li>Déconnectez-vous toujours après utilisation</li>
                        <li>Contactez-nous immédiatement si vous suspectez une activité suspecte</li>
                    </ul>
                </div>
                
                <h3 style="color: #0047AB; margin-top: 30px;">🌟 Que pouvez-vous faire maintenant ?</h3>
                <ul style="color: #555;">
                    <li><strong>Suivre vos colis</strong> en temps réel</li>
                    <li><strong>Gérer vos expéditions</strong> facilement</li>
                    <li><strong>Accéder à votre historique</strong> complet</li>
                    <li><strong>Bénéficier du support client</strong> 24/7</li>
                </ul>
                
                <p style="margin-top: 30px;">Si vous avez des questions ou besoin d\'aide, n\'hésitez pas à nous contacter. Notre équipe est là pour vous accompagner !</p>
                
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
 * Génère le template HTML pour l'email de bienvenue OAuth (sans mot de passe)
 */
function generateWelcomeEmailOAuthTemplate($first_name, $last_name, $provider) {
    $loginUrl = 'https://smartcoreexpress.com/auth/login.php';
    $providerName = $provider === 'google' ? 'Google' : 'Apple';
    
    return '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bienvenue chez Smartcore Express</title>
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
            .welcome-message {
                font-size: 24px;
                font-weight: bold;
                color: #0047AB;
                margin-bottom: 20px;
                text-align: center;
            }
            .oauth-info {
                background-color: #f8f9fa;
                border: 2px solid #28a745;
                border-radius: 10px;
                padding: 25px;
                margin: 30px 0;
                text-align: center;
            }
            .login-button {
                display: inline-block;
                background-color: #0047AB;
                color: white !important;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                margin: 20px 0;
                transition: background-color 0.3s;
            }
            .login-button:hover {
                background-color: #003d96;
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
                <div class="welcome-message">
                    🎉 Bienvenue ' . htmlspecialchars($first_name) . ' !
                </div>
                
                <p>Cher(e) <strong>' . htmlspecialchars($first_name . ' ' . $last_name) . '</strong>,</p>
                
                <p>Nous sommes ravis de vous accueillir dans la famille <strong>Smartcore Express</strong> ! Votre compte a été créé avec succès via ' . $providerName . ' et vous pouvez maintenant profiter de tous nos services de livraison internationale.</p>
                
                <div class="oauth-info">
                    <h3 style="color: #28a745; margin-bottom: 15px;">✅ Connexion sécurisée via ' . $providerName . '</h3>
                    <p>Votre compte est maintenant lié à votre compte ' . $providerName . '. Vous pouvez vous connecter facilement en utilisant le bouton "Continuer avec ' . $providerName . '" sur notre page de connexion.</p>
                </div>
                
                <div style="text-align: center;">
                    <a href="' . $loginUrl . '" class="login-button">
                        🚀 Se connecter maintenant
                    </a>
                </div>
                
                <h3 style="color: #0047AB; margin-top: 30px;">🌟 Que pouvez-vous faire maintenant ?</h3>
                <ul style="color: #555;">
                    <li><strong>Suivre vos colis</strong> en temps réel</li>
                    <li><strong>Gérer vos expéditions</strong> facilement</li>
                    <li><strong>Accéder à votre historique</strong> complet</li>
                    <li><strong>Bénéficier du support client</strong> 24/7</li>
                </ul>
                
                <p style="margin-top: 30px;">Si vous avez des questions ou besoin d\'aide, n\'hésitez pas à nous contacter. Notre équipe est là pour vous accompagner !</p>
                
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
 * Fonction pour supprimer un utilisateur par email
 */
function deleteUserByEmail($email) {
    try {
        // Log de la tentative de suppression
        error_log("Tentative de suppression de l'utilisateur: $email");
        
        // Utiliser la même méthode de connexion que dans welcome_email_with_validation.php
        $mysqli = new mysqli('srv449.hstgr.io', 'u929653200_smartcore_db', 'Lorvens22@', 'u929653200_smartcore_db');
        
        if ($mysqli->connect_error) {
            $error = 'Erreur de connexion à la base de données: ' . $mysqli->connect_error;
            error_log("deleteUserByEmail - $error");
            throw new Exception($error);
        }
        
        $mysqli->set_charset('utf8mb4');
        
        // Vérifier d'abord si l'utilisateur existe
        $checkStmt = $mysqli->prepare("SELECT id, username FROM users WHERE email = ?");
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("deleteUserByEmail - Aucun utilisateur trouvé avec l'email: $email");
            $mysqli->close();
            return [
                'success' => false,
                'message' => 'Aucun utilisateur trouvé avec cet email'
            ];
        }
        
        $user = $result->fetch_assoc();
        error_log("deleteUserByEmail - Utilisateur trouvé: ID={$user['id']}, Username={$user['username']}");
        
        // Procéder à la suppression
        $stmt = $mysqli->prepare("DELETE FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            error_log("deleteUserByEmail - Requête exécutée, lignes affectées: $affectedRows");
            
            if ($affectedRows > 0) {
                error_log("deleteUserByEmail - Suppression réussie pour: $email");
                $mysqli->close();
                return [
                    'success' => true,
                    'message' => 'Utilisateur supprimé avec succès'
                ];
            } else {
                error_log("deleteUserByEmail - Aucune ligne affectée lors de la suppression de: $email");
                $mysqli->close();
                return [
                    'success' => false,
                    'message' => 'Aucune ligne supprimée (utilisateur peut-être déjà supprimé)'
                ];
            }
        } else {
            $error = 'Erreur lors de l\'exécution de la requête de suppression: ' . $stmt->error;
            error_log("deleteUserByEmail - $error");
            $mysqli->close();
            return [
                'success' => false,
                'message' => $error
            ];
        }
        
    } catch (Exception $e) {
        $errorMsg = "Erreur suppression utilisateur $email: " . $e->getMessage();
        error_log($errorMsg);
        return [
            'success' => false,
            'message' => 'Erreur de base de données: ' . $e->getMessage()
        ];
    }
}

/**
 * Fonction pour tester l'envoi d'email (utile pour les tests)
 */
function testWelcomeEmail($email = 'test@gmail.com', $first_name = 'Test', $last_name = 'User') {
    $username = 'testuser';
    $password = 'TestPass123';
    
    return sendWelcomeEmailWithPassword($email, $first_name, $last_name, $username, $password);
}
?>