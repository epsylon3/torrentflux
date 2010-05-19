<?php

/* $Id: lang-french.php 3111 2007-06-16 23:49:48Z b4rt $ */

/**************************************************************************/
/* TorrentFlux - PHP Torrent Client
/* ============================================
/*
/* This is the language file with all the system messages
/*
/* If you would like to make a translation, please email qrome@yahoo.com
/* the translated file. Please keep the original text order,
/* and just one message per line, also double check your translation!
/*
/* You need to change the second quoted phrase, not the capital one!
/*
/* If you need to use double quotes (") remember to add a backslash (\),
/* so your entry will look like: This is \"double quoted\" text.
/* And, if you use HTML code, please double check it.
/**************************************************************************/

define("_CHARSET", "iso-8859-15");  // if you don't know... then leave this as is.
define("_SELECTFILE", htmlentities("Uploader un torrent depuis votre poste"));
define("_URLFILE",htmlentities("URL de fichier.torrent depuis un site web externe (https géré !)"));
define("_UPLOAD", htmlentities("Envoyer"));
define("_GETFILE", htmlentities("Ajouter le torrent"));
define("_LINKS", htmlentities("Liens"));
define("_ONLINE", htmlentities("En ligne"));
define("_OFFLINE", htmlentities("Hors ligne"));
define("_STORAGE", htmlentities("Disque dur"));
define("_DRIVESPACE", htmlentities("Espace Disque"));
define("_SERVERSTATS", htmlentities("Stats du serveur / Qui"));
define("_DIRECTORYLIST", htmlentities("Listing répertoire"));
define("_ALL", htmlentities("Tous"));
define("_PAGEWILLREFRESH", htmlentities("La Page se recharge toutes les"));
define("_SECONDS", htmlentities("secondes"));
define("_TURNONREFRESH", htmlentities("Activer le Rechargement Auto"));
define("_TURNOFFREFRESH", htmlentities("Arrêter le Rechargement Auto"));
define("_WARNING", htmlentities("AVERTISSEMENT"));
define("_DRIVESPACEUSED", htmlentities("L'espace disque est saturé!"));
define("_ADMINMESSAGE", htmlentities("Vous avez un message d'un administrateur dans votre boîte de messages."));
define("_TORRENTS", htmlentities("Torrents"));
define("_UPLOADHISTORY", htmlentities("Historique"));
define("_MYPROFILE", htmlentities("Éditer Mon Profil"));
define("_ADMINISTRATION", htmlentities("Administration"));
define("_SENDMESSAGETO", htmlentities("Envoyez un message à"));
define("_TRANSFERFILE", htmlentities("Fichier Torrent"));
define("_FILESIZE", htmlentities("Taille Du Fichier"));
define("_STATUS", htmlentities("Statut"));
define("_ADMIN", htmlentities("Admin"));
define("_BADFILE", htmlentities("Mauvais Fichier"));
define("_DATETIMEFORMAT", htmlentities("Y/m/d - H:i:s"));
define("_DATEFORMAT", htmlentities("Y/m/d"));
define("_ESTIMATEDTIME", htmlentities("Temps Estimé"));
define("_DOWNLOADSPEED", htmlentities("Vitesse Download"));
define("_UPLOADSPEED", htmlentities("Vitesse Upload"));
define("_SHARING", htmlentities("Partage"));
define("_USER", htmlentities("Utilisateur"));
define("_DONE", htmlentities("FAIT"));
define("_INCOMPLETE", htmlentities("INACHEVÉ"));
define("_NEW", htmlentities("NOUVEAU"));
define("_TRANSFERDETAILS", htmlentities("Details du Transfert"));
define("_STOPTRANSFER", htmlentities("Stopper le Transfert"));
define("_RUNTRANSFER", htmlentities("Lancer le Transfert"));
define("_SEEDTRANSFER", htmlentities("Partager le Transfert"));
define("_DELETE", htmlentities("Effacer"));
define("_ABOUTTODELETE", htmlentities("Vous êtes sur le point de supprimer"));
define("_NOTOWNER", htmlentities("Pas proprietaire du Transfert"));
define("_MESSAGETOALL", htmlentities("Ce message a ete envoye à TOUS LES UTILISATEURS"));
define("_TRYDIFFERENTUSERID", htmlentities("Erreur: Essayez un utilisateur different."));
define("_HASBEENUSED", htmlentities("a été utilisé."));
define("_RETURNTOEDIT", htmlentities("Retour à l'édition"));
define("_ADMINUSERACTIVITY", htmlentities("Administration - Activité D'Utilisateurs"));
define("_ADMIN_MENU", htmlentities("admin"));
define("_ACTIVITY_MENU", htmlentities("activité"));
define("_LINKS_MENU", htmlentities("liens"));
define("_NEWUSER_MENU", htmlentities("nouvel utilisateur"));
define("_BACKUP_MENU", htmlentities("sauvegarde"));
define("_ALLUSERS", htmlentities("Tous les Utilisateurs"));
define("_NORECORDSFOUND", htmlentities("PAS D'ENREGISTREMENT TROUVÉ"));
define("_SHOWPREVIOUS", htmlentities("Précédent"));
define("_SHOWMORE", htmlentities("Voir Plus"));
define("_ACTIVITYSEARCH", htmlentities("Activité des Recherches"));
define("_FILE", htmlentities("Fichier"));
define("_ACTION", htmlentities("Action"));
define("_SEARCH", htmlentities("Search"));
define("_ACTIVITYLOG", htmlentities("Fichier de journalisation - Dernier"));
define("_DAYS", htmlentities("Jours"));
define("_IP", htmlentities("IP"));
define("_TIMESTAMP", htmlentities("Temps"));
define("_USERDETAILS", htmlentities("Détails de l'Utilisateur"));
define("_HITS", htmlentities("Hits"));
define("_UPLOADACTIVITY", htmlentities("Activité D'Upload"));
define("_JOINED", htmlentities("à rejoint")); // header for the date when the user joined (became a member)
define("_LASTVISIT", htmlentities("Dernière Visite")); // header for when the user last visited the site
define("_USERSACTIVITY", htmlentities("Activité")); // used for popup to display Activity next to users name
define("_NORMALUSER", htmlentities("Utilisateur Normal")); // used to describe a normal user's account type
define("_ADMINISTRATOR", htmlentities("Administrateur")); // used to describe an administrator's account type
define("_SUPERADMIN", htmlentities("Super Admin")); // used to describe Super Admin's account type
define("_EDIT", htmlentities("Éditer"));
define("_USERADMIN", htmlentities("Administration - Utilisateurs")); // title of page for user administration
define("_EDITUSER", htmlentities("Éditer L'Utilisateur"));
define("_UPLOADPARTICIPATION", htmlentities("Participation au Téléchargement"));
define("_UPLOADS", htmlentities("Uploads")); // Number of uploads a user has contributed
define("_PERCENTPARTICIPATION", htmlentities("Pourcentage de Participation"));
define("_PARTICIPATIONSTATEMENT", htmlentities("La participation et les téléchargements basés sur les derniers")); // ends with 15 Days
define("_TOTALPAGEVIEWS", htmlentities("Pages Vues au Total"));
define("_THEME", htmlentities("Thème"));
define("_USERTYPE", htmlentities("Type d'utilisateur"));
define("_NEWPASSWORD", htmlentities("Nouveau Mot de passe"));
define("_CONFIRMPASSWORD", htmlentities("Confirmez le Mot de passe"));
define("_HIDEOFFLINEUSERS", htmlentities("Masquer les utilisateurs Hors-ligne de la page d'acceuil"));
define("_UPDATE", htmlentities("Mise à jour"));
define("_USERIDREQUIRED", htmlentities("L'identification de l'utilisateur est exigée."));
define("_PASSWORDLENGTH", htmlentities("Le mot de passe doit avoir 6 caractères ou plus."));
define("_PASSWORDNOTMATCH", htmlentities("Les mots de passe sont différents."));
define("_PLEASECHECKFOLLOWING", htmlentities("Veuillez vérifier ces informations")); // Displays errors after this statement
define("_NEWUSER", htmlentities("Nouvel Utilisateur"));
define("_PASSWORD", htmlentities("Mot de passe"));
define("_CREATE", htmlentities("Créer")); // button text to create a new user
define("_ADMINEDITLINKS", htmlentities("Administration - Modifier Les Liens"));
define("_FULLURLLINK", htmlentities("URL Complete"));
define("_BACKTOPARRENT", htmlentities("Répertoire Parent"));  // indicates going back to parent directory
define("_DOWNLOADDETAILS", htmlentities("Détails Du Téléchargement"));
define("_PERCENTDONE", htmlentities("Pourcentage Terminé"));
define("_RETURNTOTRANSFERS", htmlentities("Revenir aux Transfers")); // Link at the bottom of each page
define("_DATE", htmlentities("Date"));
define("_WROTE", htmlentities("a écrit"));  // Used in a reply to tag what the user had writen
define("_SENDMESSAGETITLE", htmlentities("Envoyer un message"));  // Title of page
define("_TO", htmlentities("À"));
define("_FROM", htmlentities("De"));
define("_YOURMESSAGE", htmlentities("Votre Message"));
define("_SENDTOALLUSERS", htmlentities("Envoyez à tous les utilisateurs"));
define("_FORCEUSERSTOREAD", htmlentities("Force Utilisateur(s) à lire")); // Admin option in messaging
define("_SEND", htmlentities("Envoyer"));  // Button to send private message
define("_PROFILE", htmlentities("Profil"));
define("_PROFILEUPDATEDFOR", htmlentities("Profil mis à jour pour"));  // Profile updated for 'username'
define("_REPLY", htmlentities("Répondre"));  // popup text for reply button
define("_MESSAGE", htmlentities("Message"));
define("_MESSAGES", htmlentities("Messages"));  // plural (more than one)
define("_RETURNTOMESSAGES", htmlentities("Retourner aux messages"));
define("_COMPOSE", htmlentities("Ecrire"));  // As in 'Compose a message' for button
define("_LANGUAGE", htmlentities("Langue")); // label
define("_CURRENTDOWNLOAD", htmlentities("Current Download"));
define("_CURRENTUPLOAD", htmlentities("Current Upload"));
define("_SERVERLOAD", htmlentities("Server Load"));
define("_FREESPACE", htmlentities("Free Space"));
define("_UPLOADED", "Uploaded");
define("_QMANAGER_MENU", htmlentities("queue"));
define("_FLUXD_MENU", htmlentities("fluxd"));
define("_SETTINGS_MENU", htmlentities("settings"));
define("_SEARCHSETTINGS_MENU", htmlentities("search"));
define("_ERRORSREPORTED", htmlentities("Errors"));
define("_STARTED", "Started");
define("_ENDED", "Ended");
define("_QUEUED", htmlentities("Queued"));
define("_DELQUEUE", htmlentities("Remove from Queue"));
define("_FORCESTOP", htmlentities("Kill Transfer"));
define("_STOPPING", htmlentities("Stopping"));
define("_COOKIE_MENU", htmlentities("cookies"));
define('_TOTALXFER','Total Transfer');
define('_MONTHXFER','Month\'s Transfer');
define('_WEEKXFER','Week\'s Transfer');
define('_DAYXFER','Today\'s Transfer');
define('_XFERTHRU','Transfer thru');
define('_REMAINING','Remaining');
define('_TOTALSPEED','Total Speed');
define('_SERVERXFERSTATS','Server Transfer Stats');
define('_YOURXFERSTATS','Your Transfer Stats');
define('_OTHERSERVERSTATS','Other Server Stats');
define('_TOTAL','Total');
define('_DOWNLOAD','Download');
define('_MONTHSTARTING','Month Starting');
define('_WEEKSTARTING','Week Starting');
define('_DAY','Day');
define('_XFER','transfer');
define('_XFER_USAGE','Transfer Usage');
define('_QUEUEMANAGER','Queue Manager');
define('_MULTIPLE_UPLOAD','Multiple Upload');
define('_TDDU','Directory Size:');
define("_FULLSITENAME", "Site Name");
define('_MOVE_STRING','Move File/Folder to: ');
define('_DIR_MOVE_LINK', 'Move File/Folder');
define('_MOVE_FILE', 'File/Folder: ');
define('_MOVE_FILE_TITLE', 'Move Data...');
define('_REN_STRING','Rename File/Folder to: ');
define('_DIR_REN_LINK', 'Rename File/Folder');
define('_REN_FILE', 'File/Folder: ');
define('_REN_DONE', 'Done!');
define('_REN_ERROR', 'An error accured, please try again!');
define('_REN_ERR_ARG', 'Wrong argument supplied!');
define('_REN_TITLE', 'Rename');
define('_ID_PORT','Port');
define('_ID_PORTS','Ports');
define('_ID_CONNECTIONS','Connexions');
define('_ID_HOST','Host');
define('_ID_HOSTS','Hosts');
define('_ID_IMAGES','Images');

?>