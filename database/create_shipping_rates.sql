-- Création de la table shipping_rates manquante
CREATE TABLE IF NOT EXISTS shipping_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type VARCHAR(50) NOT NULL DEFAULT 'standard',
    destination_country VARCHAR(100) NOT NULL DEFAULT 'Haiti',
    weight_min DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    weight_max DECIMAL(10,2) NOT NULL DEFAULT 999.99,
    base_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    rate_per_kg DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service_type (service_type),
    INDEX idx_destination (destination_country),
    INDEX idx_weight_range (weight_min, weight_max),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de données par défaut pour les tarifs de shipping
INSERT INTO shipping_rates (service_type, destination_country, weight_min, weight_max, base_rate, rate_per_kg, is_active) VALUES
('standard', 'Haiti', 0.00, 1.00, 25.00, 15.00, 1),
('standard', 'Haiti', 1.01, 2.00, 30.00, 12.00, 1),
('standard', 'Haiti', 2.01, 5.00, 35.00, 10.00, 1),
('standard', 'Haiti', 5.01, 10.00, 40.00, 8.00, 1),
('standard', 'Haiti', 10.01, 999.99, 50.00, 6.00, 1),
('express', 'Haiti', 0.00, 1.00, 35.00, 20.00, 1),
('express', 'Haiti', 1.01, 2.00, 40.00, 18.00, 1),
('express', 'Haiti', 2.01, 5.00, 45.00, 15.00, 1),
('express', 'Haiti', 5.01, 10.00, 50.00, 12.00, 1),
('express', 'Haiti', 10.01, 999.99, 60.00, 10.00, 1);