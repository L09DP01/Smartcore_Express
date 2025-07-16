<?php
/**
 * Script cron pour les tâches automatisées
 * À exécuter périodiquement (par exemple toutes les heures)
 * 
 * Pour configurer un cron job sur Linux/Mac :
 * 0 * * * * /usr/bin/php /path/to/Smartcore_Express/cron_tasks.php
 * 
 * Pour Windows, utilisez le Planificateur de tâches :
 * php.exe "C:\wamp64\www\Smartcore_Express\cron_tasks.php"
 */

// Définir le fuseau horaire
date_default_timezone_set('America/Port-au-Prince');

// Inclure le fichier des tâches automatisées
require_once __DIR__ . '/includes/automated_tasks.php';

// Fonction de logging
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
    
    // Écrire dans un fichier de log
    $log_file = __DIR__ . '/logs/cron.log';
    
    // Créer le dossier logs s'il n'existe pas
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    
    // Aussi afficher dans la console si exécuté en ligne de commande
    if (php_sapi_name() === 'cli') {
        echo $log_message;
    }
}

// Fonction pour nettoyer les anciens logs (garder seulement les 30 derniers jours)
function cleanOldLogs() {
    $log_file = __DIR__ . '/logs/cron.log';
    
    if (file_exists($log_file)) {
        $lines = file($log_file);
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        $new_lines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                if ($matches[1] >= $cutoff_date) {
                    $new_lines[] = $line;
                }
            }
        }
        
        if (count($new_lines) < count($lines)) {
            file_put_contents($log_file, implode('', $new_lines));
            logMessage("Nettoyage des logs: " . (count($lines) - count($new_lines)) . " anciennes entrées supprimées.");
        }
    }
}

try {
    logMessage("=== DÉBUT DES TÂCHES AUTOMATISÉES ===");
    
    // 1. Nettoyer les tokens de réinitialisation expirés
    logMessage("Nettoyage des tokens expirés...");
    $cleaned_tokens = cleanExpiredTokens();
    if ($cleaned_tokens !== false) {
        logMessage("Tokens expirés supprimés: {$cleaned_tokens}");
    } else {
        logMessage("ERREUR: Échec du nettoyage des tokens");
    }
    
    // 2. Envoyer les emails de bienvenue aux nouveaux utilisateurs
    logMessage("Envoi des emails de bienvenue...");
    $welcome_emails = sendWelcomeEmails();
    if ($welcome_emails !== false) {
        logMessage("Emails de bienvenue envoyés: {$welcome_emails}");
    } else {
        logMessage("ERREUR: Échec de l'envoi des emails de bienvenue");
    }
    
    // 3. Envoyer les rappels pour les colis en attente (seulement une fois par jour)
    $current_hour = (int)date('H');
    if ($current_hour === 9) { // Envoyer les rappels à 9h du matin
        logMessage("Envoi des rappels de colis en attente...");
        $reminder_emails = sendPackageReminders();
        if ($reminder_emails !== false) {
            logMessage("Rappels envoyés: {$reminder_emails}");
        } else {
            logMessage("ERREUR: Échec de l'envoi des rappels");
        }
    }
    
    // 4. Nettoyer les anciens logs (une fois par jour à minuit)
    if ($current_hour === 0) {
        logMessage("Nettoyage des anciens logs...");
        cleanOldLogs();
    }
    
    // 5. Statistiques de base (optionnel)
    try {
        $conn = getDBConnection();
        
        // Compter les colis actifs
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM colis WHERE status != 'Livré'");
        $stmt->execute();
        $active_packages = $stmt->fetch()['total'];
        
        // Compter les nouveaux utilisateurs aujourd'hui
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $new_users_today = $stmt->fetch()['total'];
        
        logMessage("Statistiques: {$active_packages} colis actifs, {$new_users_today} nouveaux utilisateurs aujourd'hui");
        
    } catch (Exception $e) {
        logMessage("ERREUR statistiques: " . $e->getMessage());
    }
    
    logMessage("=== FIN DES TÂCHES AUTOMATISÉES ===");
    
} catch (Exception $e) {
    logMessage("ERREUR CRITIQUE: " . $e->getMessage());
    
    // En cas d'erreur critique, envoyer un email à l'admin (optionnel)
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@smartcoreexpress.com';
        $mail->Password = 'Lorvens22@';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        
        $mail->setFrom('noreply@smartcoreexpress.com', 'Smartcore Express - Système');
        $mail->addAddress('admin@smartcoreexpress.com', 'Administrateur');
        
        $mail->isHTML(false);
        $mail->Subject = 'ERREUR - Tâches automatisées Smartcore Express';
        $mail->Body = "Une erreur critique s'est produite lors de l'exécution des tâches automatisées :\n\n" . $e->getMessage() . "\n\nVeuillez vérifier le système.";
        
        $mail->send();
        logMessage("Email d'alerte envoyé à l'administrateur");
        
    } catch (Exception $mail_error) {
        logMessage("ERREUR: Impossible d'envoyer l'email d'alerte - " . $mail_error->getMessage());
    }
}

// Retourner le code de sortie approprié
if (php_sapi_name() === 'cli') {
    exit(0); // Succès
}
?>