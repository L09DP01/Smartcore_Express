<?php
/**
 * Configuration de la base de données pour Smartcore Express
 * Paramètres de connexion MySQL
 */

class Database {
    // Configuration pour environnement local (WAMP/XAMPP) - commentée
    // private $host = 'localhost';
    // private $db_name = 'smartcore_db';
    // private $username = 'root';
    // private $password = '';
    // private $charset = 'utf8mb4';
    
    // Configuration pour production
    private $host = 'srv449.hstgr.io';
    private $db_name = 'u929653200_smartcore_db';
    private $username = 'u929653200_smartcore_db';
    private $password = 'Lorvens22@';
    private $charset = 'utf8mb4';
    public $conn;

    /**
     * Établir la connexion à la base de données
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            $this->conn->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch(PDOException $exception) {
            echo "Erreur de connexion: " . $exception->getMessage();
        }
        
        return $this->conn;
    }

    /**
     * Fermer la connexion
     */
    public function closeConnection() {
        $this->conn = null;
    }

    /**
     * Vérifier si la base de données existe et la créer si nécessaire
     */
    public static function initializeDatabase() {
        try {
            // Connexion sans spécifier de base de données
            $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Créer la base de données si elle n'existe pas
            $pdo->exec("CREATE DATABASE IF NOT EXISTS smartcore_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            return true;
        } catch(PDOException $e) {
            error_log("Erreur lors de l'initialisation de la base de données: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exécuter le script SQL d'initialisation
     */
    public static function runInitScript() {
        try {
            $database = new self();
            $conn = $database->getConnection();
            
            if ($conn) {
                $sqlFile = __DIR__ . '/../database/smartcore_db.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    $conn->exec($sql);
                    return true;
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Erreur lors de l'exécution du script SQL: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Fonction utilitaire pour obtenir une connexion à la base de données
 * @return PDO|null
 */
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Fonction pour sécuriser les données d'entrée
 * @param string $data
 * @return string
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Fonction pour générer un numéro de suivi unique
 * @return string
 */
function generateTrackingNumber() {
    $prefix = 'SCE';
    $year = date('Y');
    $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    return $prefix . $year . $random;
}

/**
 * Fonction pour formater les dates
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Fonction pour obtenir la couleur du statut
 * @param string $status
 * @return string
 */
function getStatusColor($status) {
    $colors = [
        'Reçue à entrepôt' => 'bg-yellow-100 text-yellow-800',
        'En preparation' => 'bg-blue-100 text-blue-800',
        'Expédié vers Haïti' => 'bg-orange-100 text-orange-800',
        'Arrivé en Haïti' => 'bg-purple-100 text-purple-800',
        'En dédouanement' => 'bg-indigo-100 text-indigo-800',
        'Prêt pour livraison' => 'bg-teal-100 text-teal-800',
        'Livré' => 'bg-green-100 text-green-800'
    ];
    
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Fonction pour obtenir l'icône du statut
 * @param string $status
 * @return string
 */
function getStatusIcon($status) {
    // Normaliser le statut pour la comparaison (première lettre majuscule, reste en minuscules)
    if ($status) {
        $status = ucfirst(strtolower($status));
    }
    
    $icons = [
        'Reçue à entrepôt' => 'fas fa-warehouse',
    'En preparation' => 'fas fa-box-open',
    'Expédié vers Haïti' => 'fas fa-shipping-fast',
    'Arrivé en Haïti' => 'fas fa-plane-arrival',
    'En dédouanement' => 'fas fa-clipboard-check',
    'Prêt pour livraison' => 'fas fa-truck',
    'Livré' => 'fas fa-check-circle'
    ];
    
    return $icons[$status] ?? 'fas fa-question-circle';
}

/**
 * Fonction pour calculer les frais de livraison
 * @param float $weight
 * @param string $serviceType
 * @return float
 */
function calculateShippingCost($weight, $serviceType = 'standard') {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT rate_per_kg, base_rate 
            FROM shipping_rates 
            WHERE weight_min <= ? AND weight_max >= ? AND service_type = ? AND is_active = 1
            ORDER BY weight_min ASC 
            LIMIT 1
        ");
        
        $stmt->execute([$weight, $weight, $serviceType]);
        $rate = $stmt->fetch();
        
        if ($rate) {
            return $rate['base_rate'] + ($weight * $rate['rate_per_kg']);
        }
        
        // Tarif par défaut si aucun tarif trouvé
        return 15.00 + ($weight * 10.00);
        
    } catch(PDOException $e) {
        error_log("Erreur calcul frais: " . $e->getMessage());
        return 15.00 + ($weight * 10.00);
    }
}
?>