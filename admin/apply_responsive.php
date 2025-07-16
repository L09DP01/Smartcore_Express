<?php
/**
 * Script pour appliquer automatiquement les modifications responsives
 * à toutes les pages d'administration
 */

// Liste des fichiers à modifier
$admin_files = [
    'colis_management.php',
    'reports.php',
    'sponsors.php',
    'settings.php',
    'profile.php',
    'update_status.php',
    'colis.php'
];

// Répertoire admin
$admin_dir = __DIR__;

function addResponsiveToFile($file_path) {
    if (!file_exists($file_path)) {
        echo "Fichier non trouvé: $file_path\n";
        return false;
    }
    
    $content = file_get_contents($file_path);
    $modified = false;
    
    // Ajouter les liens CSS et JS responsives si pas déjà présents
    if (strpos($content, 'admin-responsive.css') === false) {
        $content = str_replace(
            '<link rel="stylesheet" href="../css/theme.css">',
            '<link rel="stylesheet" href="../css/theme.css">\n    <link rel="stylesheet" href="../css/admin-responsive.css">',
            $content
        );
        $modified = true;
    }
    
    if (strpos($content, 'admin-responsive.js') === false) {
        $content = str_replace(
            '<script src="../js/theme.js"></script>',
            '<script src="../js/theme.js"></script>\n    <script src="../js/admin-responsive.js"></script>',
            $content
        );
        $modified = true;
    }
    
    // Modifier les classes du body
    if (strpos($content, 'admin-layout') === false) {
        $content = str_replace(
            '<body class="bg-gray-50">',
            '<body class="bg-gray-50 admin-layout">',
            $content
        );
        $modified = true;
    }
    
    // Modifier la sidebar
    $content = preg_replace(
        '/<div class="w-64 bg-white shadow-lg border-r border-gray-200">/',
        '<aside class="admin-sidebar w-64 bg-white shadow-lg border-r border-gray-200">',
        $content
    );
    
    // Modifier le main content
    $content = preg_replace(
        '/<div class="flex-1 overflow-x-hidden overflow-y-auto">/',
        '<main class="admin-main flex-1 overflow-x-hidden overflow-y-auto">',
        $content
    );
    
    // Modifier le header
    $content = preg_replace(
        '/<header class="bg-white shadow-sm border-b border-gray-200">/',
        '<header class="admin-header bg-white shadow-sm border-b border-gray-200">',
        $content
    );
    
    // Modifier les grilles de stats
    $content = preg_replace(
        '/<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[0-9]+ gap-6 mb-[0-9]+">/',
        '<div class="stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">',
        $content
    );
    
    // Modifier les cartes de stats
    $content = preg_replace(
        '/<div class="bg-white rounded-lg shadow p-6">/',
        '<div class="stat-card bg-white rounded-lg shadow p-6">',
        $content
    );
    
    // Modifier les tables
    $content = preg_replace(
        '/<div class="overflow-x-auto">\s*<table class="min-w-full/',
        '<div class="table-container overflow-x-auto">\n                        <table class="responsive-table min-w-full',
        $content
    );
    
    // Modifier les modals
    $content = preg_replace(
        '/<div id="[^"]*modal[^"]*" class="fixed inset-0 bg-gray-600 bg-opacity-50/',
        '<div id="$1" class="modal fixed inset-0 bg-gray-600 bg-opacity-50',
        $content
    );
    
    // Fermer correctement les balises
    $content = str_replace(
        '</main>\n        </div>',
        '</div>\n        </main>',
        $content
    );
    
    if ($modified) {
        file_put_contents($file_path, $content);
        echo "✓ Fichier modifié: " . basename($file_path) . "\n";
        return true;
    } else {
        echo "- Fichier déjà à jour: " . basename($file_path) . "\n";
        return false;
    }
}

echo "=== Application des modifications responsives ===\n\n";

$total_modified = 0;
foreach ($admin_files as $file) {
    $file_path = $admin_dir . '/' . $file;
    if (addResponsiveToFile($file_path)) {
        $total_modified++;
    }
}

echo "\n=== Résumé ===\n";
echo "Fichiers traités: " . count($admin_files) . "\n";
echo "Fichiers modifiés: $total_modified\n";
echo "\n✓ Application terminée!\n";

// Créer un fichier de vérification
file_put_contents($admin_dir . '/responsive_applied.txt', date('Y-m-d H:i:s') . " - Modifications responsives appliquées\n", FILE_APPEND);

echo "\nPour tester les modifications, visitez les pages d'administration.\n";
echo "Les pages sont maintenant responsives et s'adaptent aux différentes tailles d'écran.\n";
?>