# Sécurité — SnappyMail-carddav

## Points d'entrée et authentification

| Point d'entrée | Méthode | Authentification | Autorisation | Contrôles |
|---|---|---|---|---|
| accroche de configuration à la connexion | appelée par le cœur | connexion réussie | compte de la session | gabarit seulement s'il est réglé ; `Mode: 0` préservé |
| réglages | panneau d'administration | administrateur SnappyMail | — | gabarit libre, borné aux quatre substitutions |
| synchronisation CardDAV | HTTPS vers le serveur réglé | identifiants de la session | carnet de la personne | serveur choisi par l'administrateur, plus d'hôte tiers en dur |

## Données personnelles

| Donnée | Finalité | Base légale | Durée de conservation | Destinataires |
|---|---|---|---|---|
| contacts du carnet | synchronisation | exécution du service | celle du carnet et du serveur | le serveur CardDAV réglé |

L'amont envoyait les contacts à l'hôte d'un hébergeur tiers écrit en dur ; le fork ne les envoie
qu'au serveur que l'administrateur règle.

## Menaces et mesures

| Menace | Mesure en place | Exigence ASVS | Test qui le prouve |
|---|---|---|---|
| Fuite des contacts vers un tiers | hôte en dur retiré, gabarit obligatoire | V1.14 | `tests/DavUrlTest.php` |
| Effacement des contacts locaux | `Mode: 0` préservé ; garde de liste vide proposée à l'amont | V1.14 | `tests/DavUrlTest.php` |
| Réglage annulé à chaque connexion | aucune réécriture sans gabarit | — | `tests/DavUrlTest.php` |

## Secrets

Aucun détenu par le greffon.

## Écarts connus

- Le correctif de liste vide vit dans SnappyMail et dépend de l'amont.

## Signalement

Voir [SECURITY.md](../SECURITY.md).
