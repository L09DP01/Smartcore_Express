-- Script pour ajouter la colonne profile_completed à la table users
-- Cette colonne permet de suivre si l'utilisateur a complété son profil après l'inscription OAuth

USE u929653200_smartcore_db;

-- Ajouter la colonne profile_completed si elle n'existe pas déjà
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS profile_completed TINYINT(1) DEFAULT 0 COMMENT 'Indique si l\'utilisateur a complété son profil (0=non, 1=oui)';

-- Mettre à jour les utilisateurs existants pour marquer leur profil comme complété
-- (on assume que les utilisateurs existants ont déjà un profil complet)
UPDATE users 
SET profile_completed = 1 
WHERE profile_completed IS NULL OR profile_completed = 0;

-- Afficher la structure mise à jour de la table
DESCRIBE users;