<?php
/**
 * Script pour ajouter les colonnes OAuth à la base de données
 * Exécuter ce script une seule fois pour configurer l'authentification OAuth
 */

require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDBConnection();
    
    echo "<h2>Configuration OAuth - Modification de la base de données</h2>";
    echo "<pre>";
    
    // Vérifier et ajouter chaque colonne individuellement
    $columns = [
        'oauth_provider' => "VARCHAR(20) NULL COMMENT 'Provider OAuth (google, apple)'",
        'oauth_provider_id' => "VARCHAR(255) NULL COMMENT 'ID unique du provider OAuth'",
        'profile_photo' => "VARCHAR(500) NULL COMMENT 'URL de la photo de profil OAuth'",
        'email_verified' => "TINYINT(1) DEFAULT 0 COMMENT 'Email vérifié par OAuth'",
        'last_login' => "TIMESTAMP NULL COMMENT 'Dernière connexion'"
    ];
    
    foreach ($columns as $columnName => $columnDefinition) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE '$columnName'");
        if ($checkColumn->rowCount() == 0) {
            echo "Ajout de la colonne $columnName...\n";
            $conn->exec("ALTER TABLE users ADD COLUMN $columnName $columnDefinition");
            echo "✓ Colonne $columnName ajoutée avec succès.\n";
        } else {
            echo "✓ La colonne $columnName existe déjà.\n";
        }
    }
    
    // Vérifier si l'index existe
    $checkIndex = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_oauth_provider_id'");
    if ($checkIndex->rowCount() > 0) {
        echo "✓ L'index OAuth existe déjà.\n";
    } else {
        echo "Création de l'index OAuth...\n";
        $conn->exec("CREATE INDEX idx_oauth_provider_id ON users(oauth_provider, oauth_provider_id)");
        echo "✓ Index OAuth créé avec succès.\n";
    }
    
    // Modifier la contrainte de mot de passe
    echo "Modification de la contrainte password_hash...\n";
    $conn->exec("ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL COMMENT 'Hash du mot de passe (NULL pour OAuth)'");
    echo "✓ Contrainte password_hash modifiée avec succès.\n";
    
    // Vérifier la structure finale
    echo "\n=== Structure finale de la table users ===\n";
    $columns = $conn->query("SHOW COLUMNS FROM users");
    while ($column = $columns->fetch()) {
        echo sprintf("%-20s %-20s %-10s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Default'] ?? 'NULL'
        );
    }
    
    echo "\n✅ Configuration OAuth terminée avec succès !\n";
    echo "\nVous pouvez maintenant configurer vos clés OAuth dans config/oauth_config.php\n";
    
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<pre>";
    echo "❌ Erreur lors de la configuration OAuth: " . $e->getMessage() . "\n";
    echo "</pre>";
} catch (Exception $e) {
    echo "<pre>";
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "</pre>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration OAuth - Smartcore Express</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        pre { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { color: #0047AB; }
    </style>
</head>
<body>
    <div style="max-width: 800px; margin: 0 auto;">
        <p><a href="../auth/login.php">← Retour à la connexion</a></p>
        <p><strong>Note:</strong> Ce script ne doit être exécuté qu'une seule fois. Supprimez ce fichier après utilisation pour des raisons de sécurité.</p>
    </div>
</body>
</html>