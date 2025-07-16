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
                        primary: '#8B5CF6',
                        secondary: '#06B6D4',
                        accent: '#10B981',
                        dark: '#0F0F23',
                        darker: '#0A0A1A'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0F0F23 0%, #1a1a2e 50%, #16213e 100%);
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
        }
        
        .input-field {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #8B5CF6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .input-field::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        }
    </style>
    <link rel="icon" type="image/png" href="../client/logo.png">
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Back Button -->
        <div class="mb-6">
            <a href="loginapp.php" class="inline-flex items-center text-white/70 hover:text-white transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back
            </a>
        </div>
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Reset Password</h1>
            <p class="text-white/70">Enter your new password</p>
        </div>
        
        <!-- Form Container -->
        <div class="glass-effect rounded-2xl p-8">
            <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <div class="text-sm mt-2 text-green-300">Redirecting...</div>
            </div>
            <div class="text-center mt-4">
                <a href="loginapp.php" class="text-primary hover:text-primary/80 font-medium">
                    <i class="fas fa-arrow-right mr-2"></i>Sign in now
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($user_data && !$message): ?>
                <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 mb-6">
                    <div class="flex items-center text-blue-400 mb-2">
                        <i class="fas fa-user mr-2"></i>
                        <span class="text-sm font-medium">User Account</span>
                    </div>
                    <p class="text-white font-medium"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></p>
                    <p class="text-white/70 text-sm"><?php echo htmlspecialchars($user_data['email']); ?></p>
                </div>
                
                <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4 mb-6">
                    <div class="flex items-center text-yellow-400 mb-2">
                        <i class="fas fa-shield-alt mr-2"></i>
                        <span class="text-sm font-medium">Password Requirements</span>
                    </div>
                    <ul class="text-white/70 text-sm space-y-1">
                        <li><i class="fas fa-check text-xs mr-2 text-green-400"></i>At least 6 characters</li>
                        <li><i class="fas fa-check text-xs mr-2 text-green-400"></i>Use a combination of letters and numbers</li>
                        <li><i class="fas fa-check text-xs mr-2 text-green-400"></i>Avoid simple passwords</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <div class="mb-6">
                        <label for="new_password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>New Password
                        </label>
                        <input type="password" id="new_password" name="new_password" required minlength="6"
                               class="input-field w-full px-4 py-3 rounded-xl focus:outline-none"
                               placeholder="Enter your new password">
                    </div>
                    
                    <div class="mb-8">
                        <label for="confirm_password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                               class="input-field w-full px-4 py-3 rounded-xl focus:outline-none"
                               placeholder="Confirm your password">
                    </div>
                    
                    <button type="submit" class="btn-primary w-full text-white py-3 px-4 rounded-xl font-medium">
                        <i class="fas fa-key mr-2"></i>Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if (!$user_data && !$message): ?>
            <div class="text-center">
                <p class="text-white/70 mb-4">
                    The reset link is invalid or has expired.
                </p>
                <a href="forgot_passwordapp.php" class="text-primary hover:text-primary/80 font-medium">
                    <i class="fas fa-arrow-left mr-2"></i>Request a new link
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (!$message): ?>
            <div class="mt-8 text-center">
                <p class="text-white/70">
                    Back to
                    <a href="loginapp.php" class="text-primary hover:text-primary/80 font-medium ml-1">
                        sign in
                    </a>
                </p>
            </div>
            <?php endif; ?>
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