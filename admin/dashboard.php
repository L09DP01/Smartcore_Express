<?php
// Inclure le gestionnaire de session avec gestion d'inactivité
require_once '../auth/session_manager.php';
require_once '../config/database.php';

// Vérifier si l'utilisateur est admin (la session est déjà vérifiée par session_manager.php)
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getDBConnection();

// Récupérer les informations de l'utilisateur connecté
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // S'assurer que les clés existent même si elles sont NULL
    if ($user) {
        $user['profile_photo'] = $user['profile_photo'] ?? null;
        $user['first_name'] = $user['first_name'] ?? '';
        $user['last_name'] = $user['last_name'] ?? '';
        $user['email'] = $user['email'] ?? '';
    }
} catch (Exception $e) {
    $user = null;
}

// Traitement de la recherche de colis
$search_results = [];
$search_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    if (!empty($search_term)) {
        try {
            // Récupération des informations complètes des colis et des utilisateurs associés
            $stmt = $conn->prepare("
                SELECT c.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.city, u.country
                FROM colis c 
                LEFT JOIN users u ON c.user_id = u.id 
                WHERE c.tracking_number LIKE ? OR c.description LIKE ? OR 
                      CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?
                ORDER BY c.created_at DESC 
                LIMIT 10
            ");
            $search_param = "%$search_term%";
            $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
            $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupération des statuts disponibles pour chaque colis
            foreach ($search_results as $key => $result) {
                // Récupération de l'historique de suivi pour chaque colis (table tracking_updates)
                $stmt = $conn->prepare("SELECT * FROM tracking_updates WHERE colis_id = ? ORDER BY timestamp DESC LIMIT 1");
                $stmt->execute([$result['id']]);
                $latest_status = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Mise à jour du statut avec la dernière entrée de l'historique si disponible
                if ($latest_status) {
                    $search_results[$key]['status'] = $latest_status['status'] ?? $result['status'] ?? 'Reçue à entrepôt';
                    $search_results[$key]['location'] = $latest_status['location'] ?? $result['current_location'] ?? 'Entrepôt Miami';
                    $search_results[$key]['last_update'] = $latest_status['timestamp'] ?? $result['updated_at'] ?? $result['created_at'];
                } else {
                    // Si pas d'historique, utiliser les données de la table colis
                    $search_results[$key]['status'] = $result['status'] ?? 'Reçue à entrepôt';
                    $search_results[$key]['location'] = $result['current_location'] ?? 'Entrepôt Miami';
                    $search_results[$key]['last_update'] = $result['updated_at'] ?? $result['created_at'];
                }
            }
        } catch(PDOException $e) {
            error_log("Erreur recherche: " . $e->getMessage());
            $search_error = 'Erreur lors de la recherche: ' . $e->getMessage();
        }
    }
}

// Récupérer les statistiques
try {
    // Total des colis
    $stmt = $conn->query("SELECT COUNT(*) as total FROM colis");
    $totalColis = $stmt->fetch()['total'];
    
    // Colis en transit
$stmt = $conn->query("SELECT COUNT(*) as total FROM colis WHERE status IN ('En preparation', 'Expédié vers Haïti', 'Arrivé en Haïti', 'En dédouanement', 'Prêt pour livraison')");
    $colisEnTransit = $stmt->fetch()['total'];
    
    // Colis livrés ce mois
    $stmt = $conn->query("SELECT COUNT(*) as total FROM colis WHERE status = 'Livré' AND MONTH(updated_at) = MONTH(CURRENT_DATE())");
    $colisLivresMois = $stmt->fetch()['total'];
    
    // Total des utilisateurs
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
    $totalUsers = $stmt->fetch()['total'];
    
    // Revenus du mois
    $stmt = $conn->query("SELECT SUM(shipping_cost) as total FROM colis WHERE MONTH(created_at) = MONTH(CURRENT_DATE())");
    $revenusMois = $stmt->fetch()['total'] ?? 0;
    
    // Colis récents
    $stmt = $conn->query("
        SELECT c.id, c.tracking_number, c.description, c.weight, c.destination, 
               c.status, c.total_cost, c.created_at, c.updated_at,
               u.first_name, u.last_name 
        FROM colis c 
        JOIN users u ON c.user_id = u.id 
        ORDER BY c.created_at DESC 
        LIMIT 10
    ");
    $colisRecents = $stmt->fetchAll();
    
    // Statistiques par statut
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count 
        FROM colis 
        GROUP BY status
    ");
    $statsStatut = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Erreur dashboard: " . $e->getMessage());
    $totalColis = $colisEnTransit = $colisLivresMois = $totalUsers = 0;
    $revenusMois = 0;
    $colisRecents = [];
    $statsStatut = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Smartcore Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/admin-responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/session_activity.js"></script>
    <script src="../js/admin-responsive.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
    <link rel="icon" type="image/png" href="../client/logo.png">
</head>
<body class="bg-gray-50 admin-layout">
    <!-- Sidebar -->
    <div class="flex h-screen">
        <aside class="admin-sidebar w-64 bg-white shadow-lg border-r border-gray-200">
            <div class="p-6 bg-gradient-to-r from-blue-50 to-indigo-50">
                <div class="flex items-center">
                    <img src="../img/Logo.png" alt="Logo" class="h-12 w-auto">
                    <span class="ml-2 text-xl font-bold text-gray-800">Admin Panel</span>
                </div>
            </div>
            
            <nav class="mt-6">
                <a href="dashboard.php" class="flex items-center px-6 py-3 bg-primary text-white border-r-4 border-secondary">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="colis_management.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-box mr-3"></i>
                    Gestion Colis
                </a>
                <a href="users.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-users mr-3"></i>
                    Utilisateurs
                </a>

                <a href="reports.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Rapports
                </a>
                <a href="sponsors.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-bullhorn mr-3"></i>
                    Sponsors
                </a>
                <a href="settings.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-cog mr-3"></i>
                    Paramètres
                </a>
            </nav>
            
            <div class="absolute bottom-0 w-64 p-6">
                <a href="../auth/logout.php" class="flex items-center text-red-500 hover:text-red-700 transition">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Déconnexion
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <main class="admin-main flex-1 overflow-x-hidden overflow-y-auto">
            <!-- Header -->
            <header class="admin-header bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div class="relative">
                            <button class="p-2 text-gray-400 hover:text-gray-600 transition">
                                <i class="fas fa-bell text-lg"></i>
                                <span class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">3</span>
                            </button>
                        </div>
                        
                        <!-- Admin Profile -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-100 transition">
                                <img src="<?php echo ($user && isset($user['profile_photo']) && !empty($user['profile_photo'])) ? '../' . $user['profile_photo'] : '../img/admin-profile.jpg'; ?>" alt="Admin" class="w-8 h-8 rounded-full object-cover">
                                <div class="text-left">
                                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrateur'); ?></p>
                                    <p class="text-xs text-gray-500">Administrateur</p>
                                </div>
                                <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                                <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-3"></i>Mon Profil
                                </a>
                                <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-3"></i>Paramètres
                                </a>
                                <hr class="my-2">
                                <!-- Theme Toggle -->
                                <div class="flex items-center justify-between px-4 py-2">
                                    <div class="flex items-center">
                                        <i class="fas fa-palette mr-3 text-gray-700"></i>
                                        <span class="text-sm text-gray-700">Thème</span>
                                    </div>
                                    <label class="theme-switch">
                                        <input type="checkbox" id="theme-toggle">
                                        <span class="slider">
                                            <i class="fas fa-sun theme-icon sun"></i>
                                            <i class="fas fa-moon theme-icon moon"></i>
                                        </span>
                                    </label>
                                </div>
                                <hr class="my-2">
                                <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-3"></i>Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Content -->
            <div class="p-6">
                <!-- Stats Cards -->
                <div class="stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-box text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Colis</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalColis); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-truck text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">En Transit</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($colisEnTransit); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Livrés ce mois</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($colisLivresMois); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-dollar-sign text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Revenus du mois</p>
                                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($revenusMois, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Status Chart -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Répartition par Statut</h3>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Recherche de Colis -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Rechercher un Colis</h3>
                        
                        <!-- Formulaire de recherche -->
                        <form method="GET" class="mb-4">
                            <div class="flex">
                                <input type="text" name="search" 
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                       placeholder="Numéro de suivi, nom client, email..."
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <button type="submit" class="bg-primary text-white px-4 py-2 hover:bg-blue-700 transition">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($_GET['search'])): ?>
                                <a href="dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-r-md hover:bg-gray-600 transition ml-1">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <!-- Résultats de recherche -->
                        <?php if (isset($_GET['search'])): ?>
                            <?php if (!empty($search_results)): ?>
                                <div class="space-y-2 max-h-64 overflow-y-auto">
                                    <h4 class="font-medium text-gray-700 mb-2">Résultats trouvés (<?php echo count($search_results); ?>):</h4>
                                    <?php foreach($search_results as $result): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <!-- Numéro de suivi -->
                                                <p class="font-medium text-gray-800 mb-1">
                                                    <i class="fas fa-box mr-1"></i>
                                                    <?php echo htmlspecialchars($result['tracking_number'] ?? 'N/A'); ?>
                                                </p>
                                                
                                                <!-- Informations client -->
                                                <div class="flex items-center text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <span><?php echo htmlspecialchars(trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? '')) ?: 'Client non défini'); ?></span>
                                                    <?php if (!empty($result['email'])): ?>
                                                        <span class="ml-2 text-gray-400">•</span>
                                                        <span class="ml-2"><?php echo htmlspecialchars($result['email']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Description -->
                                                <p class="text-xs text-gray-500 mb-1">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    <?php echo htmlspecialchars(substr($result['description'] ?? 'Aucune description', 0, 50)) . (strlen($result['description'] ?? '') > 50 ? '...' : ''); ?>
                                                </p>
                                                
                                                <!-- Localisation et poids -->
                                                <div class="flex items-center text-xs text-gray-400 space-x-4">
                                                    <?php if (!empty($result['location'])): ?>
                                                        <span><i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($result['location']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($result['weight'])): ?>
                                                        <span><i class="fas fa-weight mr-1"></i><?php echo htmlspecialchars($result['weight']); ?> kg</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($result['last_update'])): ?>
                                                        <span><i class="fas fa-clock mr-1"></i><?php echo date('d/m/Y H:i', strtotime($result['last_update'])); ?></span>
                                                    <?php elseif (!empty($result['created_at'])): ?>
                                                        <span><i class="fas fa-calendar mr-1"></i><?php echo date('d/m/Y', strtotime($result['created_at'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Prix d'expédition -->
                                            <div class="flex flex-col items-end">
                                                <?php if (!empty($result['shipping_cost'])): ?>
                                                    <span class="text-sm font-medium text-green-600">
                                                        <?php echo number_format($result['shipping_cost'], 2); ?> USD
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif (!empty($_GET['search'])): ?>
                                <p class="text-gray-500 text-center py-4">Aucun résultat trouvé pour "<?php echo htmlspecialchars($_GET['search']); ?>"</p>
                            <?php endif; ?>
                            
                            <?php if (!empty($search_error)): ?>
                                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                                    <?php echo htmlspecialchars($search_error); ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Actions rapides quand pas de recherche -->
                            <div class="space-y-2">
                                <a href="colis_management.php" class="flex items-center p-2 text-primary hover:bg-blue-50 rounded transition">
                                    <i class="fas fa-plus mr-3"></i>
                                    Ajouter un Colis
                                </a>
                                <a href="users.php?action=add" class="flex items-center p-2 text-secondary hover:bg-orange-50 rounded transition">
                                    <i class="fas fa-user-plus mr-3"></i>
                                    Ajouter un Utilisateur
                                </a>
                                <a href="reports.php" class="flex items-center p-2 text-purple-600 hover:bg-purple-50 rounded transition">
                                    <i class="fas fa-download mr-3"></i>
                                    Exporter Rapport
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Packages -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Colis Récents</h3>
                    </div>
                    <div class="table-container overflow-x-auto">
                        <table class="responsive-table min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro de Suivi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hide-mobile">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hide-small">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach($colisRecents as $colis): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($colis['tracking_number'] ?? ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars(($colis['first_name'] ?? '') . ' ' . ($colis['last_name'] ?? '')); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 hide-mobile">
                                        <?php 
                                        $description = $colis['description'] ?? '';
                                        echo htmlspecialchars(substr($description, 0, 50)) . (strlen($description) > 50 ? '...' : ''); 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusColor($colis['status'] ?? 'En attente'); ?>">
                                            <?php echo htmlspecialchars($colis['status'] ?? 'En attente'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 hide-small">
                                        <?php echo formatDate($colis['created_at'] ?? date('Y-m-d'), 'd/m/Y'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="table-actions">
                                            <button onclick="openStatusModal(<?php echo $colis['id']; ?>, '<?php echo htmlspecialchars($colis['tracking_number'] ?? ''); ?>', '<?php echo htmlspecialchars($colis['status'] ?? 'En attente'); ?>')" class="action-btn edit" title="Mettre à jour le statut">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="colis_management.php?id=<?php echo $colis['id']; ?>" class="action-btn edit" title="Modifier">
                                                <i class="fas fa-cog"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Chart for status distribution
        const ctx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($statsStatut); ?>;
        
        const labels = statusData.map(item => item.status);
        const data = statusData.map(item => item.count);
        const colors = [
            '#FEF3C7', '#DBEAFE', '#FED7AA', '#E9D5FF', 
            '#D1FAE5', '#FEE2E2', '#F3F4F6'
        ];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10
                    }
                }
            }
        });
        
        // Initialiser le gestionnaire de session pour le dashboard admin
        document.addEventListener('DOMContentLoaded', function() {
            // Le gestionnaire de session est déjà initialisé par session_activity.js
            console.log('Dashboard admin - Gestionnaire de session actif');
        });
        
        // Fonctions pour le modal de mise à jour du statut
        function openStatusModal(id, trackingNumber, currentStatus) {
            document.getElementById('package-id').value = id;
            document.getElementById('modal-tracking-number').textContent = trackingNumber;
            
            // Sélectionner le statut actuel dans le dropdown
            const statusSelect = document.getElementById('status-select');
            for (let i = 0; i < statusSelect.options.length; i++) {
                if (statusSelect.options[i].value === currentStatus) {
                    statusSelect.selectedIndex = i;
                    break;
                }
            }
            
            document.getElementById('status-modal').classList.remove('hidden');
        }
        
        function closeStatusModal() {
            document.getElementById('status-modal').classList.add('hidden');
        }
    </script>
    
    <!-- Modal pour la mise à jour du statut -->
    <div id="status-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="modal-content relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Mettre à jour le statut</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500 mb-3">
                        Colis: <span id="modal-tracking-number" class="font-semibold"></span>
                    </p>
                    <form action="update_status.php" method="post">
                        <input type="hidden" id="package-id" name="package_id">
                        <div class="mb-4">
                            <label for="status-select" class="block text-sm font-medium text-gray-700 text-left mb-1">Nouveau statut:</label>
                            <select id="status-select" name="status" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                                <option value="Reçue à entrepôt">Reçue à entrepôt</option>
                            <option value="En preparation">En preparation</option>
                            <option value="Expédié vers Haïti">Expédié vers Haïti</option>
                            <option value="Arrivé en Haïti">Arrivé en Haïti</option>
                            <option value="En dédouanement">En dédouanement</option>
                            <option value="Prêt pour livraison">Prêt pour livraison</option>
                            <option value="Livré">Livré</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="status-notes" class="block text-sm font-medium text-gray-700 text-left mb-1">Notes (optionnel):</label>
                            <textarea id="status-notes" name="notes" rows="3" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeStatusModal()" class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                Annuler
                            </button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white text-base font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Mettre à jour
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>