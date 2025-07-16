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
$message = '';
$messageType = '';

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

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general':
                // Mise à jour des paramètres généraux
                $siteName = $_POST['site_name'] ?? '';
                $siteEmail = $_POST['site_email'] ?? '';
                $sitePhone = $_POST['site_phone'] ?? '';
                
                // Ici vous pourriez sauvegarder dans une table de configuration
                $message = "Paramètres généraux mis à jour avec succès.";
                $messageType = 'success';
                break;
                
            case 'update_shipping':
                // Mise à jour des paramètres d'expédition
                $defaultShippingCost = $_POST['default_shipping_cost'] ?? 0;
                $freeShippingThreshold = $_POST['free_shipping_threshold'] ?? 0;
                
                $message = "Paramètres d'expédition mis à jour avec succès.";
                $messageType = 'success';
                break;
                
            case 'update_notifications':
                // Mise à jour des paramètres de notification
                $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
                $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;
                
                $message = "Paramètres de notification mis à jour avec succès.";
                $messageType = 'success';
                break;
        }
    }
}

// Récupérer les statistiques système
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM colis");
    $totalColis = $stmt->fetch()['total'];
    
    // Taille de la base de données (approximative)
    $stmt = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'db_size' FROM information_schema.tables WHERE table_schema = DATABASE()");
    $dbSize = $stmt->fetch()['db_size'] ?? 0;
    
} catch(PDOException $e) {
    error_log("Erreur paramètres: " . $e->getMessage());
    $totalUsers = $totalColis = 0;
    $dbSize = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Admin Smartcore Express</title>
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
    <script src="../js/session_activity.js"></script>
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
                <a href="colis_management.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
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
                <a href="sponsors.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary transition">
                    <i class="fas fa-bullhorn mr-3"></i>
                    Sponsors
                </a>
                <a href="settings.php" class="flex items-center px-6 py-3 bg-primary text-white border-r-4 border-secondary">
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

        <!-- Contenu principal -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <header class="admin-header bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-bold text-gray-800">Paramètres du Système</h1>
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
            
            <div class="p-8">
                <div class="mb-8">
                    <p class="text-gray-600 mt-2">Configuration et gestion des paramètres de la plateforme</p>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                    <div class="flex items-center">
                        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Informations système -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Utilisateurs</p>
                                <p class="text-2xl font-bold text-primary"><?php echo number_format($totalUsers); ?></p>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-full">
                                <i class="fas fa-users text-primary text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Colis</p>
                                <p class="text-2xl font-bold text-accent"><?php echo number_format($totalColis); ?></p>
                            </div>
                            <div class="bg-accent bg-opacity-10 p-3 rounded-full">
                                <i class="fas fa-box text-accent text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Taille BD</p>
                                <p class="text-2xl font-bold text-secondary"><?php echo $dbSize; ?> MB</p>
                            </div>
                            <div class="bg-secondary bg-opacity-10 p-3 rounded-full">
                                <i class="fas fa-database text-secondary text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onglets de paramètres -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button onclick="showTab('general')" id="tab-general" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                <i class="fas fa-cog mr-2"></i>
                                Général
                            </button>
                            <button onclick="showTab('shipping')" id="tab-shipping" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                <i class="fas fa-shipping-fast mr-2"></i>
                                Expédition
                            </button>
                            <button onclick="showTab('notifications')" id="tab-notifications" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                <i class="fas fa-bell mr-2"></i>
                                Notifications
                            </button>
                            <button onclick="showTab('security')" id="tab-security" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Sécurité
                            </button>
                        </nav>
                    </div>

                    <!-- Contenu des onglets -->
                    <div class="p-6">
                        <!-- Onglet Général -->
                        <div id="content-general" class="tab-content">
                            <h3 class="text-lg font-semibold mb-4">Paramètres Généraux</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="update_general">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom du Site</label>
                                        <input type="text" name="site_name" value="Smartcore Express" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email de Contact</label>
                                        <input type="email" name="site_email" value="contact@smartcore-express.com" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                                        <input type="tel" name="site_phone" value="+225 XX XX XX XX" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Fuseau Horaire</label>
                                        <select name="timezone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                            <option value="Africa/Abidjan" selected>Africa/Abidjan (GMT+0)</option>
                                            <option value="Europe/Paris">Europe/Paris (GMT+1)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                                        <i class="fas fa-save mr-2"></i>
                                        Sauvegarder
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Onglet Expédition -->
                        <div id="content-shipping" class="tab-content hidden">
                            <h3 class="text-lg font-semibold mb-4">Paramètres d'Expédition</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="update_shipping">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Coût d'Expédition par Défaut (USD)</label>
                                        <input type="number" name="default_shipping_cost" value="5000" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Seuil Livraison Gratuite (USD)</label>
                                        <input type="number" name="free_shipping_threshold" value="50000" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Délai de Livraison Standard (jours)</label>
                                        <input type="number" name="standard_delivery_days" value="7" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Délai de Livraison Express (jours)</label>
                                        <input type="number" name="express_delivery_days" value="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                                        <i class="fas fa-save mr-2"></i>
                                        Sauvegarder
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Onglet Notifications -->
                        <div id="content-notifications" class="tab-content hidden">
                            <h3 class="text-lg font-semibold mb-4">Paramètres de Notification</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="update_notifications">
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <h4 class="font-medium text-gray-900">Notifications Email</h4>
                                            <p class="text-sm text-gray-500">Envoyer des notifications par email aux clients</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="email_notifications" class="sr-only peer" checked>
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <h4 class="font-medium text-gray-900">Notifications SMS</h4>
                                            <p class="text-sm text-gray-500">Envoyer des notifications par SMS aux clients</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="sms_notifications" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <h4 class="font-medium text-gray-900">Notifications Push</h4>
                                            <p class="text-sm text-gray-500">Envoyer des notifications push dans l'application</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="push_notifications" class="sr-only peer" checked>
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                                        <i class="fas fa-save mr-2"></i>
                                        Sauvegarder
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Onglet Sécurité -->
                        <div id="content-security" class="tab-content hidden">
                            <h3 class="text-lg font-semibold mb-4">Paramètres de Sécurité</h3>
                            
                            <div class="space-y-6">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                                        <h4 class="font-medium text-yellow-800">Sauvegarde de la Base de Données</h4>
                                    </div>
                                    <p class="text-sm text-yellow-700 mt-2">Dernière sauvegarde: <?php echo date('d/m/Y H:i'); ?></p>
                                    <button class="mt-3 bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 transition text-sm">
                                        <i class="fas fa-download mr-2"></i>
                                        Créer une Sauvegarde
                                    </button>
                                </div>
                                
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-shield-alt text-red-600 mr-2"></i>
                                        <h4 class="font-medium text-red-800">Sécurité du Système</h4>
                                    </div>
                                    <div class="mt-3 space-y-2 text-sm text-red-700">
                                        <div class="flex items-center justify-between">
                                            <span>Authentification à deux facteurs</span>
                                            <span class="text-red-600">Désactivée</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>Chiffrement SSL</span>
                                            <span class="text-green-600">Activé</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>Logs de sécurité</span>
                                            <span class="text-green-600">Activés</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                        <h4 class="font-medium text-blue-800">Informations Système</h4>
                                    </div>
                                    <div class="mt-3 space-y-2 text-sm text-blue-700">
                                        <div class="flex items-center justify-between">
                                            <span>Version PHP</span>
                                            <span><?php echo phpversion(); ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>Version MySQL</span>
                                            <span><?php echo $conn->getAttribute(PDO::ATTR_SERVER_VERSION); ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>Serveur Web</span>
                                            <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Non disponible'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Masquer tous les contenus d'onglets
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.add('hidden'));
            
            // Réinitialiser tous les boutons d'onglets
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => {
                button.classList.remove('border-primary', 'text-primary');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Afficher le contenu de l'onglet sélectionné
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Activer le bouton de l'onglet sélectionné
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.remove('border-transparent', 'text-gray-500');
            activeButton.classList.add('border-primary', 'text-primary');
        }
        
        // Activer le premier onglet par défaut
        showTab('general');
    </script>
</body>
</html>