<?php
// Inclure le gestionnaire de session avec gestion d'inactivité
require_once '../auth/session_manager.php';
require_once '../config/database.php';
// require_once '../config/email_functions.php'; // Supprimé - fonctionnalité email retirée

// Vérifier si l'utilisateur est admin (la session est déjà vérifiée par session_manager.php)
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';

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

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Ajouter un nouveau colis
        $user_id = sanitizeInput($_POST['user_id'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $weight = floatval($_POST['weight'] ?? 0);
        $declared_value = floatval($_POST['declared_value'] ?? 0);
        $destination_country = sanitizeInput($_POST['destination_country'] ?? 'Haïti');
        
        if (empty($user_id) || empty($description)) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            try {
                $tracking_number = generateTrackingNumber();
                $shipping_cost = calculateShippingCost($weight, 'Standard');
                
                $stmt = $conn->prepare("
                    INSERT INTO colis (user_id, tracking_number, description, weight, declared_value, 
                                     destination_country, shipping_cost, status, current_location) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'En attente', 'Entrepôt Miami')
                ");
                
                if ($stmt->execute([$user_id, $tracking_number, $description, $weight, $declared_value, $destination_country, $shipping_cost])) {
                    $colis_id = $conn->lastInsertId();
                    
                    // Ajouter l'entrée de suivi initiale
                    $stmt = $conn->prepare("
                        INSERT INTO tracking_updates (colis_id, status, location, description, created_by) 
                        VALUES (?, 'En attente', 'Entrepôt Miami', 'Colis reçu à l\'entrepôt', ?)
                    ");
                    $stmt->execute([$colis_id, $_SESSION['user_id']]);
                    
                    // Notifications email supprimées - fonctionnalité retirée
                    $message = 'Colis ajouté avec succès. Numéro de suivi: ' . $tracking_number . '.';
                } else {
                    $error = 'Erreur lors de l\'ajout du colis.';
                }
            } catch(PDOException $e) {
                error_log("Erreur ajout colis: " . $e->getMessage());
                $error = 'Erreur de base de données.';
            }
        }
    } elseif ($action === 'update_status') {
        // Mettre à jour le statut d'un colis
        $colis_id = intval($_POST['colis_id'] ?? 0);
        $new_status = sanitizeInput($_POST['new_status'] ?? '');
        $new_location = sanitizeInput($_POST['new_location'] ?? '');
        $description = sanitizeInput($_POST['update_description'] ?? '');
        
        if ($colis_id && $new_status) {
            try {
                // Mettre à jour le colis
                $stmt = $conn->prepare("UPDATE colis SET status = ?, current_location = ? WHERE id = ?");
                $stmt->execute([$new_status, $new_location, $colis_id]);
                
                // Ajouter l'entrée de suivi
                $stmt = $conn->prepare("
                    INSERT INTO tracking_updates (colis_id, status, location, description, created_by) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$colis_id, $new_status, $new_location, $description, $_SESSION['user_id']]);
                
                $message = 'Statut mis à jour avec succès.';
            } catch(PDOException $e) {
                error_log("Erreur mise à jour: " . $e->getMessage());
                $error = 'Erreur lors de la mise à jour.';
            }
        }
    }
}

// Récupérer la liste des colis avec pagination
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(c.tracking_number LIKE ? OR c.description LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Compter le total
    $count_query = "SELECT COUNT(*) as total FROM colis c JOIN users u ON c.user_id = u.id $where_clause";
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Récupérer les colis
    $query = "
        SELECT c.*, u.first_name, u.last_name, u.email 
        FROM colis c 
        JOIN users u ON c.user_id = u.id 
        $where_clause 
        ORDER BY c.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $colis_list = $stmt->fetchAll();
    
    // Récupérer la liste des utilisateurs pour le formulaire
    $stmt = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'client' ORDER BY first_name, last_name");
    $users = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Erreur récupération colis: " . $e->getMessage());
    $colis_list = [];
    $users = [];
    $total_pages = 1;
}

$status_options = ['Reçue à entrepôt', 'En preparation', 'Expédié vers Haïti', 'Arrivé en Haïti', 'En dédouanement', 'Prêt pour livraison', 'Livré'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Colis - Smartcore Express</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">\n    <script src="../js/session_activity.js"></script>
    <link rel="stylesheet" href="../css/theme.css">\n    <link rel="stylesheet" href="../css/admin-responsive.css">
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
                <a href="colis_management.php" class="flex items-center px-6 py-3 bg-primary text-white border-r-4 border-secondary">
                    <i class="fas fa-box mr-3"></i>
                    Gestion Colis
                </a>
                <a href="users.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
                    <i class="fas fa-users mr-3"></i>
                    Utilisateurs
                </a>

                <a href="reports.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Rapports
                </a>
                <a href="settings.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
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
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-bold text-gray-800">Gestion des Colis</h1>
                        <button onclick="openAddModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-plus mr-2"></i>Nouveau Colis
                        </button>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <button class="relative p-2 text-gray-600 hover:text-gray-800 transition">
                            <i class="fas fa-bell text-lg"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
                        </button>
                        
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
            
            <!-- Content -->
            <main class="p-6">
                <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4 items-end">
                        <div class="flex-1 min-w-64">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Rechercher</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Numéro de suivi, description, client..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Tous les statuts</option>
                                <?php foreach($status_options as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                <i class="fas fa-search mr-2"></i>Filtrer
                            </button>
                        </div>
                        <div>
                            <a href="colis_management.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                                <i class="fas fa-times mr-2"></i>Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Colis Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="table-container overflow-x-auto">\n                        <table class="responsive-table min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro de Suivi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Localisation</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach($colis_list as $colis): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($colis['tracking_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($colis['first_name'] . ' ' . $colis['last_name']); ?></div>
                                            <div class="text-gray-500"><?php echo htmlspecialchars($colis['email']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars(substr($colis['description'], 0, 50)) . (strlen($colis['description']) > 50 ? '...' : ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusColor($colis['status']); ?>">
                                            <?php echo htmlspecialchars($colis['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($colis['current_location'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDate($colis['created_at'], 'd/m/Y'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="openUpdateModal(<?php echo $colis['id']; ?>, '<?php echo htmlspecialchars($colis['status']); ?>', '<?php echo htmlspecialchars($colis['current_location']); ?>')" 
                                                class="text-primary hover:text-blue-700">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Précédent
                            </a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Suivant
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Affichage de <span class="font-medium"><?php echo $offset + 1; ?></span> à 
                                    <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span> sur 
                                    <span class="font-medium"><?php echo $total_records; ?></span> résultats
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-primary border-primary text-white' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php endfor; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal Ajouter Colis -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Ajouter un Nouveau Colis</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client *</label>
                        <select name="user_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Sélectionner un client</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                        <textarea name="description" required rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                                  placeholder="Description du colis..."></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Poids (kg)</label>
                            <input type="number" name="weight" step="0.1" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Valeur déclarée ($)</label>
                            <input type="number" name="declared_value" step="0.01" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pays de destination</label>
                        <select name="destination_country" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="Haïti">Haïti</option>
                            <option value="République Dominicaine">République Dominicaine</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                            Annuler
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-blue-700 transition">
                            Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Mettre à jour Statut -->
    <div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Mettre à jour le Statut</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="colis_id" id="update_colis_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nouveau Statut *</label>
                        <select name="new_status" id="update_status" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <?php foreach($status_options as $status): ?>
                            <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nouvelle Localisation</label>
                        <input type="text" name="new_location" id="update_location" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                               placeholder="Localisation actuelle...">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description de la mise à jour</label>
                        <textarea name="update_description" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                                  placeholder="Détails de la mise à jour..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeUpdateModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                            Annuler
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-blue-700 transition">
                            Mettre à jour
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }
        
        function openUpdateModal(colisId, currentStatus, currentLocation) {
            document.getElementById('update_colis_id').value = colisId;
            document.getElementById('update_status').value = currentStatus;
            document.getElementById('update_location').value = currentLocation;
            document.getElementById('updateModal').classList.remove('hidden');
        }
        
        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
        }
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const updateModal = document.getElementById('updateModal');
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === updateModal) {
                closeUpdateModal();
            }
        }
    </script>
</body>
</html>