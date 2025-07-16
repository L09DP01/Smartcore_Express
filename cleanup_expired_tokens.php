<?php
/**
 * Script de nettoyage automatique des tokens de réinitialisation expirés
 * 
 * Ce script peut être exécuté :
 * 1. Manuellement via la ligne de commande : php cleanup_expired_tokens.php
 * 2. Via un cron job (recommandé) : 0 asterisk/6 asterisk asterisk asterisk /usr/bin/php /path/to/Smartcore_Express/cleanup_expired_tokens.php
 * 3. Via le planificateur de tâches Windows
 * 
 * Recommandation : Exécuter toutes les 6 heures pour maintenir la base de données propre
 */

// Définir le fuseau horaire
date_default_timezone_set('America/Port-au-Prince');

// Inclure les fonctions nécessaires
require_once __DIR__ . '/includes/password_reset_functions.php';

// Fonction de logging
function logCleanupMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] CLEANUP TOKENS: {$message}" . PHP_EOL;
    
    // Écrire dans un fichier de log
    $log_file = __DIR__ . '/logs/token_cleanup.log';
    
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
function cleanOldTokenLogs() {
    $log_file = __DIR__ . '/logs/token_cleanup.log';
    
    if (file_exists($log_file) && filesize($log_file) > 1024 * 1024) { // Si le fichier fait plus de 1MB
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
            logCleanupMessage("Ancien logs nettoyés. " . (count($lines) - count($new_lines)) . " lignes supprimées.");
        }
    }
}

// Début du script
logCleanupMessage("Début du nettoyage automatique des tokens expirés.");

// Vérifier que l'extension PDO MySQL est disponible
if (!extension_loaded('pdo_mysql')) {
    logCleanupMessage("ERREUR: L'extension PDO MySQL n'est pas activée dans PHP.");
    logCleanupMessage("Solution: Activez l'extension pdo_mysql dans votre fichier php.ini");
    exit(1);
}

try {
    // Nettoyer les tokens expirés
    $deleted_count = cleanExpiredPasswordResetTokens();
    
    if ($deleted_count === false) {
        logCleanupMessage("ERREUR: Échec du nettoyage des tokens expirés.");
        exit(1);
    } elseif ($deleted_count > 0) {
        logCleanupMessage("Succès: {$deleted_count} tokens expirés supprimés.");
    } else {
        logCleanupMessage("Aucun token expiré trouvé.");
    }
    
    // Nettoyer les anciens logs
    cleanOldTokenLogs();
    
    logCleanupMessage("Nettoyage terminé avec succès.");
    
} catch (Exception $e) {
    logCleanupMessage("ERREUR CRITIQUE: " . $e->getMessage());
    exit(1);
} catch (Error $e) {
    logCleanupMessage("ERREUR FATALE: " . $e->getMessage());
    logCleanupMessage("Vérifiez que WAMP/XAMPP est démarré et que MySQL est accessible.");
    exit(1);
}

// Afficher un résumé si exécuté via navigateur
if (php_sapi_name() !== 'cli') {
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Nettoyage des tokens</title></head><body>";
    echo "<h2>Nettoyage des tokens de réinitialisation</h2>";
    echo "<p>Nettoyage terminé. Consultez les logs pour plus de détails.</p>";
    echo "<p>Tokens supprimés: " . ($deleted_count ?: 0) . "</p>";
    echo "<p>Heure d'exécution: " . date('Y-m-d H:i:s') . "</p>";
    echo "</body></html>";
}
?>