# AoXML – Language Reference (draft)

AoXML is the canonical source markup of AOMarkup. It is XML-shaped, strict about
nesting and closing, and describes **meaning**, not presentation. Every exporter
(AOML, lepanel, ANSI, plain text, HTML, Discord) decides on its own how to show it.

Status: **draft**. Everything in here is up for discussion until the first release.

## 1. General rules

### 1.1 Syntax
- Elements must be closed: `<green>…</green>`, `<tr>…</tr>`, `<li>…</li>`.
  Nothing is closed implicitly.
- Void elements are written as `<slider … />`. Known void elements
  (`<br>`, `<hr>`, `<pagebreak>`, `<img>`, `<myname>`, `<myguild>`, `<symbol>`, `<var>`)
  may omit the slash. Any other element may be written self-closing when it is empty.
- Comments (`<!-- … -->`) are dropped.
- Attribute values are quoted with `"` or `'`. Boolean attributes may be written without a
  value: `<table border>` is the same as `<table border="true">`. `"true"` and `"false"`
  are the canonical values; the serializer always writes them. An unquoted value
  (`border=true`) is accepted with a warning.
- `<`, `>` and `&` in text are written as `&lt;`, `&gt;` and `&amp;`. A bare `&` that
  does not start an entity is accepted as text, with a warning.
- Unknown elements are kept as text and logged as a warning; the parser never throws
  on bad input but recovers and reports through PSR-3.

### 1.2 Whitespace
- A line break in the source is a line break in the output.
- An empty line separates paragraphs.
- Leading whitespace on a line is **not significant**. Indent the source as you like.
- Runs of spaces collapse into a single space. Spaces are never a layout tool;
  use tables, field lists (`<dl>`), lists or `<indent>` instead.
- Line breaks directly after an opening or before/after a closing **block** element
  are not significant. Inside element-only containers (`<table>`, `<tr>`, `<ul>`, `<ol>`, `<dl>`)
  all whitespace is ignored.
- Inside an inline element, an empty line is just two line breaks; paragraphs can only
  be separated at the block level.

## 2. Block elements

| Element | Attributes | Meaning |
|---|---|---|
| `<section>` | `title` | A titled block. Replaces `<header2>` + `<tab>`-indented lines. Preferred page-break position if the window has no `<pagebreak/>`; kept together when possible. |
| `<dl>` | | A list of key/value fields, aligned together. Contains `<dt>` and `<dd>` only. If all values of a list are numeric, they are right-aligned. |
| `<dt>` | | A key. Starts a new field; every field has one key. |
| `<dd>` | `unit`? | A value of the key before it. The value is emphasised; `unit` follows it unemphasised. A key may have several values: the first is next to the key, the others below it in the same column. |
| `<table>` | `border`? | A table. Without `border` it is a pure layout table. |
| `<tr>` | | A row. |
| `<th>`, `<td>` | `align`?, `colspan`? | Header and data cells. `align` is `left`, `right` or `center`, see [2.1](#21-table-alignment). Rows made only of `<th>` are repeated on every page. |
| `<ul>`, `<ol>` | `start`? (ol) | Unordered and ordered lists. Lists can be nested. |
| `<li>` | `icon`? | A list item. `icon` is an image reference (`rdb:12345`, `tdb:GFX_…`). |
| `<indent>` | | Indent its content by one level. |
| `<h1>`, `<h2>` | | Headings. |
| `<center>` | | Centered content. |
| `<hr/>` | | A horizontal separator. |
| `<pagebreak/>` | | A *possible* page break, never a forced one. See [2.2](#22-pagination). |
| `<keep>` | | Never split this content across pages. |
| `<repeat>` | | Repeat this content at the top of every page of the current popup. |
| `<footer>` | | A closing note of a window: credits ("History provided by …"), legends, sources. Only inside a `<window>`; always shown at the end of the window, wherever it is in the source. Several footers are joined in order. Inline content. |

### 2.1 Table alignment
The `align` of a `<th>` is the default alignment of the cells below it in the same
column; a `<td align>` overrides it for that cell.

```xml
<table border>
	<tr><th>Date</th><th align="right">Level</th></tr>
	<tr><td>2010-02-24</td><td>195</td></tr>               <!-- 195 is right-aligned -->
	<tr><td>2010-02-25</td><td align="left">n/a</td></tr>  <!-- n/a is left-aligned -->
</table>
```

- Only a `<th>` without `colspan` sets a column default. A `<th colspan>` only aligns itself.
- With several header rows, a column takes the `align` of the last `<th>` above the cell
  that has one.
- A cell with `colspan` does not inherit; it is `left` unless it has its own `align`.
- Everything else is `left`, in particular every cell of a table without `<th>`.

```xml
<dl>
	<dt>Attack</dt><dd unit="second(s)">1.2</dd>
	<dt>Alts</dt><dd>Nadyita</dd><dd>Nadybot</dd>
</dl>
```

A `<dd>` before the first `<dt>`, a `<dt>` without `<dd>` and text outside of `<dt>`/`<dd>`
are reported as warnings.

### 2.2 Pagination
Targets with a size limit (AOML, BBML, lepanel) split a window into several pages. The source
can only give hints; it never decides where a page ends.

- `<pagebreak/>` marks a position where a page *may* end. It is not a forced break: pages are
  filled as far as possible and end at the last `<pagebreak/>` that still fits. A window that
  fits on one page is not split at all.
- If a window contains at least one `<pagebreak/>`, pages end **only** there. Only if the content
  between two of them does not fit on a page on its own, that part is split by the rules below.
- Without `<pagebreak/>`, pages end between blocks. A `<section>` that does not fit on the
  current page starts a new one; it is only split if it does not fit on a page on its own, and
  then continues on the next page without its title (indented as before).
- Tables are split between rows, lists between items, field lists between fields, paragraphs
  between lines. A split table or field list keeps the column widths of the whole, so it looks
  the same on every page.
- `<keep>` is never split, unless it does not fit on a page on its own. A `<footer>` is only on
  the last page. `<repeat>` and the header rows at the top of a table are repeated at the top of
  every page.
- Every page is a complete message. The link to the window and its title get the page number,
  `History of Orana (Page 1 / 3)` by default (the format is a parameter of the `Paginator`).
- Targets without pagination ignore `<pagebreak/>`; it renders nothing.

## 3. Inline elements

### 3.1 Semantic styles
| Element | Meaning |
|---|---|
| `<strong>` | Emphasis, e.g. a value. Each target decides how to show it: highlight colour in the game, bold in Discord, `<strong>` in HTML. |
| `<on>`, `<off>` | Enabled / disabled state |
| `<neutral>`, `<omni>`, `<clan>`, `<unknown>` | Faction |
| `<match>` | Part matching a search |
| `<selected>` | The currently selected choice |
| `<syntax>` | Command syntax; the content is a bot command without prefix |
| `<u>`, `<i>` | Underline, italic |
| `<header>`, `<header2>` | Only the colour of the window title / of section titles (the theme's header colours). No structure: headings are `<h1>`, `<h2>` and `<section title>`, the window title is `<window title>`. In the game, `<h1>`/`<h2>` are a line in these colours; so `BbmlImporter` turns a line that is only `<header>…<end>`/`<header2>…<end>` back into `<h1>`/`<h2>`. |

### 3.2 Raw colours
`<black>`, `<white>`, `<yellow>`, `<blue>`, `<green>`, `<red>`, `<orange>`, `<grey>`,
`<cyan>`, `<violet>` and `<color value="#RRGGBB">`. Prefer semantic styles where possible.

### 3.3 Game objects and links
| Element | Attributes | Meaning |
|---|---|---|
| `<item>` | `id`, `ql` | An item. `id` is `lowid/highid`, or just `id` if both are the same. `ql` is required; if it is missing or invalid, a warning is logged and QL 1 is used. Content = link text. |
| `<nano>` | `id` | A nano program. |
| `<skill>` | `id` | A skill. |
| `<char>` | `name`? | A character. Without `name`, the content is the name; with `name`, the content is the link text. |
| `<cmd>` | `command`?, `show`?, `via`? | A command for this bot, without prefix. Without `command`, the content is the command (`<cmd>help syntax</cmd>`) and `show` decides the link text: `command` (default, `!help syntax`) or `tell` (`/tell Botname !help syntax`). With `command`, the content is the link text: `<cmd command="help">Help</cmd>`. `via` is how the command reaches the bot: `tell` (default, `/tell <myname> !…`), `priv` (the bot's private channel, `/g <myname> !…`) or `org` (the org channel, `/o !…`). The exporter adds all of this. |
| `<client>` | `command`? | A command for the game client, e.g. `/assist Nady` or `/macro …`. Without `command`, the content is the command; with `command`, the content is the link text. Targets outside the game show it as code. |
| `<waypoint>` | `x`, `y`, `pf` | A waypoint at the given coordinates of playfield `pf` (numeric id). Content = link text. |
| `<url>` | `href` | An external link. AoXML has no `<a>`: links are always one of the elements above. |
| `<img>` | `src` = `rdb:ID` or `tdb:NAME` | An inline image. |

### 3.4 Placeholders
`<myname/>`, `<myguild/>`, `<symbol/>` and the generic `<var name="…"/>`.
They are resolved at export time by a `PlaceholderProvider`.

Placeholders are elements, so they only exist in content. **Attribute values are always plain
text**: `command="/g &lt;myname&gt; hi"` is the literal text `/g <myname> hi`, nothing is
replaced. Where a command needs the bot's name or prefix, the element provides it
(`<cmd via="priv">`).

## 4. Windows and popups
A **window** is defined once, at the top level of the message, and opened by **popup** links
in the message:

```xml
There are <popup window="bans"><strong>2</strong> bans</popup> on this bot.
<window id="bans" title="Banlist (2)">…window content…</window>
```

| Element | Attributes | Meaning |
|---|---|---|
| `<window>` | `id`, `title` | A window. Only allowed at the top level, never inside another element. Its position in the source does not matter. |
| `<popup>` | `window` | A link that opens the window with this id. The content is the link text and may contain markup. Only allowed in the message, not inside a window. |

- Windows are never nested: a window cannot contain popups. This is what the official client
  can show (`text://` cannot contain `text://`), and it keeps every target simple.
- One window may be opened by several popups.
- A popup to a window that does not exist, a window without `id` or `title`, a duplicate `id`
  and a popup inside a window are reported as warnings. A popup inside a window keeps its
  link text as plain text; a `<window>` inside another element is still used, with a warning.
- Code usually does not write ids by hand: the library's builder returns a window object,
  and the popup is created from it.

## 5. Widgets
Widgets are registered elements with a mandatory fallback to core elements, see
[Architecture](Architecture.md#widgets). Built-in widgets:

| Widget | Attributes | Meaning |
|---|---|---|
| `<slider/>` | `pos`, `min`=0, `max`=100, `left`?, `right`?, `marker`? | A horizontal scale with a marker, e.g. the agg/def slider. |
| `<unavailable/>` | | Marks something as not available in game. |
| `<param>` | | A command parameter inside `<syntax>`, e.g. `<param>attack time</param>`. |
| `<choice>` | `command`, `via`?, `inline`? | A choice between values. Choosing an option runs the bot command `command` followed by a space and the option's `value`; `via` works like on `<cmd>`. Contains `<option>`s only. Targets with dropdowns (web UI, Discord select menus, maybe lepanel) show one; the fallback lists the options one per line, or next to each other with `inline`. |
| `<option>` | `value`, `selected`? | An option of a `<choice>`. The content is the label and may contain markup. `selected` marks the current value. |

```xml
<choice command="settings save online_show_relay">
	<option value="0" selected>No</option>
	<option value="1">Always</option>
</choice>
```

## 6. BBML
BBML, the Budabot markup language (`<highlight>…<end>`, `<tab>`, `<header2>`, `<myname>`,
`<a href="text://…">`, `itemref://`, `chatcmd://`, …), is what Budabot and Nadybot write today.
It is **not** AoXML, and it is not Funcom AOML either: AOML only knows tags like `<font>`,
`<a>`, `<u>`, `<i>` and `<img>`, and is a subset of BBML.

- BBML's `<a href="…">` is the link for everything (`text://`, `chatcmd://`, `itemref://`, …).
  In AoXML, every kind of link has its own element instead.
- **Import:** `BbmlImporter` reads BBML and therefore plain AOML too. The translation is literal
  but clean, and it never guesses: colours stay colours, text aligned with spaces or `|` stays
  text (no tables), and nothing becomes a `<section>`, `<footer>`, `<dl>` or widget. The
  `expected-import.aoxml` files in `tests/Samples` show the result for each `legacy.bbml`.

  | BBML / AOML | AoXML |
  |---|---|
  | `<a href="text://…">label</a>` | `<popup window="window1">label</popup>` + `<window id="window1">` (ids in order) |
  | window colour `<font color=…>` at the start of a popup | dropped |
  | `<header>T<end>` + empty line at the start of a popup, or a first line that is the link text | the window title |
  | `<permheader>…</permheader>`, `<pagebreak>`, `<center>` | `<repeat>`, `<pagebreak/>`, `<center>` |
  | a line that is only `<header>…<end>` / `<header2>…<end>` | `<h1>` / `<h2>` |
  | leading `<tab>`s, or 4 leading spaces each | `<indent>`, nested by level; `<tab>` inside a line is a space |
  | lines, empty lines | line breaks, paragraphs; runs of spaces collapse |
  | `<highlight>` | `<strong>` |
  | `<green>`, `<red>`, `<neutral>`, `<header>`/`<header2>` inside a line, … | the style of the same name |
  | `<font color=#…>` | `<color value="#…">` |
  | `<black>…<end>`, `<font color=#000000>` | dropped (invisible padding) |
  | `<u>`, `<i>`, `<img src=rdb://…>`, `<img src=tdb://id:…>` | `<u>`, `<i>`, `<img>` |
  | `itemref://`, `itemid://53019/`, `skillid://`, `user://` | `<item>`, `<nano>`, `<skill>`, `<char>` |
  | `chatcmd:///tell <myname> …`, `/g <myname> …`, `/o <symbol>…` | `<cmd>`, `via="priv"`, `via="org"`; no link text if it is what `<cmd>` shows anyway |
  | `chatcmd:///start URL`, `chatcmd:///waypoint x y pf`, other `chatcmd://`, `http(s)://` | `<url>`, `<waypoint>`, `<client>`, `<url>` |
  | `<myname>`, `<myguild>`, `<symbol>` | the placeholders |

  Plain AOML has the bot's name and command prefix instead of `<myname>` and `<symbol>`;
  give them to the importer (`botName`, `symbol`) to get `<cmd>` instead of `<client>`.
  Spaces at the edges of styles and link texts move outside. Unknown tags and link targets
  are logged and dropped, their text is kept. Popups inside popups keep only their link text.

### 6.1 Table detection
Alignment with spaces only works in fonts we know; Lost Eden lets every window have its own
font size. `new BbmlImporter(detectTables: true)` therefore turns tables in BBML into real
`<table>`s. This **guesses**, so it is off by default, and only one, unambiguous pattern is
recognised so far (the samples `02-history` and `07-history-pages`, `expected-detected.aoxml`):

- Columns are separated by `|`: a single `|`, plain or in a colour of its own
  (`<highlight>|<end>`); `||` is not a separator (bars, `||` as text).
- A table is a run of at least two consecutive lines on the same indentation level with
  separators, at least two of them with the most columns, and at least two columns.
  Separators at the start or end of a line are ignored (`| a | b |`).
- A line with fewer columns spans the rest with its last cell (`colspan`).
- The header row is a line whose columns are separated by two or more spaces, followed by a line
  of `_`, `-`, `=` or `─` (in a `<permheader>`, or as two lines) right before the rows; the
  `<permheader>` is dropped, a table repeats its header on every page anyway. A first row that is
  underlined as a whole (`<u>…</u>`, as `AomlExporter` writes it) is a header row, too.
- Columns whose cells are all numbers are right-aligned. Tables get `border="true"`.

Columns aligned with spaces only (`Text::alignNumber()`, black zeros) are not recognised yet;
that needs real messages to tune and measure against.

### 6.2 List detection
`new BbmlImporter(detectLists: true)` turns lists into `<ul>`/`<ol>`, like Nadybot's
`WebChatConverter` does for the web UI. It guesses as well, so it is off by default.

- A list is a run of at least two consecutive lines on the same indentation level that start with
  the same bullet and a space (`- `, `* `, `• `), or with numbers that count up without gaps
  (`1. `, `2. `, …; the first number is the `start` of the `<ol>`). `-5 degrees` is no item.
- Deeper indented lines (a `<tab>` or 4 spaces) right after an item belong to it: continuation
  lines and nested lists. Lines indented by less are not part of the item.
- Both detectors can be combined; tables are found first.
- **Export:** `BbmlExporter` writes BBML for relays: named styles stay named, so the receiving
  bot applies its own theme, while placeholders are resolved, since the receiver would fill in
  its own name. `AomlExporter` writes Funcom AOML with all colours resolved.

## 7. Canonical form
`AoXmlSerializer` writes every document the same way, and parsing that gives the same tree:
the message first, then the windows; sibling blocks separated by an empty line; one tab of
indentation per level; list items, table rows and fields on one line each unless their content
has line breaks; boolean attributes as `"true"`; attributes with their default value left out.
The `input.aoxml` files of the samples are in canonical form.
