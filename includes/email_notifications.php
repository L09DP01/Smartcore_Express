<?php
// Charger l'autoloader Composer pour les dépendances
require_once __DIR__ . '/../vendor/autoload.php';



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoie un email de notification pour un nouveau colis
 */
function sendNewPackageNotification($user_email, $user_name, $tracking_number, $total_cost, $description) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
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
        $mail->Subject = 'Nouveau colis ajouté - Smartcore Express';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nouveau colis ajouté - Smartcore Express</title>
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
                .package-message {
                    font-size: 24px;
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .tracking-box {
                    background-color: #f8f9fa;
                    border: 2px solid #0047AB;
                    border-radius: 10px;
                    padding: 25px;
                    margin: 30px 0;
                    text-align: center;
                }
                .tracking-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }
                .detail-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin: 15px 0;
                    padding: 15px;
                    background-color: white;
                    border-radius: 8px;
                    border: 1px solid #e0e0e0;
                }
                .detail-label {
                    font-weight: bold;
                    color: #555;
                    flex: 1;
                }
                .detail-value {
                    background-color: #f1f3f4;
                    padding: 8px 12px;
                    border-radius: 5px;
                    border: 1px solid #d0d0d0;
                    flex: 2;
                    margin: 0 10px;
                    word-break: break-all;
                }
                .track-button {
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
                .track-button:hover {
                    background-color: #003d96;
                }
                .info-notice {
                    background-color: #e7f3ff;
                    border: 1px solid #b3d9ff;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 30px 0;
                }
                .info-title {
                    font-weight: bold;
                    color: #0047AB;
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
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Nouveau colis ajouté</p>
                </div>
                
                <div class="content">
                    <div class="package-message">
                        📦 Nouveau colis reçu
                    </div>
                    
                    <p>Bonjour <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
                    
                    <p>Nous avons le plaisir de vous informer qu\'un nouveau colis a été ajouté à votre compte <strong>Smartcore Express</strong>.</p>
                    
                    <div class="tracking-box">
                        <div class="tracking-title">
                            📋 Détails du colis
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Numéro de suivi :</div>
                            <div class="detail-value">' . htmlspecialchars($tracking_number) . '</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Description :</div>
                            <div class="detail-value">' . htmlspecialchars($description) . '</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Coût total :</div>
                            <div class="detail-value">$' . number_format($total_cost, 2) . '</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Statut :</div>
                            <div class="detail-value">Reçu à l\'entrepôt</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Localisation :</div>
                            <div class="detail-value">Entrepôt Miami</div>
                        </div>
                    </div>
                    
                    <div class="info-notice">
                        <div class="info-title">
                            ℹ️ Information importante
                        </div>
                        <p style="margin: 0;">Vous pouvez suivre votre colis en temps réel en vous connectant à votre compte ou en utilisant le numéro de suivi ci-dessus.</p>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="https://smartcoreexpress.com/client/dashboard.php" class="track-button">Voir mes colis</a>
                    </p>
                    
                    <p>Si vous avez des questions, n\'hésitez pas à nous contacter.</p>
                    
                    <p>Cordialement,<br><strong>L\'équipe Smartcore Express</strong></p>
                </div>
                
                <div class="footer">
                    <p><strong>SMARTCORE EXPRESS</strong></p>
                    <p>Service de livraison rapide et sécurisé vers Haïti</p>
                    
                    <div class="company-info">
                        <p>© 2024 Smartcore Express. Tous droits réservés.</p>
                        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Bonjour {$user_name},\n\nUn nouveau colis a été ajouté à votre compte.\n\nNuméro de suivi: {$tracking_number}\nDescription: {$description}\nCoût total: $" . number_format($total_cost, 2) . "\nStatut: Reçu à l'entrepôt\n\nConnectez-vous à votre compte pour plus de détails.\n\nSmartcore Express";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email nouveau colis: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envoie un email de notification pour un changement de statut
 */
function sendStatusUpdateNotification($user_email, $user_name, $tracking_number, $old_status, $new_status, $location, $description = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
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
        $mail->Subject = 'Mise à jour du statut de votre colis - Smartcore Express';
        
        // Déterminer la couleur selon le statut
        $status_color = '#0047AB';
        switch($new_status) {
            case 'Expédié vers Haïti':
                $status_color = '#FF6B00';
                break;
            case 'Arrivé en Haïti':
                $status_color = '#00A86B';
                break;
            case 'Livré':
                $status_color = '#28a745';
                break;
        }
        
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Mise à jour de votre colis - Smartcore Express</title>
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
                .status-message {
                    font-size: 24px;
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .tracking-box {
                    background-color: #f8f9fa;
                    border: 2px solid #0047AB;
                    border-radius: 10px;
                    padding: 25px;
                    margin: 30px 0;
                    text-align: center;
                }
                .tracking-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }
                .detail-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin: 15px 0;
                    padding: 15px;
                    background-color: white;
                    border-radius: 8px;
                    border: 1px solid #e0e0e0;
                }
                .detail-label {
                    font-weight: bold;
                    color: #555;
                    flex: 1;
                }
                .detail-value {
                    background-color: #f1f3f4;
                    padding: 8px 12px;
                    border-radius: 5px;
                    border: 1px solid #d0d0d0;
                    flex: 2;
                    margin: 0 10px;
                    word-break: break-all;
                }
                .status-badge {
                    background-color: ' . $status_color . ';
                    color: white;
                    padding: 8px 15px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: bold;
                }
                .status-evolution {
                    background-color: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 30px 0;
                }
                .evolution-title {
                    font-weight: bold;
                    color: #856404;
                    margin-bottom: 10px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .track-button {
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
                .track-button:hover {
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
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Mise à jour de votre colis</p>
                </div>
                
                <div class="content">
                    <div class="status-message">
                        🚚 Statut mis à jour
                    </div>
                    
                    <p>Bonjour <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
                    
                    <p>Le statut de votre colis a été mis à jour dans notre système <strong>Smartcore Express</strong>.</p>
                    
                    <div class="tracking-box">
                        <div class="tracking-title">
                            📦 Colis #' . htmlspecialchars($tracking_number) . '
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Nouveau statut :</div>
                            <div class="detail-value"><span class="status-badge">' . htmlspecialchars($new_status) . '</span></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Localisation :</div>
                            <div class="detail-value">' . htmlspecialchars($location) . '</div>
                        </div>
                        
                        ' . ($description ? '<div class="detail-item"><div class="detail-label">Description :</div><div class="detail-value">' . htmlspecialchars($description) . '</div></div>' : '') . '
                        
                        <div class="detail-item">
                            <div class="detail-label">Date de mise à jour :</div>
                            <div class="detail-value">' . date('d/m/Y à H:i') . '</div>
                        </div>
                    </div>
                    
                    <div class="status-evolution">
                        <div class="evolution-title">
                            📈 Évolution du statut
                        </div>
                        <p><strong>Ancien statut :</strong> ' . htmlspecialchars($old_status) . '</p>
                        <p style="margin: 0;"><strong>Nouveau statut :</strong> ' . htmlspecialchars($new_status) . '</p>
                    </div>
                    
                    <p>Vous pouvez suivre l\'évolution complète de votre colis en vous connectant à votre compte.</p>
                    
                    <p style="text-align: center;">
                        <a href="https://smartcoreexpress.com/client/dashboard.php" class="track-button">Suivre mon colis</a>
                    </p>
                    
                    <p>Si vous avez des questions, n\'hésitez pas à nous contacter.</p>
                    
                    <p>Cordialement,<br><strong>L\'équipe Smartcore Express</strong></p>
                </div>
                
                <div class="footer">
                    <p><strong>SMARTCORE EXPRESS</strong></p>
                    <p>Service de livraison rapide et sécurisé vers Haïti</p>
                    
                    <div class="company-info">
                        <p>© 2024 Smartcore Express. Tous droits réservés.</p>
                        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Bonjour {$user_name},\n\nLe statut de votre colis #{$tracking_number} a été mis à jour.\n\nNouveau statut: {$new_status}\nLocalisation: {$location}\n" . ($description ? "Description: {$description}\n" : "") . "\nConnectez-vous à votre compte pour plus de détails.\n\nSmartcore Express";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email changement statut: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envoie un email de notification pour la livraison
 */
function sendDeliveryNotification($user_email, $user_name, $tracking_number, $delivery_location) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
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
        $mail->Subject = '🎉 Votre colis a été livré ! - Smartcore Express';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Colis livré - Smartcore Express</title>
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
                    background: linear-gradient(135deg, #28a745, #34ce57);
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
                .delivery-message {
                    font-size: 24px;
                    font-weight: bold;
                    color: #28a745;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .success-box {
                    background-color: #d4edda;
                    border: 2px solid #28a745;
                    border-radius: 10px;
                    padding: 25px;
                    margin: 30px 0;
                    text-align: center;
                }
                .success-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #155724;
                    margin-bottom: 15px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }
                .tracking-box {
                    background-color: #f8f9fa;
                    border: 2px solid #28a745;
                    border-radius: 10px;
                    padding: 25px;
                    margin: 30px 0;
                    text-align: center;
                }
                .tracking-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #28a745;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }
                .detail-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin: 15px 0;
                    padding: 15px;
                    background-color: white;
                    border-radius: 8px;
                    border: 1px solid #e0e0e0;
                }
                .detail-label {
                    font-weight: bold;
                    color: #555;
                    flex: 1;
                }
                .detail-value {
                    background-color: #f1f3f4;
                    padding: 8px 12px;
                    border-radius: 5px;
                    border: 1px solid #d0d0d0;
                    flex: 2;
                    margin: 0 10px;
                    word-break: break-all;
                }
                .success-badge {
                    background-color: #28a745;
                    color: white;
                    padding: 8px 15px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: bold;
                }
                .view-button {
                    display: inline-block;
                    background-color: #28a745;
                    color: white !important;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: bold;
                    margin: 20px 0;
                    transition: background-color 0.3s;
                }
                .view-button:hover {
                    background-color: #218838;
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
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">🎉 Votre colis a été livré !</p>
                </div>
                
                <div class="content">
                    <div class="delivery-message">
                        ✅ Livraison confirmée
                    </div>
                    
                    <p>Bonjour <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
                    
                    <div class="success-box">
                        <div class="success-title">
                            🎯 Mission accomplie !
                        </div>
                        <p style="margin: 0; font-size: 16px;">Nous avons le plaisir de vous confirmer que votre colis a été <strong>livré avec succès</strong> !</p>
                    </div>
                    
                    <div class="tracking-box">
                        <div class="tracking-title">
                            📦 Colis #' . htmlspecialchars($tracking_number) . '
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Statut :</div>
                            <div class="detail-value"><span class="success-badge">Livré</span></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Lieu de livraison :</div>
                            <div class="detail-value">' . htmlspecialchars($delivery_location) . '</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Date de livraison :</div>
                            <div class="detail-value">' . date('d/m/Y à H:i') . '</div>
                        </div>
                    </div>
                    
                    <p>Merci d\'avoir choisi <strong>Smartcore Express</strong> pour vos envois vers Haïti. Nous espérons vous revoir bientôt pour vos prochains envois !</p>
                    
                    <p style="text-align: center;">
                        <a href="https://smartcoreexpress.com/client/dashboard.php" class="view-button">Voir l\'historique</a>
                    </p>
                    
                    <p>Si vous avez des questions ou des commentaires sur notre service, n\'hésitez pas à nous contacter.</p>
                    
                    <p>Cordialement,<br><strong>L\'équipe Smartcore Express</strong></p>
                </div>
                
                <div class="footer">
                    <p><strong>SMARTCORE EXPRESS</strong></p>
                    <p>Service de livraison rapide et sécurisé vers Haïti</p>
                    
                    <div class="company-info">
                        <p>© 2024 Smartcore Express. Tous droits réservés.</p>
                        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Bonjour {$user_name},\n\n🎉 Votre colis #{$tracking_number} a été livré avec succès !\n\nLieu de livraison: {$delivery_location}\nDate de livraison: " . date('d/m/Y à H:i') . "\n\nMerci d'avoir choisi Smartcore Express !\n\nSmartcore Express";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email livraison: " . $mail->ErrorInfo);
        return false;
    }
}
?>