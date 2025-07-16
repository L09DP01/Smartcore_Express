<?php
require_once 'c:/wamp64/www/Smartcore_Express/config/database.php';

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Impossible de se connecter à la base de données');
    }
    
    echo "Connexion à la base de données réussie.\n";
    
    // Lire le fichier SQL
    $sqlFile = __DIR__ . '/create_shipping_rates.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('Fichier SQL introuvable: ' . $sqlFile);
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Diviser les requêtes par point-virgule
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            $conn->exec($query);
            echo "Requête exécutée: " . substr($query, 0, 50) . "...\n";
        }
    }
    
    echo "\nTable shipping_rates créée avec succès!\n";
    echo "Données par défaut insérées.\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    error_log("Erreur setup shipping_rates: " . $e->getMessage());
}
?>