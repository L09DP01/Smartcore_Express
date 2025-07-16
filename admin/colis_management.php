<?php
require_once '../auth/session_manager.php';
require_once '../config/database.php';
require_once '../includes/email_notifications.php';

// Initialiser la connexion à la base de données
$conn = getDBConnection();

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Récupérer les messages de session
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';

// Nettoyer les messages de session après affichage
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    unset($_SESSION['error']);
}

// Récupérer les informations de l'utilisateur connecté
try {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, profile_photo FROM users WHERE id = ?");
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
    error_log("Erreur récupération utilisateur: " . $e->getMessage());
}

// Fonction pour calculer le coût total
function calculateTotalCost($weight) {
    $rate_per_lb = 4.5;
    $shipping_cost = 10.0;
    
    // Calcul de base
    $base_cost = $weight * $rate_per_lb;
    
    // Augmentation de 10% si le poids dépasse 10 lb
    if ($weight > 10) {
        $base_cost *= 1.1;
    }
    
    return $base_cost + $shipping_cost;
}



// Traitement du formulaire d'ajout de colis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_package'])) {
    try {
        $user_id = intval($_POST['user_id']);
        $weight = floatval($_POST['weight']);
        $destination = trim($_POST['destination']);
        $description = trim($_POST['description']);
        $instructions = trim($_POST['instructions']);
        
        // Validation
        if (empty($user_id) || $user_id <= 0) {
            $error = 'Veuillez sélectionner un client valide.';
        } elseif (empty($weight) || $weight <= 0) {
            $error = 'Le poids doit être supérieur à 0.';
        } elseif (empty($destination)) {
            $error = 'La destination est requise.';
        } elseif (empty($description)) {
            $error = 'La description est requise.';
        } else {
            // Vérifier que l'utilisateur existe
            $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? AND role = 'client'");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'Client introuvable.';
            } else {
                // Générer numéro de suivi unique
                do {
                    $tracking_number = generateTrackingNumber();
                    $stmt = $conn->prepare("SELECT id FROM colis WHERE tracking_number = ?");
                    $stmt->execute([$tracking_number]);
                } while ($stmt->fetch());
                
                // Calculer le coût total
                $total_cost = calculateTotalCost($weight);
                
                // Insérer le colis
                $stmt = $conn->prepare("
                    INSERT INTO colis (
                        user_id, tracking_number, weight, destination, 
                        description, instructions, total_cost, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $user_id,
                    $tracking_number,
                    $weight,
                    $destination,
                    $description,
                    $instructions,
                    $total_cost,
                    $_SESSION['user_id']
                ]);
                
                $colis_id = $conn->lastInsertId();
                
                // Ajouter une entrée dans l'historique de suivi
                $stmt = $conn->prepare("
                    INSERT INTO tracking_updates (colis_id, status, location, description, created_by)
                    VALUES (?, 'En attente', 'Entrepôt Miami', 'Colis reçu et en attente de traitement', ?)
                ");
                $stmt->execute([$colis_id, $_SESSION['user_id']]);
                
                // Créer une notification pour le client
                $stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, colis_id, title, message, type)
                    VALUES (?, ?, 'Nouveau colis ajouté', ?, 'info')
                ");
                $notification_message = "Votre colis #{$tracking_number} a été ajouté au système. Coût total: $" . number_format($total_cost, 2);
                $stmt->execute([$user_id, $colis_id, $notification_message]);
                
                // Envoyer un email de notification
                $user_name = $user['first_name'] . ' ' . $user['last_name'];
                $email_sent = sendNewPackageNotification(
                    $user['email'], 
                    $user_name, 
                    $tracking_number, 
                    $total_cost, 
                    $description
                );
                
                $success = "Colis ajouté avec succès! Numéro de suivi: {$tracking_number}. Coût total: $" . number_format($total_cost, 2);
                if ($email_sent) {
                    $success .= " Email de notification envoyé au client.";
                } else {
                    $success .= " (Erreur lors de l'envoi de l'email de notification)";
                }
                
                // Réinitialiser le formulaire
                $_POST = [];
            }
        }
    } catch (Exception $e) {
        error_log("Erreur ajout colis: " . $e->getMessage());
        $error = 'Erreur lors de l\'ajout du colis. Veuillez réessayer.';
    }
}

// Récupérer la liste des clients
try {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE role = 'client' AND is_active = 1 ORDER BY first_name, last_name");
    $stmt->execute();
    $clients = $stmt->fetchAll();
} catch (Exception $e) {
    $clients = [];
    error_log("Erreur récupération clients: " . $e->getMessage());
}

// Récupérer les colis récents
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.tracking_number, c.weight, c.destination, c.description, 
               c.status, c.total_cost, c.created_at, 
               u.first_name, u.last_name, u.email
        FROM colis c
        JOIN users u ON c.user_id = u.id
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_packages = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_packages = [];
    error_log("Erreur récupération colis récents: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Colis - Admin Smartcore Express</title>
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
    <link rel="stylesheet" href="../css/theme.css">\n    <link rel="stylesheet" href="../css/admin-responsive.css">
    <script src="../js/theme.js"></script>\n    <script src="../js/admin-responsive.js"></script>
    <script src="../js/session_activity.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .cost-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }
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
                <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="colis_management.php" class="flex items-center px-6 py-3 bg-primary text-white border-r-4 border-secondary">
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
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Colis</h1>
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
                                    <p class="text-sm font-medium text-gray-800"><?php echo $user ? htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) : 'Utilisateur'; ?></p>
                                    <p class="text-xs text-gray-500">Admin</p>
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
                                <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-3"></i>Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
        <!-- Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Formulaire d'ajout de colis -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                    Ajouter un Nouveau Colis
                </h2>
                
                <form method="POST" id="packageForm" class="space-y-4">
                    <!-- Sélection du client -->
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Client <span class="text-red-500">*</span>
                        </label>
                        <select name="user_id" id="user_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Sélectionner un client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $client['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name'] . ' (' . $client['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Poids -->
                    <div>
                        <label for="weight" class="block text-sm font-medium text-gray-700 mb-2">
                            Poids (lb) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="weight" id="weight" step="0.1" min="0.1" required
                               value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Ex: 2.5">
                    </div>

                    <!-- Destination -->
                    <div>
                        <label for="destination" class="block text-sm font-medium text-gray-700 mb-2">
                            Destination <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="destination" id="destination" required
                               value="<?php echo htmlspecialchars($_POST['destination'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Ex: Port-au-Prince, Haïti">
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <textarea name="description" id="description" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Décrivez le contenu du colis..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Instructions -->
                    <div>
                        <label for="instructions" class="block text-sm font-medium text-gray-700 mb-2">
                            Instructions spéciales
                        </label>
                        <textarea name="instructions" id="instructions" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Instructions particulières pour la livraison..."><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                    </div>

                    <!-- Affichage du coût calculé -->
                    <div id="costDisplay" class="cost-display hidden">
                        <h3 class="text-lg font-semibold mb-2">Coût Calculé</h3>
                        <div id="costBreakdown"></div>
                        <div class="text-xl font-bold mt-2" id="totalCost"></div>
                    </div>

                    <button type="submit" name="add_package" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-plus mr-2"></i> Ajouter le Colis
                    </button>
                </form>
            </div>

            <!-- Colis récents -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-clock text-green-600 mr-2"></i>
                    Colis Récents
                </h2>
                
                <?php if (empty($recent_packages)): ?>
                    <p class="text-gray-500 text-center py-8">Aucun colis trouvé.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_packages as $package): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($package['tracking_number']); ?></h3>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($package['first_name'] . ' ' . $package['last_name']); ?></p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            <?php echo htmlspecialchars($package['status'] ?? 'En attente'); ?>
                                        </span>
                                        <button onclick="openStatusModal(<?php echo $package['id']; ?>, '<?php echo htmlspecialchars($package['tracking_number']); ?>', '<?php echo htmlspecialchars($package['status'] ?? 'En attente'); ?>')" class="text-blue-600 hover:text-blue-700 p-1" title="Mettre à jour le statut">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-700 mb-2"><?php echo htmlspecialchars($package['description'] ?? ''); ?></p>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span><?php echo htmlspecialchars($package['weight'] ?? '0'); ?> lb</span>
                                    <span>$<?php echo number_format($package['total_cost'] ?? 0, 2); ?></span>
                                    <span><?php echo $package['created_at'] ? date('d/m/Y', strtotime($package['created_at'])) : '-'; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Calculateur de coût en temps réel
        function calculateCost() {
            const weight = parseFloat(document.getElementById('weight').value) || 0;
            const ratePerLb = 4.5;
            const shippingCost = 10.0;
            
            if (weight > 0) {
                let baseCost = weight * ratePerLb;
                let surcharge = 0;
                
                if (weight > 10) {
                    surcharge = baseCost * 0.1;
                    baseCost *= 1.1;
                }
                
                const totalCost = baseCost + shippingCost;
                
                // Afficher le détail du calcul
                let breakdown = `
                    <div class="text-sm">
                        <div>Poids: ${weight} lb × $${ratePerLb}/lb = $${(weight * ratePerLb).toFixed(2)}</div>
                        ${weight > 10 ? `<div>Surcharge (+10%): $${surcharge.toFixed(2)}</div>` : ''}
                        <div>Frais d'expédition: $${shippingCost.toFixed(2)}</div>
                    </div>
                `;
                
                document.getElementById('costBreakdown').innerHTML = breakdown;
                document.getElementById('totalCost').innerHTML = `Total: $${totalCost.toFixed(2)}`;
                document.getElementById('costDisplay').classList.remove('hidden');
            } else {
                document.getElementById('costDisplay').classList.add('hidden');
            }
        }
        
        // Écouter les changements de poids
        document.getElementById('weight').addEventListener('input', calculateCost);
        
        // Calculer au chargement si une valeur existe
        document.addEventListener('DOMContentLoaded', calculateCost);
        
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
            </main>
        </div>
    </div>
    
    <!-- Modal pour la mise à jour du statut -->
    <div id="" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
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