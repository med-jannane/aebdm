/*
 MASTER SCHEMA SQL SERVER - PROJET SAV
 - Fichier unique pour creer/metre a jour tout le schema
 - Idempotent: peut etre relance sans casser l'existant
 - Schema uniquement (pas de seed)
*/

USE SAV_DB;
GO

SET NOCOUNT ON;
GO

/* =========================================================
   1) TABLES COEUR
   ========================================================= */

IF OBJECT_ID('dbo.Users', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Users (
        id NVARCHAR(50) NOT NULL PRIMARY KEY,
        nom NVARCHAR(100) NOT NULL UNIQUE,
        mot_de_passe NVARCHAR(255) NOT NULL, 
        nom_complet NVARCHAR(150) NULL,
        role NVARCHAR(50) NOT NULL,
        telephone NVARCHAR(50) NULL,
        email NVARCHAR(255) NULL,
        region NVARCHAR(100) NULL,
        cree_le DATETIME NOT NULL CONSTRAINT DF_Users_cree_le DEFAULT GETDATE()
    );
END
GO

IF OBJECT_ID('dbo.SAV_Clients', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.SAV_Clients (
        ID_Client NVARCHAR(50) NOT NULL PRIMARY KEY,
        Nom NVARCHAR(255) NOT NULL,
        Adresse NVARCHAR(MAX) NULL,
        Ville NVARCHAR(100) NULL,
        Contact NVARCHAR(100) NULL,
        TEL NVARCHAR(50) NULL,
        TEL2 NVARCHAR(50) NULL,
        TEL3 NVARCHAR(50) NULL,
        Fax NVARCHAR(50) NULL,
        Email NVARCHAR(255) NULL,
        Site NVARCHAR(255) NULL,
        Blocage BIT NOT NULL CONSTRAINT DF_SAV_Clients_Blocage DEFAULT(0),
        Activite NVARCHAR(100) NULL,
        Secteur_Activite_Sec NVARCHAR(100) NULL,
        Code_Secteur_Activite_Princ NVARCHAR(50) NULL,
        Code_Secteur_Activite_Sec NVARCHAR(50) NULL,
        Modalite_Paiement NVARCHAR(100) NULL,
        SysGM_Client_Bit BIT NOT NULL CONSTRAINT DF_SAV_Clients_SysGM_Client_Bit DEFAULT(1)
    );
END
GO

IF OBJECT_ID('dbo.SAV_Sites', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.SAV_Sites (
        Id_Site NVARCHAR(50) NOT NULL PRIMARY KEY,
        Id_Client NVARCHAR(50) NOT NULL,
        Ville NVARCHAR(100) NULL,
        Nom_Client NVARCHAR(255) NULL,
        Nom NVARCHAR(255) NOT NULL,
        Adresse NVARCHAR(MAX) NULL,
        Tel NVARCHAR(50) NULL,
        Fax NVARCHAR(50) NULL,
        SiteWeb NVARCHAR(255) NULL,
        Email NVARCHAR(255) NULL,
        Comment NVARCHAR(MAX) NULL,
        Modem NVARCHAR(100) NULL,
        Blocage BIT NOT NULL CONSTRAINT DF_SAV_Sites_Blocage DEFAULT(0),
        DATEBL DATETIME NULL,
        DATEDBL DATETIME NULL,
        TEL2 NVARCHAR(50) NULL,
        TEL3 NVARCHAR(50) NULL,
        Remote_Login1 NVARCHAR(100) NULL,
        MDP1 NVARCHAR(100) NULL,
        Remote_Login2 NVARCHAR(100) NULL,
        MDP2 NVARCHAR(100) NULL,
        Zone_Geo NVARCHAR(100) NULL,
        Tel_Siege NVARCHAR(50) NULL,
        Code_Agence NVARCHAR(50) NULL,
        latitude VARCHAR(50) NULL,
        longitude VARCHAR(50) NULL,
        contact_nom VARCHAR(100) NULL,
        routeur_login NVARCHAR(255) NULL,
        routeur_password NVARCHAR(255) NULL,
        poste_inclut NVARCHAR(100) NULL,
        type_abonnement NVARCHAR(100) NULL,
        cablage NVARCHAR(255) NULL,
        inclusions NVARCHAR(MAX) NULL
    );
END
GO

IF OBJECT_ID('dbo.Produits', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Produits (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        nom NVARCHAR(150) NOT NULL,
        description NVARCHAR(MAX) NULL,
        prix_achat DECIMAL(12,2) NULL,
        prix_vente DECIMAL(12,2) NULL,
        image_path NVARCHAR(255) NULL,
        stock INT NOT NULL CONSTRAINT DF_Produits_stock DEFAULT(0)
    );
END
GO

IF OBJECT_ID('dbo.CONTRAT', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.CONTRAT (
        ID_CONTRAT NVARCHAR(50) NOT NULL PRIMARY KEY,
        ID_USER NVARCHAR(50) NULL,
        ID_CLIENT NVARCHAR(100) NULL,
        CODE_CONTRAT NVARCHAR(100) NULL,
        Date_Creation DATETIME NULL,
        Date_Debut DATETIME NULL,
        PERIODE NVARCHAR(50) NULL,
        Date_Fin DATETIME NULL,
        Date_Signature DATETIME NULL,
        DATERESIL DATETIME NULL,
        AVENANT NVARCHAR(50) NULL,
        Contrat_Originale NVARCHAR(255) NULL,
        ETAT NVARCHAR(50) NULL,
        TYPE NVARCHAR(50) NULL,
        VP NVARCHAR(50) NULL,
        Code_Client NVARCHAR(100) NULL,
        Nom_Client NVARCHAR(500) NULL,
        Code_Site NVARCHAR(255) NULL,
        Nom_Site NVARCHAR(500) NULL,
        Montant_Contrat DECIMAL(18,2) NULL,
        Mode_Facturation NVARCHAR(50) NULL,
        Periode_Facturation NVARCHAR(50) NULL,
        Echeance_Facturation NVARCHAR(50) NULL,
        VPPLANIFIER NVARCHAR(50) NULL,
        SERVICE NVARCHAR(500) NULL,
        Couverture_Heures NVARCHAR(255) NULL,
        Couverture_Jours NVARCHAR(255) NULL,
        Ville NVARCHAR(500) NULL,
        ETAT_REDOUANE NVARCHAR(50) NULL,
        NBRFACTURE INT NULL,
        RESERVES NVARCHAR(MAX) NULL
    );
END
GO

IF OBJECT_ID('dbo.CONTRAT_SITE', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.CONTRAT_SITE (
        ID_CONTRAT NVARCHAR(50) NOT NULL,
        ID_SITE NVARCHAR(50) NOT NULL,
        CONSTRAINT PK_CONTRAT_SITE PRIMARY KEY (ID_CONTRAT, ID_SITE)
    );
END
GO

IF OBJECT_ID('dbo.TICKET', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.TICKET (
        ID_TICKET NVARCHAR(50) NOT NULL PRIMARY KEY,
        ID_USER NVARCHAR(50) NULL,
        ID_CLIENT NVARCHAR(50) NOT NULL,
        ID_SITE NVARCHAR(50) NULL,
        CODE NVARCHAR(80) NULL,
        DATE DATETIME NOT NULL CONSTRAINT DF_TICKET_DATE DEFAULT GETDATE(),
        OBJET NVARCHAR(255) NULL,
        COMMENT NVARCHAR(MAX) NULL,
        PRIORITE NVARCHAR(50) NOT NULL CONSTRAINT DF_TICKET_PRIORITE DEFAULT('normale'),
        ETAT NVARCHAR(50) NOT NULL CONSTRAINT DF_TICKET_ETAT DEFAULT('ouvert'),
        NOM_USER NVARCHAR(100) NULL,
        TEL_USER NVARCHAR(50) NULL,
        EMAIL_USER NVARCHAR(255) NULL,
        MESSAGE_DISPATCH NVARCHAR(MAX) NULL,
        TAC_DIAGNOSTIC NVARCHAR(MAX) NULL,
        TAC_SOLUTION NVARCHAR(MAX) NULL,
        TAC_DATE_TRAITEMENT DATETIME NULL,
        tac_diagnostic NVARCHAR(MAX) NULL,
        tac_solution NVARCHAR(MAX) NULL,
        tac_tests NVARCHAR(MAX) NULL,
        tac_resultat NVARCHAR(MAX) NULL,
        tac_duree INT NULL,
        tac_moyen NVARCHAR(50) NULL,
        tac_date_traitement DATETIME NULL,
        message_relais_dispatch NVARCHAR(MAX) NULL
    );
END
GO

IF OBJECT_ID('dbo.Interventions', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Interventions (
        id NVARCHAR(50) NOT NULL PRIMARY KEY,
        ticket_id NVARCHAR(50) NOT NULL,
        tech_id NVARCHAR(50) NULL,
        date_planifiee DATETIME NULL,
        date_intervention DATETIME NULL,
        date_fin DATETIME NULL,
        rapport NVARCHAR(MAX) NULL,
        rapport_dispatch NVARCHAR(MAX) NULL,
        rapport_tech NVARCHAR(MAX) NULL,
        fichiers_path NVARCHAR(255) NULL,
        statut NVARCHAR(20) NOT NULL CONSTRAINT DF_Interventions_statut DEFAULT('planifie'),
        created_at DATETIME NOT NULL CONSTRAINT DF_Interventions_created_at DEFAULT GETDATE(),
        instructions NVARCHAR(MAX) NULL,
        travaux_recommandes NVARCHAR(MAX) NULL,
        commentaire_client NVARCHAR(MAX) NULL,
        nom_signataire_client NVARCHAR(255) NULL,
        heure_arrivee_matin TIME NULL,
        heure_depart_matin TIME NULL,
        duree_trajet_matin INT NULL,
        heure_arrivee_soir TIME NULL,
        heure_depart_soir TIME NULL,
        duree_trajet_soir INT NULL
    );
END
GO

IF OBJECT_ID('dbo.Intervention_Produits', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Intervention_Produits (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        intervention_id NVARCHAR(50) NOT NULL,
        produit_id INT NOT NULL,
        quantite INT NOT NULL CONSTRAINT DF_Intervention_Produits_quantite DEFAULT(1)
    );
END
GO

IF OBJECT_ID('dbo.Commandes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Commandes (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        site_id NVARCHAR(50) NULL,
        code_client NVARCHAR(50) NOT NULL,
        nom_client NVARCHAR(200) NOT NULL,
        numero_commande NVARCHAR(100) NOT NULL,
        montant_ht DECIMAL(10,2) NOT NULL,
        statut NVARCHAR(50) NOT NULL CONSTRAINT DF_Commandes_statut DEFAULT('EN_ATTENTE'),
        fichier_joint NVARCHAR(255) NULL,
        created_at DATETIME NOT NULL CONSTRAINT DF_Commandes_created_at DEFAULT GETDATE()
    );
END
GO

IF OBJECT_ID('dbo.Intervention_Messages', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Intervention_Messages (
        id NVARCHAR(50) NOT NULL PRIMARY KEY,
        intervention_id NVARCHAR(50) NOT NULL,
        expediteur_id NVARCHAR(50) NOT NULL,
        message NVARCHAR(MAX) NOT NULL,
        cree_le DATETIME NOT NULL CONSTRAINT DF_Intervention_Messages_cree_le DEFAULT GETDATE()
    );
END
GO

IF OBJECT_ID('dbo.Notifications', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Notifications (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        user_id NVARCHAR(50) NULL,
        role_target VARCHAR(50) NULL,
        message NVARCHAR(255) NOT NULL,
        link VARCHAR(255) NULL,
        is_read BIT NOT NULL CONSTRAINT DF_Notifications_is_read DEFAULT(0),
        created_at DATETIME NOT NULL CONSTRAINT DF_Notifications_created_at DEFAULT GETDATE()
    );
END
GO

IF OBJECT_ID('dbo.VP', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.VP (
        ID_VP NVARCHAR(50) NOT NULL PRIMARY KEY,
        ID_CONTRAT NVARCHAR(50) NOT NULL,
        CODE_VP NVARCHAR(50) NULL,
        DATE_PREVUE DATETIME NULL,
        DATE_REALISEE DATETIME NULL,
        STATUT NVARCHAR(50) NULL,
        NOTES NVARCHAR(MAX) NULL
    );
END
GO

IF OBJECT_ID('dbo.VP_Tickets', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.VP_Tickets (
        ID_VP NVARCHAR(50) NOT NULL,
        ID_TICKET NVARCHAR(50) NOT NULL,
        CONSTRAINT PK_VP_Tickets PRIMARY KEY (ID_VP, ID_TICKET)
    );
END
GO

IF OBJECT_ID('dbo.CodeSequences', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.CodeSequences (
        entity NVARCHAR(50) NOT NULL PRIMARY KEY,
        last_value BIGINT NOT NULL
    );
END
GO

IF OBJECT_ID('dbo.AppSettings', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.AppSettings (
        setting_key NVARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value NVARCHAR(255) NOT NULL,
        updated_at DATETIME NOT NULL CONSTRAINT DF_AppSettings_updated_at DEFAULT GETDATE()
    );
END
GO

IF OBJECT_ID('dbo.SystemLogs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.SystemLogs (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        action NVARCHAR(100) NOT NULL,
        description NVARCHAR(MAX) NULL,
        user_id NVARCHAR(50) NULL,
        created_at DATETIME NOT NULL CONSTRAINT DF_SystemLogs_created_at DEFAULT GETDATE()
    );
END
GO

/* =========================================================
   2) CLES ETRANGERES (ajout conditionnel)
   ========================================================= */

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SAV_Sites_Client')
    ALTER TABLE dbo.SAV_Sites ADD CONSTRAINT FK_SAV_Sites_Client FOREIGN KEY (Id_Client) REFERENCES dbo.SAV_Clients(ID_Client) ON DELETE CASCADE;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_CONTRAT_CLIENT')
    ALTER TABLE dbo.CONTRAT ADD CONSTRAINT FK_CONTRAT_CLIENT FOREIGN KEY (ID_CLIENT) REFERENCES dbo.SAV_Clients(ID_Client);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_CONTRAT_SITE_CONTRAT')
    ALTER TABLE dbo.CONTRAT_SITE ADD CONSTRAINT FK_CONTRAT_SITE_CONTRAT FOREIGN KEY (ID_CONTRAT) REFERENCES dbo.CONTRAT(ID_CONTRAT) ON DELETE CASCADE;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_CONTRAT_SITE_SITE')
    ALTER TABLE dbo.CONTRAT_SITE ADD CONSTRAINT FK_CONTRAT_SITE_SITE FOREIGN KEY (ID_SITE) REFERENCES dbo.SAV_Sites(Id_Site);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_TICKET_CLIENT')
    ALTER TABLE dbo.TICKET ADD CONSTRAINT FK_TICKET_CLIENT FOREIGN KEY (ID_CLIENT) REFERENCES dbo.SAV_Clients(ID_Client);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_TICKET_SITE')
    ALTER TABLE dbo.TICKET ADD CONSTRAINT FK_TICKET_SITE FOREIGN KEY (ID_SITE) REFERENCES dbo.SAV_Sites(Id_Site);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_Interventions_Ticket')
    ALTER TABLE dbo.Interventions ADD CONSTRAINT FK_Interventions_Ticket FOREIGN KEY (ticket_id) REFERENCES dbo.TICKET(ID_TICKET) ON DELETE CASCADE;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_Interventions_UserTech')
    ALTER TABLE dbo.Interventions ADD CONSTRAINT FK_Interventions_UserTech FOREIGN KEY (tech_id) REFERENCES dbo.Users(id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_Intervention_Produits_Intervention')
    ALTER TABLE dbo.Intervention_Produits ADD CONSTRAINT FK_Intervention_Produits_Intervention FOREIGN KEY (intervention_id) REFERENCES dbo.Interventions(id) ON DELETE CASCADE;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_Intervention_Produits_Produit')
    ALTER TABLE dbo.Intervention_Produits ADD CONSTRAINT FK_Intervention_Produits_Produit FOREIGN KEY (produit_id) REFERENCES dbo.Produits(id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_Intervention_Messages_Intervention')
    ALTER TABLE dbo.Intervention_Messages ADD CONSTRAINT FK_Intervention_Messages_Intervention FOREIGN KEY (intervention_id) REFERENCES dbo.Interventions(id) ON DELETE CASCADE;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_Intervention_Messages_User')
    ALTER TABLE dbo.Intervention_Messages ADD CONSTRAINT FK_Intervention_Messages_User FOREIGN KEY (expediteur_id) REFERENCES dbo.Users(id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_VP_CONTRAT')
    ALTER TABLE dbo.VP ADD CONSTRAINT FK_VP_CONTRAT FOREIGN KEY (ID_CONTRAT) REFERENCES dbo.CONTRAT(ID_CONTRAT) ON DELETE CASCADE;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_VP_Tickets_VP')
    ALTER TABLE dbo.VP_Tickets ADD CONSTRAINT FK_VP_Tickets_VP FOREIGN KEY (ID_VP) REFERENCES dbo.VP(ID_VP) ON DELETE CASCADE;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_VP_Tickets_TICKET')
    ALTER TABLE dbo.VP_Tickets ADD CONSTRAINT FK_VP_Tickets_TICKET FOREIGN KEY (ID_TICKET) REFERENCES dbo.TICKET(ID_TICKET);
GO

/* =========================================================
   3) INDEXES
   ========================================================= */

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SAV_Sites_Id_Client' AND object_id = OBJECT_ID('dbo.SAV_Sites'))
    CREATE INDEX IX_SAV_Sites_Id_Client ON dbo.SAV_Sites (Id_Client);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_TICKET_ETAT' AND object_id = OBJECT_ID('dbo.TICKET'))
    CREATE INDEX IX_TICKET_ETAT ON dbo.TICKET (ETAT);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_TICKET_CLIENT' AND object_id = OBJECT_ID('dbo.TICKET'))
    CREATE INDEX IX_TICKET_CLIENT ON dbo.TICKET (ID_CLIENT);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Interventions_ticket_id' AND object_id = OBJECT_ID('dbo.Interventions'))
    CREATE INDEX IX_Interventions_ticket_id ON dbo.Interventions (ticket_id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Interventions_tech_id' AND object_id = OBJECT_ID('dbo.Interventions'))
    CREATE INDEX IX_Interventions_tech_id ON dbo.Interventions (tech_id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_notif_user' AND object_id = OBJECT_ID('dbo.Notifications'))
    CREATE INDEX idx_notif_user ON dbo.Notifications(user_id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_notif_role' AND object_id = OBJECT_ID('dbo.Notifications'))
    CREATE INDEX idx_notif_role ON dbo.Notifications(role_target);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_notif_read' AND object_id = OBJECT_ID('dbo.Notifications'))
    CREATE INDEX idx_notif_read ON dbo.Notifications(is_read);
GO

PRINT 'Schema SQL Server master applique avec succes.';
GO
