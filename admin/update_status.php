<?php
session_start();
require_once '../config/database.php';
require_once '../includes/email_notifications.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = $_POST['package_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    if (!$package_id || !$new_status) {
        $_SESSION['error'] = 'Données manquantes pour la mise à jour du statut.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    try {
        $pdo = getDBConnection();
        
        // Commencer une transaction
        $pdo->beginTransaction();
        
        // Mettre à jour le statut du colis
        $stmt = $pdo->prepare("UPDATE colis SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $package_id]);
        
        // Récupérer les informations du colis et de l'utilisateur pour la mise à jour de suivi
        $stmt = $pdo->prepare("SELECT c.tracking_number, c.user_id, c.status as old_status, u.email, u.first_name, u.last_name FROM colis c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
        $stmt->execute([$package_id]);
        $colis = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($colis) {
            // Ajouter une entrée dans tracking_updates
            $update_message = "Statut mis à jour: " . $new_status;
            if (!empty($notes)) {
                $update_message .= " - " . $notes;
            }
            
            $stmt = $pdo->prepare("INSERT INTO tracking_updates (colis_id, status, location, description, timestamp, created_by) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$package_id, $new_status, 'Centre de tri', $update_message, $_SESSION['user_id']]);
            
            // Créer une notification pour le client
            $notification_title = "Mise à jour du statut";
            $notification_message = "Le statut de votre colis {$colis['tracking_number']} a été mis à jour: {$new_status}";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, colis_id, title, message, type, created_at) VALUES (?, ?, ?, ?, 'info', NOW())");
            $stmt->execute([$colis['user_id'], $package_id, $notification_title, $notification_message]);
            
            // Envoyer un email de notification pour le changement de statut
            $user_name = $colis['first_name'] . ' ' . $colis['last_name'];
            $location = 'Centre de tri'; // Vous pouvez adapter selon le statut
            
            // Adapter la localisation selon le statut
            switch($new_status) {
                case 'Reçue à entrepôt':
                    $location = 'Entrepôt Miami';
                    break;
                case 'En preparation':
                    $location = 'Entrepôt Miami';
                    break;
                case 'Expédié vers Haïti':
                    $location = 'En transit vers Haïti';
                    break;
                case 'Arrivé en Haïti':
                    $location = 'Port-au-Prince, Haïti';
                    break;
                case 'En dédouanement':
                    $location = 'Douanes Haïti';
                    break;
                case 'Prêt pour livraison':
                    $location = 'Centre de distribution';
                    break;
                case 'Livré':
                    $location = 'Destination finale';
                    break;
            }
            
            if ($new_status === 'Livré') {
                // Email spécial pour la livraison
                $email_sent = sendDeliveryNotification(
                    $colis['email'],
                    $user_name,
                    $colis['tracking_number'],
                    $location
                );
            } else {
                // Email standard pour changement de statut
                $email_sent = sendStatusUpdateNotification(
                    $colis['email'],
                    $user_name,
                    $colis['tracking_number'],
                    $colis['old_status'],
                    $new_status,
                    $location,
                    $notes
                );
            }
        }
        
        // Valider la transaction
        $pdo->commit();
        
        $success_message = 'Statut mis à jour avec succès.';
        if (isset($email_sent) && $email_sent) {
            $success_message .= ' Email de notification envoyé au client.';
        } elseif (isset($email_sent)) {
            $success_message .= ' (Erreur lors de l\'envoi de l\'email de notification)';
        }
        $_SESSION['success'] = $success_message;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $pdo->rollBack();
        error_log("Erreur mise à jour statut: " . $e->getMessage());
        $_SESSION['error'] = 'Erreur lors de la mise à jour du statut.';
    }
} else {
    $_SESSION['error'] = 'Méthode non autorisée.';
}

// Rediriger vers la page précédente
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?>