<?php
// Vérifier si l'utilisateur est connecté
session_start();
require_once 'config/database.php';

$tracking_number = '';
$package = null;
$tracking_history = [];
$error = '';
$user = null;

// Obtenir la connexion à la base de données
$conn = getDBConnection();

// Si l'utilisateur est connecté, récupérer ses informations
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Erreur récupération utilisateur: " . $e->getMessage());
    }
}

// Traitement de la recherche
if (isset($_GET['tracking']) && !empty($_GET['tracking'])) {
    $tracking_number = trim($_GET['tracking']);
    
    try {
        // Rechercher le colis par numéro de suivi
        $stmt = $conn->prepare("SELECT c.*, u.first_name, u.last_name FROM colis c LEFT JOIN users u ON c.user_id = u.id WHERE c.tracking_number = ?");
        $stmt->execute([$tracking_number]);
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
        } else {
            $error = 'Aucun colis trouvé avec ce numéro de suivi.';
        }
    } catch (Exception $e) {
        error_log("Erreur suivi: " . $e->getMessage());
        $error = 'Erreur lors de la recherche du colis.';
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
    <title>Suivi de Colis - Smartcore Express</title>
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
    <link rel="manifest" href="manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="img/Logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Smartcore Express">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileColor" content="#0047AB">
    <meta name="msapplication-TileImage" content="img/Logo.png">
    
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
        .hero-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
    <link rel="icon" type="image/png" href="client/logo.png">
    
    <script src="pwa-global.js" defer></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg relative">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="img/Logo.png" alt="Smartcore Express" class="h-10 w-auto mr-3">
                    <span class="text-xl font-bold text-primary">Smartcore Express</span>
                </div>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Menu pour utilisateur connecté -->
                <div class="hidden md:flex items-center space-x-6">
                    <a href="client/dashboard.php" class="text-gray-600 hover:text-primary transition">Tableau de Bord</a>
                    <a href="track.php" class="text-primary font-medium border-b-2 border-primary pb-1">Suivi Colis</a>
                    <a href="client/mes_colis.php" class="text-gray-600 hover:text-primary transition">Mes Colis</a>
                    <a href="client/profile.php" class="text-gray-600 hover:text-primary transition">Mon Profil</a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Lien Paramètres Desktop -->
                    <a href="client/settings.php" class="flex items-center space-x-2 text-gray-600 hover:text-primary">
                        <?php if ($user && $user['profile_photo']): ?>
                            <img src="<?php echo $user['profile_photo']; ?>" alt="Photo de profil" class="w-8 h-8 rounded-full object-cover border-2 border-gray-200">
                        <?php else: ?>
                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-gray-600">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                        <?php endif; ?>
                        <span>Paramètres</span>
                    </a>
                    <!-- Lien Déconnexion Desktop -->
                    <a href="auth/logout.php" class="flex items-center space-x-2 text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
                
                <!-- Menu mobile -->
                <div class="md:hidden flex items-center space-x-3">
                    <!-- Photo de profil mobile -->
                    <a href="client/settings.php" class="flex items-center">
                        <?php if ($user && $user['profile_photo']): ?>
                            <img src="<?php echo $user['profile_photo']; ?>" alt="Photo de profil" class="w-8 h-8 rounded-full object-cover border-2 border-gray-200">
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
                <?php else: ?>
                <!-- Menu pour utilisateur non connecté -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="index.html" class="text-gray-600 hover:text-primary transition">
                        <i class="fas fa-home mr-1"></i> Accueil
                    </a>
                    <a href="services.html" class="text-gray-600 hover:text-primary transition">
                        <i class="fas fa-truck mr-1"></i> Services
                    </a>
                    <a href="contact.html" class="text-gray-600 hover:text-primary transition">
                        <i class="fas fa-envelope mr-1"></i> Contact
                    </a>
                    <a href="auth/login.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-user mr-1"></i> Mon Compte
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-gray-600 hover:text-primary focus:outline-none focus:text-primary">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Menu mobile -->
        <div id="mobile-menu" class="md:hidden hidden absolute top-full left-0 w-full bg-white shadow-lg z-50">
            <div class="px-4 py-2 space-y-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                <a href="client/dashboard.php" class="block py-2 text-gray-600">Tableau de Bord</a>
                <a href="track.php" class="block py-2 text-primary font-medium">Suivi Colis</a>
                <a href="client/mes_colis.php" class="block py-2 text-gray-600">Mes Colis</a>
                <a href="client/profile.php" class="block py-2 text-gray-600">Mon Profil</a>
                <a href="client/settings.php" class="block py-2 text-gray-600">Paramètres</a>
                <hr class="my-2">
                <a href="auth/logout.php" class="block py-2 text-red-600">Déconnexion</a>
                <?php else: ?>
                <a href="index.html" class="block px-3 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-md transition">
                    <i class="fas fa-home mr-2"></i> Accueil
                </a>
                <a href="services.html" class="block px-3 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-md transition">
                    <i class="fas fa-truck mr-2"></i> Services
                </a>
                <a href="contact.html" class="block px-3 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-md transition">
                    <i class="fas fa-envelope mr-2"></i> Contact
                </a>
                <a href="auth/login.php" class="block px-3 py-2 bg-primary text-white rounded-md hover:bg-blue-700 transition">
                    <i class="fas fa-user mr-2"></i> Mon Compte
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-bg text-white py-16">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-6">
                <i class="fas fa-search mr-3"></i>
                Suivi de Colis
            </h1>
            <p class="text-xl mb-8 opacity-90">
                Entrez votre numéro de suivi pour connaître l'état de votre colis en temps réel
            </p>
            
            <!-- Formulaire de recherche -->
            <form method="GET" class="max-w-md mx-auto">
                <div class="flex">
                    <input type="text" 
                           name="tracking" 
                           value="<?php echo htmlspecialchars($tracking_number); ?>"
                           placeholder="Entrez votre numéro de suivi"
                           class="flex-1 px-4 py-3 text-gray-800 rounded-l-lg border-0 focus:ring-2 focus:ring-blue-300 focus:outline-none"
                           required>
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700 transition focus:ring-2 focus:ring-blue-300 focus:outline-none">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="max-w-6xl mx-auto py-8 px-4">
        <?php if (!empty($error)): ?>
            <!-- Message d'erreur -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-2xl mr-4"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-red-800">Colis non trouvé</h3>
                        <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                        <p class="text-red-600 text-sm mt-2">Vérifiez votre numéro de suivi et réessayez.</p>
                    </div>
                </div>
            </div>
        <?php elseif ($package): ?>
            <!-- Résultats du suivi -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Informations principales -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- En-tête du colis -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($package['tracking_number'] ?? ''); ?>
                                </h2>
                                <p class="text-gray-600">
                                    <i class="fas fa-map-marker-alt mr-2"></i>
                                    Destination: <?php echo htmlspecialchars($package['destination'] ?? ''); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-full border <?php echo getStatusColor($package['status'] ?? ''); ?>">
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
                                        <span class="font-medium"><?php echo ($package['weight'] ?? 0); ?> lb</span>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="font-semibold text-gray-700 mb-3">Expéditeur</h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Nom:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars(($package['first_name'] ?? '') . ' ' . ($package['last_name'] ?? '')); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Date d'envoi:</span>
                                        <span class="font-medium"><?php echo isset($package['created_at']) ? date('d/m/Y', strtotime($package['created_at'])) : ''; ?></span>
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
                    <!-- Informations de livraison -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Informations de Livraison</h3>
                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-gray-600">Destination:</span>
                                <p class="font-medium"><?php echo htmlspecialchars($package['destination'] ?? 'Non spécifiée'); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-600">Localisation actuelle:</span>
                                <p class="font-medium"><?php echo htmlspecialchars($package['current_location'] ?? 'Non disponible'); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-600">Coût total:</span>
                                <p class="font-bold text-green-600">$<?php echo number_format($package['total_cost'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($tracking_number)): ?>
            <!-- Instructions d'utilisation -->
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        Comment utiliser le suivi de colis
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-barcode text-2xl text-blue-600"></i>
                            </div>
                            <h3 class="font-semibold text-gray-800 mb-2">1. Numéro de suivi</h3>
                            <p class="text-gray-600 text-sm">Entrez le numéro de suivi fourni lors de l'expédition de votre colis.</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-search text-2xl text-green-600"></i>
                            </div>
                            <h3 class="font-semibold text-gray-800 mb-2">2. Recherche</h3>
                            <p class="text-gray-600 text-sm">Cliquez sur le bouton de recherche pour obtenir les informations de suivi.</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-route text-2xl text-purple-600"></i>
                            </div>
                            <h3 class="font-semibold text-gray-800 mb-2">3. Suivi en temps réel</h3>
                            <p class="text-gray-600 text-sm">Consultez l'historique complet et le statut actuel de votre colis.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-6xl mx-auto px-4 text-center">
            <div class="flex justify-center items-center mb-4">
                <img src="img/Logo.png" alt="Logo" class="h-8 w-auto mr-2">
                <span class="text-xl font-bold">Smartcore Express</span>
            </div>
            <p class="text-gray-400 mb-4">Service de livraison rapide et fiable</p>
            <div class="flex justify-center space-x-6">
                <a href="index.html" class="text-gray-400 hover:text-white transition">Accueil</a>
                <a href="services.html" class="text-gray-400 hover:text-white transition">Services</a>
                <a href="contact.html" class="text-gray-400 hover:text-white transition">Contact</a>
                <a href="politique-confidentialite.html" class="text-gray-400 hover:text-white transition">Confidentialité</a>
            </div>
            <div class="mt-6 pt-6 border-t border-gray-700">
                <p class="text-gray-400 text-sm">&copy; 2024 Smartcore Express. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript pour le menu mobile -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = mobileMenuButton.querySelector('i');
            
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
                
                // Changer l'icône du bouton
                if (!mobileMenu.classList.contains('hidden')) {
                    menuIcon.classList.remove('fa-bars');
                    menuIcon.classList.add('fa-times');
                } else {
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            });
            
            // Fermer le menu mobile quand on clique sur un lien
            const mobileMenuLinks = mobileMenu.querySelectorAll('a');
            mobileMenuLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    mobileMenu.classList.add('hidden');
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                });
            });
            
            // Fermer le menu mobile quand on redimensionne la fenêtre
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    mobileMenu.classList.add('hidden');
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            });
        });
    </script>
</body>
</html>