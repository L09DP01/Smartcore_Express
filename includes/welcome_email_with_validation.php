<?php
/**
 * Script PHP pour envoyer un email de bienvenue avec validation
 * Si l'envoi échoue, l'utilisateur est automatiquement supprimé de la base de données
 * 
 * @author Smartcore Express
 * @version 1.0
 */

require_once '../vendor/autoload.php'; // Assurez-vous que PHPMailer est installé via Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Configuration de la base de données
 */
define('DB_HOST', 'srv449.hstgr.io');
define('DB_NAME', 'u929653200_smartcore_db');
define('DB_USER', 'u929653200_smartcore_db');
define('DB_PASS', 'Lorvens22@');

/**
 * Configuration SMTP
 */
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USERNAME', 'noreply@smartcoreexpress.com');
define('SMTP_PASSWORD', 'Lorvens22@');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');

/**
 * Classe pour gérer l'envoi d'emails de bienvenue avec validation
 */
class WelcomeEmailValidator {
    private $mysqli;
    private $mail;
    
    public function __construct() {
        $this->initDatabase();
        $this->initMailer();
    }
    
    /**
     * Initialiser la connexion à la base de données
     */
    private function initDatabase() {
        try {
            $this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->mysqli->connect_error) {
                throw new Exception('Erreur de connexion à la base de données: ' . $this->mysqli->connect_error);
            }
            
            $this->mysqli->set_charset('utf8mb4');
        } catch (Exception $e) {
            error_log('Erreur base de données: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Initialiser PHPMailer
     */
    private function initMailer() {
        $this->mail = new PHPMailer(true);
        
        try {
            // Configuration SMTP
            $this->mail->isSMTP();
            $this->mail->Host = SMTP_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = SMTP_USERNAME;
            $this->mail->Password = SMTP_PASSWORD;
            $this->mail->SMTPSecure = SMTP_SECURE;
            $this->mail->Port = SMTP_PORT;
            
            // Configuration de l'expéditeur
            $this->mail->setFrom(SMTP_USERNAME, 'Smartcore Express');
            
            // Configuration générale
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log('Erreur configuration PHPMailer: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Envoyer un email de bienvenue et valider l'adresse
     * 
     * @param string $email Adresse email du destinataire
     * @param string $firstName Prénom de l'utilisateur
     * @param string $lastName Nom de famille de l'utilisateur
     * @param string $username Nom d'utilisateur
     * @return array Résultat de l'opération
     */
    public function sendWelcomeEmailWithValidation($email, $firstName = '', $lastName = '', $username = '') {
        $result = [
            'success' => false,
            'message' => '',
            'user_deleted' => false,
            'smtp_error' => ''
        ];
        
        try {
            // Vérifier si l'utilisateur existe dans la base de données
            $user = $this->getUserByEmail($email);
            if (!$user) {
                $result['message'] = 'Utilisateur non trouvé dans la base de données.';
                return $result;
            }
            
            // Utiliser les données de la base si les paramètres ne sont pas fournis
            $firstName = $firstName ?: $user['first_name'];
            $lastName = $lastName ?: $user['last_name'];
            $username = $username ?: $user['username'];
            
            // Préparer l'email
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, trim($firstName . ' ' . $lastName));
            
            $this->mail->Subject = 'Bienvenue chez Smartcore Express !';
            $this->mail->Body = $this->generateWelcomeEmailTemplate($firstName, $lastName, $username, $email, '');
            $this->mail->AltBody = $this->generatePlainTextEmail($firstName, $lastName, $username);
            
            // Tentative d'envoi de l'email
            if ($this->mail->send()) {
                $result['success'] = true;
                $result['message'] = 'Email de bienvenue envoyé avec succès.';
                
                // Marquer l'email comme vérifié dans la base de données
                $this->markEmailAsVerified($email);
                
                // Log de succès
                error_log("Email de bienvenue envoyé avec succès à: $email");
                
            } else {
                throw new Exception('Échec de l\'envoi de l\'email');
            }
            
        } catch (Exception $e) {
            // Capturer l'erreur SMTP
            $result['smtp_error'] = $this->mail->ErrorInfo;
            $result['message'] = 'Adresse email invalide ou non fonctionnelle. Veuillez en entrer une autre.';
            
            // Log de l'erreur
            error_log("Erreur envoi email à $email: " . $e->getMessage() . " | SMTP Error: " . $this->mail->ErrorInfo);
            
            // Supprimer l'utilisateur de la base de données
            $deleteResult = $this->deleteUserByEmail($email);
            $result['user_deleted'] = $deleteResult['success'];
            
            if ($deleteResult['success']) {
                $result['message'] .= ' L\'utilisateur a été supprimé de la base de données.';
                error_log("Utilisateur supprimé de la base de données: $email");
            } else {
                $result['message'] .= ' Erreur lors de la suppression de l\'utilisateur: ' . $deleteResult['message'];
                error_log("Erreur suppression utilisateur $email: " . $deleteResult['message']);
            }
        }
        
        return $result;
    }
    
    /**
     * Récupérer un utilisateur par son email
     * 
     * @param string $email
     * @return array|null
     */
    private function getUserByEmail($email) {
        try {
            $stmt = $this->mysqli->prepare("SELECT id, username, email, first_name, last_name FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            
            $result = $stmt->get_result();
            return $result->fetch_assoc();
            
        } catch (Exception $e) {
            error_log('Erreur récupération utilisateur: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Supprimer un utilisateur par son email
     * 
     * @param string $email
     * @return array
     */
    private function deleteUserByEmail($email) {
        $result = ['success' => false, 'message' => ''];
        
        try {
            $stmt = $this->mysqli->prepare("DELETE FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $result['success'] = true;
                    $result['message'] = 'Utilisateur supprimé avec succès.';
                } else {
                    $result['message'] = 'Aucun utilisateur trouvé avec cette adresse email.';
                }
            } else {
                $result['message'] = 'Erreur lors de l\'exécution de la requête de suppression.';
            }
            
        } catch (Exception $e) {
            $result['message'] = 'Erreur base de données: ' . $e->getMessage();
            error_log('Erreur suppression utilisateur: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Marquer l'email comme vérifié
     * 
     * @param string $email
     */
    private function markEmailAsVerified($email) {
        try {
            $stmt = $this->mysqli->prepare("UPDATE users SET email_verified = 1 WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
        } catch (Exception $e) {
            error_log('Erreur mise à jour email_verified: ' . $e->getMessage());
        }
    }
    
    /**
     * Générer le template HTML de l'email de bienvenue
     * 
     * @param string $firstName
     * @param string $lastName
     * @param string $username
     * @param string $email
     * @param string $password
     * @return string
     */
    private function generateWelcomeEmailTemplate($firstName, $lastName, $username, $email, $password = '') {
        $loginUrl = 'https://smartcoreexpress.com/auth/login.php';
        
        return '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Bienvenue chez Smartcore Express</title>
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
                .welcome-message {
                    font-size: 24px;
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .credentials-box {
                    background-color: #f8f9fa;
                    border: 2px solid #0047AB;
                    border-radius: 10px;
                    padding: 25px;
                    margin: 30px 0;
                    text-align: center;
                }
                .credentials-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #0047AB;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }
                .credential-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin: 15px 0;
                    padding: 15px;
                    background-color: white;
                    border-radius: 8px;
                    border: 1px solid #e0e0e0;
                }
                .credential-label {
                    font-weight: bold;
                    color: #555;
                    flex: 1;
                }
                .credential-value {
                    font-family: monospace;
                    background-color: #f1f3f4;
                    padding: 8px 12px;
                    border-radius: 5px;
                    border: 1px solid #d0d0d0;
                    flex: 2;
                    margin: 0 10px;
                    word-break: break-all;
                }

                .login-button {
                    display: inline-block;
                    background-color: #0047AB;
                    color: white !important;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: bold;
                    margin: 20px 0;
                    transition: background-color 0.3s;
                }
                .login-button:hover {
                    background-color: #003d96;
                }
                .security-notice {
                    background-color: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 30px 0;
                }
                .security-title {
                    font-weight: bold;
                    color: #856404;
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
                    .credential-item {
                        flex-direction: column;
                        gap: 10px;
                    }
                    .credential-value {
                        margin: 0;
                        width: 100%;
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
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Votre partenaire logistique de confiance</p>
                </div>
                
                <div class="content">
                    <div class="welcome-message">
                        🎉 Bienvenue ' . htmlspecialchars($firstName) . ' !
                    </div>
                    
                    <p>Cher(e) <strong>' . htmlspecialchars($firstName . ' ' . $lastName) . '</strong>,</p>
                    
                    <p>Nous sommes ravis de vous accueillir dans la famille <strong>Smartcore Express</strong> ! Votre compte a été créé avec succès et vous pouvez maintenant profiter de tous nos services de livraison internationale.</p>
                    ' . ($password ? '
                    <div class="credentials-box">
                        <div class="credentials-title">
                            🔐 Vos identifiants de connexion
                        </div>
                        
                        <div class="credential-item">
                            <div class="credential-label">Email :</div>
                            <div class="credential-value">' . htmlspecialchars($email) . '</div>
                        </div>
                        
                        <div class="credential-item">
                            <div class="credential-label">Mot de passe :</div>
                            <div class="credential-value">' . htmlspecialchars($password) . '</div>
                        </div>
                    </div>' : '') . '
                    
                    <div style="text-align: center;">
                        <a href="' . $loginUrl . '" class="login-button">
                            🚀 Se connecter maintenant
                        </a>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="https://smartcoreexpress.com/client/dashboard.php" class="cta-button" style="display: inline-block; background-color: #28a745; color: white !important; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; transition: background-color 0.3s;">
                            Accéder à mon espace
                        </a>
                    </div>
                    ' . ($password ? '
                    <div class="security-notice">
                        <div class="security-title">
                            🛡️ Conseils de sécurité
                        </div>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Changez votre mot de passe lors de votre première connexion</li>
                            <li>Ne partagez jamais vos identifiants avec qui que ce soit</li>
                            <li>Déconnectez-vous toujours après utilisation</li>
                            <li>Contactez-nous immédiatement si vous suspectez une activité suspecte</li>
                        </ul>
                    </div>' : '') . '
                    
                    <h3 style="color: #0047AB; margin-top: 30px;">🌟 Que pouvez-vous faire maintenant ?</h3>
                    <ul style="color: #555;">
                        <li><strong>Suivre vos colis</strong> en temps réel</li>
                        <li><strong>Gérer vos expéditions</strong> facilement</li>
                        <li><strong>Accéder à votre historique</strong> complet</li>
                        <li><strong>Bénéficier du support client</strong> 24/7</li>
                    </ul>
                    
                    <p style="margin-top: 30px;">Si vous avez des questions ou besoin d\'aide, n\'hésitez pas à nous contacter. Notre équipe est là pour vous accompagner !</p>
                    
                    <p style="margin-top: 20px;">Cordialement,<br>
                    <strong>L\'équipe Smartcore Express</strong></p>
                </div>
                
                <div class="footer">
                    <p><strong>Smartcore Express</strong></p>
                    <p>Email: contact@smartcoreexpress.com | Téléphone: 40035664</p>
                    <div class="company-info">
                        <p>© 2024 Smartcore Express. Tous droits réservés.</p>
                        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Générer la version texte brut de l'email
     * 
     * @param string $firstName
     * @param string $lastName
     * @param string $username
     * @return string
     */
    private function generatePlainTextEmail($firstName, $lastName, $username) {
        $fullName = trim($firstName . ' ' . $lastName);
        $displayName = $fullName ?: $username;
        
        return "
Bienvenue chez Smartcore Express !

Bonjour $displayName,

Nous sommes ravis de vous accueillir dans la famille Smartcore Express !
Votre compte a été créé avec succès.

Informations de votre compte :
- Nom d'utilisateur : $username
- Nom complet : $fullName

Prochaines étapes :
1. Connectez-vous à votre espace client
2. Explorez nos services de livraison
3. Créez votre première demande d'expédition
4. Suivez vos colis en temps réel

Accédez à votre compte : https://smartcoreexpress.com/auth/login.php

Besoin d'aide ?
Email : contact@smartcoreexpress.com
WhatsApp : +509 4003 5664

© 2024 Smartcore Express. Tous droits réservés.
        ";
    }
    
    /**
     * Fermer les connexions
     */
    public function __destruct() {
        if ($this->mysqli) {
            $this->mysqli->close();
        }
    }
}

/**
 * Fonction utilitaire pour envoyer un email de bienvenue avec validation
 * 
 * @param string $email Adresse email du destinataire
 * @param string $firstName Prénom (optionnel)
 * @param string $lastName Nom (optionnel)
 * @param string $username Nom d'utilisateur (optionnel)
 * @return array Résultat de l'opération
 */
function sendWelcomeEmailWithValidation($email, $firstName = '', $lastName = '', $username = '') {
    try {
        $emailValidator = new WelcomeEmailValidator();
        return $emailValidator->sendWelcomeEmailWithValidation($email, $firstName, $lastName, $username);
    } catch (Exception $e) {
        error_log('Erreur fonction sendWelcomeEmailWithValidation: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erreur système lors de l\'envoi de l\'email.',
            'user_deleted' => false,
            'smtp_error' => $e->getMessage()
        ];
    }
}

// Exemple d'utilisation si le script est appelé directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    // Test avec des données d'exemple
    if (isset($_POST['test_email'])) {
        $testEmail = $_POST['test_email'];
        $result = sendWelcomeEmailWithValidation($testEmail);
        
        echo "<h2>Résultat du test d'envoi d'email</h2>";
        echo "<p><strong>Email testé :</strong> " . htmlspecialchars($testEmail) . "</p>";
        echo "<p><strong>Succès :</strong> " . ($result['success'] ? 'Oui' : 'Non') . "</p>";
        echo "<p><strong>Message :</strong> " . htmlspecialchars($result['message']) . "</p>";
        
        if (!$result['success']) {
            echo "<p><strong>Utilisateur supprimé :</strong> " . ($result['user_deleted'] ? 'Oui' : 'Non') . "</p>";
            if ($result['smtp_error']) {
                echo "<p><strong>Erreur SMTP :</strong> " . htmlspecialchars($result['smtp_error']) . "</p>";
            }
        }
    } else {
        // Formulaire de test
        echo "
        <h2>Test d'envoi d'email de bienvenue</h2>
        <form method='POST'>
            <label for='test_email'>Adresse email à tester :</label><br>
            <input type='email' id='test_email' name='test_email' required style='width: 300px; padding: 5px;'><br><br>
            <button type='submit' style='padding: 10px 20px; background: #0047AB; color: white; border: none; border-radius: 5px;'>Tester l'envoi</button>
        </form>
        ";
    }
}
?>