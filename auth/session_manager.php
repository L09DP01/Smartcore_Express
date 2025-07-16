<?php
// Gestionnaire de session avec inactivité et remember me
// À inclure au début de chaque page protégée

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Vérifier l'inactivité de session et gérer le remember me
 */
function checkSessionActivity() {
    // Vérifier l'inactivité de session (15 jours = 1296000 secondes)
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        $session_timeout = isset($_COOKIE['remember_token']) ? 1296000 : 3600; // 15 jours si "Se souvenir de moi" est coché, sinon 1 heure
        
        if ($inactive_time > $session_timeout) {
            // Détruire la session
            session_unset();
            session_destroy();
            
            // Supprimer le cookie "remember me" si il existe
            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/');
            }
            
            // Rediriger vers la page de connexion
            header('Location: ../auth/login.php');
            exit();
        }
    }
    
    // Vérifier le cookie "remember me" si pas de session active
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.is_active 
                                   FROM users u 
                                   JOIN remember_tokens rt ON u.id = rt.user_id 
                                   WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1");
            $stmt->execute([$_COOKIE['remember_token']]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Mettre à jour la dernière connexion
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Restaurer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
            } else {
                // Token invalide, supprimer le cookie
                setcookie('remember_token', '', time() - 3600, '/');
                header('Location: ../auth/login.php');
                exit();
            }
        } catch(PDOException $e) {
            error_log("Erreur remember token: " . $e->getMessage());
            header('Location: ../auth/login.php');
            exit();
        }
    }
    
    // Si pas de session et pas de remember token, rediriger vers login
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit();
    }
    
    // Mettre à jour l'activité
    $_SESSION['last_activity'] = time();
}

/**
 * Mettre à jour l'activité de session (pour les requêtes AJAX)
 */
function updateActivity() {
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

/**
 * Déconnecter l'utilisateur et nettoyer les tokens
 */
function logout() {
    if (isset($_SESSION['user_id'])) {
        try {
            $conn = getDBConnection();
            // Supprimer tous les tokens remember de cet utilisateur
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch(PDOException $e) {
            error_log("Erreur logout: " . $e->getMessage());
        }
    }
    
    // Supprimer le cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Détruire la session
    session_unset();
    session_destroy();
}

// Gérer les requêtes AJAX pour mettre à jour l'activité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_activity'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (updateActivity()) {
        echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in'], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// Appeler automatiquement la vérification
checkSessionActivity();
?>