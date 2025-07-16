<?php
/**
 * Script d'initialisation de la base de données locale pour WAMP
 * Exécuter ce script pour créer la base de données et les tables nécessaires
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "<h2>Initialisation de la base de données locale</h2>";
    echo "<pre>";
    
    // Connexion sans spécifier de base de données
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Créer la base de données si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS smartcore_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Base de données 'smartcore_db' créée ou existe déjà\n";
    
    // Se connecter à la base de données
    $pdo->exec("USE smartcore_db");
    
    // Créer la table users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(50),
            last_name VARCHAR(50),
            phone VARCHAR(20),
            role ENUM('admin', 'client') DEFAULT 'client',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            oauth_provider VARCHAR(20) NULL COMMENT 'Provider OAuth (google, apple)',
            oauth_provider_id VARCHAR(255) NULL COMMENT 'ID unique du provider OAuth',
            profile_photo VARCHAR(500) NULL COMMENT 'URL de la photo de profil OAuth',
            email_verified TINYINT(1) DEFAULT 0 COMMENT 'Email vérifié par OAuth',
            last_login TIMESTAMP NULL COMMENT 'Dernière connexion'
        )
    ");
    echo "✓ Table 'users' créée\n";
    
    // Créer la table remember_tokens
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        )
    ");
    echo "✓ Table 'remember_tokens' créée\n";
    
    // Créer la table colis
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS colis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tracking_number VARCHAR(50) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            description TEXT,
            weight DECIMAL(10,2),
            dimensions VARCHAR(100),
            status ENUM('Reçue à entrepôt', 'En preparation', 'Expédié vers Haïti', 'Arrivé en Haïti', 'En dédouanement', 'Prêt pour livraison', 'Livré') DEFAULT 'Reçue à entrepôt',
            shipping_cost DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Table 'colis' créée\n";
    
    // Créer la table shipping_rates
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shipping_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            weight_min DECIMAL(10,2) NOT NULL,
            weight_max DECIMAL(10,2) NOT NULL,
            rate_per_kg DECIMAL(10,2) NOT NULL,
            base_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
            service_type VARCHAR(50) DEFAULT 'standard',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Table 'shipping_rates' créée\n";
    
    // Créer la table purchase_requests
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS purchase_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            cart_link TEXT NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100) NOT NULL,
            address TEXT NOT NULL,
            screenshot_path TEXT COMMENT 'JSON array of image paths',
            status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
            admin_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        )
    ");
    echo "✓ Table 'purchase_requests' créée\n";
    
    // Insérer un utilisateur admin par défaut
    $adminExists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminExists == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
            VALUES ('admin', 'admin@smartcoreexpress.com', '$adminPassword', 'Admin', 'SmartCore', 'admin', 1)
        ");
        echo "✓ Utilisateur admin créé (email: admin@smartcoreexpress.com, mot de passe: admin123)\n";
    } else {
        echo "✓ Utilisateur admin existe déjà\n";
    }
    
    // Insérer des tarifs de base
    $ratesExists = $pdo->query("SELECT COUNT(*) FROM shipping_rates")->fetchColumn();
    if ($ratesExists == 0) {
        $pdo->exec("
            INSERT INTO shipping_rates (weight_min, weight_max, rate_per_kg, base_rate, service_type) VALUES
            (0.1, 1.0, 10.00, 15.00, 'standard'),
            (1.1, 5.0, 8.00, 20.00, 'standard'),
            (5.1, 10.0, 6.00, 25.00, 'standard'),
            (10.1, 20.0, 5.00, 30.00, 'standard')
        ");
        echo "✓ Tarifs de livraison par défaut ajoutés\n";
    } else {
        echo "✓ Tarifs de livraison existent déjà\n";
    }
    
    echo "\n🎉 Initialisation terminée avec succès !\n";
    echo "\nVous pouvez maintenant vous connecter avec :\n";
    echo "Email: admin@smartcoreexpress.com\n";
    echo "Mot de passe: admin123\n";
    echo "</pre>";
    
} catch(PDOException $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Erreur lors de l'initialisation :</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p><strong>Vérifiez que :</strong></p>";
    echo "<ul>";
    echo "<li>WAMP/XAMPP est démarré</li>";
    echo "<li>MySQL est en cours d'exécution</li>";
    echo "<li>Les paramètres de connexion sont corrects</li>";
    echo "</ul>";
    echo "</div>";
}
?>