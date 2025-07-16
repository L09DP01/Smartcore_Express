<?php
require_once '../auth/session_manager.php';
require_once '../config/database.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$package = null;
$tracking_history = [];

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

// Récupérer l'ID du colis
$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($package_id > 0) {
    try {
        // Récupérer les détails du colis
        $stmt = $conn->prepare("SELECT * FROM colis WHERE id = ? AND user_id = ?");
        $stmt->execute([$package_id, $user_id]);
        $package = $stmt->fetch();
        
        if ($package) {
            // Récupérer l'historique de suivi
            $stmt = $conn->prepare("
                SELECT tu.*, u.first_name, u.last_name
                FROM tracking_updates tu
                LEFT JOIN users u ON tu.created_by = u.id
                WHERE tu.colis_id = ?
                ORDER BY tu.timestamp DESC
            ");
            $stmt->execute([$package['id']]);
            $tracking_history = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Erreur détails colis: " . $e->getMessage());
        $error = 'Erreur lors de la récupération des détails du colis.';
    }
}

// La fonction getStatusColor est définie dans database.php

// La fonction getStatusIcon est définie dans database.php
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Colis - Smartcore Express</title>
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
    <script src="../js/session_activity.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .timeline-item {
            position: relative;
        }
        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 1.5rem;
            top: 3rem;
            width: 2px;
            height: calc(100% - 2rem);
            background-color: #e5e7eb;
        }
        .timeline-item.active:not(:last-child)::after {
            background-color: #3b82f6;
        }
        
        /* Styles responsive pour mobile */
        @media (max-width: 768px) {
            .mobile-nav {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }
            
            .mobile-nav-items {
                flex-direction: column;
                width: 100%;
                margin-top: 1rem;
                gap: 0.5rem;
            }
            
            .mobile-nav-items a {
                padding: 0.5rem;
                border-radius: 0.375rem;
                background-color: #f3f4f6;
                text-align: center;
            }
            
            .mobile-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem;
            }
            
            .mobile-flex {
                flex-direction: column;
                gap: 1rem;
            }
            
            .mobile-text-sm {
                font-size: 0.875rem;
            }
            
            .mobile-p-4 {
                padding: 1rem;
            }
            
            .mobile-hidden {
                display: none;
            }
            
            .timeline-item {
                padding-left: 0;
            }
            
            .timeline-item:not(:last-child)::after {
                left: 1rem;
            }
            
            .breadcrumb-mobile {
                flex-wrap: wrap;
                gap: 0.25rem;
            }
            
            .breadcrumb-mobile .fas {
                display: none;
            }
        }
    </style>
    <link rel="icon" type="image/png" href="../client/logo.png">
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
        <!-- Breadcrumb -->
        <div class="mb-6">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="dashboard.php" class="text-gray-600 hover:text-primary transition">
                            <i class="fas fa-home mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <a href="mes_colis.php" class="text-gray-600 hover:text-primary transition">
                                Mes colis
                            </a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-gray-500">Détails</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <?php if (!$package): ?>
            <!-- Colis non trouvé -->
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <i class="fas fa-exclamation-triangle text-6xl text-yellow-500 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Colis non trouvé</h2>
                <p class="text-gray-600 mb-6">Le colis demandé n'existe pas ou vous n'avez pas les permissions pour le consulter.</p>
                <a href="mes_colis.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Retour à mes colis
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Informations principales -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- En-tête du colis -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($package['tracking_number'] ?? ''); ?>
                                </h1>
                                <p class="text-gray-600"><?php echo htmlspecialchars($package['destination'] ?? ''); ?></p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-full border <?php echo getStatusColor($package['status'] ?? ''); ?>">
                                    <i class="<?php echo getStatusIcon($package['status'] ?? ''); ?> mr-2"></i>
                                    <?php echo htmlspecialchars($package['status'] ?? ''); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="font-semibold text-gray-700 mb-3">Informations du Colis</h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Description:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($package['description'] ?? ''); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Poids:</span>
                                        <span class="font-medium"><?php echo $package['weight'] ?? 0; ?> lb</span>
                                    </div>

                                </div>
                            </div>
                            
                            <div>
                                <h3 class="font-semibold text-gray-700 mb-3">Informations de Livraison</h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Destination:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($package['destination'] ?? ''); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Frais d'expédition:</span>
                                        <span class="font-medium">$<?php echo number_format($package['shipping_cost'] ?? 0, 2); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Coût total:</span>
                                        <span class="font-bold text-green-600">$<?php echo number_format($package['total_cost'] ?? 0, 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($package['instructions'] ?? '')): ?>
                            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h4 class="font-semibold text-blue-800 mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>Instructions spéciales
                                </h4>
                                <p class="text-blue-700"><?php echo nl2br(htmlspecialchars($package['instructions'] ?? '')); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Historique de suivi -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-route text-blue-600 mr-2"></i>
                            Historique de Suivi
                        </h2>
                        
                        <?php if (empty($tracking_history)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-clock text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">Aucun historique de suivi disponible pour le moment.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach($tracking_history as $index => $update): ?>
                                    <div class="timeline-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $index === 0 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600'; ?>">
                                                    <i class="<?php echo getStatusIcon($update['status'] ?? ''); ?>"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4 flex-1">
                                                <div class="flex justify-between items-start mb-1">
                                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($update['status'] ?? ''); ?></h3>
                                                    <span class="text-sm text-gray-500"><?php echo isset($update['timestamp']) ? date('d/m/Y H:i', strtotime($update['timestamp'])) : ''; ?></span>
                                                </div>
                                                <?php if (!empty($update['location'] ?? '')): ?>
                                                    <p class="text-sm text-gray-600 mb-1">
                                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                                        <?php echo htmlspecialchars($update['location'] ?? ''); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (!empty($update['description'] ?? '')): ?>
                                                    <p class="text-sm text-gray-700"><?php echo htmlspecialchars($update['description'] ?? ''); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($update['first_name'] ?? '')): ?>
                                                    <p class="text-xs text-gray-500 mt-2">
                                                        Mis à jour par: <?php echo htmlspecialchars(($update['first_name'] ?? '') . ' ' . ($update['last_name'] ?? '')); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Actions rapides -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Actions Rapides</h3>
                        <div class="space-y-3">
                            <a href="../track.php?tracking=<?php echo urlencode($package['tracking_number'] ?? ''); ?>" 
                               class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-search mr-2"></i> Suivi Public
                            </a>
                            <a href="mes_colis.php" 
                               class="w-full flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                                <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
                            </a>
                        </div>
                    </div>
                    
                    <!-- Informations supplémentaires -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Informations</h3>
                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-gray-600">Date de création:</span>
                                <p class="font-medium"><?php echo isset($package['created_at']) ? date('d/m/Y H:i', strtotime($package['created_at'])) : ''; ?></p>
                            </div>
                            <div>
                                <span class="text-gray-600">Dernière mise à jour:</span>
                                <p class="font-medium"><?php echo isset($package['updated_at']) ? date('d/m/Y H:i', strtotime($package['updated_at'])) : ''; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Calcul des coûts -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Détail des Coûts</h3>
                        <div class="space-y-2 text-sm">
                            <?php 
                            $weight = $package['weight'];
                            $rate_per_lb = 4.5;
                            $base_cost = $weight * $rate_per_lb;
                            $surcharge = 0;
                            if ($weight > 10) {
                                $surcharge = $base_cost * 0.1;
                            }
                            ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Poids:</span>
                                <span><?php echo $weight; ?> lb</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tarif de base:</span>
                                <span>$<?php echo number_format($base_cost, 2); ?></span>
                            </div>
                            <?php if ($surcharge > 0): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Surcharge (+10%):</span>
                                    <span>$<?php echo number_format($surcharge, 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Frais d'expédition:</span>
                                <span>$<?php echo number_format($package['shipping_cost'], 2); ?></span>
                            </div>
                            <hr class="my-2">
                            <div class="flex justify-between font-bold text-green-600">
                                <span>Total:</span>
                                <span>$<?php echo number_format($package['total_cost'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Menu mobile toggle
        document.addEventListener('DOMContentLoaded', function () {
            const button = document.getElementById('mobile-menu-button');
            const menu = document.getElementById('mobile-menu');
            
            if (button && menu) {
                button.addEventListener('click', function () {
                    menu.classList.toggle('hidden');
                });
            }
        });
    </script>
</body>
</html>