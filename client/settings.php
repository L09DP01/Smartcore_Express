<?php
// Inclure
require_once '../config/database.php';
require_once '../auth/session_manager.php';
// La vérification de session est maintenant gérée par session_manager.php

// Obtenir la connexion à la base de données
$pdo = getDBConnection();
if (!$pdo) {
    die('Erreur de connexion à la base de données');
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Récupérer les informations de l'utilisateur d'abord
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des données utilisateur.';
}

// Gérer le message de succès depuis la redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = 'Profil mis à jour avec succès!';
}

// Traitement du formulaire de mise à jour des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        // Gestion de l'upload de photo
        $photo_path = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profile_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Supprimer l'ancienne photo si elle existe
                if (isset($user['profile_photo']) && !empty($user['profile_photo']) && file_exists('../' . $user['profile_photo'])) {
                    unlink('../' . $user['profile_photo']);
                }
                
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    $photo_path = 'uploads/profile_photos/' . $new_filename;
                } else {
                    $error = 'Erreur lors du téléchargement du fichier.';
                }
            } else {
                $error = 'Format de fichier non autorisé. Utilisez JPG, JPEG, PNG ou GIF.';
            }
        } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Gestion des erreurs d'upload
            switch ($_FILES['profile_photo']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = 'Le fichier est trop volumineux.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = 'Le fichier n\'a été que partiellement téléchargé.';
                    break;
                default:
                    $error = 'Erreur lors du téléchargement du fichier.';
            }
        }
        
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error = 'Tous les champs obligatoires doivent être remplis.';
        } else {
            try {
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?";
                $params = [$first_name, $last_name, $email, $phone];
                
                if ($photo_path) {
                    $sql .= ", profile_photo = ?";
                    $params[] = $photo_path;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $user_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Mettre à jour les variables de session
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $_SESSION['email'] = $email;
                if ($photo_path) {
                    $_SESSION['profile_photo'] = $photo_path;
                }
                
                // Recharger les données utilisateur pour afficher la nouvelle photo
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                $message = 'Profil mis à jour avec succès!';
                
                // Redirection pour éviter la resoumission du formulaire
                header('Location: settings.php?success=1');
                exit();
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour du profil.';
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        // Inclure les fonctions d'email de notification
        require_once '../includes/password_change_notification.php';
        
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        // Validation des entrées
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Tous les champs de mot de passe sont obligatoires.';
            logPasswordChangeAttempt($user_id, $user['email'] ?? '', false, 'Champs manquants');
        } elseif ($new_password !== $confirm_password) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
            logPasswordChangeAttempt($user_id, $user['email'] ?? '', false, 'Mots de passe non correspondants');
        } elseif (strlen($new_password) < 6) {
            $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
            logPasswordChangeAttempt($user_id, $user['email'] ?? '', false, 'Mot de passe trop court');
        } elseif ($current_password === $new_password) {
            $error = 'Le nouveau mot de passe doit être différent de l\'ancien.';
            logPasswordChangeAttempt($user_id, $user['email'] ?? '', false, 'Même mot de passe');
        } else {
            try {
                // Vérifier que la connexion à la base de données fonctionne
                if (!$pdo) {
                    throw new Exception('Connexion à la base de données indisponible. Veuillez vérifier que l\'extension PDO MySQL est activée.');
                }
                
                // Vérifier le mot de passe actuel
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $userData = $stmt->fetch();
                
                if (!$userData) {
                    throw new Exception('Utilisateur introuvable.');
                }
                
                if (password_verify($current_password, $userData['password_hash'])) {
                    // Hacher le nouveau mot de passe
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Mettre à jour le mot de passe
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $result = $stmt->execute([$hashed_password, $user_id]);
                    
                    if ($result && $stmt->rowCount() > 0) {
                        // Envoyer l'email de notification
                        $emailSent = sendPasswordChangeNotification(
                            $userData['email'],
                            $userData['first_name'],
                            $userData['last_name']
                        );
                        
                        // Log du succès
                        logPasswordChangeAttempt($user_id, $userData['email'], true);
                        
                        if ($emailSent) {
                            $message = 'Mot de passe modifié avec succès!';
                        } else {
                            $message = 'Mot de passe modifié avec succès! (Email de confirmation non envoyé - vérifiez votre configuration email)';
                        }
                        
                        // Mettre à jour les données utilisateur en session
                        $user = $userData;
                    } else {
                        throw new Exception('Échec de la mise à jour du mot de passe.');
                    }
                } else {
                    $error = 'Mot de passe actuel incorrect.';
                    logPasswordChangeAttempt($user_id, $userData['email'], false, 'Mot de passe actuel incorrect');
                }
                
            } catch (PDOException $e) {
                // Erreur de base de données
                $errorMsg = 'Erreur de base de données';
                if (strpos($e->getMessage(), 'could not find driver') !== false) {
                    $errorMsg = 'Extension PDO MySQL non activée. Contactez l\'administrateur.';
                } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
                    $errorMsg = 'Erreur de connexion à la base de données.';
                }
                
                $error = $errorMsg . ' Veuillez réessayer plus tard.';
                logPasswordChangeAttempt($user_id, $user['email'] ?? '', false, $e->getMessage());
                
                // Log détaillé pour l'administrateur
                error_log("Erreur PDO changement mot de passe - User ID: {$user_id}, Error: " . $e->getMessage());
                
            } catch (Exception $e) {
                // Autres erreurs
                $error = $e->getMessage();
                logPasswordChangeAttempt($user_id, $user['email'] ?? '', false, $e->getMessage());
                error_log("Erreur changement mot de passe - User ID: {$user_id}, Error: " . $e->getMessage());
            }
        }
    }
}

// Traitement du formulaire d'adresse Haïti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_haiti_address'])) {
    $haiti_address = trim($_POST['haiti_address']);
    $haiti_city = trim($_POST['haiti_city']);
    $haiti_department = $_POST['haiti_department'];
    $haiti_postal_code = trim($_POST['haiti_postal_code']);
    $haiti_phone = trim($_POST['haiti_phone']);
    
    // Validation
    if (empty($haiti_address) || empty($haiti_city)) {
        $error = 'L\'adresse et la ville sont obligatoires.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET address = ?, city = ?, country = ?, postal_code = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$haiti_address, $haiti_city, $haiti_department, $haiti_postal_code, $haiti_phone, $_SESSION['user_id']]);
            
            $message = 'Adresse Haïti mise à jour avec succès!';
            
            // Recharger les données utilisateur
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Erreur mise à jour adresse Haïti: " . $e->getMessage());
            $error = 'Erreur lors de la mise à jour de l\'adresse: ' . $e->getMessage();
        }
    }
}

// Les informations utilisateur sont déjà récupérées en haut du fichier
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Smartcore Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="../js/session_activity.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#1E40AF'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="icon" type="image/png" href="../client/logo.png">
</head>
<body class="bg-gray-50 font-sans">
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
                    <a href="mes_colis.php" class="text-gray-600 hover:text-primary transition">Mes Colis</a>
                    <a href="achat_online.php" class="text-gray-600 hover:text-primary transition">Achat en Ligne</a>
                    <a href="profile.php" class="text-gray-600 hover:text-primary transition">Mon Profil</a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Lien Paramètres Desktop -->
                    <a href="settings.php" class="flex items-center space-x-2 text-primary font-medium">
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
                <a href="mes_colis.php" class="block py-2 text-gray-600">Mes Colis</a>
                <a href="achat_online.php" class="block py-2 text-gray-600">Achat en Ligne</a>
                <a href="profile.php" class="block py-2 text-gray-600">Mon Profil</a>
                <a href="settings.php" class="block py-2 text-primary font-medium">Paramètres</a>
                <hr class="my-2">
                <a href="../auth/logout.php" class="block py-2 text-red-600">Déconnexion</a>
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900">
                    <i class="fas fa-cog mr-3 text-primary"></i>Paramètres du compte
                </h2>
                <p class="text-gray-600 mt-1">Gérez vos informations personnelles et paramètres de sécurité</p>
            </div>

            <div class="p-6">
                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Onglets -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="-mb-px flex space-x-8 overflow-x-auto">
                        <button onclick="showTab('profile')" id="profile-tab" class="tab-button active py-2 px-1 border-b-2 border-primary text-primary font-medium text-sm whitespace-nowrap">
                            <i class="fas fa-user mr-2"></i>Informations personnelles
                        </button>
                        <button onclick="showTab('security')" id="security-tab" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
                            <i class="fas fa-shield-alt mr-2"></i>Sécurité
                        </button>

                        <button onclick="showTab('address')" id="address-tab" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
                            <i class="fas fa-map-marker-alt mr-2"></i>Adresse Haïti
                        </button>
                    </nav>
                </div>

                <!-- Onglet Informations personnelles -->
                <div id="profile-content" class="tab-content">
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Prénom *
                                </label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       required>
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nom *
                                </label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       required>
                            </div>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email *
                            </label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   required>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Téléphone
                            </label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <!-- Photo de profil -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Photo de profil
                            </label>
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <?php if (isset($user['profile_photo']) && !empty($user['profile_photo']) && file_exists('../' . $user['profile_photo'])): ?>
                                        <img src="../<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                             alt="Photo de profil" 
                                             class="w-16 h-16 rounded-full object-cover border-2 border-gray-300">
                                    <?php else: ?>
                                        <div class="w-16 h-16 rounded-full bg-primary text-white flex items-center justify-center text-xl font-semibold border-2 border-gray-300">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <input type="file" id="profile_photo" name="profile_photo" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <p class="text-sm text-gray-500 mt-1">Formats acceptés: JPG, JPEG, PNG, GIF (max 5MB)</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="update_profile" 
                                    class="bg-primary text-white px-6 py-2 rounded-md hover:bg-secondary transition-colors">
                                <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Onglet Sécurité -->
                <div id="security-content" class="tab-content hidden">
                    <form method="POST" class="space-y-6">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Mot de passe actuel *
                            </label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   required>
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Nouveau mot de passe *
                            </label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   minlength="6" required>
                            <p class="text-sm text-gray-500 mt-1">Le mot de passe doit contenir au moins 6 caractères</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirmer le nouveau mot de passe *
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   minlength="6" required>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="change_password" 
                                    class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700 transition-colors">
                                <i class="fas fa-key mr-2"></i>Modifier le mot de passe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Onglet Adresse Haïti -->
            <div id="address-content" class="tab-content hidden">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">
                        <i class="fas fa-map-marker-alt mr-2 text-primary"></i>Adresse de Livraison en Haïti
                    </h3>
                    
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="haiti_address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Adresse *
                                </label>
                                <textarea id="haiti_address" name="haiti_address" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                          placeholder="Entrez votre adresse en Haïti"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div>
                                <label for="haiti_city" class="block text-sm font-medium text-gray-700 mb-2">
                                    Ville *
                                </label>
                                <input type="text" id="haiti_city" name="haiti_city" 
                                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Port-au-Prince, Cap-Haïtien, etc." required>
                            </div>
                            
                            <div>
                                <label for="haiti_department" class="block text-sm font-medium text-gray-700 mb-2">
                                    Département
                                </label>
                                <select id="haiti_department" name="haiti_department" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">Sélectionner un département</option>
                                    <option value="Ouest" <?php echo ($user['country'] ?? '') === 'Ouest' ? 'selected' : ''; ?>>Ouest</option>
                                    <option value="Nord" <?php echo ($user['country'] ?? '') === 'Nord' ? 'selected' : ''; ?>>Nord</option>
                                    <option value="Sud" <?php echo ($user['country'] ?? '') === 'Sud' ? 'selected' : ''; ?>>Sud</option>
                                    <option value="Artibonite" <?php echo ($user['country'] ?? '') === 'Artibonite' ? 'selected' : ''; ?>>Artibonite</option>
                                    <option value="Centre" <?php echo ($user['country'] ?? '') === 'Centre' ? 'selected' : ''; ?>>Centre</option>
                                    <option value="Grand'Anse" <?php echo ($user['country'] ?? '') === 'Grand\'Anse' ? 'selected' : ''; ?>>Grand'Anse</option>
                                    <option value="Nippes" <?php echo ($user['country'] ?? '') === 'Nippes' ? 'selected' : ''; ?>>Nippes</option>
                                    <option value="Nord-Est" <?php echo ($user['country'] ?? '') === 'Nord-Est' ? 'selected' : ''; ?>>Nord-Est</option>
                                    <option value="Nord-Ouest" <?php echo ($user['country'] ?? '') === 'Nord-Ouest' ? 'selected' : ''; ?>>Nord-Ouest</option>
                                    <option value="Sud-Est" <?php echo ($user['country'] ?? '') === 'Sud-Est' ? 'selected' : ''; ?>>Sud-Est</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="haiti_postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                                    Code postal
                                </label>
                                <input type="text" id="haiti_postal_code" name="haiti_postal_code" 
                                       value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Code postal (optionnel)">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="haiti_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Téléphone de contact en Haïti
                                </label>
                                <input type="tel" id="haiti_phone" name="haiti_phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="+509 XXXX XXXX">
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-blue-900 mb-1">Information importante</h4>
                                    <p class="text-sm text-blue-700">
                                        Cette adresse sera utilisée pour la livraison de vos colis en Haïti. 
                                        Assurez-vous qu'elle soit complète et exacte pour éviter tout retard de livraison.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="update_haiti_address" 
                                    class="bg-primary text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">
                                <i class="fas fa-save mr-2"></i>Enregistrer l'Adresse
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de contact -->
    <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Contacter le Support</h3>
                    <button onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-phone text-primary"></i>
                        <span>+33 1 23 45 67 89</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-envelope text-primary"></i>
                        <span>support@smartcore-express.com</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-clock text-primary"></i>
                        <span>Lun-Ven: 9h-18h</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du menu mobile
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    mobileMenu.classList.toggle('hidden');
                });
                
                // Fermer le menu en cliquant à l'extérieur
                document.addEventListener('click', function(e) {
                    if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                        mobileMenu.classList.add('hidden');
                    }
                });
            }
        });

        // Gestion des onglets
        function showTab(tabName) {
            // Cacher tous les contenus d'onglets
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.add('hidden'));
            
            // Réinitialiser tous les boutons d'onglets
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => {
                button.classList.remove('active', 'border-primary', 'text-primary');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Afficher le contenu de l'onglet sélectionné
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // Activer le bouton de l'onglet sélectionné
            const activeButton = document.getElementById(tabName + '-tab');
            activeButton.classList.add('active', 'border-primary', 'text-primary');
            activeButton.classList.remove('border-transparent', 'text-gray-500');
        }

        // Fonctions pour le modal de contact
        function showContactInfo() {
            document.getElementById('contactModal').classList.remove('hidden');
        }

        function closeContactModal() {
            document.getElementById('contactModal').classList.add('hidden');
        }

        // Validation du mot de passe
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>