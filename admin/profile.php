<?php
// Inclure le gestionnaire de session avec gestion d'inactivité
require_once '../auth/session_manager.php';
require_once '../config/database.php';

// Vérifier si l'utilisateur est admin (la session est déjà vérifiée par session_manager.php)
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Obtenir la connexion à la base de données
$pdo = getDBConnection();

$message = '';
$error = '';

// Récupérer les informations actuelles de l'utilisateur d'abord
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // S'assurer que les clés existent même si elles sont NULL
    if ($user) {
        $user['profile_photo'] = $user['profile_photo'] ?? null;
        $user['first_name'] = $user['first_name'] ?? '';
        $user['last_name'] = $user['last_name'] ?? '';
        $user['email'] = $user['email'] ?? '';
        $user['phone'] = $user['phone'] ?? '';
    }
} catch (Exception $e) {
    $error = 'Erreur lors du chargement des données.';
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $user_id = $_SESSION['user_id'];
        
        // Gestion de l'upload de photo
        $photo_path = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../img/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Supprimer l'ancienne photo si elle existe
                if ($user && !empty($user['profile_photo']) && file_exists('../' . $user['profile_photo'])) {
                    unlink('../' . $user['profile_photo']);
                }
                
                $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    $photo_path = 'img/profiles/' . $new_filename;
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
        
        // Mise à jour des informations
        if (empty($error)) {
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
            $_SESSION['admin_name'] = $first_name . ' ' . $last_name;
            if ($photo_path) {
                $_SESSION['profile_photo'] = $photo_path;
            }
            
            $message = 'Profil mis à jour avec succès!';
            
            // Redirection pour éviter la resoumission du formulaire et forcer le rechargement
            header('Location: profile.php?success=1');
            exit();
        }
    } catch (Exception $e) {
        $error = 'Erreur lors de la mise à jour: ' . $e->getMessage();
    }
}

// Gérer le message de succès depuis la redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = 'Profil mis à jour avec succès!';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">\n    <script src="../js/session_activity.js"></script>
    <link rel="stylesheet" href="../css/theme.css">\n    <link rel="stylesheet" href="../css/admin-responsive.css">
    <script src="../js/theme.js"></script>\n    <script src="../js/admin-responsive.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

        <!-- Contenu principal -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <header class="admin-header bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 transition">
                            <i class="fas fa-arrow-left mr-2"></i>Retour
                        </a>
                        <h1 class="text-2xl font-bold text-gray-800">Mon Profil</h1>
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
                <!-- Messages -->
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-700 border border-green-200">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-700 border border-red-200">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formulaire de profil -->
                <div class="stat-card bg-white rounded-lg shadow p-6">
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- Photo de profil -->
                        <div class="flex items-center space-x-6">
                            <div class="relative">
                                <img id="profile-preview" src="<?php echo ($user && isset($user['profile_photo']) && !empty($user['profile_photo'])) ? '../' . $user['profile_photo'] : '../img/admin-profile.jpg'; ?>" alt="Photo de profil" class="w-24 h-24 rounded-full object-cover border-4 border-primary">
                                <label for="profile_photo" class="absolute bottom-0 right-0 bg-primary text-white rounded-full p-2 cursor-pointer hover:bg-blue-800 transition">
                                    <i class="fas fa-camera text-sm"></i>
                                </label>
                                <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="hidden" onchange="previewImage(this)">
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Photo de profil</h3>
                                <p class="text-sm text-gray-500">Cliquez sur l'icône caméra pour changer votre photo</p>
                                <p class="text-xs text-gray-400 mt-1">Formats acceptés: JPG, JPEG, PNG, GIF</p>
                            </div>
                        </div>

                        <!-- Informations personnelles -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                                <input type="text" name="first_name" value="<?php echo $user ? htmlspecialchars($user['first_name']) : ''; ?>" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                                <input type="text" name="last_name" value="<?php echo $user ? htmlspecialchars($user['last_name']) : ''; ?>" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" name="email" value="<?php echo $user ? htmlspecialchars($user['email']) : ''; ?>" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                                <input type="tel" name="phone" value="<?php echo $user ? htmlspecialchars($user['phone'] ?? '') : ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <!-- Boutons -->
                        <div class="flex justify-end space-x-4">
                            <a href="dashboard.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                                Annuler
                            </a>
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-md hover:bg-blue-800 transition">
                                <i class="fas fa-save mr-2"></i>Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>