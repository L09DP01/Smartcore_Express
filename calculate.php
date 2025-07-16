<?php
require_once 'config/database.php';

$conn = getDBConnection();
$shipping_cost = null;
$error = '';
$success = '';
$countries = [];
$service_types = [];

// Récupérer la liste des pays disponibles
try {
    $stmt = $conn->query("SELECT DISTINCT destination_country FROM shipping_rates ORDER BY destination_country");
    $countries = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Si aucun pays n'est trouvé, ajouter des valeurs par défaut
    if (empty($countries)) {
        $countries = ['Haïti', 'République Dominicaine', 'États-Unis', 'Canada', 'France'];
    }
    
    // Récupérer les types de service
    $stmt = $conn->query("SELECT DISTINCT service_type FROM shipping_rates ORDER BY service_type");
    $service_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Si aucun type de service n'est trouvé, ajouter des valeurs par défaut
    if (empty($service_types)) {
        $service_types = ['Standard', 'Express', 'Premium'];
    }
} catch(PDOException $e) {
    error_log("Erreur récupération données: " . $e->getMessage());
    // Utiliser des valeurs par défaut en cas d'erreur
    $countries = ['Haïti', 'République Dominicaine', 'États-Unis', 'Canada', 'France'];
    $service_types = ['Standard', 'Express', 'Premium'];
}

// Nouvelle fonction de calcul
function calculateNewShippingCost($category, $model, $weight, $declared_value) {
    switch ($category) {
        case 'telephone':
            // iPhone 16 coûte 80$, tous les autres téléphones 45$
            if (stripos($model, 'iphone 16') !== false) {
                return 80.00;
            } else {
                return 45.00;
            }
            
        case 'laptop':
            // MacBook nouveau modèle coûte 70$, tous les autres laptops 50$
            if (stripos($model, 'macbook') !== false && (stripos($model, 'new') !== false || stripos($model, 'nouveau') !== false)) {
                return 70.00;
            } else {
                return 50.00;
            }
            
        case 'vetement_accessoires':
            // 4.5$/lb avec taxe de 10% si plus de 10lb
            $base_cost = $weight * 4.5;
            if ($weight > 10) {
                $base_cost *= 1.10; // Ajouter 10% de taxe
            }
            return $base_cost;
            
        case 'achat_assiste':
            // 0$ frais de service
            return 0.00;
            
        case 'assurance_premium':
            // 2% de la valeur déclarée
            return $declared_value * 0.02;
            
        default:
            return null;
    }
}

// Traitement du calcul
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $weight = floatval($_POST['weight']);
    $destination = $_POST['destination'];
    $service_type = $_POST['service_type'];
    $declared_value = floatval($_POST['declared_value']);
    $item_category = $_POST['item_category'] ?? '';
    // Récupérer le modèle selon la catégorie
    if ($item_category === 'telephone') {
        $item_model = $_POST['telephone_model'] ?? '';
    } elseif ($item_category === 'laptop') {
        $item_model = $_POST['laptop_model'] ?? '';
    } else {
        $item_model = $_POST['item_model'] ?? '';
    }
    
    // Validation
        if (empty($item_category)) {
            $error = "Veuillez sélectionner une catégorie d'article.";
        } elseif ($item_category === 'telephone' && empty($_POST['telephone_model'])) {
            $error = "Veuillez sélectionner un modèle de téléphone.";
        } elseif ($item_category === 'laptop' && empty($_POST['laptop_model'])) {
            $error = "Veuillez sélectionner un modèle de laptop.";
        } elseif (!in_array($item_category, ['telephone', 'laptop', 'achat_assiste']) && $weight <= 0) {
            $error = "Veuillez entrer un poids valide.";
        } elseif (empty($destination)) {
            $error = "Veuillez sélectionner une destination.";
        } else {
        try {
            // Calculer les frais selon la nouvelle logique
            $shipping_cost = calculateNewShippingCost($item_category, $item_model, $weight, $declared_value);
            
            // Vérifier si le calcul a réussi
            if ($shipping_cost !== null) {
                $success = 'Calcul effectué avec succès!';
            } else {
                $error = 'Impossible de calculer les frais pour cette catégorie d\'article.';
            }
        } catch(PDOException $e) {
            error_log("Erreur calcul: " . $e->getMessage());
            $error = 'Erreur lors du calcul. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculateur de Tarifs - Smartcore Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="img/Logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Smartcore Express">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileColor" content="#0047AB">
    <meta name="msapplication-TileImage" content="img/Logo.png">
    
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .calculator-card {
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHg9IjAiIHk9IjAiIHdpZHRoPSIyIiBoZWlnaHQ9IjIiIGZpbGw9IiMwMDQ3QUIiIGZpbGwtb3BhY2l0eT0iMC4wNSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgeD0iMCIgeT0iMCIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNwYXR0ZXJuKSIvPjwvc3ZnPg==');
        }
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            appearance: textfield;
            -moz-appearance: textfield;
        }
    </style>
    <link rel="icon" type="image/png" href="client/logo.png">
    
    <script src="pwa-global.js" defer></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.html" class="flex items-center">
                        <img src="img/Logo.png" alt="Smartcore Express" class="h-10 w-auto">
                        <span class="ml-2 text-xl font-bold text-primary">Smartcore Express</span>
                    </a>
                </div>
                
                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-4">
                
                    <a href="auth/login.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Mon Compte
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-button" class="text-gray-700 hover:text-primary focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="hidden md:hidden absolute top-full left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-50">
                <div class="px-2 pt-2 pb-3 space-y-1">
                    
                    <a href="auth/login.php" class="block mx-3 my-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-center">
                        Mon Compte
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-primary to-blue-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Calculateur de Tarifs</h1>
            <p class="text-xl mb-8 opacity-90">Estimez le coût de votre expédition en quelques clics</p>
        </div>
    </section>
    
    <!-- Calculateur Section -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="max-w-4xl mx-auto">
                <!-- Messages -->
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
                    <!-- Formulaire de Calcul -->
                    <div class="md:col-span-3">
                        <div class="bg-white rounded-lg shadow-lg p-6 calculator-card">
                            <h2 class="text-2xl font-bold text-gray-800 mb-6">Estimez vos frais d'expédition</h2>
                            
                            <form method="POST" class="space-y-6">
                                <div>
                                    <label for="item_category" class="block text-sm font-medium text-gray-700 mb-1">Catégorie d'article <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-tags text-gray-400"></i>
                                        </div>
                                        <select id="item_category" name="item_category" 
                                                class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                                                required>
                                            <option value="">Sélectionnez une catégorie</option>
                                            <option value="telephone" <?php echo (isset($_POST['item_category']) && $_POST['item_category'] === 'telephone') ? 'selected' : ''; ?>>Téléphone</option>
                                            <option value="laptop" <?php echo (isset($_POST['item_category']) && $_POST['item_category'] === 'laptop') ? 'selected' : ''; ?>>Laptop</option>
                                            <option value="vetement_accessoires" <?php echo (isset($_POST['item_category']) && $_POST['item_category'] === 'vetement_accessoires') ? 'selected' : ''; ?>>Vêtement et Accessoires</option>
                                            <option value="achat_assiste" <?php echo (isset($_POST['item_category']) && $_POST['item_category'] === 'achat_assiste') ? 'selected' : ''; ?>>Achat Assisté</option>
                                            <option value="assurance_premium" <?php echo (isset($_POST['item_category']) && $_POST['item_category'] === 'assurance_premium') ? 'selected' : ''; ?>>Assurance Premium</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Champ modèle pour téléphones -->
                                <div id="telephone_model_field" style="display: none;">
                                    <label for="telephone_model" class="block text-sm font-medium text-gray-700 mb-1">Modèle de téléphone <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-mobile-alt text-gray-400"></i>
                                        </div>
                                        <select id="telephone_model" name="telephone_model" 
                                                class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                                            <option value="">Sélectionnez un modèle</option>
                                            <option value="iPhone 16" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 16') ? 'selected' : ''; ?>>iPhone 16</option>
                                            <option value="iPhone 16 Plus" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 16 Plus') ? 'selected' : ''; ?>>iPhone 16 Plus</option>
                                            <option value="iPhone 16 Pro" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 16 Pro') ? 'selected' : ''; ?>>iPhone 16 Pro</option>
                                            <option value="iPhone 16 Pro Max" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 16 Pro Max') ? 'selected' : ''; ?>>iPhone 16 Pro Max</option>
                                            <option value="iPhone 15" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 15') ? 'selected' : ''; ?>>iPhone 15</option>
                                            <option value="iPhone 15 Plus" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 15 Plus') ? 'selected' : ''; ?>>iPhone 15 Plus</option>
                                            <option value="iPhone 15 Pro" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 15 Pro') ? 'selected' : ''; ?>>iPhone 15 Pro</option>
                                            <option value="iPhone 15 Pro Max" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 15 Pro Max') ? 'selected' : ''; ?>>iPhone 15 Pro Max</option>
                                            <option value="iPhone 14" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 14') ? 'selected' : ''; ?>>iPhone 14</option>
                                            <option value="iPhone 14 Plus" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 14 Plus') ? 'selected' : ''; ?>>iPhone 14 Plus</option>
                                            <option value="iPhone 14 Pro" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 14 Pro') ? 'selected' : ''; ?>>iPhone 14 Pro</option>
                                            <option value="iPhone 14 Pro Max" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 14 Pro Max') ? 'selected' : ''; ?>>iPhone 14 Pro Max</option>
                                            <option value="iPhone 13" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 13') ? 'selected' : ''; ?>>iPhone 13</option>
                                            <option value="iPhone 13 Mini" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 13 Mini') ? 'selected' : ''; ?>>iPhone 13 Mini</option>
                                            <option value="iPhone 13 Pro" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 13 Pro') ? 'selected' : ''; ?>>iPhone 13 Pro</option>
                                            <option value="iPhone 13 Pro Max" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'iPhone 13 Pro Max') ? 'selected' : ''; ?>>iPhone 13 Pro Max</option>
                                            <option value="Samsung Galaxy S24" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'Samsung Galaxy S24') ? 'selected' : ''; ?>>Samsung Galaxy S24</option>
                                            <option value="Samsung Galaxy S24+" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'Samsung Galaxy S24+') ? 'selected' : ''; ?>>Samsung Galaxy S24+</option>
                                            <option value="Samsung Galaxy S24 Ultra" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'Samsung Galaxy S24 Ultra') ? 'selected' : ''; ?>>Samsung Galaxy S24 Ultra</option>
                                            <option value="Google Pixel 8" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'Google Pixel 8') ? 'selected' : ''; ?>>Google Pixel 8</option>
                                            <option value="Google Pixel 8 Pro" <?php echo (isset($_POST['telephone_model']) && $_POST['telephone_model'] === 'Google Pixel 8 Pro') ? 'selected' : ''; ?>>Google Pixel 8 Pro</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Champ modèle pour laptops -->
                                <div id="laptop_model_field" style="display: none;">
                                    <label for="laptop_model" class="block text-sm font-medium text-gray-700 mb-1">Modèle d'ordinateur <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-laptop text-gray-400"></i>
                                        </div>
                                        <select id="laptop_model" name="laptop_model" 
                                                class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                                            <option value="">Sélectionnez un modèle</option>
                                            <option value="MacBook Air M3" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'MacBook Air M3') ? 'selected' : ''; ?>>MacBook Air M3</option>
                                            <option value="MacBook Air M2" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'MacBook Air M2') ? 'selected' : ''; ?>>MacBook Air M2</option>
                                            <option value="MacBook Pro 14\" M3" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'MacBook Pro 14" M3') ? 'selected' : ''; ?>>MacBook Pro 14" M3</option>
                                            <option value="MacBook Pro 16\" M3" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'MacBook Pro 16" M3') ? 'selected' : ''; ?>>MacBook Pro 16" M3</option>
                                            <option value="Dell XPS 13" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'Dell XPS 13') ? 'selected' : ''; ?>>Dell XPS 13</option>
                                            <option value="Dell XPS 15" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'Dell XPS 15') ? 'selected' : ''; ?>>Dell XPS 15</option>
                                            <option value="HP Spectre x360" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'HP Spectre x360') ? 'selected' : ''; ?>>HP Spectre x360</option>
                                            <option value="HP Pavilion" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'HP Pavilion') ? 'selected' : ''; ?>>HP Pavilion</option>
                                            <option value="Lenovo ThinkPad X1" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'Lenovo ThinkPad X1') ? 'selected' : ''; ?>>Lenovo ThinkPad X1</option>
                                            <option value="Lenovo IdeaPad" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'Lenovo IdeaPad') ? 'selected' : ''; ?>>Lenovo IdeaPad</option>
                                            <option value="ASUS ZenBook" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'ASUS ZenBook') ? 'selected' : ''; ?>>ASUS ZenBook</option>
                                            <option value="ASUS ROG" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'ASUS ROG') ? 'selected' : ''; ?>>ASUS ROG</option>
                                            <option value="Surface Laptop" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'Surface Laptop') ? 'selected' : ''; ?>>Surface Laptop</option>
                                            <option value="Surface Pro" <?php echo (isset($_POST['laptop_model']) && $_POST['laptop_model'] === 'Surface Pro') ? 'selected' : ''; ?>>Surface Pro</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div id="weight_field">
                                        <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Poids (lbs) <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-weight text-gray-400"></i>
                                            </div>
                                            <input type="number" id="weight" name="weight" step="0.1" min="0.1" 
                                                   value="<?php echo isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : ''; ?>"
                                                   class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                                                   placeholder="Ex: 5.5">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="destination" class="block text-sm font-medium text-gray-700 mb-1">Destination <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-globe text-gray-400"></i>
                                            </div>
                                            <select id="destination" name="destination" 
                                                    class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                                                    required>
                                                <option value="">Sélectionnez un pays</option>
                                                <?php foreach($countries as $country): ?>
                                                <option value="<?php echo htmlspecialchars($country); ?>" <?php echo (isset($_POST['destination']) && $_POST['destination'] === $country) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($country); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Service fixe Express -->
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de Service</label>
                                    <div class="bg-gray-50 border border-gray-300 rounded-md p-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-shipping-fast text-primary mr-3"></i>
                                            <span class="font-medium text-gray-800">Express - Livraison en 3 à 5 jours ouvrables</span>
                                        </div>
                                    </div>
                                    <input type="hidden" name="service_type" value="Express">
                                    <input type="hidden" name="declared_value" value="0">
                                </div>
                                
                                <div class="pt-4">
                                    <button type="submit" class="w-full bg-primary text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition focus:outline-none focus:ring-2 focus:ring-primary focus:ring-opacity-50">
                                        <i class="fas fa-calculator mr-2"></i> Calculer le Tarif
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Résultat et Informations -->
                    <div class="md:col-span-2">
                        <?php if ($shipping_cost !== null): ?>
                        <!-- Résultat du Calcul -->
                        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Résultat</h3>
                            <div class="bg-blue-50 rounded-lg p-4 mb-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700 font-medium">Coût d'expédition estimé:</span>
                                    <span class="text-2xl font-bold text-primary">$<?php echo number_format($shipping_cost, 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Catégorie:</span>
                                    <span class="font-medium">
                                        <?php 
                                        $categories = [
                                            'telephone' => 'Téléphone',
                                            'laptop' => 'Laptop',
                                            'vetement_accessoires' => 'Vêtement et Accessoires',
                                            'achat_assiste' => 'Achat Assisté',
                                            'assurance_premium' => 'Assurance Premium'
                                        ];
                                        echo $categories[$item_category] ?? $item_category;
                                        ?>
                                    </span>
                                </div>
                                <?php if (!empty($item_model)): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Modèle:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($item_model); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Poids:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($_POST['weight']); ?> lbs</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Destination:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($_POST['destination']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Service:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($_POST['service_type']); ?></span>
                                </div>
                                <?php if (isset($_POST['declared_value']) && $_POST['declared_value'] > 0): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Valeur Déclarée:</span>
                                    <span class="font-medium">$<?php echo number_format($_POST['declared_value'], 2); ?></span>
                                </div>
                                <?php if ($item_category === 'assurance_premium'): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Assurance (2%):</span>
                                    <span class="font-medium">$<?php echo number_format($_POST['declared_value'] * 0.02, 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($item_category === 'vetement_accessoires' && $weight > 10): ?>
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Taxe de 10% appliquée (poids > 10 lbs)
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-6 pt-4 border-t border-gray-200">
                                <a href="auth/login.php" class="block w-full bg-secondary text-white text-center py-2 px-4 rounded-lg hover:bg-orange-600 transition">
                                    <i class="fas fa-box mr-2"></i> Expédier Maintenant
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Informations -->
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Informations</h3>
                            
                            <div class="space-y-4 text-sm">
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-1"><i class="fas fa-info-circle text-primary mr-2"></i> À propos du calcul</h4>
                                    <p class="text-gray-600">Les tarifs sont calculés en fonction de la catégorie d'article, du poids en livres et de la destination.</p>
                                </div>
                                
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-1"><i class="fas fa-truck text-primary mr-2"></i> Notre service</h4>
                                    <p class="text-gray-600"><span class="font-medium">Express:</span> Livraison rapide en 3 à 5 jours ouvrables pour toutes les destinations.</p>
                                </div>
                                
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-1"><i class="fas fa-tags text-primary mr-2"></i> Catégories tarifaires</h4>
                                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                                        <li><span class="font-medium">Téléphones:</span> Tarifs fixes selon le modèle</li>
                                        <li><span class="font-medium">Laptops:</span> Tarifs fixes selon le modèle</li>
                                        <li><span class="font-medium">Vêtements & Accessoires:</span> 4,50$/lb (taxe 10% si > 10 lbs)</li>
                                        <li><span class="font-medium">Achat Assisté:</span> Service gratuit</li>
                                        <li><span class="font-medium">Assurance Premium:</span> 2% de la valeur déclarée</li>
                                    </ul>
                                </div>
                                
                                <div class="bg-yellow-50 rounded-lg p-3">
                                    <p class="text-yellow-800 text-xs">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Ce calculateur fournit une estimation. Les tarifs réels peuvent varier en fonction de facteurs supplémentaires comme les dimensions du colis et les taxes douanières.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    

    
    <!-- FAQ Section -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Questions Fréquentes</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Trouvez des réponses aux questions les plus courantes sur nos tarifs et services</p>
            </div>
            
            <div class="max-w-3xl mx-auto">
                <div class="space-y-4">
                    <!-- Question 1 -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <button class="faq-toggle w-full text-left px-6 py-4 focus:outline-none flex justify-between items-center">
                            <span class="font-medium text-gray-800">Comment sont calculés les frais d'expédition ?</span>
                            <i class="fas fa-chevron-down text-gray-500 transition-transform"></i>
                        </button>
                        <div class="faq-content hidden px-6 pb-4">
                            <p class="text-gray-600">
                                Nos frais d'expédition sont calculés en fonction du poids du colis, de la destination et du type de service choisi. Des frais supplémentaires peuvent s'appliquer pour l'assurance (basée sur la valeur déclarée) et pour les colis surdimensionnés.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Question 2 -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <button class="faq-toggle w-full text-left px-6 py-4 focus:outline-none flex justify-between items-center">
                            <span class="font-medium text-gray-800">Quelle est la différence entre les services Standard, Express et Premium ?</span>
                            <i class="fas fa-chevron-down text-gray-500 transition-transform"></i>
                        </button>
                        <div class="faq-content hidden px-6 pb-4">
                            <p class="text-gray-600">
                                La principale différence réside dans les délais de livraison et les services inclus :
                                <ul class="list-disc list-inside mt-2 space-y-1">
                                    <li>Standard : 5-7 jours ouvrables, suivi de base</li>
                                    <li>Express : 2-3 jours ouvrables, suivi en temps réel, livraison le samedi</li>
                                    <li>Premium : Livraison le jour suivant, service prioritaire, livraison 7j/7</li>
                                </ul>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Question 3 -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <button class="faq-toggle w-full text-left px-6 py-4 focus:outline-none flex justify-between items-center">
                            <span class="font-medium text-gray-800">Pourquoi devrais-je déclarer la valeur de mon colis ?</span>
                            <i class="fas fa-chevron-down text-gray-500 transition-transform"></i>
                        </button>
                        <div class="faq-content hidden px-6 pb-4">
                            <p class="text-gray-600">
                                Déclarer la valeur de votre colis permet d'obtenir une assurance appropriée en cas de perte, de vol ou de dommage. Sans déclaration de valeur, l'indemnisation sera limitée au montant de base inclus dans votre service d'expédition.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Question 4 -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <button class="faq-toggle w-full text-left px-6 py-4 focus:outline-none flex justify-between items-center">
                            <span class="font-medium text-gray-800">Les taxes douanières sont-elles incluses dans l'estimation ?</span>
                            <i class="fas fa-chevron-down text-gray-500 transition-transform"></i>
                        </button>
                        <div class="faq-content hidden px-6 pb-4">
                            <p class="text-gray-600">
                                Non, les taxes douanières et frais d'importation ne sont pas inclus dans notre estimation. Ces frais varient selon les pays et sont généralement payés par le destinataire au moment de la livraison ou avant la réception du colis.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Question 5 -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <button class="faq-toggle w-full text-left px-6 py-4 focus:outline-none flex justify-between items-center">
                            <span class="font-medium text-gray-800">Comment puis-je obtenir un devis personnalisé pour une expédition importante ?</span>
                            <i class="fas fa-chevron-down text-gray-500 transition-transform"></i>
                        </button>
                        <div class="faq-content hidden px-6 pb-4">
                            <p class="text-gray-600">
                                Pour les expéditions importantes ou régulières, nous vous recommandons de contacter directement notre service client pour obtenir un devis personnalisé. Nous offrons des tarifs préférentiels pour les envois en volume et les clients réguliers.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center">
                <div class="flex items-center justify-center mb-4">
                    <img src="img/Logo.png" alt="Smartcore Express" class="h-8 w-auto">
                    <span class="ml-2 text-xl font-bold">Smartcore Express</span>
                </div>
                <p class="text-gray-400 mb-4">Votre partenaire de confiance pour les livraisons rapides et sécurisées</p>
                <div class="flex justify-center space-x-6">
                    <a href="#" class="text-gray-400 hover:text-white transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white transition">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white transition">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white transition">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
                <div class="mt-6 pt-6 border-t border-gray-700">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <p class="text-gray-400 text-sm mb-4 md:mb-0">© 2024 Smartcore Express. Tous droits réservés.</p>
                        <div class="flex space-x-6">
                            <a href="conditions-utilisation.html" class="text-gray-400 hover:text-white text-sm transition">Conditions d'utilisation</a>
                            <a href="politique-confidentialite.html" class="text-gray-400 hover:text-white text-sm transition">Politique de confidentialité</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <script>
        // FAQ Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const faqToggles = document.querySelectorAll('.faq-toggle');
            
            faqToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const icon = this.querySelector('i');
                    
                    // Toggle content
                    if (content.classList.contains('hidden')) {
                        content.classList.remove('hidden');
                        icon.classList.add('transform', 'rotate-180');
                    } else {
                        content.classList.add('hidden');
                        icon.classList.remove('transform', 'rotate-180');
                    }
                    
                    // Close other FAQs
                    faqToggles.forEach(otherToggle => {
                        if (otherToggle !== toggle) {
                            const otherContent = otherToggle.nextElementSibling;
                            const otherIcon = otherToggle.querySelector('i');
                            
                            otherContent.classList.add('hidden');
                            otherIcon.classList.remove('transform', 'rotate-180');
                        }
                    });
                });
            });
            
            // Category change handler
            const categorySelect = document.getElementById('item_category');
            const telephoneModelField = document.getElementById('telephone_model_field');
            const laptopModelField = document.getElementById('laptop_model_field');
            const weightField = document.getElementById('weight_field');
            const weightInput = document.getElementById('weight');
            const telephoneModelSelect = document.getElementById('telephone_model');
            const laptopModelSelect = document.getElementById('laptop_model');
            
            function updateFieldsVisibility() {
                const category = categorySelect.value;
                
                // Masquer tous les champs modèle
                telephoneModelField.style.display = 'none';
                laptopModelField.style.display = 'none';
                
                // Réinitialiser les sélections
                telephoneModelSelect.value = '';
                laptopModelSelect.value = '';
                
                // Gérer l'affichage selon la catégorie
                if (category === 'telephone') {
                    telephoneModelField.style.display = 'block';
                    weightField.style.display = 'none';
                    weightInput.value = '1'; // Valeur par défaut pour téléphones
                    telephoneModelSelect.required = true;
                    laptopModelSelect.required = false;
                    weightInput.required = false;
                } else if (category === 'laptop') {
                    laptopModelField.style.display = 'block';
                    weightField.style.display = 'none';
                    weightInput.value = '1'; // Valeur par défaut pour laptops
                    laptopModelSelect.required = true;
                    telephoneModelSelect.required = false;
                    weightInput.required = false;
                } else if (category === 'achat_assiste') {
                    weightField.style.display = 'none';
                    weightInput.value = '1'; // Valeur par défaut pour achat assisté
                    telephoneModelSelect.required = false;
                    laptopModelSelect.required = false;
                    weightInput.required = false;
                } else {
                    // Pour vêtements/accessoires et assurance premium
                    weightField.style.display = 'block';
                    weightInput.value = '';
                    telephoneModelSelect.required = false;
                    laptopModelSelect.required = false;
                    weightInput.required = true;
                }
            }
            
            categorySelect.addEventListener('change', updateFieldsVisibility);
            
            // Initialiser l'affichage au chargement de la page
            updateFieldsVisibility();
            
            // Mobile menu functionality
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>
</body>
</html>