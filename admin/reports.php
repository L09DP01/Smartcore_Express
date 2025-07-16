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

// Récupérer les statistiques pour les rapports
try {
    // Statistiques générales
    $stmt = $conn->query("SELECT COUNT(*) as total FROM colis");
    $totalColis = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
    $totalClients = $stmt->fetch()['total'];
    
    // Revenus par mois (6 derniers mois)
    $stmt = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mois,
            SUM(shipping_cost) as revenus,
            COUNT(*) as nb_colis
        FROM colis 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY mois DESC
    ");
    $revenusParMois = $stmt->fetchAll();
    
    // Répartition par statut
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count 
        FROM colis 
        GROUP BY status
    ");
    $repartitionStatut = $stmt->fetchAll();
    
    // Top 10 clients
    $stmt = $conn->query("
        SELECT 
            u.first_name, u.last_name, u.email,
            COUNT(c.id) as nb_colis,
            SUM(c.shipping_cost) as total_depense
        FROM users u
        LEFT JOIN colis c ON u.id = c.user_id
        WHERE u.role = 'client'
        GROUP BY u.id
        ORDER BY nb_colis DESC
        LIMIT 10
    ");
    $topClients = $stmt->fetchAll();
    
    // Colis récents avec toutes les informations nécessaires
    $stmt = $conn->query("
        SELECT 
            c.id,
            c.tracking_number,
            c.destination,
            c.status,
            c.shipping_cost,
            c.total_cost,
            c.created_at,
            CONCAT(u.first_name, ' ', u.last_name) as client_name,
            u.email as client_email
        FROM colis c 
        INNER JOIN users u ON c.user_id = u.id 
        ORDER BY c.created_at DESC 
        LIMIT 20
    ");
    $colisRecents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Erreur rapports: " . $e->getMessage());
    $totalColis = $totalClients = 0;
    $revenusParMois = $repartitionStatut = $topClients = $colisRecents = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Admin Smartcore Express</title>
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
    <link rel="stylesheet" href="../css/theme.css">\n    <link rel="stylesheet" href="../css/admin-responsive.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/theme.js"></script>\n    <script src="../js/admin-responsive.js"></script>
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
                <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="colis_management.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
                    <i class="fas fa-box mr-3"></i>
                    Gestion Colis
                </a>
                <a href="users.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
                    <i class="fas fa-users mr-3"></i>
                    Utilisateurs
                </a>

                <a href="reports.php" class="flex items-center px-6 py-3 bg-blue-50 text-primary border-r-4 border-primary">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Rapports
                </a>
                <a href="sponsors.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
                    <i class="fas fa-bullhorn mr-3"></i>
                    Sponsors
                </a>
                <a href="settings.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
                    <i class="fas fa-cog mr-3"></i>
                    Paramètres
                </a>
            </nav>
            
            <div class="absolute bottom-0 w-64 p-6">
                <a href="../auth/logout.php" class="flex items-center text-red-600 hover:text-red-800 transition">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Déconnexion
                </a>
            </div>
        </div>

        <!-- Contenu principal -->
        <main class="admin-main flex-1 overflow-x-hidden overflow-y-auto">
            <!-- Header -->
            <header class="admin-header bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-800">Rapports et Statistiques</h1>
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
            <main class="p-6">
                <div class="mb-8">
                    <p class="text-gray-600 mt-2">Analyse détaillée des performances de la plateforme</p>
                </div>

                <!-- Statistiques générales -->
                <div class="stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Colis</p>
                                <p class="text-2xl font-bold text-primary"><?php echo number_format($totalColis); ?></p>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-full">
                                <i class="fas fa-box text-primary text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Clients</p>
                                <p class="text-2xl font-bold text-accent"><?php echo number_format($totalClients); ?></p>
                            </div>
                            <div class="bg-accent bg-opacity-10 p-3 rounded-full">
                                <i class="fas fa-users text-accent text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Revenus Total</p>
                                <p class="text-2xl font-bold text-secondary">
                                    <?php 
                                    $totalRevenus = !empty($revenusParMois) ? array_sum(array_column($revenusParMois, 'revenus')) : 0;
                                    echo number_format($totalRevenus ?: 0, 0, ',', ' ') . ' USD';
                                    ?>
                                </p>
                            </div>
                            <div class="bg-secondary bg-opacity-10 p-3 rounded-full">
                                <i class="fas fa-dollar-sign text-secondary text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Moyenne/Mois</p>
                                <p class="text-2xl font-bold text-purple-600">
                                    <?php 
                                    $moyenneMois = count($revenusParMois) > 0 ? $totalRevenus / count($revenusParMois) : 0;
                                    echo number_format($moyenneMois, 0, ',', ' ') . ' USD';
                                    ?>
                                </p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphiques -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Répartition par statut -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold mb-4">Répartition par Statut</h3>
                        <div style="height: 300px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Évolution des revenus -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold mb-4">Évolution des Revenus (6 derniers mois)</h3>
                        <div style="height: 300px;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top clients -->
                <div class="bg-white rounded-lg shadow-md mb-8">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">Top 10 Clients</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nb Colis</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Dépensé</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach($topClients as $client): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                        <?php echo htmlspecialchars($client['email']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo $client['nb_colis']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">
                                        <?php echo number_format($client['total_depense'] ?: 0, 0, ',', ' ') . ' USD'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Colis récents -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Colis Récents</h3>
                        <p class="text-sm text-gray-600 mt-1">Liste des 20 derniers colis enregistrés</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coût</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($colisRecents)): ?>
                                    <?php foreach($colisRecents as $colis): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-blue-600">
                                                <?php echo htmlspecialchars($colis['tracking_number'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($colis['client_name'] ?? 'Client inconnu'); ?>
                                            </div>
                                            <?php if (!empty($colis['client_email'])): ?>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($colis['client_email']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($colis['destination'] ?? 'Non spécifiée'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $status = $colis['status'] ?? 'Reçue à entrepôt';
                                            $statusColor = getStatusColor($status);
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php 
                                                $cost = $colis['shipping_cost'] ?? $colis['total_cost'] ?? 0;
                                                echo number_format($cost, 2, ',', ' ') . ' USD'; 
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php 
                                                if (!empty($colis['created_at'])) {
                                                    echo date('d/m/Y', strtotime($colis['created_at']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php 
                                                if (!empty($colis['created_at'])) {
                                                    echo date('H:i', strtotime($colis['created_at']));
                                                }
                                                ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center">
                                            <div class="text-gray-500">
                                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8V4a1 1 0 00-1-1H7a1 1 0 00-1 1v1m8 0V4.5" />
                                                </svg>
                                                <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun colis trouvé</h3>
                                                <p class="mt-1 text-sm text-gray-500">Aucun colis n'a été enregistré pour le moment.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Graphique répartition par statut
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($repartitionStatut, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($repartitionStatut, 'count')); ?>,
                    backgroundColor: [
                        '#0047AB',
                        '#FF6B00', 
                        '#00A86B',
                        '#FFC107',
                        '#DC3545',
                        '#6C757D'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.5
            }
        });

        // Graphique évolution des revenus
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse(array_column($revenusParMois, 'mois'))); ?>,
                datasets: [{
                    label: 'Revenus (USD)',
                    data: <?php echo json_encode(array_reverse(array_column($revenusParMois, 'revenus'))); ?>,
                    borderColor: '#0047AB',
                    backgroundColor: 'rgba(0, 71, 171, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' USD';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>