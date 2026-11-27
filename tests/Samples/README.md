# Samples

Every directory is one real Nadybot command output:

| Sample | Command | Shows |
|---|---|---|
| `01-banlist` | `!banlist` | sections, field lists (`<dl>`), quotes in popups |
| `02-history` | `!history Pigtail` | data table with border, colspan, colours in cells |
| `03-aggdef`  | `!aggdef 1.2 1.7 300` | numeric field lists with units, section, `<slider>` widget, skill link |
| `04-items`   | `!items first aid` | list, item links, `<match>`, `<selected>`, `<unavailable/>` |
| `05-help`    | `!help aggdef` | `<syntax>`/`<param>`, `<cmd>`, `<indent>`, italic |
| `06-settings` | `!settings change online_show_relay` | `<choice>`/`<option>` widget with native renderers |
| `07-history-pages` | `!history Orana 1` | pagination of a long table: rows split, header repeated, same column widths on every page, footer on the last page |

## Files

| File | Content |
|---|---|
| `legacy.bbml` | What Nadybot's code produces today (BBML, before colours are resolved, with the window colour `Text::makeBlob()` adds) |
| `input.aoxml` | The same output written in AoXML, in canonical form (`AoXmlSerializer` must reproduce it exactly) |
| `expected-import.aoxml` | `BbmlImporter` on `legacy.bbml`: the literal, clean translation (it guesses nothing, so it is not `input.aoxml`) |
| `expected-detected.aoxml` | `BbmlImporter` with `detectTables` and `detectLists` on `legacy.bbml`, only for samples with tables or lists (`docs/AoXML.md` §6.1, §6.2); for all others the result must be `expected-import.aoxml` |
| `expected.aoml` | `AomlExporter` (official client) |
| `expected.bbml` | `BbmlExporter` (relays) |
| `expected.lepanel` | `LePanelExporter` (Lost Eden). **Outdated**: written with tags we had asked for (`<table>`, `<ul>`, `<stat value unit>`, `<bar left right marker>`); the real tags are in `docs/LePanel.md`, the files still have to be rewritten |
| `expected.ansi` | `AnsiExporter`, in ANSI notation (see below) |
| `expected.txt` | `PlainExporter` |
| `expected.html` | `HtmlExporter` |
| `expected.discord.json` | `DiscordExporter` |

The expected files are the **specification**. Change them when we change our minds,
then make the code follow.

## Render context used by all samples
- Placeholders: `myname` = `Nadybot`, `symbol` = `!`, `myguild` = `Troet`
- Theme: `AO\Markup\Context\DefaultTheme` (Nadybot's default colours)
- Links: `TemplateLinkResolver` defaults (`https://auno.org/ao/db.php?id={id}&ql={ql}`)
- Times: `DefaultTimeFormatter` defaults (UTC, `Y-m-d H:i`, `Y-m-d`, `H:i`, durations like
  Nadybot's `Util::unixtimeToReadable($seconds, false)`)
- Pagination: AOML and BBML are split into pages of at most 7 500 bytes (Nadybot's default
  `max_blob_size`); in the expected files, the pages are separated by a line
  `----- next message -----`. Discord is split by its own limits (see below), the other targets are not paginated.

## ANSI notation
Raw escape codes are unreadable in diffs, so `expected.ansi` uses a notation that
`tests/Support/AnsiNotation.php` turns into real escape codes:

| Notation | Escape code |
|---|---|
| `{#RRGGBB}` | `ESC[38;2;R;G;Bm` (push colour) |
| `{/}` | back to the previous colour, `ESC[0m` at the outermost level |
| `{u}` / `{/u}` | `ESC[4m` / `ESC[24m` |
| `{i}` / `{/i}` | `ESC[3m` / `ESC[23m` |
| `{link URL}` / `{/link}` | OSC 8 hyperlink start / end |
| `{{` / `}}` | literal `{` / `}` |

`php bin/show-sample 03-aggdef` prints a sample in your terminal.

## Rendering rules the samples follow

### All targets
- A message that consists of a single popup link shows the window directly in targets
  without popups (ANSI, plain text, Discord).
- Fields (`<dl>`): the value is emphasised, `unit` follows unemphasised. A group whose values are
  all numeric is right-aligned.
- A field with several values shows the first next to the key and the others below it, in the
  value column.
- `<footer>`: at the end of the window, after an empty line, unstyled.
- Sections: title in `header2`, content indented by one level (4 spaces) where indentation is
  done with spaces.
- Blocks are separated by an empty line, except an `<indent>` directly after a paragraph: it
  continues the paragraph and follows it on the next line (`If you are lazy, just use` in 05).
- Blocks inside a list item or a table cell are not separated by empty lines.
- `<datetime/>`, `<date/>`, `<time/>`, `<duration/>`: the widget fallback (plain text formatted by
  the `RenderContext`'s `TimeFormatter`, e.g. `2026-11-26 17:02`, `2026-11-26`, `17:02`,
  `6 days 3 hrs`) in AOML, BBML, ANSI and plain text, and wherever a target has no native form
  for them (see HTML and Discord).

### AOML (official client)
- Popup: `<a href="text://…">label</a>`, starting with the window colour and the title in
  `header` colour, then an empty line. `"` inside the popup becomes `&quot;`, attributes of
  inner links use `'`.
- Alignment uses `AoFontMetric` (pixels); padding is rounded to the nearest space (5 px).
- Numeric content (a table cell or field value whose text is only digits, `.` and `,`) is padded
  with black `0`s next to the number, one per 8 px (a digit is always 8 px wide), the rest with
  spaces on the outer side. Numbers of the same shape then line up exactly. A column or field
  list is numeric if all its cells/values are; header cells and cells with `colspan` don't count.
- Fields: label + `:`, padded to the widest label of the group plus one space.
- Tables with border: columns separated by ` | `, header row underlined (`<u>`) with highlighted
  cells. Layout tables: columns separated by two spaces.
- Every cell is aligned to the pixel position of its column (left, right or centre), not to the
  end of the previous cell, so rounding errors don't add up along a row (±2 px per cell).
- Lists: `- ` and `1. ` prefixes (the bullet is configurable, `-` is the default).
- `<match>`: red brackets around the content, `<selected>`: yellow brackets.
- `<syntax>`: highlighted, with the command prefix.
- `<slider>`: the widget fallback (`l` bars, red `|` marker, marker text centred below it). The client
  shows Latin-1 only, so there is no `│`; targets that can show it (ANSI, plain text) use `│`.
- Links: `itemref://low/high/ql`, `itemid://53019/id` (nano), `skillid://id`, `user://name`,
  `chatcmd:///tell Bot !cmd` (`/g Bot` for `via="priv"`, `/o` for `via="org"`), `chatcmd://` +
  client command, `chatcmd:///waypoint x y pf`, `chatcmd:///start URL`. `'` in links becomes `&#39;`.
- `<hr/>` is the image `tdb://id:GFX_GUI_FRIENDLIST_SPLITTER`, `<center>` stays `<center>`.
- `<choice>`: the widget fallback, one option per line: the label emphasised, then `[select]`
  as a command link, or `(current)` for the selected option. With `inline`, the labels are the
  links and the selected one is shown like `<selected>`.

### BBML (relays)
- Same layout as AOML (same metric, same padding), but indentation (sections, `<indent>`) is
  written as `<tab>`, one per level, like Nadybot does. Alignment padding stays spaces.
- Theme colours become their BBML names, closed with `<end>`: `<header>`, `<header2>`,
  `<highlight>`, `<green>`, `<neutral>`, … `<strong>` and `<syntax>` become `<highlight>`, explicit `<color>`
  stays `<font color=…>…</font>`.
- The popup starts with the sender's window colour as `<font color=…>`, like `Text::makeBlob()`
  in Nadybot does.
- Placeholders are resolved (`/tell Nadybot !help`), since the receiver would fill in its own name.

### ANSI / plain text
- Monospace alignment (`MonospaceMetric`).
- Tables with border: ` │ ` between columns and a `─┼─` rule under the header row.
- Lists: `• ` (configurable); numbers right-aligned (` 9.`, `10.`), continuation lines indented
  to the text.
- ANSI: window colour as base colour, links blue and underlined, item links as OSC 8 hyperlinks.
- A message that is more than a single popup link comes first; every window follows it after an
  empty line (title, empty line, content).
- Commands show their link text, or without one the command as typed (`!help syntax`, or
  `/tell Nadybot !help syntax` with `show="tell"`; `/g Nadybot …` and `/o …` for `via`).
- `<url>`: ANSI makes the text an OSC 8 hyperlink; plain text appends ` (URL)` unless the text is
  the URL itself.
- Header cells of tables are emphasised (`strong`); the last column is not padded.
- `<hr/>` is 40 `─`. `<center>` is not centred (there is no width to centre in). Images are dropped.
- `<choice>`: the widget fallback, like AOML.

### HTML
- No inline colours: everything is a CSS class (`aom-green`, `aom-clan`, …). `<strong>` becomes
  `<strong class="aom-strong">`.
- Fields: `<dl class="aom-fields">`, numeric groups get `aom-numeric`, units `aom-unit`.
- Windows: `<section class="aom-window" id="…">`, linked from the message.
- `<slider>`: native renderer with `<meter>`.
- Commands: `<a class="aom-cmd" data-cmd="…">`, so the web UI can send them.
- `<footer>`: `<footer class="aom-footer">`.
- `<choice>`: native renderer, `<select class="aom-choice" data-cmd="…">` with one `<option>` per
  option; the web UI sends `data-cmd` + space + value.
- The message is `<div class="aom-message">`, followed by every window. Containers whose content
  is a single paragraph (message, `<indent>`, `<center>`, `<footer>`, list items, table cells) write
  it without `<p>`; sections and windows always use `<p>`.
- Header rows (all cells `<th>`) at the start of a table go into `<thead>`, the rest into `<tbody>`.
  Alignment is a class (`aom-right`, `aom-center`), left is the default.
- Links outside the game: items and nanos are `<a href>` (`LinkResolver`), `<url>` is
  `<a class="aom-url">`. Things only the game understands carry their data for the web UI:
  `<span class="aom-skill" data-skill>`, `aom-char` (`data-char`), `aom-client` (`data-cmd`),
  `aom-waypoint` (`data-x`, `data-y`, `data-pf`), images as `<span class="aom-image" data-src="rdb:…">`.
  `via="priv|org"` adds `data-via` to commands and choices.
- `<color>` is the only inline colour: `<span class="aom-color" style="color:#…">`.
- `<h1>`/`<h2>` become `<h3>`/`<h4>` (the window title is `<h1>`, sections are `<h2>`).
- `<datetime/>`: native renderer, `<time class="aom-datetime" datetime="2026-11-26T17:02:00Z">`
  with the formatted text (`TimeFormatter`); `<date/>` and `<time/>` the same with `aom-date` and
  `aom-time`. The `datetime` attribute is always in UTC with `Z`, so the web UI can show it in the
  reader's time zone.
- `<duration/>`: native renderer, `<span class="aom-duration" data-seconds="529200">` with the
  formatted text.

### Discord
- A window becomes an embed. Sections become embed fields if the window consists only of an
  optional intro, sections, and an optional `<footer>`. Otherwise everything goes into the
  description.
- Sections in the description: a `**title**` line, followed by the content.
- `<footer>` becomes the embed footer (plain text, Discord has no Markdown there).
- `<strong>` → `**bold**`, `<match>` → `__underline__`, `<selected>` → `**bold**`, `<i>` → `*italic*`,
  colours are dropped.
- Tables and the slider become code blocks (same layout as plain text).
- `<syntax>` and `<cmd>` become inline code, items become `[name](url)`, `<indent>` becomes `> `.
- The output is a list of messages. The message is the `content` (empty if it is only a link to a
  window); every window is an embed.
- Discord's limits (content 2 000, title 256, description 4 096, 25 fields of 1 024, footer 2 048,
  6 000 characters per message) are kept by paginating like the game targets: every message is
  a page with `(Page 1 / 2)` in the title, tables are split between rows with the header in every
  code block. A field value that is too long continues in further fields named
  `Title (continued)`, split at line breaks (like Nadybot). An intro before the sections of a field embed becomes its description.
- Blocks are separated by an empty line, except after a code block (Discord sets it apart
  anyway) and before an `<indent>` that continues a paragraph.
- Fields: `Key: **value** unit`; further values of a key on their own lines.
- Text is escaped (`\`, `*`, `~`, `` ` ``, `|`, and `_` at word boundaries; `online_show_relay`
  stays as it is). Items and nanos are `[text](url)`, `<url>` is `[text](url)` or the bare URL;
  skills, characters, waypoints and popup links are plain text, `<client>` is inline code.
- `<hr/>` is a line of 20 `─`; images are dropped.
- `<choice>`: the options are listed in the description (label in bold, `(current)` for the
  selected one), and a select menu is added as a message component: `custom_id` is
  `aom-choice:` + command, the selected option is the `default`.
- `<datetime/>`, `<date/>`, `<time/>`: Discord timestamps `<t:UNIX:f>`, `<t:UNIX:d>`, `<t:UNIX:t>`,
  which Discord shows in the reader's time zone and locale. Where Discord does not render them
  (code blocks, the embed footer), the widget fallback is used. `<duration/>` uses its fallback.

