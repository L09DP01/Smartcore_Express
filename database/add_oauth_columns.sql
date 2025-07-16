-- Script SQL pour ajouter les colonnes OAuth à la table users
-- Exécuter ce script pour supporter l'authentification Google et Apple

USE smartcore_express;

-- Ajouter les colonnes OAuth à la table users
ALTER TABLE users 
ADD COLUMN oauth_provider VARCHAR(20) NULL COMMENT 'Provider OAuth (google, apple)',
ADD COLUMN oauth_provider_id VARCHAR(255) NULL COMMENT 'ID unique du provider OAuth',
ADD COLUMN profile_photo VARCHAR(500) NULL COMMENT 'URL de la photo de profil OAuth',
ADD COLUMN email_verified TINYINT(1) DEFAULT 0 COMMENT 'Email vérifié par OAuth',
ADD COLUMN last_login TIMESTAMP NULL COMMENT 'Dernière connexion';

-- Créer un index sur oauth_provider_id pour les recherches rapides
CREATE INDEX idx_oauth_provider_id ON users(oauth_provider, oauth_provider_id);

-- Modifier la contrainte de mot de passe pour permettre NULL (pour les comptes OAuth)
ALTER TABLE users 
MODIFY COLUMN password_hash VARCHAR(255) NULL COMMENT 'Hash du mot de passe (NULL pour OAuth)';

-- Ajouter une contrainte pour s'assurer qu'un utilisateur a soit un mot de passe soit OAuth
ALTER TABLE users 
ADD CONSTRAINT chk_auth_method 
CHECK (
    (password_hash IS NOT NULL AND oauth_provider IS NULL) OR 
    (password_hash IS NULL AND oauth_provider IS NOT NULL) OR
    (password_hash IS NOT NULL AND oauth_provider IS NOT NULL)
);

SELECT 'Colonnes OAuth ajoutées avec succès à la table users' AS message;