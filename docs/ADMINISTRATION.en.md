# Administration — SnappyMail-carddav

[Français](ADMINISTRATION.md) · **English**

## Installation

### Where the plugin lives

| Location | Path | Role |
|---|---|---|
| Source | `SnappyMail-carddav` repository | development, tests |
| Production | `<snappymail>/data/_data_/_default_/plugins/carddav` | deployed folder |
| Reference | `git.convergent.tn/fbennasr/SnappyMail-carddav` | reference repository, mirrored to GitHub |

⚠️ The repository is mirrored to GitHub with `--mirror` semantics: push **to Forgejo only**,
otherwise branches missing from Forgejo — including an upstream PR's — disappear.

### Procedure

1. Back up the existing `carddav` folder outside the served tree.
2. Copy `index.php`, `update-carddav.js` and `langs/` into `plugins/carddav/`.
3. Under SELinux, `restorecon -R` on the folder; otherwise the web server reads nothing and
   SnappyMail removes the plugin from `enabled_list`.
4. Web server ownership and permissions, files in `644`.
5. Enable the plugin in the admin panel, then re-read `enabled_list`.

## Configuration

| Option | Where | Default | Effect | Restart? |
|---|---|---|---|---|
| `carddav_url_template` | panel, plugin `carddav` | empty | URL template; `{user}`, `{email}`, `{login}`, `{domain}` | no |
| `dav_default_domain` | same | empty | domain whose addresses use their local part only | no |

Example: `https://dav.example.com/dav/addressbooks/user/{user}/Default/` with `example.com` as the
default domain.

## Operations

### Logs

A missing template is noted in SnappyMail's log at sign-in; `contacts_sync` is left untouched.

### Monitoring

Re-read `enabled_list` after any panel visit. Watch contact counts after a first two-way sync.

### Backup and restore

The plugin stores nothing; contacts live in the SnappyMail address book and on the CardDAV server.

## Upgrade and uninstall

Upgrade: `VERSION` constant, `CHANGELOG.md`, tests, procedure above (1.9.1 awaits deployment).
Uninstall: remove `carddav` from `enabled_list`; `contacts_sync` entries already written remain.

## Troubleshooting

| Symptom | Diagnosis | Fix |
|---|---|---|
| No synchronisation | log: "no template" | set `carddav_url_template` |
| Server answers `403 supported-address-data` | core HTTP headers dropped | SnappyMail with the fix submitted upstream |
| Local contacts deleted | empty list treated as authoritative by `PdoAddressBook::Sync()` | upstream fix; keep `Mode: 0` meanwhile |
