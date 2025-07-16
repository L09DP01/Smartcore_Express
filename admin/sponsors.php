<?php
// Inclure le gestionnaire de session avec gestion d'inactivité
require_once '../auth/session_manager.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Vérifier si l'utilisateur est admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getDBConnection();

// Créer la table sponsors_config si elle n'existe pas
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sponsors_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            message_content TEXT NOT NULL,
            daily_frequency INT DEFAULT 1,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Créer la table sponsors_logs pour suivre les envois
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sponsors_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            message_content TEXT,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('sent', 'failed') DEFAULT 'sent',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch(PDOException $e) {
    error_log("Erreur création tables sponsors: " . $e->getMessage());
}

// Traitement des actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_message':
                $messageContent = trim($_POST['message_content']);
                $dailyFreq = (int)$_POST['daily_frequency'];
                
                if (!empty($messageContent) && $dailyFreq > 0) {
                    try {
                        // Sauvegarder la configuration
                        $stmt = $conn->prepare("
                            INSERT INTO sponsors_config (message_content, daily_frequency) 
                            VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            message_content = VALUES(message_content), 
                            daily_frequency = VALUES(daily_frequency),
                            updated_at = CURRENT_TIMESTAMP
                        ");
                        $stmt->execute([$messageContent, $dailyFreq]);
                        
                        // Récupérer tous les clients actifs
                        $stmt = $conn->prepare("SELECT id, email, first_name, last_name FROM users WHERE role = 'client' AND is_active = 1");
                        $stmt->execute();
                        $clients = $stmt->fetchAll();
                        
                        $sentCount = 0;
                        $failedCount = 0;
                        
                        foreach ($clients as $client) {
                            try {
                                // Envoyer l'email (utiliser la fonction d'envoi d'email existante)
                                $emailSent = sendSponsorEmail($client['email'], $client['first_name'], $messageContent);
                                
                                if ($emailSent) {
                                    // Logger l'envoi réussi
                                    $logStmt = $conn->prepare("INSERT INTO sponsors_logs (user_id, message_content, status) VALUES (?, ?, 'sent')");
                                    $logStmt->execute([$client['id'], $messageContent]);
                                    $sentCount++;
                                } else {
                                    // Logger l'échec
                                    $logStmt = $conn->prepare("INSERT INTO sponsors_logs (user_id, message_content, status) VALUES (?, ?, 'failed')");
                                    $logStmt->execute([$client['id'], $messageContent]);
                                    $failedCount++;
                                }
                            } catch (Exception $e) {
                                $failedCount++;
                                error_log("Erreur envoi email sponsor: " . $e->getMessage());
                            }
                        }
                        
                        $message = "Message envoyé avec succès à {$sentCount} clients.";
                        if ($failedCount > 0) {
                            $message .= " {$failedCount} envois ont échoué.";
                        }
                        
                    } catch(PDOException $e) {
                        $error = "Erreur lors de l'envoi: " . $e->getMessage();
                    }
                } else {
                    $error = "Veuillez remplir tous les champs requis.";
                }
                break;
                
            case 'clear_all':
                try {
                    // Supprimer tous les logs
                    $conn->exec("DELETE FROM sponsors_logs");
                    // Supprimer la configuration
                    $conn->exec("DELETE FROM sponsors_config");
                    
                    $message = "Toutes les données ont été effacées avec succès.";
                } catch(PDOException $e) {
                    $error = "Erreur lors de la suppression: " . $e->getMessage();
                }
                break;
        }
    }
}

// Récupérer la configuration actuelle
$currentConfig = null;
try {
    $stmt = $conn->query("SELECT * FROM sponsors_config ORDER BY updated_at DESC LIMIT 1");
    $currentConfig = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Erreur récupération config: " . $e->getMessage());
}

// Récupérer les statistiques
$totalClients = 0;
$totalSent = 0;
$totalFailed = 0;
$lastSent = null;

try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client' AND is_active = 1");
    $totalClients = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM sponsors_logs WHERE status = 'sent'");
    $totalSent = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM sponsors_logs WHERE status = 'failed'");
    $totalFailed = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT sent_at FROM sponsors_logs ORDER BY sent_at DESC LIMIT 1");
    $lastSentResult = $stmt->fetch();
    $lastSent = $lastSentResult ? $lastSentResult['sent_at'] : null;
} catch(PDOException $e) {
    error_log("Erreur récupération stats: " . $e->getMessage());
}

// Fonction pour envoyer un email sponsor
function sendSponsorEmail($email, $firstName, $messageContent) {
    try {
        
        $mail = new PHPMailer(true);
        
        // Configuration SMTP Hostinger
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'contact@smartcoreexpress.com';
        $mail->Password = 'Lorvens22@';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        
        // Destinataire
        $mail->setFrom('contact@smartcoreexpress.com', 'Smartcore Express');
        $mail->addAddress($email, $firstName);
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = 'Important - Smartcore Express';
        
        $htmlContent = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Message de nos sponsors - Smartcore Express</title>
            <style>
                body {
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 0 20px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #0047AB, #1e5bb8);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .logo {
                    width: 80px;
                    height: 80px;
                    background-color: white;
                    border-radius: 50%;
                    margin: 0 auto 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                }
                .logo img {
                    width: 70px;
                    height: 70px;
                    object-fit: contain;
                }
                .content {
                    padding: 40px 30px;
                }
                .sponsor-message {
                    font-size: 24px;
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .message-box {
                    background-color: #f8f9fa;
                    border: 2px solid #0047AB;
                    border-radius: 10px;
                    padding: 25px;
                    margin: 30px 0;
                    text-align: left;
                }
                .message-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }
                .message-content {
                    background-color: white;
                    padding: 20px;
                    border-radius: 8px;
                    border: 1px solid #e0e0e0;
                    line-height: 1.8;
                }
                .info-notice {
                    background-color: #e7f3ff;
                    border: 1px solid #b3d9ff;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 30px 0;
                }
                .info-title {
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 10px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .footer {
                    background-color: #f8f9fa;
                    padding: 30px;
                    text-align: center;
                    color: #666;
                    border-top: 1px solid #e0e0e0;
                }
                .company-info {
                    margin-top: 20px;
                    font-size: 14px;
                }
                @media (max-width: 600px) {
                    .container {
                        margin: 10px;
                        border-radius: 5px;
                    }
                    .content {
                        padding: 20px 15px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">
                        <img src="https://smartcoreexpress.com/img/Logo.png" alt="Smartcore Express Logo">
                    </div>
                    <h1 style="margin: 0; font-size: 28px;">SMARTCORE EXPRESS</h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Message de nos partenaires sponsors</p>
                </div>
                
                <div class="content">
                    <div class="sponsor-message">
                        📢 Important
                    </div>
                    
                    <p>Bonjour <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
                    
                        
                        <div class="message-content">
                            ' . nl2br(htmlspecialchars($messageContent)) . '
                      
                    </div>
                    
                    <p>Merci de faire confiance à <strong>Smartcore Express</strong> pour tous vos envois vers Haïti.</p>
                    
                    <p>Cordialement,<br><strong>L\'équipe Smartcore Express</strong></p>
                </div>
                
                <div class="footer">
                    <p><strong>SMARTCORE EXPRESS</strong></p>
                    <p>Service de livraison rapide et sécurisé vers Haïti</p>
                    
                    <div class="company-info">
                        <p>© 2024 Smartcore Express. Tous droits réservés.</p>
                        <p>Site web: <a href="https://smartcoreexpress.com" style="color: #0047AB;">smartcoreexpress.com</a></p>
                        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->Body = $htmlContent;
        $mail->AltBody = strip_tags($messageContent);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email sponsor: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Sponsors - Smartcore Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
    <link rel="stylesheet" href="../css/theme.css">\n    <link rel="stylesheet" href="../css/admin-responsive.css">
    <script src="../js/theme.js"></script>\n    <script src="../js/admin-responsive.js"></script>
    <script src="../js/session_activity.js"></script>
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
                <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="colis_management.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-box mr-3"></i>
                    Gestion Colis
                </a>
                <a href="users.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-users mr-3"></i>
                    Utilisateurs
                </a>
                <a href="reports.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Rapports
                </a>
                <a href="sponsors.php" class="flex items-center px-6 py-3 bg-primary text-white border-r-4 border-secondary">
                    <i class="fas fa-bullhorn mr-3"></i>
                    Sponsors
                </a>
                <a href="settings.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-primary hover:text-white transition">
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
        
        <!-- Main Content -->
        <main class="admin-main flex-1 overflow-x-hidden overflow-y-auto">
            <!-- Header -->
            <header class="admin-header bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-800">Gestion Sponsors</h1>
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-users mr-1"></i>
                            <?php echo number_format($totalClients); ?> clients actifs
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <main class="p-6">
                <!-- Messages d'alerte -->
                <?php if (!empty($message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <!-- Statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Clients Actifs</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalClients); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-paper-plane text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Messages Envoyés</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalSent); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Échecs d'Envoi</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalFailed); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Dernier Envoi</p>
                                <p class="text-sm font-bold text-gray-900">
                                    <?php echo $lastSent ? date('d/m/Y H:i', strtotime($lastSent)) : 'Aucun'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formulaire d'envoi de message -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-bullhorn mr-2 text-primary"></i>
                            Envoyer un Message Sponsor
                        </h3>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="send_message">
                            
                            <div>
                                <label for="message_content" class="block text-sm font-medium text-gray-700 mb-2">
                                    Message à envoyer à tous les clients
                                </label>
                                <textarea 
                                    id="message_content" 
                                    name="message_content" 
                                    rows="6" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="Saisissez votre message ici..."
                                    required><?php echo htmlspecialchars($currentConfig['message_content'] ?? ''); ?></textarea>
                            </div>
                            
                            <div>
                                <label for="daily_frequency" class="block text-sm font-medium text-gray-700 mb-2">
                                    Fréquence d'envoi par jour
                                </label>
                                <select 
                                    id="daily_frequency" 
                                    name="daily_frequency" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                    required>
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($currentConfig && $currentConfig['daily_frequency'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> fois par jour
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="flex space-x-3">
                                <button 
                                    type="submit" 
                                    class="flex-1 bg-primary text-white py-2 px-4 rounded-md hover:bg-blue-700 transition font-medium"
                                    onclick="return confirm('Êtes-vous sûr de vouloir envoyer ce message à tous les clients ?')">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Envoyer à Tous les Clients
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Actions et Configuration -->
                    <div class="stat-card bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-cogs mr-2 text-secondary"></i>
                            Actions et Configuration
                        </h3>
                        
                        <div class="space-y-4">
                            <!-- Configuration actuelle -->
                            <?php if ($currentConfig): ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-800 mb-2">Configuration Actuelle</h4>
                                <p class="text-sm text-gray-600 mb-2">
                                    <strong>Fréquence:</strong> <?php echo $currentConfig['daily_frequency']; ?> fois par jour
                                </p>
                                <p class="text-sm text-gray-600">
                                    <strong>Dernière mise à jour:</strong> 
                                    <?php echo date('d/m/Y H:i', strtotime($currentConfig['updated_at'])); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Bouton d'effacement -->
                            <div class="border-t pt-4">
                                <h4 class="font-medium text-gray-800 mb-3">Zone de Danger</h4>
                                <form method="POST" onsubmit="return confirmClearAll()">
                                    <input type="hidden" name="action" value="clear_all">
                                    <button 
                                        type="submit" 
                                        class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition font-medium">
                                        <i class="fas fa-trash mr-2"></i>
                                        Effacer Toutes les Données
                                    </button>
                                </form>
                                <p class="text-xs text-gray-500 mt-2">
                                    Cette action supprimera tous les logs d'envoi et la configuration actuelle.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        function confirmClearAll() {
            return confirm('ATTENTION: Cette action supprimera définitivement tous les logs d\'envoi et la configuration des sponsors. Cette action est irréversible. Êtes-vous absolument sûr de vouloir continuer ?');
        }
        
        // Initialiser le gestionnaire de session
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page Sponsors - Gestionnaire de session actif');
        });
    </script>
</body>
</html>