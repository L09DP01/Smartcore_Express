<?php
require_once '../auth/session_manager.php';
require_once '../config/database.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$packages = [];
$total_packages = 0;
$search = '';
$status_filter = '';

// Obtenir la connexion à la base de données
$conn = getDBConnection();

// Récupérer les informations de l'utilisateur
$user = null;
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Erreur récupération utilisateur: " . $e->getMessage());
}

// Paramètres de pagination
$packages_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Traitement de la recherche et des filtres
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}
if (isset($_GET['status'])) {
    $status_filter = trim($_GET['status']);
}

try {
    // Construction de la requête avec filtres
    $where_conditions = ["user_id = ?"];
    $params = [$user_id];
    
    if (!empty($search)) {
        $where_conditions[] = '(tracking_number LIKE ? OR description LIKE ? OR destination LIKE ?)';
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = 'status = ?';
        $params[] = $status_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Compter le total des colis
    $count_query = "SELECT COUNT(*) FROM colis {$where_clause}";
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_packages = $stmt->fetchColumn();
    
    // Calculer la pagination
    $total_pages = ceil($total_packages / $packages_per_page);
    $offset = ($current_page - 1) * $packages_per_page;
    
    // Récupérer les colis avec pagination
    $query = "
        SELECT * FROM colis
        {$where_clause}
        ORDER BY created_at DESC
        LIMIT {$packages_per_page} OFFSET {$offset}
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $packages = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erreur récupération colis: " . $e->getMessage());
    $error = 'Erreur lors de la récupération des colis.';
}

// La fonction getStatusColor est définie dans database.php

// Récupérer les statistiques
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status NOT IN ('Livré') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'Livré' THEN 1 ELSE 0 END) as delivered
        FROM colis 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'active' => 0, 'delivered' => 0];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Colis - Smartcore Express</title>
    <link rel="icon" type="image/png" href="../client/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0047AB',
                        secondary: '#FF6B00',
                        accent: '#00A86B',
                        dark: '#1A1A1A',
                        light: '#F5F5F5'
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="../manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="../img/Logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Smartcore Express">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileColor" content="#0047AB">
    <meta name="msapplication-TileImage" content="../img/Logo.png">
    
    <link rel="icon" type="image/png" href="../client/logo.png">
    <style>
        .package-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .package-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        body { font-family: 'Poppins', sans-serif; }
    </style>
    
    <script src="../pwa-global.js" defer></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg relative">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="../img/Logo.png" alt="Smartcore Express" class="h-10 w-auto mr-3">
                    <span class="text-xl font-bold text-primary">Smartcore Express</span>
                </div>
                
                <div class="hidden md:flex items-center space-x-6">
                    <a href="dashboard.php" class="text-gray-600 hover:text-primary transition">Tableau de Bord</a>
                    <a href="../track.php" class="text-gray-600 hover:text-primary transition">Suivi Colis</a>
                    <a href="mes_colis.php" class="text-primary font-medium border-b-2 border-primary pb-1">Mes Colis</a>

                    <a href="achat_online.php" class="text-gray-600 hover:text-primary transition">Achat en Ligne</a>
                    <a href="profile.php" class="text-gray-600 hover:text-primary transition">Mon Profil</a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Lien Paramètres Desktop -->
                    <a href="settings.php" class="flex items-center space-x-2 text-gray-600 hover:text-primary">
                        <?php if ($user && isset($user['profile_photo']) && !empty($user['profile_photo'])): ?>
                            <img src="<?php echo '../' . $user['profile_photo']; ?>" alt="Photo de profil" class="w-8 h-8 rounded-full object-cover border-2 border-gray-200">
                        <?php else: ?>
                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-gray-600">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                        <?php endif; ?>
                        <span>Paramètres</span>
                    </a>
                    <!-- Lien Déconnexion Desktop -->
                    <a href="../auth/logout.php" class="flex items-center space-x-2 text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
                
                <!-- Menu mobile -->
                <div class="md:hidden flex items-center space-x-3">
                    <!-- Photo de profil mobile -->
                    <a href="settings.php" class="flex items-center">
                        <?php if ($user && isset($user['profile_photo']) && !empty($user['profile_photo'])): ?>
                            <img src="<?php echo '../' . $user['profile_photo']; ?>" alt="Photo de profil" class="w-8 h-8 rounded-full object-cover border-2 border-gray-200">
                        <?php else: ?>
                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-gray-600">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                        <?php endif; ?>
                    </a>
                    <button id="mobile-menu-button" class="text-gray-600 hover:text-primary">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Menu mobile -->
        <div id="mobile-menu" class="md:hidden hidden absolute top-full left-0 w-full bg-white shadow-lg z-50">
            <div class="px-4 py-2 space-y-2">
                <a href="dashboard.php" class="block py-2 text-gray-600">Tableau de Bord</a>
                <a href="../track.php" class="block py-2 text-gray-600">Suivi Colis</a>
                <a href="mes_colis.php" class="block py-2 text-primary font-medium">Mes Colis</a>
                <a href="achat_online.php" class="block py-2 text-gray-600">Achat en Ligne</a>
                <a href="profile.php" class="block py-2 text-gray-600">Mon Profil</a>
                <a href="settings.php" class="block py-2 text-gray-600">Paramètres</a>
                <hr class="my-2">
                <a href="../auth/logout.php" class="block py-2 text-red-600">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- En-tête -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Mes Colis</h1>
            <p class="text-gray-600">Suivez et gérez tous vos envois</p>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-box text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Colis</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Actifs</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['active']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Livrés</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['delivered']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres et recherche -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Rechercher par numéro de suivi, description ou destination..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Tous les statuts</option>
                        <option value="Reçue à entrepôt" <?php echo $status_filter === 'Reçue à entrepôt' ? 'selected' : ''; ?>>Reçue à entrepôt</option>
                        <option value="En preparation" <?php echo $status_filter === 'En preparation' ? 'selected' : ''; ?>>En preparation</option>
                        <option value="Expédié vers Haïti" <?php echo $status_filter === 'Expédié vers Haïti' ? 'selected' : ''; ?>>Expédié vers Haïti</option>
                        <option value="Arrivé en Haïti" <?php echo $status_filter === 'Arrivé en Haïti' ? 'selected' : ''; ?>>Arrivé en Haïti</option>
                        <option value="En dédouanement" <?php echo $status_filter === 'En dédouanement' ? 'selected' : ''; ?>>En dédouanement</option>
                        <option value="Prêt pour livraison" <?php echo $status_filter === 'Prêt pour livraison' ? 'selected' : ''; ?>>Prêt pour livraison</option>
                        <option value="Livré" <?php echo $status_filter === 'Livré' ? 'selected' : ''; ?>>Livré</option>
                    </select>
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-search mr-2"></i>Rechercher
                </button>
            </form>
        </div>

        <!-- Liste des colis -->
        <?php if (empty($packages)): ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun colis trouvé</h3>
                <p class="text-gray-500">Vous n'avez pas encore de colis ou aucun colis ne correspond à vos critères de recherche.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($packages as $package): ?>
                    <div class="package-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-1">
                                    <?php echo htmlspecialchars($package['tracking_number'] ?? ''); ?>
                                </h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($package['destination'] ?? ''); ?></p>
                            </div>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo getStatusColor($package['status'] ?? ''); ?>">
                                <?php echo htmlspecialchars($package['status'] ?? ''); ?>
                            </span>
                        </div>
                        
                        <p class="text-gray-700 mb-4"><?php echo htmlspecialchars($package['description'] ?? ''); ?></p>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-4">
                            <div>
                                <span class="font-medium">Poids:</span>
                                <span><?php echo $package['weight'] ?? 0; ?> lb</span>
                            </div>
                            <div>
                                <span class="font-medium">Coût:</span>
                                <span class="text-green-600 font-semibold">$<?php echo number_format($package['total_cost'] ?? 0, 2); ?></span>
                            </div>
                            <div>
                                <span class="font-medium">Créé:</span>
                                <span><?php echo isset($package['created_at']) ? date('d/m/Y', strtotime($package['created_at'])) : ''; ?></span>
                            </div>

                        </div>
                        
                        <?php if (!empty($package['instructions'] ?? '')): ?>
                            <div class="bg-blue-50 p-3 rounded-lg mb-4">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Instructions:</strong> <?php echo htmlspecialchars($package['instructions'] ?? ''); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between items-center">
                            <a href="colis_details.php?id=<?php echo $package['id'] ?? 0; ?>" 
                               class="text-blue-600 hover:text-blue-800 font-medium">
                                <i class="fas fa-eye mr-1"></i>Voir détails
                            </a>
                            <a href="../track.php?tracking=<?php echo urlencode($package['tracking_number'] ?? ''); ?>" 
                               class="text-green-600 hover:text-green-800 font-medium">
                                <i class="fas fa-route mr-1"></i>Suivre
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <nav class="flex space-x-2">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                               class="px-3 py-2 border rounded-md <?php echo $i === $current_page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Gestion du menu mobile
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
                
                // Fermer le menu en cliquant à l'extérieur
                document.addEventListener('click', function(event) {
                    if (!mobileMenuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                        mobileMenu.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html>