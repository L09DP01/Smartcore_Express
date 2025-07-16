<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    // Supprimer le flag de completion de profil de la session
    unset($_SESSION['complete_profile_required']);
    
    // Optionnel: marquer dans la base de données que l'utilisateur a choisi de passer cette étape
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE users SET profile_completed = 1 WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Profil marqué comme passé']);
    
} catch (Exception $e) {
    error_log('Erreur skip_profile: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>