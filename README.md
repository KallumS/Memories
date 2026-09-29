# Family Memories

A WordPress plugin for a website where family and friends can share stories and memories about the people you love, with photos if they like. Nothing appears on the site until you have read it and approved it.

**What visitors see**

- **A "Share a Memory" page.** A simple form for their name, how they know the person, who the memory is about, a title, the story, when and where it happened, and up to 5 photos.
- **A "Memories" page.** Every approved memory as a card with its photo, newest first, with a search box and a "choose a person" filter.
- **A page for each memory**, showing the full story, all its photos, and who shared it.
- **A page for each person.** Like a Wikipedia entry: their portrait, a short biography, and every memory about them.
- **A "Family" page.** A directory of everyone, with portraits.

**What you get**

- An email every time someone shares a memory, with the story included and a link to review it.
- A **Memories → Waiting for review** list in your dashboard, with a red counter showing how many are waiting.
- One click to **Approve & publish**, or **Trash** to delete. You can also open a memory and fix typos before approving.
- If the person who shared it gave an email address, they get a thank-you email with a link once you approve it. You can switch this off.
- Built-in spam protection, so you don't have to deal with junk.

---

## Setting it up, step by step

### 1. Get a WordPress site that allows plugins

This is a *plugin*, a small add-on you upload to WordPress. You need a WordPress site that lets you upload plugins:

- **WordPress.com:** plugins need the **Business** plan or higher. The free and cheaper plans won't work.
- **Any regular WordPress hosting company** (sometimes called "self-hosted" or "WordPress.org" hosting): plugins are always allowed.

### 2. Download the plugin

1. In this GitHub project, click the file **`family-memories.zip`**.
2. Click the **Download** button (the arrow pointing down, near the top right of the file view).
3. Keep the file as it is. **Don't unzip it.**

### 3. Install it

1. Log in to your WordPress dashboard.
2. Go to **Plugins → Add New Plugin**, then click **Upload Plugin** at the top.
3. Choose `family-memories.zip` and click **Install Now**.
4. Click **Activate**.

A new **Memories** item (with a heart icon) appears in the left-hand menu.

### 4. Add the people

1. Go to **Memories → People**.
2. For each person (e.g. "Dad"), fill in:
   - **Name**, as you want it to appear.
   - **Description**: a few lines about them. This becomes the introduction on their page.
   - **Portrait photo**: click *Choose photo* and upload or pick a picture.
3. Click **Add a Person**.

If you only add one person, the form automatically ticks them. You can add more family members at any time.

### 5. Create the pages

Go to **Pages → Add New Page** and create these three pages. In each one, click the **+** button, search for **Shortcode**, add that block, and type the code shown:

| Page title (your choice) | Type this into a Shortcode block |
|---|---|
| Share a Memory | `[memory_form]` |
| Memories | `[memory_wall]` |
| Family | `[family_members]` |

You can also write normal text above each one, for example a few words inviting people to share.

Then add these pages to your site's menu: **Appearance → Editor → Navigation** on newer themes, or **Appearance → Menus** on older ones.

### 6. Check your settings

Go to **Memories → Settings** to:

- choose **which email address** gets told about new memories,
- turn the **thank-you email** to contributors on or off,
- change the **thank-you message** people see after sending a memory.

### 7. Try it yourself first

Before sharing the link, send in a test memory yourself (with a photo), check you receive the email, then approve it and look at how it appears. You can delete it afterwards by clicking **Trash**.

---

## Reviewing memories

1. When someone shares a memory, you get an email. You'll also see a red number next to **Memories** in your dashboard.
2. Go to **Memories → Waiting for review**. Each memory shows its photo, the start of the story, and who sent it.
3. Hover over a memory's title to see its options:
   - **Approve & publish** puts it on the site straight away.
   - **Edit** opens it so you can read it in full, fix spelling, change the title, or change who it's about, then click **Publish**.
   - **Trash** removes it. Trashed memories stay in the Trash for 30 days in case you change your mind. After that, they and their photos are deleted for good.
4. To approve several at once, tick them, choose **Approve & publish** from the *Bulk actions* menu, and click **Apply**.

---

## Good to know

- **Keeping the site private.** Under **Settings → Reading**, tick *"Discourage search engines from indexing this site"* so it won't show up in Google. Only people you give the link to will find it. For full privacy, some hosts (and WordPress.com) let you make the whole site private.
- **If emails don't arrive.** Some hosting companies' emails end up in spam or aren't sent at all. Check your spam folder first. If that doesn't fix it, install the free **WP Mail SMTP** plugin, which makes email from WordPress much more reliable. You can always check **Memories → Waiting for review** yourself too.
- **Photo sizes.** Each photo must be under your host's upload limit. The form shows the limit. Photos from phones usually work fine, and WordPress shrinks very large ones automatically.
- **Backups.** These memories are precious. Make sure your host takes regular backups, or install a backup plugin such as **UpdraftPlus**.
- **Removing the plugin never deletes memories.** They stay safely in your WordPress database.
- **Changing the colour.** The buttons use a warm brown. To change it, go to **Appearance → Customize → Additional CSS** (or ask whoever helps with your site) and add, for example:
  `:root { --fmem-accent: #2f5d62; }`

---

## Extra options (optional)

| Code | What it does |
|---|---|
| `[memory_form person="dad"]` | Opens the form with that person already ticked. Use the name as it appears in their page address, e.g. `/person/dad/`. |
| `[memory_wall person="dad"]` | Shows only memories about that person. |
| `[memory_wall per_page="24"]` | Shows more memories per page (the default is 12). |
| `[memory_wall search="no"]` | Hides the search box. |
| `[family_members show_empty="no"]` | Hides people who don't have any memories yet. |

Each person's page is at `yoursite.com/person/their-name/`. Their page has a "Share a memory of…" button that opens the form with them already ticked.

---

## For developers

The plugin source is in [`family-memories/`](family-memories/). Content is stored as a `family_memory` post type (submissions arrive with status `pending`) and a hierarchical `family_member` taxonomy with a `fmem_portrait_id` term meta. Run `./build-zip.sh` to rebuild `family-memories.zip` after changing the source.
