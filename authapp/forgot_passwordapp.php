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
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        darker: '#0f172a'
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
    </style>
    <link rel="icon" type="image/png" href="../client/logo.png">
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
            <h1 class="text-4xl font-bold text-white mb-2">Forgot Password</h1>
            <p class="text-white/70">Reset your password</p>
        </div>
        
        <!-- Form Container -->
        <div class="glass-effect rounded-3xl p-8 space-y-6">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-semibold text-white">Reset Password</h2>
            </div>
            
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
            </div>
            <?php endif; ?>
            
            <?php if (!$message): ?>
            <div class="p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl mb-6">
                <div class="flex items-center text-blue-400 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span class="text-sm font-medium">Information</span>
                </div>
                <p class="text-sm text-blue-300">
                    Enter your email address to receive a password reset link.
                </p>
            </div>
            
            <form method="POST" action="" class="space-y-6">
                <div>
                    <input type="email" id="email" name="email" required
                           class="input-field w-full px-4 py-4 rounded-2xl text-white placeholder-white/50"
                           placeholder="Email Address"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn-primary w-full py-4 rounded-2xl text-white font-semibold text-lg">
                    Send Reset Link
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Login Link -->
            <div class="mt-8 text-center">
                <p class="text-white/70">
                    Remember your password?
                    <a href="loginapp.php" class="text-primary hover:text-primary/80 font-medium ml-1">
                        Sign in
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus sur le champ email
        document.getElementById('email')?.focus();
    </script>
</body>
</html>