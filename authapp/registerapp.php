<?php
session_start();
require_once '../config/database.php';
require_once '../config/oauth_config.php';

// Variables pour les messages
$error = '';
$success = '';

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit();
}

// Fonction pour générer un mot de passe aléatoire
function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Fonction pour valider le domaine email
function isValidEmailDomain($email) {
    $allowedDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com'];
    $domain = substr(strrchr($email, '@'), 1);
    return in_array(strtolower($domain), $allowedDomains);
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupération et nettoyage des données
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        // Génération automatique du mot de passe
        $password = generateRandomPassword(10);
        
        // Validation des champs obligatoires
        if (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
            throw new Exception('Tous les champs marqués d\'un * sont obligatoires.');
        }
        
        // Validation du nom d'utilisateur (lettres et chiffres uniquement)
        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            throw new Exception('Le nom d\'utilisateur ne peut contenir que des lettres et des chiffres.');
        }
        
        // Validation de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Adresse email invalide.');
        }
        
        // Validation du domaine email
        if (!isValidEmailDomain($email)) {
            throw new Exception('Seuls les domaines Gmail, Yahoo, Hotmail, Outlook et Live sont acceptés.');
        }
        
        // Connexion à la base de données
        $database = new Database();
        $pdo = $database->getConnection();
        if (!$pdo) {
            throw new Exception('Erreur de connexion à la base de données.');
        }
        
        // Vérification de l'unicité du nom d'utilisateur
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception('Ce nom d\'utilisateur est déjà utilisé.');
        }
        
        // Vérification de l'unicité de l'email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Cette adresse email est déjà utilisée.');
        }
        
        // Hachage du mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insertion du nouvel utilisateur
        $stmt = $pdo->prepare('
            INSERT INTO users (first_name, last_name, username, email, phone, address, password_hash, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $result = $stmt->execute([
            $first_name,
            $last_name, 
            $username,
            $email,
            $phone,
            $address,
            $hashedPassword
        ]);
        
        if (!$result) {
            throw new Exception('Erreur lors de la création du compte.');
        }
        
        $userId = $pdo->lastInsertId();
        
        // Envoi de l'email de bienvenue avec le mot de passe
        require_once '../includes/welcome_email_functions.php';
        $emailResult = sendWelcomeEmailWithPassword($email, $first_name, $last_name, $username, $password);
        
        if ($emailResult['success']) {
            // Marquer l'email comme envoyé
            $stmt = $pdo->prepare('UPDATE users SET welcome_email_sent = 1 WHERE id = ?');
            $stmt->execute([$userId]);
            
            $success = 'Compte créé avec succès ! Vos identifiants de connexion ont été envoyés par email.';
            // Redirection après 3 secondes
            echo '<script>setTimeout(function(){ window.location.href = "login.php"; }, 3000);</script>';
        } else {
            $success = 'Compte créé avec succès ! Cependant, l\'email n\'a pas pu être envoyé: ' . $emailResult['message'];
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Smartcore Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        darker: '#0f172a'
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"
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

    <div class="w-full max-w-md mx-auto">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-white mb-2">Sign Up</h1>
            <p class="text-white/70">Create your account</p>
        </div>
        
        <!-- Form Container -->
        <div class="glass-effect rounded-3xl p-8 space-y-6">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-semibold text-white">Join Smartcore Express</h2>
            </div>
            
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
                <div class="text-sm mt-2 text-green-300">Redirection en cours...</div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <input type="text" id="first_name" name="first_name" required
                               class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50"
                               placeholder="First Name *"
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <input type="text" id="last_name" name="last_name" required
                               class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50"
                               placeholder="Last Name *"
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div>
                    <input type="text" id="username" name="username" required
                           class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50"
                           pattern="[a-zA-Z0-9]+"
                           title="Seules les lettres et les chiffres sont autorisés"
                           placeholder="Username *"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <div class="text-xs text-white/50 mt-2">Letters and numbers only</div>
                </div>
                
                <div>
                    <input type="email" id="email" name="email" required
                           class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50"
                           placeholder="Email Address *"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <div class="text-xs text-white/50 mt-2">Accepted domains: Gmail, Yahoo, Hotmail, Outlook, Live</div>
                </div>
                
                <div>
                    <input type="tel" id="phone" name="phone"
                           class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50"
                           placeholder="Phone Number"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                
                <div>
                    <textarea id="address" name="address" rows="3"
                              class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50 resize-none"
                              placeholder="Address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="flex items-center space-x-3">
                    <input type="checkbox" id="terms" name="terms" required class="h-4 w-4 text-primary focus:ring-primary border-white/20 rounded bg-transparent">
                    <label for="terms" class="text-sm text-white/70">
                        I accept the <a href="../conditions-utilisation.html" class="text-primary hover:text-primary/80">terms of use</a> and <a href="../politique-confidentialite.html" class="text-primary hover:text-primary/80">privacy policy</a>
                    </label>
                </div>
                
                <div class="p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                    <div class="flex items-center text-blue-400 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span class="text-sm font-medium">Important Information</span>
                    </div>
                    <p class="text-sm text-blue-300">
                        A password will be automatically generated and sent to your email address with your login credentials.
                    </p>
                </div>
                
                <button type="submit" class="btn-primary w-full py-4 rounded-2xl text-white font-semibold text-lg">
                    Create Account
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
            
            <!-- Social Login Buttons -->
            <div class="space-y-4">
                <?php if (isGoogleConfigured()): ?>
                <a href="<?php echo getGoogleAuthUrl(); ?>" 
                   class="social-btn w-full flex items-center justify-center px-4 py-4 rounded-2xl text-white font-medium">
                    <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Sign up with Google
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Login Link -->
            <div class="mt-8 text-center">
                <p class="text-white/70">
                    Already have an account?
                    <a href="loginapp.php" class="text-primary hover:text-primary/80 font-medium ml-1">
                        Sign in
                    </a>
                </p>
            </div>
        </div>
        
        
    </div>
    
    <script>
        // Validation du nom d'utilisateur en temps réel
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const regex = /^[a-zA-Z0-9]*$/;
            
            if (!regex.test(username)) {
                // Supprimer les caractères non autorisés
                this.value = username.replace(/[^a-zA-Z0-9]/g, '');
            }
        });
        
        // Auto-focus sur le premier champ
        document.getElementById('first_name').focus();
    </script>
</body>
</html>