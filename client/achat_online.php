<?php
// Inclure le gestionnaire de session avec gestion d'inactivité
require_once '../authapp/session_managerapp.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$user = null;
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Erreur récupération utilisateur: " . $e->getMessage());
}

// Traitement du formulaire
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_link = $_POST['cart_link'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    
    // Validation
    if (empty($cart_link) || empty($phone) || empty($email) || empty($address)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        // Traitement des fichiers screenshots (plusieurs images)
        $screenshot_paths = [];
        if (isset($_FILES['screenshots']) && !empty($_FILES['screenshots']['name'][0])) {
            $upload_dir = '../uploads/screenshots/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_files = 5; // Limite à 5 images
            $file_count = count($_FILES['screenshots']['name']);
            
            if ($file_count > $max_files) {
                $error = "Vous ne pouvez télécharger que $max_files images maximum.";
            } else {
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['screenshots']['error'][$i] === UPLOAD_ERR_OK) {
                        $file_extension = pathinfo($_FILES['screenshots']['name'][$i], PATHINFO_EXTENSION);
                        
                        if (in_array(strtolower($file_extension), $allowed_extensions)) {
                            $filename = 'screenshot_' . $user_id . '_' . time() . '_' . $i . '.' . $file_extension;
                            $screenshot_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['screenshots']['tmp_name'][$i], $screenshot_path)) {
                                $screenshot_paths[] = $screenshot_path;
                            } else {
                                $error = 'Erreur lors du téléchargement de l\'image ' . ($i + 1) . '.';
                                break;
                            }
                        } else {
                            $error = 'Format de fichier non autorisé pour l\'image ' . ($i + 1) . '. Utilisez JPG, PNG ou GIF.';
                            break;
                        }
                    }
                }
            }
        }
        
        if (empty($error)) {
            // Préparer l'email
            $to = 'serviceachat@smartcoreexpress.com';
            $user_name = ($user && isset($user['first_name']) && isset($user['last_name'])) 
                ? $user['first_name'] . ' ' . $user['last_name'] 
                : 'Utilisateur inconnu';
            $subject = 'Nouvelle demande d\'achat en ligne - ' . $user_name;
            
            $email_body = "Nouvelle demande d'achat en ligne\n\n";
            $email_body .= "Client: " . $user_name . "\n";
            $email_body .= "Email: " . $email . "\n";
            $email_body .= "Téléphone: " . $phone . "\n";
            $email_body .= "Adresse: " . $address . "\n";
            $email_body .= "Lien du panier: " . $cart_link . "\n";
            if (!empty($screenshot_paths)) {
                $email_body .= "Nombre d'images jointes: " . count($screenshot_paths) . "\n";
            }
            $email_body .= "\nDate de la demande: " . date('Y-m-d H:i:s') . "\n";
            
            // Envoyer l'email avec PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // Configuration SMTP
                $mail->isSMTP();
                $mail->Host = 'smtp.hostinger.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'noreply@smartcoreexpress.com';
                $mail->Password = 'Lorvens22@';
                $mail->SMTPSecure = 'ssl';
                $mail->Port = 465;
                $mail->CharSet = 'UTF-8';
                
                // Options SSL
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Destinataires
                $mail->setFrom('noreply@smartcoreexpress.com', 'Smartcore Express');
                $mail->addAddress($to);
                $mail->addReplyTo('smartcoreexpress@gmail.com', 'Smartcore Express');
                
                // Ajouter les images en pièces jointes
                if (!empty($screenshot_paths)) {
                    foreach ($screenshot_paths as $index => $screenshot_path) {
                        if (file_exists($screenshot_path)) {
                            $mail->addAttachment($screenshot_path, 'screenshot_' . ($index + 1) . '.' . pathinfo($screenshot_path, PATHINFO_EXTENSION));
                        }
                    }
                }
                
                // Contenu
                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body = $email_body;
                
                $mail->send();
                
                // Envoyer un email de confirmation au client
                $clientMail = new PHPMailer(true);
                
                // Configuration SMTP pour l'email client
                $clientMail->isSMTP();
                $clientMail->Host = 'smtp.hostinger.com';
                $clientMail->SMTPAuth = true;
                $clientMail->Username = 'noreply@smartcoreexpress.com';
                $clientMail->Password = 'Lorvens22@';
                $clientMail->SMTPSecure = 'ssl';
                $clientMail->Port = 465;
                $clientMail->CharSet = 'UTF-8';
                
                // Options SSL
                $clientMail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Email de confirmation au client
                $clientMail->setFrom('noreply@smartcoreexpress.com', 'Smartcore Express');
                $clientMail->addAddress($email, $user_name);
                
                $client_subject = 'Confirmation de votre demande d\'achat - Smartcore Express';
                $client_body = "Bonjour $user_name,\n\n";
                $client_body .= "Nous avons bien reçu votre demande d'achat en ligne.\n\n";
                $client_body .= "Détails de votre demande :\n";
                $client_body .= "- Lien du panier : $cart_link\n";
                $client_body .= "- Téléphone : $phone\n";
                $client_body .= "- Adresse de livraison : $address\n";
                if (!empty($screenshot_paths)) {
                    $client_body .= "- Nombre d'images jointes : " . count($screenshot_paths) . "\n";
                }
                $client_body .= "\nNotre équipe va traiter votre demande dans les plus brefs délais.\n";
                $client_body .= "Vous recevrez un devis détaillé sous 24-48 heures.\n\n";
                $client_body .= "Merci de votre confiance !\n\n";
                $client_body .= "L'équipe Smartcore Express\n";
                $client_body .= "Email : serviceachat@smartcoreexpress.com\n";
                $client_body .= "Site web : https://smartcoreexpress.com";
                
                $clientMail->isHTML(false);
                $clientMail->Subject = $client_subject;
                $clientMail->Body = $client_body;
                
                $clientMail->send();
                $message = 'Votre demande d\'achat a été envoyée avec succès!';
                
                // Enregistrer la demande dans la base de données
                try {
                    $screenshot_paths_json = json_encode($screenshot_paths);
                    $stmt = $conn->prepare("INSERT INTO purchase_requests (user_id, cart_link, phone, email, address, screenshot_path, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$user_id, $cart_link, $phone, $email, $address, $screenshot_paths_json]);
                } catch(PDOException $e) {
                    error_log("Erreur enregistrement demande: " . $e->getMessage());
                }
                
            } catch (Exception $e) {
                $error = 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage();
                error_log("Erreur PHPMailer: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achat en Ligne - Smartcore Express</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="../manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="../img/Logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Smartcore Express">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileColor" content="#8B5CF6">
    <meta name="msapplication-TileImage" content="../img/Logo.png">
    
    <link rel="icon" type="image/png" href="../client/logo.png">
    
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
    <script src="../js/session_activity.js"></script>
    <script src="../pwa-global.js" defer></script>
    
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
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
            background: rgba(255, 255, 255, 0.08);
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .input-field::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }
        
        .file-upload {
            position: relative;
            overflow: hidden;
            display: inline-block;
            cursor: pointer;
        }
        
        .file-upload input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: #6366f1;
        }
        
        .nav-link.active {
            color: #6366f1;
            border-bottom: 2px solid #6366f1;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .glass-effect {
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
            }
            
            /* Improve touch targets */
            .nav-link {
                min-height: 44px;
                display: flex;
                align-items: center;
            }
            
            /* Better mobile form styling */
            .input-field {
                font-size: 16px; /* Prevents zoom on iOS */
                min-height: 44px;
            }
            
            /* Mobile menu animation */
            #mobile-menu {
                transition: all 0.3s ease-in-out;
                max-height: 0;
                overflow: hidden;
            }
            
            #mobile-menu:not(.hidden) {
                max-height: 500px;
            }
        }
        
        /* Smooth transitions */
        .nav-link, #mobile-menu-button {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="glass-effect border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex justify-between items-center py-3 sm:py-4">
                <div class="flex items-center">
                    <img src="../img/Logo.png" alt="Smartcore Express" class="h-8 sm:h-10 w-auto mr-2 sm:mr-3">
                    
                </div>
                
                <div class="hidden md:flex items-center space-x-6">
                    <a href="dashboard.php" class="nav-link">Tableau de bord</a>
                    <a href="../track.php" class="nav-link">Suivi de colis</a>
                    <a href="mes_colis.php" class="nav-link">Mes colis</a>
                    <a href="achat_online.php" class="nav-link active">Achat en ligne</a>
                    <a href="profile.php" class="nav-link">Profil</a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <a href="settings.php" class="flex items-center space-x-2 nav-link">
                        <?php if ($user && isset($user['profile_photo']) && !empty($user['profile_photo'])): ?>
                            <img src="<?php echo '../' . $user['profile_photo']; ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border-2 border-white/20">
                        <?php else: ?>
                            <div class="w-8 h-8 bg-white/10 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                        <?php endif; ?>
                        <span>Paramètres</span>
                    </a>
                    <a href="../authapp/logoutapp.php" class="flex items-center space-x-2 text-red-400 hover:text-red-300">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-white/70 hover:text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobile-menu" class="md:hidden hidden border-t border-white/10">
            <div class="px-4 py-3 space-y-3">
                <a href="dashboard.php" class="block nav-link py-2">Tableau de bord</a>
                <a href="../track.php" class="block nav-link py-2">Suivi de colis</a>
                <a href="mes_colis.php" class="block nav-link py-2">Mes colis</a>
                <a href="achat_online.php" class="block nav-link active py-2">Achat en ligne</a>
                <a href="profile.php" class="block nav-link py-2">Profil</a>
                <a href="settings.php" class="block nav-link py-2">
                    <div class="flex items-center space-x-2">
                        <?php if ($user && isset($user['profile_photo']) && !empty($user['profile_photo'])): ?>
                            <img src="<?php echo '../' . $user['profile_photo']; ?>" alt="Profile" class="w-6 h-6 rounded-full object-cover border border-white/20">
                        <?php else: ?>
                            <div class="w-6 h-6 bg-white/10 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-xs"></i>
                            </div>
                        <?php endif; ?>
                        <span>Paramètres</span>
                    </div>
                </a>
                <a href="../authapp/logoutapp.php" class="block text-red-400 hover:text-red-300 py-2">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </div>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <!-- Back Button -->
        <div class="mb-4 sm:mb-6">
            <a href="dashboard.php" class="inline-flex items-center text-white/70 hover:text-white transition-colors text-sm sm:text-base">
                <i class="fas fa-arrow-left mr-2"></i>
                Retour au tableau de bord
            </a>
        </div>
        
        <!-- Header -->
        <div class="text-center mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-3 sm:mb-4">
                <i class="fas fa-shopping-cart mr-2 sm:mr-3 text-primary"></i>
                Service d'achat en ligne
            </h1>
            <p class="text-white/70 text-sm sm:text-base lg:text-lg">Envoyez-nous les détails de votre panier et nous nous occupons de l'achat pour vous</p>
        </div>
        
        <!-- Form Container -->
        <div class="glass-effect rounded-2xl p-4 sm:p-6 lg:p-8">
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
            
            <!-- Information Box -->
            <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 sm:p-6 mb-6 sm:mb-8">
                <div class="flex items-start space-x-3">
                    <i class="fas fa-info-circle text-blue-400 text-lg sm:text-xl mt-1"></i>
                    <div>
                        <h3 class="text-blue-400 font-semibold mb-2 text-sm sm:text-base">Comment ça marche</h3>
                        <ul class="text-white/70 space-y-2 text-xs sm:text-sm">
                            <li><i class="fas fa-check text-green-400 mr-2"></i>Ajoutez des articles à votre panier sur n'importe quel site</li>
                            <li><i class="fas fa-check text-green-400 mr-2"></i>Partagez le lien du panier et les captures d'écran avec nous</li>
                            <li><i class="fas fa-check text-green-400 mr-2"></i>Nous achetons et expédions en Haïti pour vous</li>
                            <li><i class="fas fa-check text-green-400 mr-2"></i>Suivez votre colis via notre système</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" class="space-y-4 sm:space-y-6">
                <!-- Cart Link -->
                <div>
                    <label for="cart_link" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-link mr-2"></i>Lien du panier *
                    </label>
                    <input type="url" id="cart_link" name="cart_link" required
                           class="input-field w-full px-3 sm:px-4 py-2 sm:py-3 rounded-xl focus:outline-none text-sm sm:text-base"
                           placeholder="https://example.com/cart ou lien de partage"
                           value="<?php echo isset($_POST['cart_link']) ? htmlspecialchars($_POST['cart_link']) : ''; ?>">
                    <p class="text-white/50 text-xs sm:text-sm mt-1">Collez le lien de votre panier d'achat ou liste de souhaits</p>
                </div>
                
                <!-- Screenshots Upload -->
                <div>
                    <label for="screenshots" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-images mr-2"></i>Captures d'écran du panier *
                    </label>
                    <div class="file-upload">
                        <label for="screenshots" class="btn-secondary px-4 sm:px-6 py-3 rounded-xl cursor-pointer inline-flex items-center space-x-2 text-sm sm:text-base">
                            <i class="fas fa-upload"></i>
                            <span>Choisir les Images</span>
                        </label>
                        <input type="file" id="screenshots" name="screenshots[]" accept="image/*" multiple required>
                    </div>
                    <p class="text-white/50 text-xs sm:text-sm mt-1">Téléchargez des captures d'écran de votre panier (JPG, PNG, GIF) - Maximum 5 images de 5MB chacune</p>
                    <div id="file-preview" class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 hidden"></div>
                </div>
                
                <!-- Phone Number -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-phone mr-2"></i>Numéro de téléphone *
                    </label>
                    <input type="tel" id="phone" name="phone" required
                           class="input-field w-full px-3 sm:px-4 py-2 sm:py-3 rounded-xl focus:outline-none text-sm sm:text-base"
                           placeholder="+509 1234 5678"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ($user['phone'] ?? ''); ?>">
                </div>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-envelope mr-2"></i>Adresse e-mail *
                    </label>
                    <input type="email" id="email" name="email" required
                           class="input-field w-full px-3 sm:px-4 py-2 sm:py-3 rounded-xl focus:outline-none text-sm sm:text-base"
                           placeholder="votre.email@exemple.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ($user['email'] ?? ''); ?>">
                </div>
                
                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-map-marker-alt mr-2"></i>Adresse de livraison en Haïti *
                    </label>
                    <textarea id="address" name="address" required rows="3"
                              class="input-field w-full px-3 sm:px-4 py-2 sm:py-3 rounded-xl focus:outline-none resize-none text-sm sm:text-base"
                              placeholder="Entrez votre adresse complète en Haïti"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <!-- Submit Button -->
                <div class="pt-4">
                    <button type="submit" class="btn-primary w-full text-white py-3 sm:py-4 px-4 sm:px-6 rounded-xl font-semibold text-base sm:text-lg hover:scale-105 transition-all duration-200">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Envoyer la demande d'achat
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Additional Information -->
        <div class="mt-6 sm:mt-8 grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="glass-effect rounded-xl p-4 sm:p-6">
                <h3 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">
                    <i class="fas fa-shield-alt text-primary mr-2"></i>
                    Sécurisé et fiable
                </h3>
                <ul class="text-white/70 space-y-2 text-xs sm:text-sm">
                    <li><i class="fas fa-check text-green-400 mr-2"></i>Traitement de paiement sécurisé</li>
                    <li><i class="fas fa-check text-green-400 mr-2"></i>Mises à jour de suivi en temps réel</li>
                    <li><i class="fas fa-check text-green-400 mr-2"></i>Couverture d'assurance disponible</li>
                </ul>
            </div>
            
            <div class="glass-effect rounded-xl p-4 sm:p-6">
                <h3 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">
                    <i class="fas fa-headset text-primary mr-2"></i>
                    Support client
                </h3>
                <ul class="text-white/70 space-y-2 text-xs sm:text-sm">
                    <li><i class="fas fa-check text-green-400 mr-2"></i>Support client 24h/24 et 7j/7</li>
                    <li><i class="fas fa-check text-green-400 mr-2"></i>Assistance WhatsApp</li>
                    <li><i class="fas fa-check text-green-400 mr-2"></i>Email: serviceachat@smartcoreexpress.com</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        // Multiple file upload preview with size validation
        document.getElementById('screenshots').addEventListener('change', function(e) {
            const files = e.target.files;
            const label = e.target.parentElement.querySelector('label span');
            const preview = document.getElementById('file-preview');
            const maxSizeMB = 5; // 5MB max per image
            const maxSizeBytes = maxSizeMB * 1024 * 1024;
            
            // Update label text
            if (files.length > 0) {
                label.textContent = `${files.length} image(s) sélectionnée(s)`;
                preview.classList.remove('hidden');
            } else {
                label.textContent = 'Choisir les Images';
                preview.classList.add('hidden');
            }
            
            // Clear previous previews
            preview.innerHTML = '';
            
            // Check file limit
            if (files.length > 5) {
                alert('Vous ne pouvez sélectionner que 5 images maximum.');
                e.target.value = '';
                label.textContent = 'Choisir les Images';
                preview.classList.add('hidden');
                return;
            }
            
            // Validate file sizes and create previews
            let validFiles = [];
            Array.from(files).forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    // Check file size
                    if (file.size > maxSizeBytes) {
                        alert(`L'image "${file.name}" est trop volumineuse (${(file.size / 1024 / 1024).toFixed(1)}MB). Taille maximum: ${maxSizeMB}MB`);
                        return;
                    }
                    
                    validFiles.push(file);
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'relative group bg-white/5 rounded-lg p-3 border border-white/10';
                        
                        // Format file size
                        const fileSizeKB = (file.size / 1024).toFixed(1);
                        const fileSizeMB = (file.size / 1024 / 1024).toFixed(1);
                        const displaySize = file.size > 1024 * 1024 ? `${fileSizeMB}MB` : `${fileSizeKB}KB`;
                        
                        previewItem.innerHTML = `
                            <div class="flex flex-col space-y-2">
                                <img src="${e.target.result}" alt="Preview ${index + 1}" 
                                     class="w-full h-20 sm:h-24 object-cover rounded-lg border border-white/20">
                                <div class="text-xs text-white/70">
                                    <div class="font-medium truncate" title="${file.name}">${file.name}</div>
                                    <div class="text-white/50">${displaySize}</div>
                                </div>
                                <button type="button" onclick="removeImage(${index})" 
                                        class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600 transition-colors">
                                    ×
                                </button>
                            </div>
                        `;
                        preview.appendChild(previewItem);
                    };
                    reader.readAsDataURL(file);
                } else {
                    alert(`Le fichier "${file.name}" n'est pas une image valide.`);
                }
            });
            
            // Update the file input with only valid files
            if (validFiles.length !== files.length) {
                const dt = new DataTransfer();
                validFiles.forEach(file => dt.items.add(file));
                e.target.files = dt.files;
                
                if (validFiles.length > 0) {
                    label.textContent = `${validFiles.length} image(s) sélectionnée(s)`;
                } else {
                    label.textContent = 'Choisir les Images';
                    preview.classList.add('hidden');
                }
            }
        });
        
        // Function to remove image from selection
        function removeImage(index) {
            const input = document.getElementById('screenshots');
            const dt = new DataTransfer();
            const files = input.files;
            
            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }
            
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
        }
        
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = this.querySelector('i');
            
            if (mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.remove('hidden');
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times');
            } else {
                mobileMenu.classList.add('hidden');
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const mobileMenu = document.getElementById('mobile-menu');
            const menuButton = document.getElementById('mobile-menu-button');
            
            if (!menuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.add('hidden');
                const menuIcon = menuButton.querySelector('i');
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });
        
        // Close mobile menu on window resize to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                const mobileMenu = document.getElementById('mobile-menu');
                const menuButton = document.getElementById('mobile-menu-button');
                const menuIcon = menuButton.querySelector('i');
                
                mobileMenu.classList.add('hidden');
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });
        
        // Auto-hide success/error messages
        setTimeout(function() {
            const alerts = document.querySelectorAll('[class*="bg-red-500/10"], [class*="bg-green-500/10"]');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>