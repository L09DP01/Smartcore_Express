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
                        primary: '#6366f1',
                        secondary: '#3b82f6',
                        accent: '#1f2937',
                        dark: '#111827',
                        darker: '#0f172a'
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="../manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="../img/Logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Smartcore Express">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileColor" content="#111827">
    <meta name="msapplication-TileImage" content="../img/Logo.png">
    
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .input-field {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .input-field::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .input-field:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #6366f1;
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
        .social-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .social-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }
    </style>
    <link rel="icon" type="image/png" href="../client/logo.png">
    
    <script src="../pwa-global.js" defer></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <!-- Back Button -->
    <div class="absolute top-6 left-6">
        <button onclick="history.back()" class="text-white/70 hover:text-white transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </button>
    </div>

    <div class="w-full max-w-sm mx-auto">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-white mb-2">Sign In</h1>
        </div>
        
        <!-- Form Container -->
        <div class="space-y-6">
            
            <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-6">
                <!-- Email Input -->
                <div>
                    <input type="email" id="email" name="email" required
                           class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50"
                           placeholder="E-mail"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <!-- Password Input -->
                <div class="relative">
                    <input type="password" id="password" name="password" required
                           class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50 pr-12"
                           placeholder="Password">
                    <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center">
                        <i id="password-icon" class="fas fa-eye text-white/50 hover:text-white/70 transition-colors"></i>
                    </button>
                </div>
                
                <!-- Forgot Password -->
                <div class="text-right">
                    <a href="forgot_password.php" class="text-primary hover:text-primary/80 transition-colors font-medium">
                        Forgot password?
                    </a>
                </div>
                
                <!-- Remember Me -->
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-primary focus:ring-primary border-white/20 rounded bg-transparent">
                    <label for="remember" class="ml-3 text-white/70 text-sm">
                        Remember me
                    </label>
                </div>
                
                <!-- Login Button -->
                <button type="submit" class="btn-primary w-full py-4 rounded-2xl text-white font-semibold text-lg">
                    Log In
                </button>
            </form>
            
            <!-- Separator -->
            <div class="my-8">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/10"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-darker text-white/50">OR</span>
                    </div>
                </div>
            </div>
            
            
                
               
            </div>
            
            <!-- Register Link -->
            <div class="mt-8 text-center">
                <p class="text-white/70">
                    Don't have an account?
                    <a href="register.php" class="text-primary hover:text-primary/80 font-medium ml-1">
                        Sign up
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