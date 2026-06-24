# Documentation exhaustive du projet SAV

Ce document a pour but de décrire le projet dans son ensemble, avec un niveau de détail élevé sur l'architecture, les profils, les pages, les API, les utilitaires, les flux métier et les fichiers du dépôt.

Le projet est un système de gestion SAV basé sur PHP et SQL Server. Il couvre la relation client, la gestion des contrats, la création de tickets, la prise en charge TAC, la planification Dispatch, l'exécution terrain côté technicien, l'administration système et la communication interne.

La logique est organisée en couches :

- point d'entrée public
- authentification et contrôle d'accès
- modules métier par rôle
- composants partagés d'interface
- utilitaires transverses
- API JSON utilisées par l'interface
- scripts d'administration et de maintenance

Le projet est volontairement orienté processus. Une même demande peut traverser plusieurs espaces : Accueil, TAC, Dispatch, Tech, puis revenir à l'Accueil ou à l'Admin selon l'état du ticket et le statut du contrat.

## Vue d'ensemble fonctionnelle

Le système sert à suivre un ticket SAV depuis sa création jusqu'à sa clôture.

Le cycle normal est le suivant :

1. Un utilisateur crée ou consulte un ticket dans l'espace Accueil.
2. Le TAC reçoit le ticket, pose un premier diagnostic et peut l'orienter vers Dispatch.
3. Dispatch planifie une intervention et choisit un technicien.
4. Le technicien consulte son planning, réalise l'intervention et rédige le rapport terrain.
5. Le ticket peut être traité, replanifié, ou renvoyé vers TAC selon le résultat.
6. Les notifications informent les rôles concernés à chaque étape.
7. Les rapports PDF et les historiques servent de preuve et de mémoire opérationnelle.

Le projet contient aussi des fonctions d'exploitation : import CSV, audit des imports, statistiques, logs, génération de PDF, archivage d'historique, réparation de données et correction de schéma.

## Profils utilisateurs

### Accueil

Le profil Accueil centralise la création et le suivi des tickets, l'accès aux clients et sites, l'historique des sites, les commandes et l'audit d'import.

Ce profil est aussi celui qui voit le plus rapidement les problèmes qui reviennent du terrain, les tickets hors contrat et les besoins de relance commerciale ou technique.

### Commercial

Le profil Commercial gère les clients, les sites, les contrats et les écrans de consultation métier liés à la base installée.

Ce profil travaille surtout sur la donnée de référence : identité client, contrat, site, dates de validité et valeur économique.

### TAC

Le profil TAC sert de support expert. Il prend les tickets, les qualifie, conserve la trace du diagnostic et prépare le relais vers Dispatch ou vers la résolution.

Le TAC agit comme point de tri entre la demande brute et l'intervention terrain.

### Dispatch

Le profil Dispatch reçoit les tickets orientés, organise le planning, assigne un technicien et suit la charge opérationnelle.

Ce profil est fortement lié aux interventions planifiées et à la communication avec TAC et le technicien.

### Technicien

Le profil Technicien voit ses interventions planifiées, consulte les détails de site et client, dialogue via chat et rédige le rapport final.

Il produit le contenu qui alimente l'historique, le PDF et la clôture du ticket.

### Admin / Directeur

Le profil Admin ou Directeur supervise le système, les utilisateurs, les statistiques, les logs, les imports et les corrections de données.

Il peut aussi accéder à plusieurs modules métiers pour vérifier ou débloquer une situation.

### Chargé de compte

Le profil Charge de compte suit son équipe, les techniciens rattachés et les interventions de son périmètre.

Il sert de vue managériale intermédiaire entre la direction et les équipes terrain.

## Architecture technique

Le socle est en PHP côté serveur.

La base de données cible est SQL Server.

La logique métier est répartie dans des dossiers fonctionnels sous src/modules.

Les composants d'interface partagés sont dans src/includes.

Les services techniques transverses sont dans src/utils.

Le front repose sur HTML, CSS et JavaScript vanilla, avec quelques appels fetch vers des API JSON internes.

L'authentification s'appuie sur session PHP, redirection par rôle, cookie de session sécurisé et token CSRF.

La navigation est orientée sidebar par rôle, avec des tableaux de bord différents selon l'espace.

## Sécurité et contrôle d'accès

Le fichier auth_check.php initialise la session et bloque l'accès aux pages protégées si l'utilisateur n'est pas connecté.

check_role() ajoute un contrôle par rôle. Certaines pages acceptent un ou plusieurs rôles.

csrf_token(), csrf_input() et csrf_validate_request() servent à sécuriser les formulaires POST.

Plusieurs API vérifient la session avant de renvoyer du JSON.

Les messages, rapports et imports sont filtrés ou normalisés pour limiter les erreurs et réduire les injections de données malformées.

## Données et processus métiers

Le projet manipule principalement les entités suivantes :

- utilisateurs
- clients
- sites
- contrats
- tickets
- interventions
- messages d'intervention
- notifications
- produits utilisés
- paramètres applicatifs

Les statuts métier traversent plusieurs valeurs : nouveau, ouvert, en cours TAC, attente dispatch, planifiée, en cours, terminée, traitée, clôturée, hors contrat, attendue devis.

Les écrans ne sont pas seulement de la consultation. Ils changent l'état des dossiers et déclenchent des notifications, des mails, des mises à jour de contrat ou des entrées d'historique.

## Pages publiques

### index.php

Point d'entrée principal du site.

Il sert généralement de passerelle vers l'application selon la session et la logique d'accès du projet.

Il complète le rôle de la page de login quand l'utilisateur n'est pas encore authentifié.

### public/login.php

Page de connexion principale.

Elle vérifie les identifiants, lit la table Users, compare le mot de passe avec password_verify et redirige selon le rôle.

Cette page contient aussi le splash screen, la carte de connexion et les messages d'erreur ou de déconnexion réussie.

### public/login_new.php

Version alternative de la page de login.

Elle sert probablement de variante visuelle ou de refonte en cours.

### public/login_backup.php

Sauvegarde ou ancienne version de la page de connexion.

Elle est utile comme référence quand une évolution doit être comparée ou restaurée.

### public/fix_schema.php

Script de correction de schéma.

Il est utilisé pour aligner la structure de la base avec les attentes du code lorsqu'une colonne ou une table manque.

### public/seed_users.php

Script d'initialisation des utilisateurs.

Il sert à injecter des comptes de base ou des comptes de test dans la base.

### public/api/get_notifications.php

API JSON qui retourne les notifications récentes de l'utilisateur connecté.

Elle compte aussi les notifications non lues pour afficher le badge de l'interface.

### public/api/mark_read.php

API JSON qui marque une notification ou toutes les notifications comme lues.

Elle est consommée par le composant de notification de l'interface.

## Fichiers de configuration

### config/db.php

Fichier de connexion à SQL Server.

Il fournit la ressource de base utilisée partout dans l'application.

Le code du dépôt appelle souvent query() et sqlsrv_query() à partir de cette connexion.

### config/smtp_config.php

Configuration de l'envoi mail.

Elle est utilisée quand le projet envoie un rapport ou une notification par email.

Le contenu est sensible et doit rester hors du code public.

## Authentification

### src/auth/auth_check.php

Initialise la session et vérifie l'authentification.

Définit check_auth(), check_role(), redirect_by_role(), csrf_token(), csrf_input() et csrf_validate_request().

Ce fichier est l'un des points centraux de la sécurité du projet.

### src/auth/logout.php

Gestion de la déconnexion.

Le script nettoie la session et renvoie vers la page de login.

## Composants d'interface partagés

### src/includes/head.php

Charge les méta tags, les polices, les icônes, les CSS et le JavaScript partagés.

Il définit aussi le titre dynamique de page.

### src/includes/notification_ui.php

Composant visuel des notifications.

Il affiche la cloche, le badge de compteur et la liste déroulante.

### src/includes/theme_toggle.php

Composant de bascule de thème.

Il permet de changer l'apparence de l'interface sans quitter la page.

### src/includes/cloture_modal_ui.php

Modale de clôture d'intervention.

Elle regroupe le rapport de clôture, le message email et le bouton de validation.

### src/includes/cloture_modal_logic.php

Logique JavaScript et serveur liée à la clôture.

Le fichier charge les détails d'intervention, prépare le rapport, appelle l'API IA et déclenche la clôture.

## Utilitaires

### src/utils/Logger.php

Service de journalisation.

Il centralise les traces techniques et les événements métier importants.

### src/utils/NotificationManager.php

Gestionnaire des notifications en base.

Il sait créer, lire et marquer les notifications pour un rôle ou un utilisateur précis.

### src/utils/SmtpSender.php

Transport mail SMTP.

Il encapsule l'envoi effectif des messages vers le serveur SMTP configuré.

### src/utils/CsvImporter.php

Moteur d'import des fichiers CSV et des données métier associées.

Il contient la normalisation des en-têtes, la détection de valeurs métier, la gestion des dates Excel et les règles d'affectation des colonnes.

## Bibliothèques tierces

### src/libs/fpdf.php

Librairie de génération PDF.

Elle est utilisée dans les rapports d'intervention.

### src/libs/SimpleXLSX.php

Librairie de lecture de feuilles Excel.

Elle aide à importer des données structurées sans passer par un export manuel compliqué.

## SQL et base initiale

### sql/sqlserver_master_schema.sql

Script principal de schéma SQL Server.

Il sert à reconstruire ou documenter la structure de la base.

Le projet semble gérer plusieurs tables métier déjà présentes en production.

## Module Accueil

Le module accueil est l'un des espaces les plus chargés fonctionnellement.

Il mélange le pilotage des tickets, la consultation client, les commandes et le suivi des imports.

### src/modules/accueil/dashboard.php

Tableau de bord principal de l'accueil.

Il calcule les tickets ouverts, tous tickets, tickets TAC, tickets hors contrat et nombre de clients.

Il appelle updateExpiredContracts() et affiche des derniers tickets, derniers clients et alertes de retour hors contrat.

### src/modules/accueil/tickets.php

Liste de gestion des tickets.

Elle sert à consulter et probablement filtrer les dossiers SAV.

### src/modules/accueil/ticket_create.php

Création d'un ticket.

Ce formulaire est le point de départ du cycle SAV.

### src/modules/accueil/ticket_edit.php

Édition d'un ticket.

Il permet de corriger le contenu, le statut ou les informations associées.

### src/modules/accueil/ticket_details.php

Détail complet d'un ticket.

Il rassemble les informations client, site, statut, historique et éventuelles actions liées.

### src/modules/accueil/ticket_delete.php

Suppression d'un ticket.

Le script agit comme action destructive et doit être protégé par le contrôle d'accès.

### src/modules/accueil/create_ticket_form.php

Formulaire de saisie du ticket.

Il sert à structurer le motif, les coordonnées et les champs de base avant création.

### src/modules/accueil/create_ticket_search.php

Recherche préalable à la création.

Ce fichier aide à retrouver un client ou un site avant d'ouvrir un ticket.

### src/modules/accueil/client_details.php

Fiche client côté accueil.

Elle peut servir de consultation rapide ou de passerelle vers les détails commerciaux.

### src/modules/accueil/clients.php

Liste des clients consultables depuis l'accueil.

Elle facilite la navigation vers les fiches et les sites.

### src/modules/accueil/historique_site.php

Historique des interventions par site.

Il aide à comprendre les incidents récurrents sur un emplacement donné.

### src/modules/accueil/commande_create.php

Création d'une commande.

Le module suggère une gestion des commandes liées à l'activité commerciale ou opérationnelle.

### src/modules/accueil/commandes.php

Liste ou suivi des commandes.

Le fichier complète le travail de saisie par une vue de consultation.

### src/modules/accueil/get_client_details_api.php

API locale de détail client.

Elle renvoie les informations de fiche client à des composants JS de l'accueil.

### src/modules/accueil/audit_imports.php

Audit des imports depuis l'accueil.

Le fichier permet de vérifier ce qui a été injecté dans le système et de retrouver les anomalies.

## Module Commercial

Le module commercial porte les référentiels clients et contrats.

Il constitue la base de vérité pour les informations à long terme.

### src/modules/commercial/dashboard.php

Tableau de bord commercial.

Il affiche nombre de clients, contrats, contrats actifs, sites et chiffre d'affaires, puis met en avant les contrats expirant sous 30 jours.

Il propose aussi des accès rapides vers la création et la gestion métier.

### src/modules/commercial/clients.php

Liste des clients.

Elle sert d'entrée principale à la gestion client.

### src/modules/commercial/client_details.php

Fiche complète client.

Elle affiche la structure du client, ses sites, ses contrats ou ses données liées.

### src/modules/commercial/client_edit.php

Édition ou création client.

Le fichier porte les formulaires de maintenance de la base client.

### src/modules/commercial/site_edit.php

Édition des sites.

Il sert à maintenir les adresses, villes, contacts et autres attributs d'un site.

### src/modules/commercial/contrats.php

Liste des contrats.

La page donne une vue synthétique sur l'état contractuel des clients.

### src/modules/commercial/contrat_create.php

Création d'un contrat.

Elle alimente la donnée qui servira aux vérifications de couverture et d'expiration.

### src/modules/commercial/contrat_edit.php

Modification d'un contrat.

Le fichier corrige ou ajuste les champs d'un contrat déjà existant.

### src/modules/commercial/contrat_delete.php

Suppression d'un contrat.

Action sensible qui nécessite un contrôle strict.

## Module TAC

Le TAC est le support expert de niveau intermédiaire.

Il qualifie, traite et oriente les dossiers.

### src/modules/tac/dashboard.php

Tableau de bord TAC.

Il sépare les tickets à prendre, les dossiers en cours et les tickets déjà traités.

Il contient aussi un mode de routage direct vers Dispatch.

### src/modules/tac/ticket_process.php

Traitement d'un ticket TAC.

Le fichier sert à prendre en charge un ticket et à y saisir l'analyse support.

### src/modules/tac/ticket_detail.php

Détail TAC d'un ticket.

Il expose la lecture métier nécessaire au diagnostic.

### src/modules/tac/tickets_list.php

Liste des tickets TAC.

Elle permet de voir rapidement la charge du support expert.

### src/modules/tac/my_tickets.php

Mes tickets TAC.

Cette vue isole probablement les tickets affectés à l'utilisateur courant.

### src/modules/tac/historique_site.php

Historique site côté TAC.

Le TAC peut l'utiliser pour relier un incident à des précédents connus.

## Module Dispatch

Le Dispatch transforme une analyse en planning opérationnel.

Il choisit le bon intervenant et suit l'état d'exécution.

### src/modules/dispatch/dashboard.php

Tableau de bord Dispatch.

Il affiche les tickets à planifier et les actions de planification.

Il contient aussi une modale de détails du problème pour décider de l'assignation.

### src/modules/dispatch/assign_tech.php

Affectation d'un technicien.

Ce fichier relie une intervention à un technicien et fixe la responsabilité terrain.

### src/modules/dispatch/interventions_list.php

Liste globale des interventions.

Elle sert à piloter la charge et le planning.

### src/modules/dispatch/plan_intervention.php

Planification détaillée.

Le fichier aide à organiser la date, l'heure ou les contraintes de passage.

## Module Technicien

Le module technicien est le visage terrain du système.

Il contient la consultation, le rapport, le PDF et l'historique.

### src/modules/tech/dashboard.php

Tableau de bord technicien.

Il liste les interventions planifiées de l'utilisateur, affiche la localisation, le motif et les boutons vers le rapport.

Il contient aussi le chat d'intervention et la fiche client rapide.

### src/modules/tech/report_intervention.php

Formulaire de rapport terrain.

Il collecte les horaires, les travaux demandés, les travaux réalisés, les recommandations, les pièces jointes et les produits consommés.

Il met à jour l'intervention, ajuste le stock produit et fait évoluer le ticket.

### src/modules/tech/generate_pdf.php

Génération du PDF d'intervention.

Le fichier assemble l'en-tête, les sections accueil, diagnostic, intervention, matériel remplacé et signatures.

### src/modules/tech/history.php

Historique des interventions terminées.

Il donne accès aux rapports passés, aux chats et aux PDF.

## Module Accès Compte / Management

Le module charge_compte donne une vue managériale.

Il suit une équipe technique rattachée à un responsable.

### src/modules/charge_compte/dashboard.php

Tableau de bord du chargé de compte.

Il récupère l'équipe, les interventions liées et construit une vue d'ensemble du portefeuille.

Il prend aussi en compte les différences de schéma entre instances.

### src/modules/charge_compte/techniciens.php

Liste des techniciens de l'équipe.

Cette vue sert à la supervision directe.

### src/modules/charge_compte/technicien_detail.php

Détail d'un technicien.

Le fichier sert à analyser la charge ou le périmètre d'un membre de l'équipe.

### src/modules/charge_compte/interventions.php

Interventions de l'équipe.

Cette page aligne les tickets et les affectations sous un angle managérial.

### src/modules/charge_compte/affectations.php

Vue des affectations.

Elle aide à contrôler qui est lié à quoi dans l'équipe.

### src/modules/charge_compte/visites_preventives.php

Visites préventives.

Le fichier suggère un suivi planifié au-delà du curatif.

### src/modules/charge_compte/statistiques.php

Statistiques de l'équipe.

Cette vue consolide l'activité et la performance du périmètre.

## Module Admin

Le module admin regroupe la supervision, les corrections et les opérations d'exploitation.

### src/modules/admin/dashboard.php

Vue globale de direction.

Elle agrège des statistiques, des graphiques et des accès rapides vers les modules métiers.

### src/modules/admin/statistics.php

Statistiques et export.

Le module consolide les données pour analyse ou extraction.

### src/modules/admin/users.php

Liste des utilisateurs.

La page sert à administrer les comptes et leurs rôles.

### src/modules/admin/user_create.php

Création d'utilisateur.

Ce formulaire prépare les comptes internes.

### src/modules/admin/user_edit.php

Édition d'utilisateur.

Il sert à corriger le rôle, le nom ou les attributs d'un compte.

### src/modules/admin/import_csv.php

Import CSV.

Le fichier est l'une des portes d'entrée de la donnée externe.

### src/modules/admin/export_stats.php

Export statistique.

Il permet de sortir des métriques pour archivage ou analyse hors ligne.

### src/modules/admin/system_logs.php

Logs système.

Le fichier expose les événements et erreurs utiles au support technique.

### src/modules/admin/check_contrats.php

Contrôle des contrats.

Il peut servir à valider l'état ou la cohérence des contrats chargés.

### src/modules/admin/fix_contrat_fields_confusion.php

Correction de confusion de champs.

Ce script appartient à la maintenance de données importées ou mal mappées.

### src/modules/admin/repair_imported_contrat.php

Réparation d'un contrat importé.

Le fichier aide à corriger les enregistrements dégradés après import.

### src/modules/admin/delete_all_clients.php

Suppression globale des clients.

Action destructive réservée à l'administration.

### src/modules/admin/delete_all_sites.php

Suppression globale des sites.

Même logique de maintenance extrême.

### src/modules/admin/delete_all_contrats.php

Suppression globale des contrats.

Ce fichier doit être manipulé avec prudence.

### src/modules/admin/test_import.php

Test d'import.

Il permet de valider la chaîne d'import sans aller jusqu'à la production complète.

### src/modules/admin/audit_imports.php

Audit des imports côté admin.

Il complète l'audit visible depuis l'accueil.

## API métier internes

### src/modules/api/get_client.php

Renvoie un client en JSON.

Le fichier est utilisé par des modales ou des consultations rapides.

### src/modules/api/get_contrat.php

Renvoie le dernier contrat d'un client en JSON.

Il calcule aussi un statut métier dérivé des dates de début et de fin.

### src/modules/api/get_intervention_details.php

Renvoie les détails d'une intervention.

Il construit un texte de rapport à partir du rapport terrain et des recommandations.

### src/modules/api/chat_intervention.php

API de chat d'intervention.

Elle lit les messages existants et insère de nouveaux messages entre Dispatch, TAC et Tech.

### src/modules/api/rewrite_cloture_text.php

API IA de reformulation.

Elle envoie le texte à un modèle externe et récupère une version plus professionnelle du rapport de clôture.

## Automatisation

### src/modules/automation/update_contract_status.php

Mise à jour automatique du statut des contrats expirés.

Le script passe les contrats échus en TERMINE.

Il est déclenché depuis certains dashboards comme une tâche légère de cohérence.

## Lecture des écrans et logique métier

Le dashboard Accueil met l'accent sur le volume et le tri initial.

Le dashboard TAC met l'accent sur le diagnostic et le passage vers Dispatch.

Le dashboard Dispatch met l'accent sur l'assignation et le planning.

Le dashboard Tech met l'accent sur l'exécution et le rapport.

Le dashboard Commercial met l'accent sur la base client et la couverture contractuelle.

Le dashboard Admin met l'accent sur la vision globale, les statistiques et le contrôle système.

Le dashboard Charge de compte met l'accent sur l'équipe et le suivi managérial.

## Notifications

Les notifications sont un mécanisme transversal.

Elles servent à prévenir un rôle d'un nouveau ticket, d'un message de chat, d'une clôture ou d'une replanification.

Le badge du composant notification_ui.php est alimenté par l'API JSON du dossier public/api.

NotificationManager est le point de contact serveur pour créer, lire et marquer les messages.

## Clôture et IA

La clôture d'intervention s'appuie sur une modale unifiée.

Le technicien ou le dispatch peut y récupérer le rapport initial et le reformuler.

rewrite_cloture_text.php externalise la reformulation vers une API de modèle de langage.

La logique garde une limite de taille et vérifie la présence d'une clé d'API.

## Rapport terrain

Le rapport terrain est central.

Il contient la preuve du déplacement, les horaires, le travail effectué, les recommandations, les pièces jointes et les produits utilisés.

Il met à jour l'intervention puis le ticket.

Il est aussi la source du PDF final.

## PDF d'intervention

Le PDF produit un document client lisible et imprimable.

Il rassemble les informations d'accueil, de diagnostic, d'intervention et de signature.

Le document est construit par FPDF avec une mise en page fixe.

## Import et maintenance

CsvImporter fait le gros du travail sur les jeux de données d'entrée.

Le code normalise les en-têtes, détecte des types métier et essaie d'aligner des colonnes hétérogènes.

Les scripts d'audit et de réparation sont pensés pour des jeux de données déjà chargés ou partiellement corrompus.

## Interface

Le style général de l'application est moderne, avec sidebar, cartes, badges, tableaux et modales.

Le projet utilise des composants réutilisables pour ne pas dupliquer la structure de page.

Le JavaScript natif gère les modales, les menus mobiles, les appels fetch et quelques interactions dynamiques.

## Prochaines parties du document

La suite de ce fichier peut être étendue avec un inventaire fichier par fichier encore plus détaillé, des exemples de requêtes SQL, des scénarios utilisateurs complets, un glossaire des statuts et une cartographie complète des actions de chaque page.

## Annexe A - Inventaire des fichiers racine

Cette annexe résume les fichiers visibles à la racine du dépôt et leur rôle pratique.

### index.php

Entrée publique principale du projet.

Selon la logique de déploiement, ce fichier peut servir de pivot d'entrée, de page de redirection ou de zone d'initialisation minimale.

### README.md

Documentation principale historique du projet.

Le nouveau fichier DOC_PROJET_TOTAL.md vient compléter ce README avec une description beaucoup plus poussée.

### test.php

Script de test.

Il sert généralement à valider la configuration PHP, la connexion base ou un comportement simple du serveur.

### config/

Dossier de configuration.

Il contient la connexion base et les paramètres SMTP.

### public/

Dossier public accessible par le web.

Il contient les pages de login, les API publiques, les assets et plusieurs scripts utilitaires d'initialisation.

### sql/

Dossier SQL.

Il contient le schéma maître du projet et les scripts de base de données.

### src/

Dossier applicatif principal.

Il regroupe les modules métier, les include communs, les librairies et les utilitaires.

## Annexe B - Chaîne de traitement d'un ticket

Cette chaîne résume la circulation d'un ticket depuis sa naissance jusqu'à sa sortie du système.

### Étape 1 : création

Le ticket est saisi par un utilisateur du profil Accueil.

Les informations de base proviennent du client, du site, du motif et de la priorité.

### Étape 2 : qualification TAC

Le ticket arrive dans le support expert.

Le TAC identifie le problème, récupère l'historique et ajoute un message de diagnostic.

### Étape 3 : orientation

Le ticket peut être gardé en traitement interne, renvoyé vers Dispatch ou traité sans sortie terrain.

La décision dépend de la nature du problème, du contrat et de la disponibilité des équipes.

### Étape 4 : planification

Dispatch affecte une date, une ressource et un technicien.

La planification transforme un ticket en intervention planifiée.

### Étape 5 : intervention terrain

Le technicien consulte son planning, sa route, l'adresse et la fiche client.

Il exécute l'intervention et saisit le rapport terrain.

### Étape 6 : clôture ou replanification

Selon le résultat, la mission est considérée comme résolue ou à reprendre.

Le ticket change alors de statut et des notifications sont envoyées.

### Étape 7 : archive

L'historique, le PDF, le chat et les notifications gardent la trace de l'opération.

## Annexe C - Statuts métier observables

Le projet emploie plusieurs états métier. Ils ne sont pas tous identiques d'une table à l'autre, mais on retrouve un noyau commun.

### nouveau

Ticket fraîchement créé.

Il n'a pas encore été pris en charge par le TAC.

### ouvert

Ticket visible et actif.

Il peut être prêt à être analysé ou déjà vu par le support.

### en_cours_tac

Ticket en traitement par le support expert.

Le TAC est en train de diagnostiquer ou de compléter le dossier.

### attente_dispatch

Ticket préparé pour le Dispatch.

Le dossier attend un créneau ou une affectation.

### planifie

Intervention programmée.

Le technicien est associé à une date et à un ticket.

### en_route

Le technicien est en déplacement ou considéré comme parti sur site.

### en_cours

Travail terrain en cours.

### termine

Intervention techniquement terminée.

### traite

Ticket résolu ou accepté comme traité dans le flux principal.

### cloture

Ticket clôturé avec rapport envoyé ou prêt à être archivé.

### attente_devis

Ticket orienté vers une étape de chiffrage ou une sortie hors contrat.

### hors_contrat

Le ticket n'est pas couvert par le contrat actif.

### TERMINE

Statut de contrat expiré ou consommé côté maintenance automatique.

## Annexe D - Tableaux de bord par rôle

### Accueil

Le tableau de bord Accueil est orienté pilotage du flux entrant.

Il montre l'état du parc de tickets et les signaux de traitement immédiat.

### Commercial

Le tableau de bord Commercial est orienté portefeuille.

Il donne une vision des clients, contrats, sites et encours de validité.

### TAC

Le tableau de bord TAC est orienté tri et diagnostic.

Il permet d'ouvrir un dossier, de le qualifier et de décider du prochain responsable.

### Dispatch

Le tableau de bord Dispatch est orienté charge et planification.

Il se concentre sur les tickets qui doivent devenir des interventions.

### Tech

Le tableau de bord Tech est orienté exécution.

Le technicien y voit ce qu'il doit faire, où il doit aller, et à quel moment.

### Admin

Le tableau de bord Admin est orienté supervision.

Il consolide les KPI, les graphiques, les logs et les accès modules.

### Charge de compte

Le tableau de bord Charge de compte est orienté management opérationnel.

Il suit l'équipe, les tickets et la visibilité sur les missions.

## Annexe E - Inventaire des pages Accueil

### dashboard.php

Page d'accueil du module Accueil.

Elle donne les indicateurs, les derniers tickets et les alertes récentes.

### tickets.php

Liste de consultation des tickets.

Elle sert à travailler les tickets déjà ouverts ou en circulation.

### ticket_create.php

Création d'un ticket.

Page de départ d'une nouvelle demande.

### ticket_edit.php

Édition d'un ticket existant.

### ticket_details.php

Vue détaillée d'un ticket.

### ticket_delete.php

Action de suppression.

### create_ticket_form.php

Formulaire structuré de saisie.

### create_ticket_search.php

Recherche de client ou de site avant création.

### clients.php

Accès aux clients.

### client_details.php

Détail client.

### historique_site.php

Historique d'un site.

### commandes.php

Liste des commandes.

### commande_create.php

Création d'une commande.

### audit_imports.php

Audit des données importées.

## Annexe F - Inventaire des pages Commercial

### dashboard.php

Vue principale commerciale.

### clients.php

Gestion des clients.

### client_details.php

Fiche client enrichie.

### client_edit.php

Édition de client.

### site_edit.php

Édition de site.

### contrats.php

Liste des contrats.

### contrat_create.php

Création de contrat.

### contrat_edit.php

Édition de contrat.

### contrat_delete.php

Suppression de contrat.

## Annexe G - Inventaire des pages TAC

### dashboard.php

Vue support expert.

### ticket_process.php

Traitement du ticket.

### ticket_detail.php

Lecture d'un ticket côté TAC.

### tickets_list.php

Liste des tickets TAC.

### my_tickets.php

Tickets attribués à l'utilisateur.

### historique_site.php

Historique d'un site côté TAC.

## Annexe H - Inventaire des pages Dispatch

### dashboard.php

Vue des tickets à planifier.

### assign_tech.php

Affectation technique.

### interventions_list.php

Liste des interventions planifiées.

### plan_intervention.php

Planification détaillée.

## Annexe I - Inventaire des pages Tech

### dashboard.php

Vue terrain du technicien.

### report_intervention.php

Saisie du rapport.

### generate_pdf.php

Génération du document PDF.

### history.php

Historique des interventions terminées.

## Annexe J - Inventaire des pages Admin

### dashboard.php

Centre de commande.

### statistics.php

Statistiques détaillées.

### users.php

Gestion des utilisateurs.

### user_create.php

Création de compte.

### user_edit.php

Édition de compte.

### import_csv.php

Import de données.

### export_stats.php

Export de statistiques.

### system_logs.php

Consultation des journaux.

### check_contrats.php

Contrôle de cohérence contrat.

### fix_contrat_fields_confusion.php

Correction de champs importés.

### repair_imported_contrat.php

Réparation de contrat importé.

### delete_all_clients.php

Suppression globale des clients.

### delete_all_sites.php

Suppression globale des sites.

### delete_all_contrats.php

Suppression globale des contrats.

### test_import.php

Test de chaîne d'import.

### audit_imports.php

Audit des imports.

## Annexe K - Inventaire des pages Charge de compte

### dashboard.php

Vue équipe et portefeuille.

### techniciens.php

Liste des techniciens.

### technicien_detail.php

Détail technicien.

### interventions.php

Interventions de l'équipe.

### affectations.php

Répartition des affectations.

### visites_preventives.php

Suivi préventif.

### statistiques.php

Indicateurs d'équipe.

## Annexe L - API publiques et internes

### public/api/get_notifications.php

Renvoie les notifications non lues et récentes.

### public/api/mark_read.php

Marque les notifications comme lues.

### src/modules/api/get_client.php

Renvoie un client au format JSON.

### src/modules/api/get_contrat.php

Renvoie le contrat le plus récent d'un client.

### src/modules/api/get_intervention_details.php

Renvoie le rapport d'intervention ou les éléments de texte de clôture.

### src/modules/api/chat_intervention.php

Gère la lecture et l'écriture du chat.

### src/modules/api/rewrite_cloture_text.php

Reformule un texte de clôture via IA.

## Annexe M - Règles de visibilité des montants

Certaines données financières ne doivent pas être visibles par tous les rôles.

Le code de get_contrat.php masque le montant pour les rôles qui ne font pas partie de la liste autorisée.

Cette règle protège les informations économiques sensibles tout en laissant la consultation du reste du contrat.

## Annexe N - Messages et notifications

Le système de notifications n'est pas décoratif.

Il est utilisé pour la coordination quotidienne.

Une création de ticket peut être signalée.

Un message de chat peut prévenir Dispatch ou le technicien.

Une clôture peut prévenir l'administration.

Une replanification peut prévenir le support ou le TAC.

## Annexe O - Chat d'intervention

Le chat d'intervention permet un échange contextualisé autour d'une mission précise.

Le message appartient à une intervention identifiée.

Le système distingue les messages envoyés par le technicien et ceux envoyés par Dispatch ou par un autre rôle.

Le flux a une dimension asynchrone et trace les conversations utiles au suivi du dossier.

## Annexe P - Génération PDF

Le PDF d'intervention est structuré en sections lisibles.

Les données de client, de site, de technicien, d'horaires et de travaux servent à reconstituer une pièce signée.

Le document produit une sortie professionnelle pour le client et une archive pour le dossier.

## Annexe Q - Import CSV et nettoyage de données

CsvImporter est l'une des classes les plus importantes du projet.

Son rôle n'est pas seulement d'insérer des lignes.

Il tente aussi de reconnaître le sens des colonnes, de supprimer les caractères parasites et de normaliser les valeurs utiles.

Cela est essentiel dans un système où les imports proviennent souvent de fichiers externes hétérogènes.

## Annexe R - Vérification contractuelle automatique

updateExpiredContracts() est une mécanique simple mais utile.

Elle évite de laisser des contrats expirés avec un statut encore actif.

Cette tâche de cohérence est appelée dans certaines vues métier pour remettre le référentiel dans un état plus juste.

## Annexe S - Composants UI communs

head.php charge les feuilles de style et les ressources globales.

notification_ui.php standardise le bouton de notification.

cloture_modal_ui.php standardise la saisie du rapport final.

cloture_modal_logic.php apporte la logique d'ouverture et de fermeture de la modale.

theme_toggle.php donne un point de personnalisation visuelle.

## Annexe T - Rôle de la base de données

La base SQL Server est le cœur réel du projet.

Tous les écrans s'y connectent pour lire ou écrire les données métier.

Les tickets, les contrats, les interventions et les messages sont tous liés par des clés qui structurent les étapes du traitement.

Les scripts d'import, de réparation et d'audit prouvent que la base est aussi un objet d'exploitation continue et pas seulement de stockage.

## Annexe U - Lecture métier des pages de login

login.php n'est pas seulement une page de formulaire.

Elle décide du premier point d'entrée selon la session existante, le mot de passe, le rôle et l'identité.

Les variantes login_new.php et login_backup.php montrent qu'il existe un historique de refonte ou de migration de l'écran d'authentification.

## Annexe V - Fenêtres modales

Le projet utilise plusieurs modales pour ne pas quitter le contexte principal.

La modale de chat permet de converser sans quitter le planning.

La modale de détails du ticket évite de naviguer vers une autre page juste pour lire le problème.

La modale de clôture centralise la rédaction finale et l'envoi éventuel du mail.

## Annexe W - Navigation latérale

Chaque grand rôle dispose d'une sidebar adaptée.

Ce choix réduit la confusion et garde les actions les plus fréquentes à portée de clic.

La sidebar n'est pas seulement décorative. Elle formalise la séparation des responsabilités.

## Annexe X - Quelques dépendances visibles dans l'interface

Font Awesome est utilisé pour les icônes.

Chart.js est utilisé dans les tableaux de bord analytiques.

Inter est chargé comme police principale sur plusieurs écrans.

Le projet semble mélanger des styles globaux et des styles locaux par page pour conserver une identité visuelle cohérente.

## Annexe Y - Exemples de parcours utilisateur

### Parcours commercial

Le commercial ouvre son dashboard, consulte les clients, ouvre un contrat, vérifie les échéances et peut relier un client à un site.

### Parcours TAC

Le TAC ouvre les tickets nouveaux, lit les détails, ajoute un commentaire diagnostic et transmet au Dispatch.

### Parcours dispatch

Dispatch voit les tickets en attente, ouvre la fiche de détail, affecte un technicien et surveille les retours de chat.

### Parcours technicien

Le technicien consulte son planning, se rend sur site, saisit le rapport, peut utiliser l'IA de reformulation puis finalise la mission.

### Parcours admin

L'admin vérifie les comptes, les logs, les statistiques et les imports, puis corrige si besoin les anomalies structurelles.

## Annexe Z - Pourquoi ce projet est modulaire

Le projet n'est pas organisé comme une suite de pages isolées.

Il est découpé par métier, ce qui facilite l'évolution de chaque flux sans casser tout le reste.

Ce choix est visible dans l'arborescence source, où chaque dossier de module correspond à un espace de travail réel.

## Annexe AA - Points forts observables

Le système couvre plusieurs métiers avec un même socle technique.

Il contient des API internes pour éviter de recharger inutilement les pages.

Il conserve l'historique d'activité.

Il produit des PDF de sortie.

Il sait notifier les acteurs concernés.

Il dispose d'outils de maintenance et d'import.

## Annexe AB - Points de vigilance

Le projet mélange plusieurs conventions de noms selon les pages et les couches.

Certaines zones semblent héritées d'anciennes versions ou de migrations progressives.

Certains traitements doivent donc être lus avec attention avant une modification structurelle.

Les fichiers d'administration destructive doivent être traités comme des scripts de maintenance exceptionnels.

## Annexe AC - Fichiers sensibles à connaître

config/db.php

config/smtp_config.php

src/auth/auth_check.php

src/utils/SmtpSender.php

src/utils/NotificationManager.php

src/modules/api/rewrite_cloture_text.php

src/modules/tech/report_intervention.php

src/modules/tech/generate_pdf.php

src/modules/admin/import_csv.php

src/modules/admin/delete_all_clients.php

## Annexe AD - Lecture globale des flux de données

Les données entrent par les pages de création, les imports ou les formulaires d'administration.

Elles sont ensuite consommées par les tableaux de bord, les listes métier, les modales et les exports.

La sortie prend plusieurs formes : écran, PDF, notification, email, ou mise à jour de statut.

Le même enregistrement peut donc être lu, enrichi, transmis, imprimé, notifié et clôturé.

## Annexe AE - Récapitulatif métier final

Le projet SAV est une application opérationnelle complète.

Il n'est pas centré sur un seul écran mais sur la circulation d'une information entre plusieurs rôles.

Chaque dossier représente un point du cycle de vie client et technique.

Chaque profil a un ensemble de responsabilités cohérent.

Chaque module s'insère dans un flux global de support, de planification et de clôture.

Ce document peut servir de base de compréhension pour l'exploitation, la maintenance, l'audit fonctionnel et l'onboarding d'un nouveau développeur.

