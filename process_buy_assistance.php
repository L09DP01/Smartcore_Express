<?php
// Configuration de l'email
$admin_email = "lorvensondp4282@gmail.com";
$site_name = "Smartcore Express";

// Vérifier si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer et nettoyer les données du formulaire
    $nom = htmlspecialchars(trim($_POST['nom'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $telephone = htmlspecialchars(trim($_POST['telephone'] ?? ''));
    $ville = htmlspecialchars(trim($_POST['ville'] ?? ''));
    $produit1 = htmlspecialchars(trim($_POST['produit1'] ?? ''));
    $description1 = htmlspecialchars(trim($_POST['description1'] ?? ''));
    $produit2 = htmlspecialchars(trim($_POST['produit2'] ?? ''));
    $description2 = htmlspecialchars(trim($_POST['description2'] ?? ''));
    $commentaires = htmlspecialchars(trim($_POST['commentaires'] ?? ''));
    
    // Validation des champs obligatoires
    $errors = [];
    
    if (empty($nom)) {
        $errors[] = "Le nom complet est requis.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Une adresse email valide est requise.";
    }
    
    if (empty($telephone)) {
        $errors[] = "Le numéro de téléphone est requis.";
    }
    
    if (empty($ville)) {
        $errors[] = "La ville est requise.";
    }
    
    if (empty($produit1)) {
        $errors[] = "Au moins un lien de produit est requis.";
    }
    
    if (empty($description1)) {
        $errors[] = "La description du premier produit est requise.";
    }
    
    // Si pas d'erreurs, envoyer l'email
    if (empty($errors)) {
        // Préparer le contenu de l'email
        $subject = "Nouvelle demande d'achat assisté - $site_name";
        
        $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0047AB; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #0047AB; }
        .value { margin-top: 5px; padding: 8px; background-color: white; border-left: 3px solid #FF6B00; }
        .footer { background-color: #1A1A1A; color: white; padding: 15px; text-align: center; font-size: 12px; }
    </style>
    <link rel="icon" type="image/png" href="client/logo.png">
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Nouvelle Demande d'Achat Assisté</h1>
            <p>$site_name</p>
        </div>
        
        <div class='content'>
            <h2>Informations du Client</h2>
            
            <div class='field'>
                <div class='label'>Nom complet:</div>
                <div class='value'>$nom</div>
            </div>
            
            <div class='field'>
                <div class='label'>Email:</div>
                <div class='value'>$email</div>
            </div>
            
            <div class='field'>
                <div class='label'>Téléphone:</div>
                <div class='value'>$telephone</div>
            </div>
            
            <div class='field'>
                <div class='label'>Ville en Haïti:</div>
                <div class='value'>$ville</div>
            </div>
            
            <h2>Produits Demandés</h2>
            
            <div class='field'>
                <div class='label'>Produit 1 - Lien:</div>
                <div class='value'><a href='$produit1' target='_blank'>$produit1</a></div>
            </div>
            
            <div class='field'>
                <div class='label'>Produit 1 - Description:</div>
                <div class='value'>$description1</div>
            </div>";
            
        // Ajouter le produit 2 s'il existe
        if (!empty($produit2)) {
            $message .= "
            <div class='field'>
                <div class='label'>Produit 2 - Lien:</div>
                <div class='value'><a href='$produit2' target='_blank'>$produit2</a></div>
            </div>";
        }
        
        if (!empty($description2)) {
            $message .= "
            <div class='field'>
                <div class='label'>Produit 2 - Description:</div>
                <div class='value'>$description2</div>
            </div>";
        }
        
        // Ajouter les commentaires s'ils existent
        if (!empty($commentaires)) {
            $message .= "
            <div class='field'>
                <div class='label'>Commentaires/Instructions spéciales:</div>
                <div class='value'>$commentaires</div>
            </div>";
        }
        
        $message .= "
        </div>
        
        <div class='footer'>
            <p>Cette demande a été soumise via le site web $site_name</p>
            <p>Date: " . date('d/m/Y à H:i:s') . "</p>
        </div>
    </div>
</body>
</html>";
        
        // En-têtes pour l'email HTML
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@smartcoreexpress.com" . "\r\n";
        $headers .= "Reply-To: $email" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Envoyer l'email
        if (mail($admin_email, $subject, $message, $headers)) {
            // Redirection avec message de succès
            header("Location: buy_assitance.html?success=1");
            exit();
        } else {
            // Redirection avec message d'erreur
            header("Location: buy_assitance.html?error=email");
            exit();
        }
    } else {
        // Redirection avec erreurs de validation
        $error_msg = urlencode(implode(', ', $errors));
        header("Location: buy_assitance.html?error=validation&msg=$error_msg");
        exit();
    }
} else {
    // Si accès direct au fichier, rediriger vers le formulaire
    header("Location: buy_assitance.html");
    exit();
}
?>