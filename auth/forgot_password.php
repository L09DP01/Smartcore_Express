<?php
session_start();
require_once '../config/database.php';
require_once '../includes/password_reset_functions.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Veuillez saisir votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez saisir une adresse email valide.';
    } else {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            // Vérifier si l'email existe
            $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Nettoyer les tokens expirés avant de créer un nouveau
                cleanExpiredPasswordResetTokens();
                
                // Générer un token de réinitialisation
                $reset_token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Stocker le token dans la base de données
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()");
                $stmt->execute([$user['id'], $reset_token, $expires_at]);
                
                // Envoyer l'email de réinitialisation
                $emailSent = sendPasswordResetEmail($email, $user['first_name'], $user['last_name'], $reset_token);
                
                if ($emailSent) {
                    $message = 'Un lien de réinitialisation a été envoyé à votre adresse email.';
                } else {
                    $error = 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.';
                }
            } else {
                // Pour des raisons de sécurité, on affiche le même message même si l'email n'existe pas
                $message = 'Si cette adresse email existe dans notre système, un lien de réinitialisation vous sera envoyé.';
            }
        } catch (PDOException $e) {
            $error = 'Erreur de base de données. Veuillez réessayer plus tard.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Smartcore Express</title>
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
        .bg-pattern {
            background-image: url('data:image/svg+xml;charset=utf8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 304 304" width="304" height="304"%3E%3Cpath fill="%23ffffff" fill-opacity="0.1" d="M44.1 224a5 5 0 1 1 0 2H0v-2h44.1zm160 48a5 5 0 1 1 0 2H82v-2h122.1z"/%3E%3C/svg%3E');
        }
    </style>
    <link rel="icon" type="image/png" href="../client/logo.png">
</head>
<body class="bg-gradient-to-br from-primary to-blue-800 min-h-screen flex items-center justify-center bg-pattern">
    <div class="max-w-md w-full mx-4">
        <!-- Logo et titre -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center shadow-lg">
                <img src="../img/Logo.png" alt="Smartcore Express" class="h-12 w-auto">
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Smartcore Express</h1>
            <p class="text-blue-100">Réinitialiser votre mot de passe</p>
        </div>
        
        <!-- Formulaire de réinitialisation -->
        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Mot de passe oublié</h2>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!$message): ?>
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                <div class="flex items-center text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span class="text-sm font-medium">Information</span>
                </div>
                <p class="text-sm text-blue-700 mt-1">
                    Saisissez votre adresse email pour recevoir un lien de réinitialisation de mot de passe.
                </p>
            </div>
            
            <form method="POST" action="">
                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2"></i>Adresse Email
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                           placeholder="votre@email.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition duration-200 font-medium">
                    <i class="fas fa-paper-plane mr-2"></i>Envoyer le lien de réinitialisation
                </button>
            </form>
            <?php endif; ?>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Vous vous souvenez de votre mot de passe ?
                    <a href="login.php" class="text-primary hover:text-blue-700 font-medium">
                        Se connecter
                    </a>
                </p>
            </div>
        </div>
        
        <!-- Lien retour -->
        <div class="text-center mt-6">
            <a href="../index.html" class="text-blue-100 hover:text-white transition">
                <i class="fas fa-arrow-left mr-2"></i>Retour au site
            </a>
        </div>
    </div>
    
    <script>
        // Auto-focus sur le champ email
        document.getElementById('email')?.focus();
    </script>
</body>
</html>