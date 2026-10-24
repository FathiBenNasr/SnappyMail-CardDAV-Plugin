# Journal des modifications

Toutes les évolutions notables du fork SnappyMail-carddav (dossier déployé `carddav`). Format :
[Keep a Changelog](https://keepachangelog.com/fr/1.1.0/). Un numéro de version n'est jamais
réutilisé. Reconstitué le 24 octobre 2026 depuis l'historique Git.

## [Non publié]

### Ajouté

- Documentation au standard Convergent : README en français et en anglais, administration,
  architecture, sécurité ; le README d'origine est conservé tel quel dans `docs/README-amont.md`.

### Corrigé

- Le README renvoyait à un fichier `LICENSE` inexistant et disait « ce fork garde cette licence » :
  aucune licence n'a été accordée, comme le dit `AUTHORS`.

## [1.9.1] — 2026-10-22

### Modifié

- Les deux réglages d'administration suivent la langue de l'administrateur (anglais, français,
  arabe ; français par défaut). Non déployé à cette date.

## [1.8] — 2026-08-12

### Modifié

- L'URL CardDAV vient des réglages du greffon, plus d'un hôte écrit en dur ; une synchronisation
  désactivée reste désactivée.
