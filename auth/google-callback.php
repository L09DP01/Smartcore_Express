<?php
session_start();
require_once '../config/database.php';
require_once '../config/oauth_config.php';
require_once '../includes/welcome_email_functions.php';

// Vérifier si Google OAuth est configuré
if (!isGoogleConfigured()) {
    die('Configuration Google OAuth manquante');
}

// Vérifier les paramètres requis
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    die('Paramètres OAuth manquants');
}

// Vérifier l'état CSRF
if (!isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('État OAuth invalide');
}

// Nettoyer l'état de la session
unset($_SESSION['oauth_state']);

try {
    // Échanger le code contre un token d'accès
    $tokenData = exchangeCodeForToken($_GET['code']);
    
    // Obtenir les informations utilisateur
    $userInfo = getUserInfo($tokenData['access_token']);
    
    // Créer ou connecter l'utilisateur
    $user = createOrLoginUser($userInfo);
    
    // Créer la session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    // Vérifier si c'est un nouveau compte qui doit compléter son profil
    if (isset($user['is_new_user']) && $user['is_new_user'] === true) {
        $_SESSION['complete_profile_required'] = true;
        header('Location: complete-profile.php');
    } else {
        // Rediriger selon le rôle pour les utilisateurs existants
        if ($user['role'] === 'admin') {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../client/dashboard.php');
        }
    }
    exit();
    
} catch (Exception $e) {
    error_log('Erreur OAuth Google: ' . $e->getMessage());
    header('Location: login.php?error=' . urlencode('Erreur de connexion Google'));
    exit();
}

/**
 * Échanger le code d'autorisation contre un token d'accès
 */
function exchangeCodeForToken($code) {
    $data = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => GOOGLE_REDIRECT_URI
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Erreur lors de l\'échange du token: ' . $response);
    }
    
    $tokenData = json_decode($response, true);
    if (!$tokenData || !isset($tokenData['access_token'])) {
        throw new Exception('Token d\'accès invalide');
    }
    
    return $tokenData;
}

/**
 * Obtenir les informations utilisateur depuis Google
 */
function getUserInfo($accessToken) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Erreur lors de la récupération des informations utilisateur');
    }
    
    $userInfo = json_decode($response, true);
    if (!$userInfo || !isset($userInfo['email'])) {
        throw new Exception('Informations utilisateur invalides');
    }
    
    return $userInfo;
}

/**
 * Créer ou connecter l'utilisateur
 */
function createOrLoginUser($userInfo) {
    $conn = getDBConnection();
    
    // Vérifier si l'utilisateur existe
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$userInfo['email']]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        // Utilisateur existant - mettre à jour la dernière connexion
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$existingUser['id']]);
        
        return $existingUser;
    } else {
        // Nouvel utilisateur - créer le compte
        $username = generateUsername($userInfo['email']);
        $firstName = $userInfo['given_name'] ?? '';
        $lastName = $userInfo['family_name'] ?? '';
        
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, first_name, last_name, is_active, email_verified, profile_completed, created_at, last_login) 
            VALUES (?, ?, ?, ?, 1, 1, 0, NOW(), NOW())
        ");
        
        $stmt->execute([$username, $userInfo['email'], $firstName, $lastName]);
        
        $userId = $conn->lastInsertId();
        
        // Envoyer l'email de bienvenue pour le nouveau client
        try {
            $emailResult = sendWelcomeEmailOAuth($userInfo['email'], $firstName, $lastName, 'google');
            if ($emailResult['success']) {
                error_log("Email de bienvenue envoyé avec succès à: " . $userInfo['email']);
            } else {
                error_log("Erreur envoi email de bienvenue: " . $emailResult['message']);
            }
        } catch (Exception $e) {
            error_log("Exception lors de l'envoi de l'email de bienvenue: " . $e->getMessage());
        }
        
        // Retourner les données du nouvel utilisateur
        return [
            'id' => $userId,
            'username' => $username,
            'email' => $userInfo['email'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => 'client',
            'is_new_user' => true
        ];
    }
}

/**
 * Générer un nom d'utilisateur unique
 */
function generateUsername($email) {
    $conn = getDBConnection();
    $baseUsername = explode('@', $email)[0];
    $username = $baseUsername;
    $counter = 1;
    
    while (true) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if (!$stmt->fetch()) {
            break;
        }
        
        $username = $baseUsername . $counter;
        $counter++;
    }
    
    return $username;
}
?>