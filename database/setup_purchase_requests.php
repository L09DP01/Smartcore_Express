<?php
/**
 * Script pour créer la table purchase_requests
 * Exécuter ce script une seule fois pour créer la table
 */

require_once '../config/database.php';

try {
    $conn = getDBConnection();
    
    // Créer la table purchase_requests
    $sql = "
        CREATE TABLE IF NOT EXISTS purchase_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            cart_link TEXT NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            screenshot_path TEXT COMMENT 'JSON array of image paths for multiple screenshots',
            status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
            admin_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $conn->exec($sql);
    echo "Table 'purchase_requests' créée avec succès!\n";
    
    // Créer le dossier uploads/screenshots s'il n'existe pas
    $upload_dir = '../uploads/screenshots/';
    if (!is_dir($upload_dir)) {
        if (mkdir($upload_dir, 0755, true)) {
            echo "Dossier 'uploads/screenshots' créé avec succès!\n";
        } else {
            echo "Erreur lors de la création du dossier 'uploads/screenshots'\n";
        }
    } else {
        echo "Dossier 'uploads/screenshots' existe déjà.\n";
    }
    
    // Créer le fichier .htaccess pour sécuriser le dossier uploads
    $htaccess_content = "# Sécurité pour le dossier uploads\n";
    $htaccess_content .= "Options -Indexes\n";
    $htaccess_content .= "<Files *.php>\n";
    $htaccess_content .= "    Deny from all\n";
    $htaccess_content .= "</Files>\n";
    
    $htaccess_path = '../uploads/.htaccess';
    if (!file_exists($htaccess_path)) {
        if (file_put_contents($htaccess_path, $htaccess_content)) {
            echo "Fichier .htaccess créé pour sécuriser le dossier uploads.\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>