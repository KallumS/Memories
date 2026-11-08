# 2026-10-14 — the plugin's words follow Apple's writing guidelines

Part of a sweep of every repository, asked for by the owner: the words people
see follow Apple's Human Interface Guidelines on writing, with the rules
Noterator wrote down in its decision 0053, adapted to the web:

- **Buttons, menu items, headings, page and box titles, column headings and
  email subjects** in title-style capitalization: "Approve & Publish",
  "Waiting for Review", "Share This Memory", "Who Shared This Memory",
  "[Site] New Memory from Ann".
- **"…"** (one character) on a button that opens something to choose from:
  "Choose Photo…".
- **Messages, field labels, help text, placeholders and email bodies** in
  sentence case; a complete sentence ends with a full stop.
- **Errors** say what happened and what to do, with no "Sorry" and no
  "please": "Enter your name.", "“Beach.heic” isn’t a photo the site can use.
  Choose a JPG, PNG, GIF or WebP image.", "Your memory couldn’t be saved. Try
  sending it again."
- **One word per idea**: WordPress's "Trash" (the list said "bin").
- **Typography**: curly quotes and apostrophes, an em dash between phrases.

Every string kept its `__()` / `esc_html__()` / `_e()` wrapper and the
`family-memories` text domain. Version 1.0.1, so WordPress offers the new zip
as an update; `family-memories.zip` rebuilt with `build-zip.sh`. The README's
quoted labels were updated to match.

## Left alone, on purpose

- The thank-you email to a contributor ("It means a great deal to us. If you
  have more memories, we would love to hear them too.") is the family
  speaking, signed with the site's name, so its "we" stays.
- The thank-you message after sending is a setting the owner can change; its
  default was already a plain sentence.
- Internal names (`family_memory`, `family_member`, `fmem_*` options and meta
  keys, shortcodes) are stored or typed, and do not change.

## Checked

`php -l` on every file. There is no test suite and no WordPress in the
container, so the screens were not rendered; the changes are text only.
