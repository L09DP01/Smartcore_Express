<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier si c'est un nouveau compte OAuth qui doit compléter son profil
if (!isset($_SESSION['complete_profile_required'])) {
    // Rediriger vers le dashboard si le profil n'a pas besoin d'être complété
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../client/dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_POST) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    
    // Validation
    if (empty($firstName)) {
        $error = 'Le prénom est requis.';
    } elseif (empty($lastName)) {
        $error = 'Le nom de famille est requis.';
    } elseif (strlen($firstName) < 2 || strlen($firstName) > 50) {
        $error = 'Le prénom doit contenir entre 2 et 50 caractères.';
    } elseif (strlen($lastName) < 2 || strlen($lastName) > 50) {
        $error = 'Le nom de famille doit contenir entre 2 et 50 caractères.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/u', $firstName)) {
        $error = 'Le prénom contient des caractères non autorisés.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/u', $lastName)) {
        $error = 'Le nom de famille contient des caractères non autorisés.';
    } else {
        try {
            $conn = getDBConnection();
            
            // Mettre à jour le profil utilisateur
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, profile_completed = 1 WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $_SESSION['user_id']]);
            
            // Mettre à jour les informations de session
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            
            // Supprimer le flag de completion de profil
            unset($_SESSION['complete_profile_required']);
            
            // Rediriger vers le dashboard
            if ($_SESSION['role'] === 'admin') {
                header('Location: ../admin/dashboard.php?profile_updated=1');
            } else {
                header('Location: ../client/dashboard.php?profile_updated=1');
            }
            exit();
            
        } catch (Exception $e) {
            error_log('Erreur mise à jour profil: ' . $e->getMessage());
            $error = 'Une erreur est survenue lors de la mise à jour de votre profil.';
        }
    }
}

// Récupérer les informations actuelles de l'utilisateur
$currentFirstName = $_SESSION['first_name'] ?? '';
$currentLastName = $_SESSION['last_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compléter votre profil - Smartcore Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #0047AB 0%, #1e5bb8 100%);
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo et titre -->
        <div class="text-center mb-8">
            <div class="mx-auto w-20 h-20 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg">
                <img src="../img/Logo.png" alt="Smartcore Express" class="w-16 h-16 object-contain">
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Bienvenue !</h1>
            <p class="text-blue-100">Complétez votre profil pour continuer</p>
        </div>

        <!-- Formulaire -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="mb-6">
                <div class="flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mx-auto mb-4">
                    <i class="fas fa-user-edit text-2xl text-blue-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 text-center mb-2">Personnalisez votre profil</h2>
                <p class="text-gray-600 text-center text-sm">
                    Nous avons récupéré vos informations depuis Google. Vous pouvez les modifier si nécessaire.
                </p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                        <span class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Prénom -->
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-gray-400"></i>Prénom *
                    </label>
                    <input 
                        type="text" 
                        id="first_name" 
                        name="first_name" 
                        value="<?php echo htmlspecialchars($currentFirstName); ?>"
                        required 
                        maxlength="50"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        placeholder="Votre prénom"
                    >
                </div>

                <!-- Nom de famille -->
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-gray-400"></i>Nom de famille *
                    </label>
                    <input 
                        type="text" 
                        id="last_name" 
                        name="last_name" 
                        value="<?php echo htmlspecialchars($currentLastName); ?>"
                        required 
                        maxlength="50"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        placeholder="Votre nom de famille"
                    >
                </div>

                <!-- Informations supplémentaires -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mr-2 mt-1"></i>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium mb-1">Informations de votre compte :</p>
                            <p><strong>Email :</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                            <p><strong>Nom d'utilisateur :</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="space-y-4">
                    <button 
                        type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center"
                    >
                        <i class="fas fa-check mr-2"></i>
                        Confirmer et continuer
                    </button>
                    
                    <div class="text-center">
                        <button 
                            type="button" 
                            onclick="skipProfile()" 
                            class="text-gray-500 hover:text-gray-700 text-sm underline transition duration-200"
                        >
                            Passer cette étape (vous pourrez modifier plus tard)
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-blue-100 text-sm">
                © 2024 Smartcore Express. Tous droits réservés.
            </p>
        </div>
    </div>

    <script>
        function skipProfile() {
            if (confirm('Êtes-vous sûr de vouloir passer cette étape ? Vous pourrez modifier votre profil plus tard dans les paramètres.')) {
                // Envoyer une requête pour supprimer le flag de completion de profil
                fetch('skip_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Rediriger vers le dashboard
                        window.location.href = '<?php echo $_SESSION['role'] === 'admin' ? '../admin/dashboard.php' : '../client/dashboard.php'; ?>?profile_skipped=1';
                    } else {
                        alert('Erreur lors du traitement. Veuillez réessayer.');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    // Rediriger quand même en cas d'erreur
                    window.location.href = '<?php echo $_SESSION['role'] === 'admin' ? '../admin/dashboard.php' : '../client/dashboard.php'; ?>?profile_skipped=1';
                });
            }
        }

        // Validation côté client
        document.querySelector('form').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            
            if (firstName.length < 2 || firstName.length > 50) {
                e.preventDefault();
                alert('Le prénom doit contenir entre 2 et 50 caractères.');
                return;
            }
            
            if (lastName.length < 2 || lastName.length > 50) {
                e.preventDefault();
                alert('Le nom de famille doit contenir entre 2 et 50 caractères.');
                return;
            }
            
            const nameRegex = /^[a-zA-ZÀ-ÿ\s\'-]+$/;
            if (!nameRegex.test(firstName)) {
                e.preventDefault();
                alert('Le prénom contient des caractères non autorisés.');
                return;
            }
            
            if (!nameRegex.test(lastName)) {
                e.preventDefault();
                alert('Le nom de famille contient des caractères non autorisés.');
                return;
            }
        });
    </script>
</body>
</html>