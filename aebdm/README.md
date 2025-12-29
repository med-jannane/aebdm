# 🏢 Application AEBDM - Gestion d'entreprise

Application web complète de gestion d'entreprise avec gestion des utilisateurs, clients, contrats, interventions, visites préventives et produits.

## 📋 Fonctionnalités

### 🔐 Authentification et rôles
- **Directeur** : Accès complet à toutes les fonctionnalités
- **Chargé de compte** : Même droits que le directeur
- **Ingénieur** : Accès aux interventions et visites de sa région
- **Technicien** : Accès aux interventions et visites de sa région
- **Magasinier** : Gestion des produits + interventions

### 📊 Modules disponibles
- 👥 **Gestion des utilisateurs** : CRUD complet avec photos de profil
- 🏢 **Gestion des clients** : CRUD avec codes clients uniques
- 📋 **Gestion des contrats** : CRUD avec upload de fichiers (photos/PDF)
- 🔧 **Gestion des interventions** : CRUD avec assignation, statuts, multi-fichiers
- 🔍 **Gestion des visites préventives** : CRUD avec assignation, statuts, multi-fichiers
- 📦 **Gestion des produits** : CRUD avec stock, prix, photos

### ✨ Fonctionnalités avancées
- 📁 **Upload multi-fichiers** pour interventions et visites
- 📊 **Dashboard** avec statistiques selon le rôle
- 🎨 **Design responsive** (PC, tablette, smartphone)
- 🔒 **Sécurité** : Password hash, sessions, contrôle d'accès
- 📈 **Statistiques** en temps réel

## 🚀 Installation

### Prérequis
- PHP 8.0 ou supérieur
- MySQL 8.0 ou supérieur
- Serveur web (Apache/Nginx) ou XAMPP/WAMP

### Étapes d'installation

1. **Cloner/Downloader le projet**
   ```bash
   # Placer le dossier dans votre serveur web
   # Exemple : C:\xampp\htdocs\aebdm\
   ```

2. **Créer la base de données**
   ```bash
   # Importer le fichier databases.sql dans phpMyAdmin
   # Ou exécuter le script SQL directement
   ```

3. **Configurer la connexion**
   ```php
   # Modifier src/config.php si nécessaire
   $host = 'localhost';
   $db   = 'aebdm';
   $user = 'root';
   $pass = '';
   ```

4. **Créer les dossiers d'upload**
   ```bash
   # Créer les dossiers suivants :
   uploads/
   uploads/users/
   uploads/contrats/
   uploads/interventions/
   uploads/visites/
   uploads/produits/
   ```

5. **Définir les permissions**
   ```bash
   # Donner les permissions d'écriture aux dossiers uploads/
   chmod 755 uploads/
   ```

## 🔑 Connexion

### Utilisateur de test
- **Email** : `test@gmail.com`
- **Mot de passe** : `12345678`
- **Rôle** : Directeur

## 📁 Structure du projet

```
aebdm/
├── public/                 # Pages publiques
│   ├── index.php          # Page de login
│   ├── dashboard.php      # Tableau de bord
│   ├── users.php          # Gestion utilisateurs
│   ├── clients.php        # Gestion clients
│   ├── contrats.php       # Gestion contrats
│   ├── interventions.php  # Gestion interventions
│   ├── visites.php        # Gestion visites
│   ├── produits.php       # Gestion produits
│   ├── add_*.php          # Pages d'ajout
│   ├── edit_*.php         # Pages de modification
│   ├── delete_*.php       # Pages de suppression
│   ├── logout.php         # Déconnexion
│   └── assets/
│       └── style.css      # Styles CSS
├── src/                   # Code source
│   ├── config.php         # Configuration PDO
│   └── auth.php           # Fonctions d'authentification
├── uploads/               # Fichiers uploadés
│   ├── users/             # Photos de profil
│   ├── contrats/          # Photos et PDF des contrats
│   ├── interventions/     # Fichiers des interventions
│   ├── visites/          # Fichiers des visites
│   └── produits/         # Photos des produits
├── databases.sql          # Script de création de la BDD
└── README.md             # Ce fichier
```

## 🎯 Utilisation

### 1. Connexion
- Accéder à `http://localhost/aebdm/public/`
- Se connecter avec les identifiants de test

### 2. Navigation
- **Dashboard** : Vue d'ensemble avec statistiques
- **Menu** : Accès aux différents modules selon le rôle
- **Actions rapides** : Ajout direct d'éléments

### 3. Gestion des données
- **Ajouter** : Bouton "Ajouter" sur chaque page
- **Modifier** : Lien "Modifier" dans les tableaux
- **Supprimer** : Lien "Supprimer" avec confirmation

## 🔧 Configuration avancée

### Personnalisation du design
Modifier `public/assets/style.css` pour adapter le design.

### Ajout de fonctionnalités
- **Logs** : Table `logs` disponible pour tracer les actions
- **Export** : Possibilité d'ajouter des exports CSV/PDF
- **API** : Structure prête pour une API REST

### Sécurité
- **Sessions** : Gestion automatique des sessions
- **Validation** : Validation côté serveur et client
- **Upload** : Sécurisation des uploads de fichiers

## 🐛 Dépannage

### Problèmes courants
1. **Erreur de connexion BDD** : Vérifier `src/config.php`
2. **Uploads ne fonctionnent pas** : Vérifier les permissions des dossiers
3. **Page blanche** : Vérifier les logs PHP et les erreurs

### Logs
- Vérifier les logs Apache/Nginx
- Activer l'affichage des erreurs PHP en développement

## 📞 Support

Pour toute question ou problème :
1. Vérifier la documentation
2. Contrôler les logs d'erreur
3. Tester avec l'utilisateur de test

## 🔄 Mises à jour

### Version 1.0
- ✅ Authentification et rôles
- ✅ CRUD complet pour tous les modules
- ✅ Upload multi-fichiers
- ✅ Dashboard avec statistiques
- ✅ Design responsive
- ✅ Sécurité de base

### Prochaines versions
- 📊 Graphiques et rapports avancés
- 📱 Application mobile
- 🔔 Notifications en temps réel
- 📈 Analytics et métriques

---

**Application AEBDM** - Système de gestion d'entreprise complet et moderne. 