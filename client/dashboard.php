<?php

// Gérer le callback OAuth si présent
if (isset($_GET['code']) && isset($_GET['state']) && isset($_GET['provider'])) {
    include_once '../auth/oauth-callback.php';
    exit();
}

// Inclure le gestionnaire de session avec gestion d'inactivité
require_once '../auth/session_manager.php';
require_once '../config/database.php';

// La vérification de session est maintenant gérée par session_manager.php

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$user = null;
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Erreur récupération utilisateur: " . $e->getMessage());
}

// Récupérer les statistiques du client
try {
    // Total des colis du client
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM colis WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalColis = $stmt->fetch()['total'];
    
    // Colis en transit
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM colis WHERE user_id = ? AND status IN ('En preparation', 'Expédié vers Haïti', 'Arrivé en Haïti', 'En dédouanement', 'Prêt pour livraison')");
    $stmt->execute([$user_id]);
    $colisEnTransit = $stmt->fetch()['total'];
    
    // Colis livrés
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM colis WHERE user_id = ? AND status = 'Livré'");
    $stmt->execute([$user_id]);
    $colisLivres = $stmt->fetch()['total'];
    
    // Colis récents du client avec informations complètes
    $stmt = $conn->prepare("
        SELECT 
            id,
            tracking_number,
            description,
            status,
            weight,
            destination,
            shipping_cost,
            total_cost,
            current_location,
            created_at,
            updated_at
        FROM colis 
        WHERE user_id = ? 
        ORDER BY updated_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $colisRecents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Notifications non lues
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Erreur dashboard client: " . $e->getMessage());
    $totalColis = $colisEnTransit = $colisLivres = 0;
    $colisRecents = [];
    $notifications = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace Client - Smartcore Express</title>
    
    <meta name="description" content="Gérez vos colis et suivez vos livraisons Smartcore Express en temps réel">
    
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
    <script src="../pwa-global.js" defer></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <?php if (isset($_GET['profile_updated'])): ?>
        <div id="profile-success-alert" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 flex items-center space-x-3">
            <i class="fas fa-check-circle text-xl"></i>
            <span>Votre profil a été mis à jour avec succès !</span>
            <button onclick="closeAlert('profile-success-alert')" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['profile_skipped'])): ?>
        <div id="profile-skipped-alert" class="fixed top-4 right-4 bg-blue-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 flex items-center space-x-3">
            <i class="fas fa-info-circle text-xl"></i>
            <span>Vous pouvez modifier votre profil dans les paramètres.</span>
            <button onclick="closeAlert('profile-skipped-alert')" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    <!-- Navigation -->
    <nav class="bg-white shadow-lg relative">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="../img/Logo.png" alt="Smartcore Express" class="h-10 w-auto mr-3">
                    <span class="text-xl font-bold text-primary">Smartcore Express</span>
                </div>
                
                <div class="hidden md:flex items-center space-x-6">
                    <a href="dashboard.php" class="text-primary font-medium border-b-2 border-primary pb-1">Tableau de Bord</a>
                    <a href="../track.php" class="text-gray-600 hover:text-primary transition">Suivi Colis</a>
                    <a href="mes_colis.php" class="block py-2 text-gray-600">Mes Colis</a>
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
                <a href="dashboard.php" class="block py-2 text-primary font-medium">Tableau de Bord</a>
                <a href="../track.php" class="block py-2 text-gray-600">Suivi Colis</a>
                <a href="mes_colis.php" class="block py-2 text-gray-600">Mes Colis</a>
                <a href="achat_online.php" class="block py-2 text-gray-600">Achat en Ligne</a>
                <a href="profile.php" class="block py-2 text-gray-600">Mon Profil</a>
                <a href="settings.php" class="block py-2 text-gray-600">Paramètres</a>
                <hr class="my-2">
                <a href="../auth/logout.php" class="block py-2 text-red-600">Déconnexion</a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                Bienvenue, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!
            </h1>
            <p class="text-gray-600">Gérez vos colis et suivez vos livraisons en temps réel.</p>
        </div>
        

        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="mes_colis.php" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-box text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Colis</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalColis; ?></p>
                    </div>
                </div>
            </a>
            
            <a href="mes_colis.php?status=En%20transit" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-truck text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Actifs</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $colisEnTransit; ?></p>
                    </div>
                </div>
            </a>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Livrés</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $colisLivres; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Packages -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Mes Colis Récents</h3>
                    <p class="text-sm text-gray-600 mt-1">Vos 5 derniers colis avec statuts mis à jour</p>
                </div>
                <div class="p-6">
                    <?php if (empty($colisRecents)): ?>
                    <div class="text-center py-8">
                        <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-box-open text-2xl text-gray-400"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Aucun colis pour le moment</h4>
                        <p class="text-gray-500 mb-4">Vous n'avez pas encore de colis enregistrés</p>
                        <a href="#contact-support" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition duration-200" onclick="showContactInfo()">
                            <i class="fas fa-headset mr-2"></i>
                            Contacter le support
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach($colisRecents as $colis): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md hover:border-primary/20 transition-all duration-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <!-- Numéro de suivi -->
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-barcode text-primary mr-2"></i>
                                        <h4 class="font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($colis['tracking_number'] ?? 'N/A'); ?>
                                        </h4>
                                    </div>
                                    
                                    <!-- Description -->
                                    <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                        <?php 
                                        $description = $colis['description'] ?? 'Aucune description';
                                        echo htmlspecialchars(strlen($description) > 60 ? substr($description, 0, 60) . '...' : $description);
                                        ?>
                                    </p>
                                    
                                    <!-- Statut -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <?php 
                                            $status = $colis['status'] ?? 'Reçue à entrepôt';
                                            $statusColor = getStatusColor($status);
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                                <span class="w-2 h-2 rounded-full bg-current mr-1.5"></span>
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Lien Voir plus -->
                                        <a href="colis_details.php?id=<?php echo $colis['id'] ?? 0; ?>" 
                                           class="inline-flex items-center text-primary hover:text-blue-700 text-sm font-medium transition-colors duration-200">
                                            <span>Voir plus</span>
                                            <i class="fas fa-chevron-right ml-1 text-xs"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Date -->
                                <div class="text-right ml-4">
                                    <div class="text-xs text-gray-500">
                                        <?php 
                                        if (!empty($colis['updated_at'])) {
                                            echo date('d/m/Y', strtotime($colis['updated_at']));
                                        } elseif (!empty($colis['created_at'])) {
                                            echo date('d/m/Y', strtotime($colis['created_at']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <?php 
                                        if (!empty($colis['updated_at'])) {
                                            echo date('H:i', strtotime($colis['updated_at']));
                                        } elseif (!empty($colis['created_at'])) {
                                            echo date('H:i', strtotime($colis['created_at']));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informations supplémentaires -->
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <div class="flex justify-between items-center text-xs text-gray-500">
                                    
                                    <span>
                                        <i class="fas fa-weight mr-1"></i>
                                        <?php echo htmlspecialchars($colis['weight'] ?? '0'); ?> kg
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Lien vers tous les colis -->
                    <div class="mt-6 pt-4 border-t border-gray-100 text-center">
                        <a href="mes_colis.php" class="inline-flex items-center text-primary hover:text-blue-700 font-medium transition-colors duration-200">
                            <span>Voir tous mes colis</span>
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions Rapides -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Actions Rapides</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 gap-4">
                        <a href="achat_online.php" class="flex items-center p-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-shopping-cart mr-3"></i>
                            <span>Achat en Ligne</span>
                        </a>
                        <a href="../track.php" class="flex items-center p-4 bg-secondary text-white rounded-lg hover:bg-orange-600 transition">
                            <i class="fas fa-search mr-3"></i>
                            <span>Suivre un Colis</span>
                        </a>
                        <a href="../calculate.php" class="flex items-center p-4 bg-accent text-white rounded-lg hover:bg-green-600 transition">
                            <i class="fas fa-calculator mr-3"></i>
                            <span>Calculer Tarif</span>
                        </a>
                        <div class="bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            <div class="flex items-center p-4 cursor-pointer" onclick="toggleSupportWidget()">
                                <i class="fas fa-headset mr-3"></i>
                                <span>Support</span>
                            </div>
                            <div id="support-widget" class="hidden p-4 border-t border-purple-500">
                                <input type="text" id="message" placeholder="Écrivez votre message ici" class="border p-2 w-full mb-2 rounded">
                                <button onclick="sendMessage()" class="bg-green-500 text-white p-2 w-full rounded hover:bg-green-600 transition">Envoyer</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
    
    <!-- Modal pour les détails du colis -->
    <div id="packageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header du modal -->
                <div class="flex justify-between items-center pb-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Détails du Colis</h3>
                    <button onclick="closePackageModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Contenu du modal -->
                <div id="packageModalContent" class="mt-4">
                    <div class="flex justify-center items-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                        <span class="ml-2 text-gray-600">Chargement...</span>
                    </div>
                </div>
                
                <!-- Footer du modal -->
                <div class="flex justify-end pt-4 border-t mt-4">
                    <button onclick="closePackageModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="flex flex-col justify-center items-center text-center">
                <div class="flex items-center mb-4">
                    <img src="../img/Logo.png" alt="Smartcore Express" class="h-8 w-auto mr-2">
                    <span class="text-gray-600">© 2024 Smartcore Express. Tous droits réservés.</span>
                </div>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="../contact.html" class="text-gray-600 hover:text-primary transition">Contact</a>
                    <a href="../faq.html" class="text-gray-600 hover:text-primary transition">FAQ</a>
                    <a href="../conditions-utilisation.html" class="text-gray-600 hover:text-primary transition">Conditions d'utilisation</a>
                    <a href="../politique-confidentialite.html" class="text-gray-600 hover:text-primary transition">Politique de confidentialité</a>
                    <a href="support.php" class="text-gray-600 hover:text-primary transition">Support</a>
                </div>
            </div>
        </div>
    </footer>
    


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
        
        // Gestion des notifications
        document.getElementById('notificationButton').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
        });
        
        // Fermer le dropdown des notifications en cliquant ailleurs
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const button = document.getElementById('notificationButton');
            
            if (!dropdown.contains(e.target) && !button.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
        
        // Fonction pour afficher les informations de contact
        function showContactInfo() {
            document.getElementById('contactModal').classList.remove('hidden');
        }
        
        function closeContactModal() {
            document.getElementById('contactModal').classList.add('hidden');
        }
    </script>
    
    <!-- Modal de Contact Support -->
    <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Contacter le Support</h3>
                    <button onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <h4 class="font-semibold text-blue-800">Information Importante</h4>
                            </div>
                            <p class="text-blue-700 text-sm">
                                Seul le service administrateur peut ajouter des colis. Pour envoyer un colis, veuillez contacter notre équipe support.
                            </p>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <i class="fas fa-phone text-primary mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-800">Téléphone</p>
                                    <p class="text-gray-600">+509 1234-5678</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <i class="fas fa-envelope text-primary mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-800">Email</p>
                                    <p class="text-gray-600">support@smartcore-express.com</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <i class="fas fa-clock text-primary mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-800">Heures d'ouverture</p>
                                    <p class="text-gray-600">Lun-Ven: 8h00-17h00</p>
                                    <p class="text-gray-600">Sam: 8h00-12h00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button onclick="closeContactModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Détails de Notification -->
    <div id="notificationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Détails de la Notification</h3>
                    <button onclick="closeNotificationModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                    <div class="flex items-start mb-4">
                        <div class="flex-shrink-0 mr-4">
                            <i id="modalNotificationIcon" class="fas fa-info-circle text-blue-500 text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <h4 id="modalNotificationTitle" class="text-lg font-semibold text-gray-800 mb-2"></h4>
                            <div id="modalNotificationMessage" class="text-gray-600 mb-4 leading-relaxed"></div>
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-clock mr-2"></i>
                                <span id="modalNotificationDate"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between items-center p-6 border-t bg-gray-50">
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Cette notification sera marquée comme lue
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="closeNotificationModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                            Fermer
                        </button>
                        <button onclick="markAsReadAndClose()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition">
                            Marquer comme lu
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Fermer la modal de contact en cliquant à l'extérieur
        document.getElementById('contactModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeContactModal();
            }
        });
        
        // Fermer la modal de notification en cliquant à l'extérieur
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotificationModal();
            }
        });
        
        let currentNotificationId = null;
         
         // Fonction pour afficher les détails d'une notification
         function showNotificationDetails(notificationId) {
             const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
             if (!notificationElement) return;
             
             currentNotificationId = notificationId;
             
             // Récupérer les données de la notification
             const title = notificationElement.getAttribute('data-notification-title');
             const message = notificationElement.getAttribute('data-notification-message');
             const type = notificationElement.getAttribute('data-notification-type');
             const date = notificationElement.getAttribute('data-notification-date');
             
             // Remplir la modal avec les détails
             document.getElementById('modalNotificationTitle').textContent = title;
             document.getElementById('modalNotificationMessage').textContent = message;
             document.getElementById('modalNotificationDate').textContent = date;
             
             // Définir l'icône selon le type
             const iconElement = document.getElementById('modalNotificationIcon');
             iconElement.className = 'fas text-2xl ';
             switch(type) {
                 case 'success':
                     iconElement.classList.add('fa-check-circle', 'text-green-500');
                     break;
                 case 'warning':
                     iconElement.classList.add('fa-exclamation-triangle', 'text-yellow-500');
                     break;
                 case 'error':
                     iconElement.classList.add('fa-times-circle', 'text-red-500');
                     break;
                 default:
                     iconElement.classList.add('fa-info-circle', 'text-blue-500');
             }
             
             // Afficher la modal
             document.getElementById('notificationModal').classList.remove('hidden');
         }
         
         // Fonction pour fermer la modal de notification
         function closeNotificationModal() {
             document.getElementById('notificationModal').classList.add('hidden');
             currentNotificationId = null;
         }
         
         // Fonction pour marquer comme lu et fermer
         function markAsReadAndClose() {
             if (currentNotificationId) {
                 markNotificationAsRead(currentNotificationId);
                 closeNotificationModal();
             }
         }
         
         // Fonction pour marquer une notification comme lue
         function markNotificationAsRead(notificationId) {
             fetch('mark_notification_read.php', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                 },
                 body: JSON.stringify({
                     notification_id: notificationId
                 })
             })
             .then(response => response.json())
             .then(data => {
                 if (data.success) {
                     // Marquer visuellement la notification comme lue
                     const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                     if (notificationElement) {
                         notificationElement.style.opacity = '0.6';
                         notificationElement.style.backgroundColor = '#f9fafb';
                         
                         // Changer l'icône pour indiquer qu'elle est lue
                         const eyeIcon = notificationElement.querySelector('.fa-eye');
                         if (eyeIcon) {
                             eyeIcon.classList.remove('fa-eye');
                             eyeIcon.classList.add('fa-check');
                             eyeIcon.classList.add('text-green-500');
                             eyeIcon.classList.remove('text-gray-400');
                             eyeIcon.title = 'Notification lue';
                         }
                     }
                     
                     // Mettre à jour le compteur de notifications
                     const notificationCount = document.querySelector('.notification-count');
                     if (notificationCount) {
                         let currentCount = parseInt(notificationCount.textContent);
                         if (currentCount > 0) {
                             currentCount--;
                             notificationCount.textContent = currentCount;
                             if (currentCount === 0) {
                                 notificationCount.style.display = 'none';
                             }
                         }
                     }
                 } else {
                     console.error('Erreur lors du marquage de la notification:', data.message);
                 }
             })
             .catch(error => {
                 console.error('Erreur:', error);
             });
         }
        
        // Fonctions pour le widget de support
        function toggleSupportWidget() {
            const widget = document.getElementById('support-widget');
            widget.classList.toggle('hidden');
        }
        
        function sendMessage() {
            const msg = document.getElementById("message").value;
            const number = "50940035664"; // ton numéro
            const url = `https://wa.me/${number}?text=${encodeURIComponent(msg)}`;
            window.open(url, "_blank");
            document.getElementById("message").value = "";
            document.getElementById('support-widget').classList.add('hidden');
        }
        
        // Fonction pour ouvrir le modal des détails du colis
        function openPackageModal(packageId) {
            if (!packageId || packageId === 0) {
                alert('ID de colis invalide');
                return;
            }
            
            // Stocker l'ID pour le rafraîchissement automatique
            window.currentPackageId = packageId;
            
            // Afficher le modal
            document.getElementById('packageModal').classList.remove('hidden');
            
            // Réinitialiser le contenu avec le loader
            document.getElementById('packageModalContent').innerHTML = `
                <div class="flex justify-center items-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                    <span class="ml-2 text-gray-600">Chargement...</span>
                </div>
            `;
            
            // Charger les détails du colis
            fetch(`get_package_details.php?id=${packageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPackageDetails(data.package, data.tracking_history);
                    } else {
                        document.getElementById('packageModalContent').innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-4"></i>
                                <p class="text-red-600">Erreur: ${data.error || 'Impossible de charger les détails du colis'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('packageModalContent').innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-4"></i>
                            <p class="text-red-600">Erreur de connexion. Veuillez réessayer.</p>
                        </div>
                    `;
                });
        }
        
        // Fonction pour fermer le modal
        function closePackageModal() {
            document.getElementById('packageModal').classList.add('hidden');
        }
        

        
        // Fermer le modal en cliquant à l'extérieur
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('packageModal');
            if (event.target === modal) {
                closePackageModal();
            }
        });
        
        // Fermer le modal avec la touche Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('packageModal');
                if (!modal.classList.contains('hidden')) {
                    closePackageModal();
                }
            }
        });

        // Initialiser le gestionnaire de session pour ce dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Le gestionnaire de session est déjà initialisé par session_activity.js
            console.log('Dashboard client - Gestionnaire de session actif');
            
            // Initialiser la gestion PWA
            initPWAFeatures();
        });
        
        // Fonctions PWA
        function initPWAFeatures() {
            // Vérifier si l'app est déjà installée
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                console.log('PWA: Application déjà installée');
                return;
            }
            
            // Afficher la bannière PWA après un délai
            setTimeout(() => {
                const banner = document.getElementById('pwa-banner');
                const dismissed = localStorage.getItem('pwa-banner-dismissed');
                
                if (banner && !dismissed) {
                    banner.classList.remove('hidden');
                    banner.style.opacity = '0';
                    banner.style.transform = 'translateY(-20px)';
                    
                    // Animation d'apparition
                    setTimeout(() => {
                        banner.style.transition = 'all 0.5s ease-out';
                        banner.style.opacity = '1';
                        banner.style.transform = 'translateY(0)';
                    }, 100);
                }
            }, 3000);
            
            // Gestionnaire pour le bouton d'installation
            const installBtn = document.getElementById('install-pwa-btn');
            if (installBtn) {
                installBtn.addEventListener('click', () => {
                    if (window.smartcorePWA) {
                        window.smartcorePWA.installApp();
                        hidePWABanner();
                    }
                });
            }
            
            // Gestionnaire pour fermer la bannière
            const dismissBtn = document.getElementById('dismiss-pwa-banner');
            if (dismissBtn) {
                dismissBtn.addEventListener('click', () => {
                    hidePWABanner();
                    localStorage.setItem('pwa-banner-dismissed', 'true');
                });
            }
        }
        
        function hidePWABanner() {
            const banner = document.getElementById('pwa-banner');
            if (banner) {
                banner.style.transition = 'all 0.3s ease-in';
                banner.style.opacity = '0';
                banner.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    banner.classList.add('hidden');
                }, 300);
            }
        }
        
        // Fonction pour partager un colis
        function sharePackage(trackingNumber) {
            const title = 'Suivi de colis Smartcore Express';
            const text = `Suivez votre colis ${trackingNumber} avec Smartcore Express`;
            const url = `${window.location.origin}/Smartcore_Express/track.php?tracking=${trackingNumber}`;
            
            if (window.smartcorePWA) {
                window.smartcorePWA.shareContent(title, text, url);
            }
        }
        
        // Fonction pour fermer les alertes
        function closeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.transition = 'all 0.3s ease-out';
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(100%)';
                
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }
        }
        
        // Auto-fermer les alertes après 5 secondes
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = ['profile-success-alert', 'profile-skipped-alert'];
            alerts.forEach(alertId => {
                const alert = document.getElementById(alertId);
                if (alert) {
                    setTimeout(() => {
                        closeAlert(alertId);
                    }, 5000);
                }
            });
        });
    </script>
</body>
</html>