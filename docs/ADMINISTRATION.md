# Administration — SnappyMail-carddav

**Français** · [English](ADMINISTRATION.en.md)

## Installation

### Où vit le greffon

| Emplacement | Chemin | Rôle |
|---|---|---|
| Source | dépôt `SnappyMail-carddav` | développement, tests |
| Production | `<snappymail>/data/_data_/_default_/plugins/carddav` | dossier déployé |
| Référence | `git.convergent.tn/fbennasr/SnappyMail-carddav` | dépôt de référence, miroité sur GitHub |

⚠️ Le dépôt est miroité vers GitHub avec la sémantique `--mirror` : pousser **sur Forgejo
seulement**, sinon les branches absentes de Forgejo — dont celle d'une PR amont — disparaissent.

### Procédure

1. Sauvegarder le dossier `carddav` existant hors de l'arborescence servie.
2. Copier `index.php`, `update-carddav.js` et `langs/` dans `plugins/carddav/`.
3. Sous SELinux, `restorecon -R` sur le dossier ; sinon le serveur web ne lit rien et SnappyMail
   retire le greffon de `enabled_list`.
4. Propriétaire et droits du serveur web, fichiers en `644`.
5. Activer le greffon dans le panneau d'administration, puis relire `enabled_list`.

Les paquets de la suite sont bâtis par l'outil d'empaquetage commun, jamais par un script propre à
ce dépôt.

## Configuration

| Option | Où | Défaut | Effet | Redémarrage ? |
|---|---|---|---|---|
| `carddav_url_template` | panneau, greffon `carddav` | vide | gabarit d'URL ; `{user}`, `{email}`, `{login}`, `{domain}` | non |
| `dav_default_domain` | idem | vide | domaine dont les adresses n'utilisent que la partie locale | non |

Exemple : `https://dav.example.com/dav/addressbooks/user/{user}/Default/` avec `example.com` pour
domaine par défaut. Les libellés suivent la langue de l'administrateur depuis la 1.9.1.

## Exploitation

### Journaux

Un gabarit absent est signalé dans le journal de SnappyMail à la connexion ; `contacts_sync` reste
alors intact.

### Supervision

Relire `enabled_list` après toute visite du panneau. Surveiller les écarts de nombre de contacts
après une première synchronisation bidirectionnelle.

### Sauvegarde et restauration

Le greffon ne stocke rien ; les contacts vivent dans le carnet SnappyMail et sur le serveur CardDAV.

## Mise à jour et désinstallation

Montée de version : constante `VERSION`, `CHANGELOG.md`, tests, procédure ci-dessus (la 1.9.1
attend son déploiement). Désinstallation : retirer `carddav` de `enabled_list` ; les réglages
`contacts_sync` déjà écrits restent dans les comptes.

## Dépannage

| Symptôme | Diagnostic | Remède |
|---|---|---|
| Aucune synchronisation | journal : « no template » | régler `carddav_url_template` |
| `403 supported-address-data` côté serveur | en-têtes HTTP du cœur perdus | SnappyMail avec le correctif envoyé à l'amont |
| Contacts locaux effacés | liste vide jugée autoritaire par `PdoAddressBook::Sync()` | correctif amont ; garder `Mode: 0` en attendant |
