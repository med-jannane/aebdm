-- 🔄 Supprimer si déjà existant
DROP DATABASE IF EXISTS AEBDM;

-- 📂 Créer la base
CREATE DATABASE AEBDM CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE AEBDM;

-- 🧑 Table Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    numero VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    photo_profil VARCHAR(255),
    region VARCHAR(100),
    ville VARCHAR(100),
    role ENUM('directeur', 'charge_compte', 'ingenieur', 'technicien', 'magasinier') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 👤 Utilisateur de test (mot de passe : 12345678 hashé)
INSERT INTO users (nom, prenom, email, password, role) VALUES 
('test', 'test', 'test@gmail.com', '$2y$10$rTDDg83f6M2OXiT9AzL5y.fhaOQ5WgKP5A5iPAUmrq5OZTJXqcDJK', 'directeur');

-- 📄 Table Contrats
CREATE TABLE contrats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_contrat VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(150) NOT NULL,
    details TEXT,
    photo VARCHAR(255),
    pdf VARCHAR(255),
    code_client VARCHAR(50),
    date_debut DATE,
    date_fin DATE
    -- La FK code_client sera liée après la création de clients
);

-- 🧾 Table Clients (avec code_contrat lié)
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    site VARCHAR(255),
    region VARCHAR(100),
    ville VARCHAR(100),
    code_client VARCHAR(50) UNIQUE NOT NULL,
    code_contrat VARCHAR(50),
    FOREIGN KEY (code_contrat) REFERENCES contrats(code_contrat)
);

-- 🔗 Relier contrats.code_client à clients après création
ALTER TABLE contrats
ADD FOREIGN KEY (code_client) REFERENCES clients(code_client);

-- ⚙️ Table Interventions
CREATE TABLE interventions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_client VARCHAR(50),
    code_contrat VARCHAR(50),
    details TEXT,
    statut ENUM('encours', 'terminee', 'echouee', 'annulee'),
    user_id INT,
    produit_ajoute VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_client) REFERENCES clients(code_client),
    FOREIGN KEY (code_contrat) REFERENCES contrats(code_contrat),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 📂 Table Fichiers pour Interventions (multi-fichiers)
CREATE TABLE fichiers_intervention (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intervention_id INT,
    nom_fichier VARCHAR(255),
    type_fichier VARCHAR(50),
    FOREIGN KEY (intervention_id) REFERENCES interventions(id)
);

-- 🧰 Table Visites Préventives
CREATE TABLE visites_preventives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_client VARCHAR(50),
    code_contrat VARCHAR(50),
    details TEXT,
    statut ENUM('encours', 'terminee', 'echouee', 'annulee'),
    user_id INT,
    produit_ajoute VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_client) REFERENCES clients(code_client),
    FOREIGN KEY (code_contrat) REFERENCES contrats(code_contrat),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 📂 Table Fichiers pour Visites Préventives (multi-fichiers)
CREATE TABLE fichiers_visite (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visite_id INT,
    nom_fichier VARCHAR(255),
    type_fichier VARCHAR(50),
    FOREIGN KEY (visite_id) REFERENCES visites_preventives(id)
);

-- 📦 Table Produits (avec photo)
CREATE TABLE produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    reference VARCHAR(100) UNIQUE NOT NULL,
    quantite_stock INT DEFAULT 0,
    prix_achat DECIMAL(10,2),
    prix_vente DECIMAL(10,2),
    photo VARCHAR(255)
);
