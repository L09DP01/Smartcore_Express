<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../config/database.php';
require_once '../config/oauth_config.php';

$error = '';
$success = '';

// Vérifier si l'utilisateur arrive avec un paramètre d'expiration
if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $error = 'Votre session a expiré en raison d\'inactivité. Veuillez vous reconnecter.';
}

// Vérifier l'inactivité de session (1 minute = 60 secondes)
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    if ($inactive_time > 60) { // 1 minute d'inactivité
        // Détruire la session
        session_unset();
        session_destroy();
        
        // Supprimer le cookie "remember me" si il existe
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Redémarrer une nouvelle session
        session_start();
        $error = 'Votre session a expiré en raison d\'inactivité.';
    }
}

// Vérifier le cookie "remember me"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.is_active 
                               FROM users u 
                               JOIN remember_tokens rt ON u.id = rt.user_id 
                               WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Mettre à jour la dernière connexion
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Restaurer la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            // Redirection selon le rôle
            if ($user['role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../client/dashboard.php');
            }
            exit();
        } else {
            // Token invalide, supprimer le cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
    } catch(PDOException $e) {
        error_log("Erreur remember token: " . $e->getMessage());
    }
}

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time(); // Mettre à jour l'activité
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../client/dashboard.php');
    }
    exit();
}

// Gérer la mise à jour d'activité via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_activity'])) {
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id, username, email, password_hash, first_name, last_name, role, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['is_active']) {
                    // Mettre à jour la dernière connexion
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    
                    // Gérer le "Se souvenir de moi"
                    if ($remember) {
                        // Générer un token unique
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', time() + (15 * 24 * 60 * 60)); // 15 jours
                        
                        // Supprimer les anciens tokens de cet utilisateur
                        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                        $stmt->execute([$user['id']]);
                        
                        // Insérer le nouveau token
                        $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$user['id'], $token, $expires]);
                        
                        // Créer le cookie (15 jours)
                        setcookie('remember_token', $token, time() + (15 * 24 * 60 * 60), '/', '', false, true);
                    }
                    
                    // Redirection selon le rôle
                    if ($user['role'] === 'admin') {
                        header('Location: ../admin/dashboard.php');
                    } else {
                        header('Location: ../client/dashboard.php');
                    }
                    exit();
                } else {
                    $error = 'Votre compte a été désactivé. Contactez l\'administrateur.';
                }
            } else {
                $error = 'Email ou mot de passe incorrect.';
            }
        } catch(PDOException $e) {
            error_log("Erreur login: " . $e->getMessage());
            $error = 'Erreur de connexion. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Smartcore Express</title>
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
    <link rel="manifest" href="../manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="../img/Logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Smartcore Express">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileColor" content="#0047AB">
    <meta name="msapplication-TileImage" content="../img/Logo.png">
    
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .bg-pattern {
            background-image: url('data:image/svg+xml;charset=utf8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 304 304" width="304" height="304"%3E%3Cpath fill="%23ffffff" fill-opacity="0.1" d="M44.1 224a5 5 0 1 1 0 2H0v-2h44.1zm160 48a5 5 0 1 1 0 2H82v-2h122.1z"/%3E%3C/svg%3E');
        }
    </style>
    <link rel="icon" type="image/png" href="../client/logo.png">
    
    <script src="../pwa-global.js" defer></script>
</head>
<body class="bg-gradient-to-br from-primary to-blue-800 min-h-screen flex items-center justify-center bg-pattern">
    <div class="max-w-md w-full mx-4">
        <!-- Logo et titre -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center shadow-lg">
                <img src="../img/Logo.png" alt="Smartcore Express" class="h-12 w-auto">
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Smartcore Express</h1>
            <p class="text-blue-100">Connectez-vous à votre espace</p>
        </div>
        
        <!-- Formulaire de connexion -->
        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Connexion</h2>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2"></i>Adresse Email
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                           placeholder="votre@email.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Mot de Passe
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent pr-10"
                               placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i id="password-icon" class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            Se souvenir de moi
                        </label>
                    </div>
                    <a href="forgot_password.php" class="text-sm text-primary hover:text-blue-700">
                        Mot de passe oublié ?
                    </a>
                </div>
                
                <button type="submit" class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition duration-200 font-medium">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se Connecter
                </button>
            </form>
            
            <!-- Séparateur -->
            <div class="mt-6 mb-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Ou connectez-vous avec</span>
                    </div>
                </div>
            </div>
            
            <!-- Boutons OAuth -->
            <div class="space-y-3">
                <?php if (isGoogleConfigured()): ?>
                <a href="<?php echo getGoogleAuthUrl(); ?>" 
                   class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-200">
                    <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continuer avec Google
                </a>
                <?php endif; ?>
                

            </div>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Pas encore de compte ?
                    <a href="register.php" class="text-primary hover:text-blue-700 font-medium">
                        Créer un compte
                    </a>
                </p>
            </div>

        </div>
        
        
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }
        
        // Auto-focus sur le premier champ
        document.getElementById('email').focus();
        
        // Mettre à jour l'activité de session toutes les 30 secondes
        setInterval(function() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'update_activity=1'
            });
        }, 30000); // 30 secondes
    </script>
</body>
</html>