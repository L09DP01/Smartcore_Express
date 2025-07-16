<?php
// Inclure le gestionnaire de session avec gestion d'inactivité
require_once '../auth/session_manager.php';
require_once '../config/database.php';

// La vérification de session est maintenant gérée par session_manager.php

$conn = getDBConnection();
$message = '';
$error = '';
$user = null;

// Récupérer les informations de l'utilisateur
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: ../auth/logout.php');
        exit();
    }
} catch(PDOException $e) {
    error_log("Erreur récupération utilisateur: " . $e->getMessage());
    $error = 'Erreur lors de la récupération des informations.';
}

// La page profil affiche seulement les informations, les modifications se font dans les paramètres

// Récupérer les statistiques du compte
$stats = [
    'total_packages' => 0,
    'active_packages' => 0,
    'delivered_packages' => 0,
    'total_spent' => 0
];

try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_packages,
            SUM(CASE WHEN status NOT IN ('Livré', 'Annulé') THEN 1 ELSE 0 END) as active_packages,
            SUM(CASE WHEN status = 'Livré' THEN 1 ELSE 0 END) as delivered_packages,
            SUM(shipping_cost) as total_spent
        FROM colis 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
    
    if (!$stats['total_spent']) {
        $stats['total_spent'] = 0;
    }
} catch(PDOException $e) {
    error_log("Erreur statistiques profil: " . $e->getMessage());
}

// Récupérer les notifications non lues
$notifications = [];
try {
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Erreur récupération notifications: " . $e->getMessage());
    $notifications = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Smartcore Express</title>
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
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .form-section {
            transition: all 0.3s ease;
        }
        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
    <link rel="icon" type="image/png" href="logo.png">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="../img/Logo.png" alt="Smartcore Express" class="h-10 w-auto mr-3">
                    <span class="text-xl font-bold text-primary">Smartcore Express</span>
                </div>
                
                <div class="hidden md:flex items-center space-x-6">
                    <a href="dashboard.php" class="text-gray-600 hover:text-primary transition">Tableau de Bord</a>
                    <a href="../track.php" class="text-gray-600 hover:text-primary transition">Suivi Colis</a>
                    <a href="mes_colis.php" class="text-gray-600 hover:text-primary transition">Mes Colis</a>
                    <a href="achat_online.php" class="text-gray-600 hover:text-primary transition">Achat en Ligne</a>
                    <a href="profile.php" class="text-primary font-medium border-b-2 border-primary pb-1">Mon Profil</a>
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
        <div id="mobile-menu" class="md:hidden hidden bg-white border-t">
            <div class="px-4 py-2 space-y-2">
                <a href="dashboard.php" class="block py-2 text-gray-600">Tableau de Bord</a>
                <a href="../track.php" class="block py-2 text-gray-600">Suivi Colis</a>
                <a href="mes_colis.php" class="block py-2 text-gray-600">Mes Colis</a>
                <a href="achat_online.php" class="block py-2 text-gray-600">Achat en Ligne</a>
                <a href="profile.php" class="block py-2 text-primary font-medium">Mon Profil</a>
                <a href="settings.php" class="block py-2 text-gray-600">Paramètres</a>
                <hr class="my-2">
                <a href="../auth/logout.php" class="block py-2 text-red-600">Déconnexion</a>
            </div>
        </div>
    </nav>
    
    <!-- Header -->
    <header class="bg-gradient-to-r from-primary to-blue-600 text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-white bg-opacity-20 mr-4">
                    <?php if (isset($user['profile_photo']) && !empty($user['profile_photo']) && file_exists('../' . $user['profile_photo'])): ?>
                        <img src="../<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                             alt="Photo de profil" 
                             class="w-16 h-16 rounded-full object-cover border-4 border-white border-opacity-50">
                    <?php else: ?>
                        <i class="fas fa-user text-3xl"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-3xl font-bold">Mon Profil</h1>
                    <p class="text-blue-100 mt-1">Gérez vos informations personnelles et paramètres</p>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Messages -->
    <?php if ($message): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Contenu Principal -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Statistiques du Compte -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar text-primary mr-2"></i>
                        Statistiques du Compte
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-boxes text-blue-600 mr-3"></i>
                                <span class="text-sm font-medium text-gray-700">Total Colis</span>
                            </div>
                            <span class="text-xl font-bold text-blue-600"><?php echo $stats['total_packages']; ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-truck text-yellow-600 mr-3"></i>
                                <span class="text-sm font-medium text-gray-700">Actifs</span>
                            </div>
                            <span class="text-xl font-bold text-yellow-600"><?php echo $stats['active_packages']; ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-sm font-medium text-gray-700">Livrés</span>
                            </div>
                            <span class="text-xl font-bold text-green-600"><?php echo $stats['delivered_packages']; ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-dollar-sign text-purple-600 mr-3"></i>
                                <span class="text-sm font-medium text-gray-700">Total Dépensé</span>
                            </div>
                            <span class="text-xl font-bold text-purple-600"><?php echo number_format($stats['total_spent'], 2); ?> USD</span>
                        </div>
                    </div>
                </div>
                
                <!-- Informations du Compte -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle text-primary mr-2"></i>
                        Informations du Compte
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Membre depuis:</span>
                            <span class="font-medium"><?php echo formatDate($user['created_at'], 'd/m/Y'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Dernière connexion:</span>
                            <span class="font-medium">
                                <?php 
                                if (!empty($user['last_login']) && $user['last_login'] !== '0000-00-00 00:00:00') {
                                    echo formatDate($user['last_login'], 'd/m/Y H:i');
                                } else {
                                    echo 'Première connexion';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Statut:</span>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Type de compte:</span>
                            <span class="font-medium capitalize"><?php echo $user['role']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Adresses de Livraison -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Mon Adresse de Livraison USA -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-flag-usa text-primary mr-2"></i>
                            Mon Adresse de Livraison USA
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Adresse de notre entrepôt aux États-Unis pour vos achats</p>
                    </div>
                    
                    <div class="p-6 bg-blue-50">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start">
                                    <i class="fas fa-user text-blue-600 mr-3 mt-1"></i>
                                    <div>
                                        <p class="font-semibold text-gray-800" id="fullName"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                    </div>
                                </div>
                                <button onclick="copyToClipboard('fullName')" class="text-blue-600 hover:text-blue-800 transition p-1" title="Copier le nom">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            
                            <div class="flex items-start">
                                <i class="fas fa-map-marker-alt text-blue-600 mr-3 mt-1"></i>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-gray-800" id="address1"><strong>Adresse :</strong> 8298 Nw 68th St</p>
                                        <button onclick="copyToClipboard('address1', '8298 Nw 68th St')" class="text-blue-600 hover:text-blue-800 transition p-1 ml-2" title="Copier l'adresse">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-gray-800" id="address2"><strong>Adresse 2 :</strong> PQ-067720</p>
                                        <button onclick="copyToClipboard('address2', 'PQ-067720')" class="text-blue-600 hover:text-blue-800 transition p-1 ml-2" title="Copier l'adresse 2">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-gray-800" id="city"><strong>Ville :</strong> Miami</p>
                                        <button onclick="copyToClipboard('city', 'Miami')" class="text-blue-600 hover:text-blue-800 transition p-1 ml-2" title="Copier la ville">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-gray-800" id="state"><strong>State :</strong> Florida</p>
                                        <button onclick="copyToClipboard('state', 'Florida')" class="text-blue-600 hover:text-blue-800 transition p-1 ml-2" title="Copier l'état">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-gray-800" id="zipcode"><strong>Zip code :</strong> 33166</p>
                                        <button onclick="copyToClipboard('zipcode', '33166')" class="text-blue-600 hover:text-blue-800 transition p-1 ml-2" title="Copier le code postal">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-start justify-between">
                                <div class="flex items-start">
                                    <i class="fas fa-phone text-blue-600 mr-3 mt-1"></i>
                                    <div>
                                        <p class="text-gray-800" id="phone"><strong>Tél :</strong> +1 352-966-5836</p>
                                    </div>
                                </div>
                                <button onclick="copyToClipboard('phone', '+1 352-966-5836')" class="text-blue-600 hover:text-blue-800 transition p-1" title="Copier le téléphone">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            
                            <div class="mt-4 p-3 bg-yellow-100 border border-yellow-300 rounded-md">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-yellow-600 mr-2 mt-1"></i>
                                    <div class="text-sm text-yellow-800">
                                        <p><strong>Important :</strong> Utilisez cette adresse pour tous vos achats en ligne aux États-Unis. Assurez-vous d'inclure votre nom complet exactement comme indiqué ci-dessus.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mon Adresse de Livraison Haïti -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-home text-primary mr-2"></i>
                            Mon Adresse de Livraison Haïti
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Votre adresse de livraison en Haïti</p>
                    </div>
                    
                    <div class="p-6">
                        <?php if (!empty($user['address']) || !empty($user['city']) || !empty($user['country'])): ?>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <i class="fas fa-user text-green-600 mr-3 mt-1"></i>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($user['address'])): ?>
                                <div class="flex items-start">
                                    <i class="fas fa-map-marker-alt text-green-600 mr-3 mt-1"></i>
                                    <div>
                                        <p class="text-gray-800"><strong>Adresse :</strong> <?php echo htmlspecialchars($user['address']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($user['city'])): ?>
                                <div class="flex items-start">
                                    <i class="fas fa-city text-green-600 mr-3 mt-1"></i>
                                    <div>
                                        <p class="text-gray-800"><strong>Ville :</strong> <?php echo htmlspecialchars($user['city']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($user['country'])): ?>
                                <div class="flex items-start">
                                    <i class="fas fa-globe text-green-600 mr-3 mt-1"></i>
                                    <div>
                                        <p class="text-gray-800"><strong>Pays :</strong> <?php echo htmlspecialchars($user['country']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($user['phone'])): ?>
                                <div class="flex items-start">
                                    <i class="fas fa-phone text-green-600 mr-3 mt-1"></i>
                                    <div>
                                        <p class="text-gray-800"><strong>Téléphone :</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-map-marker-alt text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-600 mb-4">Aucune adresse de livraison configurée</p>
                                <a href="settings.php" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-md hover:bg-secondary transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    Ajouter une adresse
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <a href="settings.php" class="inline-flex items-center text-primary hover:text-secondary transition-colors">
                                <i class="fas fa-edit mr-2"></i>
                                Modifier mon adresse de livraison
                            </a>
                        </div>
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
        // Validation du formulaire de changement de mot de passe
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Les nouveaux mots de passe ne correspondent pas.');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Le nouveau mot de passe doit contenir au moins 6 caractères.');
                return false;
            }
        });
        
        // Confirmation visuelle de la correspondance des mots de passe
        const newPasswordField = document.getElementById('new_password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        function checkPasswordMatch() {
            if (confirmPasswordField.value && newPasswordField.value !== confirmPasswordField.value) {
                confirmPasswordField.setCustomValidity('Les mots de passe ne correspondent pas');
                confirmPasswordField.classList.add('border-red-500');
                confirmPasswordField.classList.remove('border-gray-300');
            } else {
                confirmPasswordField.setCustomValidity('');
                confirmPasswordField.classList.remove('border-red-500');
                confirmPasswordField.classList.add('border-gray-300');
            }
        }
        
        newPasswordField.addEventListener('input', checkPasswordMatch);
        confirmPasswordField.addEventListener('input', checkPasswordMatch);
        
        
        // Fonction pour afficher les informations de contact
        function showContactInfo() {
            document.getElementById('contactModal').classList.remove('hidden');
        }
        
        function closeContactModal() {
            document.getElementById('contactModal').classList.add('hidden');
        }
        
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
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
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
    
    <script>
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
        
        // Fonction pour copier du texte dans le presse-papiers
        function copyToClipboard(elementId, customText = null) {
            let textToCopy;
            
            if (customText) {
                textToCopy = customText;
            } else {
                const element = document.getElementById(elementId);
                if (!element) {
                    console.error('Élément non trouvé:', elementId);
                    return;
                }
                textToCopy = element.textContent.trim();
            }
            
            // Utiliser l'API moderne du presse-papiers si disponible
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showCopyNotification('Copié dans le presse-papiers!');
                }).catch(function(err) {
                    console.error('Erreur lors de la copie:', err);
                    fallbackCopyTextToClipboard(textToCopy);
                });
            } else {
                // Fallback pour les navigateurs plus anciens
                fallbackCopyTextToClipboard(textToCopy);
            }
        }
        
        // Fonction de fallback pour la copie
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopyNotification('Copié dans le presse-papiers!');
                } else {
                    showCopyNotification('Erreur lors de la copie', 'error');
                }
            } catch (err) {
                console.error('Erreur lors de la copie:', err);
                showCopyNotification('Erreur lors de la copie', 'error');
            }
            
            document.body.removeChild(textArea);
        }
        
        // Fonction pour afficher une notification de copie
        function showCopyNotification(message, type = 'success') {
            // Supprimer toute notification existante
            const existingNotification = document.getElementById('copy-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Créer la notification
            const notification = document.createElement('div');
            notification.id = 'copy-notification';
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 transition-all duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'
                    } mr-2"></i>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animation d'entrée
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
                notification.style.opacity = '1';
            }, 10);
            
            // Supprimer après 3 secondes
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        // Gestion du menu mobile
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
            
            // Empêcher le déplacement de la page
            if (!mobileMenu.classList.contains('hidden')) {
                document.body.style.overflow = 'hidden';
                mobileMenu.style.position = 'fixed';
                mobileMenu.style.top = '60px';
                mobileMenu.style.left = '0';
                mobileMenu.style.right = '0';
                mobileMenu.style.zIndex = '50';
                mobileMenu.style.maxHeight = 'calc(100vh - 60px)';
                mobileMenu.style.overflowY = 'auto';
            } else {
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>