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
<body class="bg-gradient-to-br from-primary to-blue-800 min-h-screen flex items-center justify-center bg-pattern py-8">
    <div class="max-w-lg w-full mx-4">
        <!-- Logo et titre -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center shadow-lg">
                <img src="../img/Logo.png" alt="Smartcore Express" class="h-12 w-auto">
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Smartcore Express</h1>
            <p class="text-blue-100">Créez votre compte client</p>
        </div>
        
        <!-- Formulaire d'inscription -->
        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Inscription</h2>
            
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
                <div class="text-sm mt-2">Redirection en cours...</div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-2"></i>Prénom *
                        </label>
                        <input type="text" id="first_name" name="first_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-2"></i>Nom *
                        </label>
                        <input type="text" id="last_name" name="last_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-at mr-2"></i>Nom d'utilisateur *
                    </label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                           pattern="[a-zA-Z0-9]+"
                           title="Seules les lettres et les chiffres sont autorisés"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <div class="text-xs text-gray-500 mt-1">Lettres et chiffres uniquement</div>
                </div>
                
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2"></i>Adresse Email *
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <div class="text-xs text-gray-500 mt-1">Domaines acceptés: Gmail, Yahoo, Hotmail, Outlook, Live</div>
                </div>
                
                <div class="mb-4">
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-phone mr-2"></i>Téléphone
                    </label>
                    <input type="tel" id="phone" name="phone"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                
                <div class="mb-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-2"></i>Adresse
                    </label>
                    <textarea id="address" name="address" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" id="terms" name="terms" required class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                        <label for="terms" class="ml-2 block text-sm text-gray-700">
                            J'accepte les <a href="../conditions-utilisation.html" class="text-primary hover:text-blue-700">conditions d'utilisation</a> et la <a href="../politique-confidentialite.html" class="text-primary hover:text-blue-700">politique de confidentialité</a>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                    <div class="flex items-center text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span class="text-sm font-medium">Information importante</span>
                    </div>
                    <p class="text-sm text-blue-700 mt-1">
                        Un mot de passe sera généré automatiquement et envoyé à votre adresse email avec vos identifiants de connexion.
                    </p>
                </div>
                
                <button type="submit" class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition duration-200 font-medium">
                    <i class="fas fa-user-plus mr-2"></i>Créer mon compte
                </button>
            </form>
            
            <!-- Séparateur -->
            <div class="mt-6 mb-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Ou inscrivez-vous avec</span>
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
                    S'inscrire avec Google
                </a>
                <?php endif; ?>
                

            </div>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Déjà un compte ?
                    <a href="login.php" class="text-primary hover:text-blue-700 font-medium">
                        Se connecter
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