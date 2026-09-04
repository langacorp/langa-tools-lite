# LANGA Tools Lite

[![DOI](https://img.shields.io/badge/DOI-10.5281%2Fzenodo.22299156-blue)](https://doi.org/10.5281/zenodo.22299156)

A WordPress plugin that groups the branding, UI/UX and site-management tools
LANGA uses across its own sites and the sites it looks after: maintenance mode,
preloader, favicon override, admin bar cleanup, ghost pages, credits, and a
client that keeps a site connected to a LANGA management server.

Owned by **LANGA Corporation S.r.l.** GPLv2 or later.

## Two builds, and why the version numbers differ

This repository holds the **self-hosted build**, the one distributed from our
own site. It includes `core/` — the client that registers a site with a LANGA
management server, signs its calls with HMAC-SHA256 and lets modules be turned
on and off remotely.

A **reduced build** is published on the WordPress plugin directory, without
`core/`, because wordpress.org does not allow a plugin to call home:

https://wordpress.org/plugins/langa-tools-lite/

The two are versioned separately, so the directory release carries a higher
number than this one. Neither is a downgrade of the other: they are different
packages.

## What is in core/

Small and readable on purpose. If you are looking at how a WordPress plugin
can be managed from a central server, this is the whole of it:

| File | Lines | What it does |
|---|---|---|
| `auth.php` | 8 | one function: `hash_hmac('sha256', $payload, $secret)` |
| `command-runner.php` | 30 | two commands: enable a module, disable a module |
| `api-client.php` | 160 | signs and sends events to the server |
| `license.php` | 618 | asks the server, caches the answer, fails open after a grace period |
| `updater.php` | 149 | update channel |

**No credentials are shipped.** The site key and the signing secret are read
from the site's own options and are set when a site is registered. There is no
`eval`, no shell, no remote code download: `command-runner` only writes a
module name into an option.

## Install

Copy the folder into `wp-content/plugins/` as `langa-tools-lite`, or upload the
zip under Plugins → Add New → Upload Plugin.

Requires WordPress 5.7 or later.

## PRO

A separate PRO edition adds SEO, forms, GDPR, security hardening, cache,
popups and events as licensed modules. **PRO is not open source and is not in
this repository.** What is here is the client side: how a site talks to the
server, not what the server grants it.

## Licence

GPLv2 or later — see LICENSE. Copyright LANGA Corporation S.r.l.
