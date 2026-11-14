# Reactions — MyBB Plugin

Adds a Facebook-style reaction system to MyBB posts with hover tooltips and AJAX functionality.

Instead of a single "likes" count, posts get a full reaction bar: hover the reaction button, pick from a set of reaction types, and the post shows who reacted with what. Everything happens over AJAX — no page reloads, no repost.

## Features

- **Reaction bar on every post** — hover tooltip with the reaction picker, rendered from registered templates.
- **AJAX reactions** — react, change reaction, remove reaction without reloading the thread.
- **Multiple reaction types** administered from the ACP (`manage_reactions.php`) — each with its own image/icon and reputation value.
- **Reaction list pages** — `reactions.php?content_type=post&content_id=123` shows everyone who reacted to a post, paginated, XenForo-style, with a `reaction_id` filter.
- **Per-user reaction summary** — a profile block showing the user's received reactions, plus a stats block and a history list.
- **Author reactions in postbit** — optionally show a per-author reaction summary next to their user details.
- **User CP preferences** — users choose whether they get notified, managed from a dedicated User CP settings page and a UCP menu entry.
- **Reputation integration** — reactions can convert into reputation, with a separate toggle for negative reactions, and gifted reactions can be converted to reputation.
- **MyAlerts integration** — registers a reaction alert formatter; only loaded when MyAlerts is actually installed.
- **Full permission layer** — minimum posts, minimum reputation, self-reactions, closed threads, a reaction time limit, flood check, and five separate usergroup lists (view, react, receive, remove, super).
- **Admin tools** — manage reactions, manually give reactions, and give reaction points to users.
- **Excluded forums** and a global on/off switch.
- **Developer integration API** — ships a `Documents/` set (developer guide, integration template, quick reference, full HTML guide) for wiring reactions into third-party plugins and other content types.
- Stylesheet registered through PluginLibrary; all markup comes from registered templates.

## Requirements

| Requirement | Version / note |
| --- | --- |
| MyBB | 1.8.x (`compatibility: 18*`) |
| PHP | 7.4+ (works on PHP 8.x) |
| PluginLibrary | Bundled — `inc/plugins/pluginlibrary.php` (v13, LGPL-3.0+, © Andreas Klauer) |
| MyAlerts | Optional — `inc/plugins/MybbStuff/MyAlerts`, integration auto-detected |
| Font Awesome 7 | Loaded from CDN (cdnjs) — activation injects the `<link>` into `headerinclude` |

## Installation

1. Extract the repository into your MyBB root (the directory containing `index.php`). `reactions.php` belongs in the forum root.
2. **Admin CP → Configuration → Plugins** → **Reactions** → **Install**, then **Activate**.
3. **Configuration → Settings → Reactions Settings** for the master switch, limits, permissions, and integrations.
4. **Configuration → Reactions** (the plugin's own ACP module) to manage reaction types, give reactions, and hand out reaction points.

Activation creates the three tables, registers the settings, registers the templates and stylesheet, and applies template edits to `headerinclude`, `postbit`, `postbit_classic`, and `member_profile`.
Deactivate reverts the template edits and deactivates the stylesheet. Uninstall drops the tables and removes the settings group and templates.

## Repository layout

Root-level page (copy into the forum root):

```
reactions.php                                       # full-page reaction list (paginated, filterable)
```

Plugin files:

```
inc/plugins/reactions.php                           # bootstrap, hooks, ACP menu routing
inc/plugins/pluginlibrary.php                       # bundled PluginLibrary (LGPL-3.0+)
inc/plugins/Reactions/install.php                   # schema, settings, templates, stylesheet, template edits
inc/plugins/Reactions/database.sql                  # 3 tables
inc/plugins/Reactions/settings.json                 # the 17 settings (PluginLibrary)
inc/plugins/Reactions/admin/                        # 4 ACP pages
    reactions.php            manage_reactions.php
    give_reactions.php       give_points.php
inc/plugins/Reactions/Functions/                    # 10 domain files
    reaction_functions.php   ajax_functions.php
    postbit_functions.php    showthread_functions.php
    member_functions.php     usercp_functions.php
    usercp_reactions_settings.php
    admin_functions.php      integration_hooks.php
    myalerts_functions.php
inc/plugins/Reactions/Assets/reactions.css          # cached via PluginLibrary
inc/plugins/Reactions/Assets/reactions.js           # hover tooltip, AJAX react/unreact
inc/plugins/Reactions/Templates/                    # 22 templates
inc/plugins/Reactions/Documents/                    # 5 developer docs (~254 KB)
inc/languages/english/reactions.lang.php
inc/languages/english/admin/reactions.lang.php
```

## Database

| Table | Purpose |
| --- | --- |
| `reactions` | Reaction *types*: id, title, image, reputation value, display order, enabled state. |
| `reaction_content` | One row per reaction on a piece of content: content type, content id, user, reaction id, timestamp. |
| `reaction_reputation` | Audit of reputation granted through reactions (used for reversal and negative-reaction handling). |

## Settings (17)

| Setting | Default | Description |
| --- | --- | --- |
| Enable Reactions | `1` | Master switch. |
| Minimum Posts to React | `0` | Post count gate. |
| Minimum Reputation to React | `0` | Reputation gate. |
| Allow Self-Reactions | `0` | Let users react to their own posts. |
| Allow Reactions in Closed Threads | `0` | Reactions on locked threads. |
| Reaction Time Limit (Minutes) | `0` | Window in which a reaction can still be changed; `0` = unlimited. |
| Show Scores in Postbit | `1` | Per-post reaction summary. |
| Show Stats in Profile | `1` | Reaction stats block on the member profile. |
| Allow User Notification Preferences | `1` | Let users opt out of reaction notifications. |
| Reaction Flood Check | `0` | Seconds between reactions; `0` = off. |
| Excluded Forums | *(none)* | Forums without reactions. |
| Integrate with MyAlerts | `0` | Send alerts for received reactions. |
| Integrate with Reputation | `0` | Convert reactions into reputation. |
| Allow Negative Reactions in Reputation | `1` | Negative reactions subtract reputation. |
| Convert Gifted Reactions to Reputation | `1` | Admin-gifted reactions also grant reputation. |
| User Groups Can View Reactions | all | Who sees reaction bars and lists. |
| User Groups not Allowed to React | `1,7` | Explicit deny list. |
| User Groups not Allowed to Receive Reactions | `1,7` | Who cannot be reacted to. |
| User Groups Allowed to Remove | `2,3,4,6` | Who can remove any reaction. |
| Super User Groups | `3,4,6` | Bypass limits and moderation gates. |

## How it works

- `postbit`, `postbit_prev`, `postbit_pm` render `{$post['reactions_bar']}`, `{$post['author_reactions']}`, and the reaction button.
- `showthread_start` / `showthread_end` preload the reaction data for every post on the page in one pass, then attach it to the post array.
- `xmlhttp` serves the AJAX endpoints: add reaction, change reaction, remove reaction, open the reaction list, paginate it.
- `member_profile_end` renders the stats/history blocks; `usercp_menu` + `usercp_start` add the user preferences page; `usercp_options_end` / `usercp_do_options_end` handle the legacy checkbox.
- `admin_config_menu`, `admin_config_action_handler`, and `admin_load` route the plugin's ACP module.
- `admin_user_groups_edit_graph` / `_commit` add the reaction permissions to the usergroup editor.
- `reputation_vote` formats reaction reputation comments; `myalerts_*` hooks are registered **only** when MyAlerts is detected.

## Notes

- **`reactions.php` is a standalone root page**, not a core MyBB script. It bootstraps `global.php`, loads `reaction_functions.php`, and renders the paginated reaction list at 50 per page.
- **Font Awesome 7 is pulled from cdnjs** and injected into `headerinclude` by a `find_replace_templatesets()` edit — a third-party request on every page, added and removed by activate/deactivate.
- The reaction picker is JS-driven (`Assets/reactions.js`) with the UI markup in registered templates; asset URLs are built from `bburl` in the page output rather than through a JS template.
- **MyAlerts is optional and detected at runtime.** If `inc/plugins/MybbStuff/MyAlerts` disappears, the plugin keeps working and silently drops alerts.
- Reputation integration writes to `reaction_reputation` as an audit trail — disabling the integration later does not retroactively remove granted reputation.
- Template edits are positional (`{$post['signature']}`, `{$post['user_details']}`, `{$profilefields}`, the postbit button container). A heavily customised theme may need the edits checked after activation.
- `Documents/` is developer material for third-party integrations (including a 200 KB HTML guide). It is not loaded at runtime and can be omitted from a production upload.

## Author

**PARAD0X** — [MyBB Community profile](https://community.mybb.com/user-134311.html)

- Website: https://mybb.epizy.com
- Version: 2.1.0

## License

GPL-3.0 — see [LICENSE](LICENSE).
