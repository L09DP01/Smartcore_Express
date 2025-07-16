<?php
/**
 * Script pour les tâches automatisées
 * - Nettoyage des tokens de réinitialisation expirés
 * - Envoi d'emails de bienvenue aux nouveaux utilisateurs
 * - Notifications de rappel pour les colis en attente
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email_notifications.php';

/**
 * Nettoie les tokens de réinitialisation expirés
 * Utilise maintenant la fonction centralisée du module password_reset_functions
 */
function cleanExpiredTokens() {
    require_once __DIR__ . '/password_reset_functions.php';
    return cleanExpiredPasswordResetTokens();
}

/**
 * Envoie des emails de bienvenue aux nouveaux utilisateurs
 */
function sendWelcomeEmails() {
    try {
        $conn = getDBConnection();
        
        // Récupérer les utilisateurs créés dans les dernières 24h qui n'ont pas reçu d'email de bienvenue
        $stmt = $conn->prepare("
            SELECT id, email, first_name, last_name 
            FROM users 
            WHERE role = 'client' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND (welcome_email_sent IS NULL OR welcome_email_sent = 0)
        ");
        $stmt->execute();
        $new_users = $stmt->fetchAll();
        
        $sent_count = 0;
        foreach ($new_users as $user) {
            $user_name = $user['first_name'] . ' ' . $user['last_name'];
            
            if (sendWelcomeEmail($user['email'], $user_name)) {
                // Marquer l'email comme envoyé
                $update_stmt = $conn->prepare("UPDATE users SET welcome_email_sent = 1 WHERE id = ?");
                $update_stmt->execute([$user['id']]);
                $sent_count++;
            }
        }
        
        if ($sent_count > 0) {
            error_log("Emails de bienvenue envoyés: {$sent_count} nouveaux utilisateurs.");
        }
        
        return $sent_count;
    } catch (Exception $e) {
        error_log("Erreur envoi emails de bienvenue: " . $e->getMessage());
        return false;
    }
}

/**
 * Envoie un email de bienvenue
 */
function sendWelcomeEmail($user_email, $user_name) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuration SMTP Hostinger
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@smartcoreexpress.com';
        $mail->Password = 'Lorvens22@';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        
        // Options SSL
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Expéditeur et destinataire
        $mail->setFrom('noreply@smartcoreexpress.com', 'Smartcore Express');
        $mail->addAddress($user_email, $user_name);
        
        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = '🎉 Bienvenue chez Smartcore Express !';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #0047AB; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .welcome-box { background-color: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 15px 0; border-radius: 5px; text-align: center; }
                .features { background-color: #fff; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .footer { background-color: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }
                .btn { background-color: #0047AB; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
                .feature-item { margin: 10px 0; padding: 10px; border-left: 3px solid #0047AB; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Bienvenue chez Smartcore Express !</h1>
                </div>
                
                <div class='content'>
                    <p>Bonjour <strong>{$user_name}</strong>,</p>
                    
                    <div class='welcome-box'>
                        <h3>✅ Votre compte a été créé avec succès !</h3>
                        <p>Merci de nous faire confiance pour vos envois vers Haïti.</p>
                    </div>
                    
                    <div class='features'>
                        <h3>🚀 Découvrez nos services :</h3>
                        
                        <div class='feature-item'>
                            <strong>📦 Suivi en temps réel</strong><br>
                            Suivez vos colis à chaque étape de leur voyage
                        </div>
                        
                        <div class='feature-item'>
                            <strong>💰 Tarifs compétitifs</strong><br>
                            À partir de 4.50$ par livre + frais d'expédition
                        </div>
                        
                        <div class='feature-item'>
                            <strong>🔔 Notifications automatiques</strong><br>
                            Recevez des emails à chaque changement de statut
                        </div>
                        
                        <div class='feature-item'>
                            <strong>🛡️ Sécurité garantie</strong><br>
                            Vos colis sont assurés et sécurisés
                        </div>
                    </div>
                    
                    <p>Vous pouvez dès maintenant vous connecter à votre espace client pour :</p>
                    <ul>
                        <li>Consulter vos colis</li>
                        <li>Suivre vos envois</li>
                        <li>Gérer votre profil</li>
                        <li>Contacter notre support</li>
                    </ul>
                    
                    <p style='text-align: center;'>
                        <a href='https://smartcoreexpress.com/client/dashboard.php' class='btn'>Accéder à mon compte</a>
                    </p>
                    
                    <p>Si vous avez des questions, n'hésitez pas à nous contacter. Notre équipe est là pour vous aider !</p>
                </div>
                
                <div class='footer'>
                    <p>© 2024 Smartcore Express - Service de livraison Haïti</p>
                    <p>Merci de votre confiance !</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Bonjour {$user_name},\n\n🎉 Bienvenue chez Smartcore Express !\n\nVotre compte a été créé avec succès. Vous pouvez maintenant suivre vos colis en temps réel et bénéficier de nos services de livraison vers Haïti.\n\nConnectez-vous à votre compte : https://smartcoreexpress.com/client/dashboard.php\n\nMerci de votre confiance !\n\nSmartcore Express";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email bienvenue: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envoie des rappels pour les colis en attente depuis plus de 7 jours
 */
function sendPackageReminders() {
    try {
        $conn = getDBConnection();
        
        // Récupérer les colis en attente depuis plus de 7 jours
        $stmt = $conn->prepare("
            SELECT c.id, c.tracking_number, c.description, c.created_at,
                   u.email, u.first_name, u.last_name
            FROM colis c
            JOIN users u ON c.user_id = u.id
            WHERE c.status IN ('Reçue à entrepôt', 'En preparation')
            AND c.created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND c.updated_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $pending_packages = $stmt->fetchAll();
        
        $sent_count = 0;
        foreach ($pending_packages as $package) {
            $user_name = $package['first_name'] . ' ' . $package['last_name'];
            
            if (sendPackageReminderEmail($package['email'], $user_name, $package['tracking_number'], $package['description'])) {
                $sent_count++;
            }
        }
        
        if ($sent_count > 0) {
            error_log("Rappels envoyés: {$sent_count} colis en attente.");
        }
        
        return $sent_count;
    } catch (Exception $e) {
        error_log("Erreur envoi rappels: " . $e->getMessage());
        return false;
    }
}

/**
 * Envoie un email de rappel pour un colis en attente
 */
function sendPackageReminderEmail($user_email, $user_name, $tracking_number, $description) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuration SMTP Hostinger (même que les autres fonctions)
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@smartcoreexpress.com';
        $mail->Password = 'Lorvens22@';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom('noreply@smartcoreexpress.com', 'Smartcore Express');
        $mail->addAddress($user_email, $user_name);
        
        $mail->isHTML(true);
        $mail->Subject = '📦 Rappel : Votre colis est en attente - Smartcore Express';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #FF6B00; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .reminder-box { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .footer { background-color: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }
                .btn { background-color: #FF6B00; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📦 Smartcore Express</h1>
                    <h2>Rappel de colis en attente</h2>
                </div>
                
                <div class='content'>
                    <p>Bonjour <strong>{$user_name}</strong>,</p>
                    
                    <div class='reminder-box'>
                        <h3>⏰ Votre colis est en attente depuis plusieurs jours</h3>
                        <p><strong>Numéro de suivi :</strong> {$tracking_number}</p>
                        <p><strong>Description :</strong> {$description}</p>
                        <p><strong>Statut actuel :</strong> En attente de traitement</p>
                    </div>
                    
                    <p>Nous travaillons activement pour traiter votre colis. Il sera bientôt expédié vers Haïti.</p>
                    
                    <p>Vous pouvez suivre l'évolution de votre colis en temps réel sur votre espace client.</p>
                    
                    <p style='text-align: center;'>
                        <a href='https://smartcoreexpress.com/client/dashboard.php' class='btn'>Suivre mon colis</a>
                    </p>
                    
                    <p>Merci de votre patience et de votre confiance.</p>
                </div>
                
                <div class='footer'>
                    <p>© 2024 Smartcore Express - Service de livraison Haïti</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Bonjour {$user_name},\n\n📦 Rappel : Votre colis #{$tracking_number} est en attente depuis plusieurs jours.\n\nDescription: {$description}\nStatut: En attente de traitement\n\nNous travaillons pour l'expédier bientôt vers Haïti.\n\nSuivez votre colis : https://smartcoreexpress.com/client/dashboard.php\n\nMerci de votre patience.\n\nSmartcore Express";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi rappel colis: " . $mail->ErrorInfo);
        return false;
    }
}

// Exécution des tâches si le script est appelé directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "Démarrage des tâches automatisées...\n";
    
    // Nettoyer les tokens expirés
    $cleaned = cleanExpiredTokens();
    if ($cleaned !== false) {
        echo "Tokens nettoyés: {$cleaned}\n";
    }
    
    // Envoyer les emails de bienvenue
    $welcome_sent = sendWelcomeEmails();
    if ($welcome_sent !== false) {
        echo "Emails de bienvenue envoyés: {$welcome_sent}\n";
    }
    
    // Envoyer les rappels
    $reminders_sent = sendPackageReminders();
    if ($reminders_sent !== false) {
        echo "Rappels envoyés: {$reminders_sent}\n";
    }
    
    echo "Tâches automatisées terminées.\n";
}
?>