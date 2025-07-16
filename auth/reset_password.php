<?php
session_start();
require_once '../config/database.php';
require_once '../includes/password_reset_functions.php';

$message = '';
$error = '';
$token = $_GET['token'] ?? '';
$user_data = null;

// Vérifier le token
if (empty($token)) {
    $error = 'Token de réinitialisation manquant.';
} else {
    $user_data = validateResetToken($token);
    if (!$user_data) {
        $error = 'Token de réinitialisation invalide ou expiré.';
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_data) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password)) {
        $error = 'Veuillez saisir un nouveau mot de passe.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            // Mettre à jour le mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_data['user_id']]);
            
            // Supprimer le token utilisé
            deleteResetToken($token);
            
            // Nettoyer automatiquement tous les tokens expirés
            cleanExpiredTokensOnPasswordReset();
            
            $message = 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.';
        } catch (PDOException $e) {
            $error = 'Erreur lors de la mise à jour du mot de passe. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe - Smartcore Express</title>
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
<body class="bg-gradient-to-br from-primary to-blue-800 min-h-screen flex items-center justify-center bg-pattern py-8">
    <div class="max-w-lg w-full mx-4">
        <!-- Logo et titre -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center shadow-lg">
                <img src="../img/Logo.png" alt="Smartcore Express" class="h-12 w-auto">
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Smartcore Express</h1>
            <p class="text-blue-100">Nouveau mot de passe</p>
        </div>
        
        <!-- Formulaire de réinitialisation -->
        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Réinitialiser le mot de passe</h2>
            
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
                <div class="text-sm mt-2">Redirection en cours...</div>
            </div>
            <div class="text-center mt-4">
                <a href="login.php" class="text-primary hover:text-blue-700 font-medium">
                    <i class="fas fa-arrow-right mr-2"></i>Se connecter maintenant
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($user_data && !$message): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                    <div class="flex items-center text-blue-800 mb-2">
                        <i class="fas fa-user mr-2"></i>
                        <span class="text-sm font-medium">Compte utilisateur</span>
                    </div>
                    <p class="text-blue-700 font-medium"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></p>
                    <p class="text-blue-600 text-sm"><?php echo htmlspecialchars($user_data['email']); ?></p>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                    <div class="flex items-center text-yellow-800 mb-2">
                        <i class="fas fa-shield-alt mr-2"></i>
                        <span class="text-sm font-medium">Exigences du mot de passe</span>
                    </div>
                    <ul class="text-yellow-700 text-sm space-y-1">
                        <li><i class="fas fa-check text-xs mr-2"></i>Au moins 6 caractères</li>
                        <li><i class="fas fa-check text-xs mr-2"></i>Utilisez une combinaison de lettres et chiffres</li>
                        <li><i class="fas fa-check text-xs mr-2"></i>Évitez les mots de passe trop simples</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2"></i>Nouveau mot de passe
                        </label>
                        <input type="password" id="new_password" name="new_password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Votre nouveau mot de passe">
                    </div>
                    
                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2"></i>Confirmer le mot de passe
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Confirmez votre mot de passe">
                    </div>
                    
                    <button type="submit" class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition duration-200 font-medium">
                        <i class="fas fa-key mr-2"></i>Réinitialiser le mot de passe
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if (!$user_data && !$message): ?>
            <div class="text-center">
                <p class="text-sm text-gray-600 mb-4">
                    Le lien de réinitialisation est invalide ou a expiré.
                </p>
                <a href="forgot_password.php" class="text-primary hover:text-blue-700 font-medium">
                    <i class="fas fa-arrow-left mr-2"></i>Demander un nouveau lien
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (!$message): ?>
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Retour à la
                    <a href="login.php" class="text-primary hover:text-blue-700 font-medium">
                        page de connexion
                    </a>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Lien retour -->
        <div class="text-center mt-6">
            <a href="../index.html" class="text-blue-100 hover:text-white transition">
                <i class="fas fa-arrow-left mr-2"></i>Retour au site
            </a>
        </div>
    </div>
    
    <script>
        // Vérification en temps réel de la correspondance des mots de passe
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            function checkPasswords() {
                if (newPassword.value && confirmPassword.value) {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Les mots de passe ne correspondent pas');
                        submitBtn.disabled = true;
                    } else {
                        confirmPassword.setCustomValidity('');
                        submitBtn.disabled = false;
                    }
                } else {
                    confirmPassword.setCustomValidity('');
                    submitBtn.disabled = false;
                }
            }
            
            newPassword.addEventListener('input', checkPasswords);
            confirmPassword.addEventListener('input', checkPasswords);
        });
    </script>
</body>
</html>