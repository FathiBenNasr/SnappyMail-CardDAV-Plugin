# TODO — CardDAV

## 1.9.1 (22 octobre 2026)
- Réglages d'administration en anglais/français/arabe (2 réglages), français par défaut ;
  `NoteLangueAdmin` sur `json.after-AdminSettingsUpdate`. 14 tests verts.
- Mutations éprouvées (chacune fait tomber un test) : q=0, première préférence en/fr,
  défaut français, borne 512, `Result === true`, `languageAdmin` non vide, marque, choix explicite.
- **Non déployé** : à faire par le propriétaire (sauvegarde, copie vers `plugins/carddav/`
  y compris `langs/`, `restorecon -R`, vérification). Dépôt miroité GitHub : pousser sur Forgejo seulement.
