# SnappyMail CardDAV plugin — Cyrus IMAP / standards-compliant fork

A fork of the **Mailbux CardDAV Auto** plugin for [SnappyMail](https://snappymail.eu),
adapted so that it follows the CardDAV standards ([RFC 6352](https://www.rfc-editor.org/rfc/rfc6352))
closely enough to work against **[Cyrus IMAP](https://www.cyrusimap.org/)**'s CardDAV
implementation — and, by the same token, against any other standards-compliant
CardDAV server.

## What was changed and why

**The server URL is configuration, not code.** Upstream hardcodes one hosting
provider's host and rewrites the account's `contacts_sync` entry on every login,
so contacts never reach any other server and a manual correction is silently
overwritten at the next sign-in. The URL now comes from the plugin's own settings
page, via a template with `{user}`, `{email}`, `{login}` and `{domain}`
placeholders. There is no built-in default: with no template configured the
plugin logs a notice and leaves `contacts_sync` untouched rather than writing a
URL that cannot work.

**Cyrus URL layout.** Cyrus serves `/dav/addressbooks/user/<user>/<collection>`,
discoverable through the standard `addressbook-home-set` property. With
`virtdomains: userid` a mailbox in the default domain is addressed by its local
part and everything else by the full address, which the `{user}` placeholder and
the default-domain setting handle.

**A disabled sync stays disabled.** The auto-configuration hook runs on every
login and unconditionally re-enabled two-way sync, overriding an administrator
who had deliberately turned it off. That matters: a two-way sync against an
addressbook that merely *looks* empty deletes local contacts. An existing
`Mode: 0` is now preserved.

## Configuration

Admin → Plugins → carddav:

| Setting | Example |
| --- | --- |
| CardDAV URL template | `https://dav.example.com/dav/addressbooks/user/{user}/Default/` |
| DAV default domain | `example.com` — addresses in this domain use the local part only, matching Cyrus `virtdomains: userid`; leave empty to always use the full address |

## Related upstream fixes

Getting a two-way sync working against Cyrus also required two fixes in
SnappyMail itself, submitted upstream rather than carried here:

- HTTP drivers dropped associative request headers, so CardDAV uploads were sent
  as `application/x-www-form-urlencoded` and refused with
  `403 supported-address-data` — the sync silently behaved as import-only.
- `PdoAddressBook::Sync()` treated an empty list on either side as authoritative,
  so a failed or empty listing deleted contacts that still existed on the other
  side.

## Credits and licence

Original plugin © 2025 Mailbux — see `LICENSE`. This fork keeps that licence and
exists only to make the plugin work against standards-compliant CardDAV servers.

---

# Original plugin README

Everything below is the upstream **Mailbux CardDAV Auto** README, kept
verbatim. This fork does not change the plugin's origin or its authors'
presentation of their service.

## ⚠️ Note

> **Important:**  
> This plugin **must be installed together with the [SnappyMail CalDAV Plugin](https://github.com/mailwish/SnappyMail-CalDAV-Plugin)** for full synchronization support.  
> The CalDAV plugin provides shared logic used by both calendar and contact synchronization modules.

# 👥 SnappyMail CardDAV Plugin

A lightweight and modern **CardDAV integration** for [SnappyMail](https://snappymail.eu), proudly created by [**Mailbux.com**](https://mailbux.com) — the all-in-one **free business email hosting** platform.

![SnappyMail CalDAV Plugin](https://mailwish.com/wp-content/uploads/2025/04/logo240.png)
---

## ✨ Description

The **SnappyMail CardDAV Plugin** enables automatic synchronization of contacts with any CardDAV server.  
It seamlessly connects SnappyMail’s address book to your remote contact source — including instant sync with [**Mailbux.com**](https://mailbux.com) address books.

This plugin intelligently detects and **auto-switches to remote CardDAV sync** when available, ensuring your contacts always stay up to date across all devices.

---

## 🚀 Features

- 👥 Sync contacts automatically via CardDAV  
- 🔄 **Auto-detect & switch** to remote CardDAV servers  
- 🔒 Secure encrypted sync (HTTPS/TLS)  
- ⚙️ Simple setup in SnappyMail settings  
- 📨 Fully compatible with [Mailbux.com](https://mailbux.com) accounts  
- 📱 Works with any CardDAV-compatible client or mobile app  

---

## 🛠️ Installation

1. Download or clone this repository.  
2. Copy the plugin folder into your SnappyMail `/plugins/` directory.  
3. Enable the plugin from the **SnappyMail Admin Panel**.  
4. Configure your CardDAV server details (Mailbux or other).  

> ✅ Once configured, SnappyMail will automatically connect and sync contacts in the background.

---

## 💡 About Mailbux

[**Mailbux.com**](https://mailbux.com) provides **unlimited, free business email hosting** — professional email at your domain, with built-in CalDAV, CardDAV, and WebDAV support for full productivity.

### 🌟 Why Choose Mailbux

| Feature | Mailbux.com | Google Workspace | Microsoft 365 |
|----------|--------------|------------------|----------------|
| Price | **Free** | $6/user/mo | $6/user/mo |
| Email Accounts | **Unlimited** | 1 per user | 1 per user |
| Domains | **Unlimited** | Limited | Limited |
| Contacts & Calendar Sync (DAV) | ✅ | ✅ | ✅ |
| Custom Branding | ✅ | ❌ | ❌ |
| SMTP Relay | ✅ | ✅ | ✅ |

### Highlights
- 💌 **Unlimited business email hosting**  
- 🌐 **CardDAV, CalDAV, and WebDAV** for contacts, calendars, and files  
- ⚙️ **Admin dashboard** with API access  
- 🧩 **White-label & rebranding** options for resellers  
- 🔐 **Ad-free, privacy-focused platform**  

> 💬 “Mailbux lets me manage all my business contacts and calendars in one place — completely free.”

---

## 📷 Screenshots

*(Optional: Add plugin or contact sync screenshots here)*

---

## 🌐 Learn More

👉 [**Mailbux.com**](https://mailbux.com) — Create your **free business email account** today.  
Unlimited mailboxes, custom domains, and integrated contact & calendar sync — all at no cost.

---

## 🧑‍💻 Author

**Developed by [Mailbux.com](https://mailbux.com)**  
📧 Support: [support@mailbux.com](mailto:support@mailbux.com)

---

© 2025 Mailbux.com — Powered by [CloudWish LLC](https://cloudwish.com)
