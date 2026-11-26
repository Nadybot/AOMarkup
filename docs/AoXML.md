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
  use tables, fields, lists or `<indent>` instead.
- Line breaks directly after an opening or before/after a closing **block** element
  are not significant. Inside element-only containers (`<table>`, `<tr>`, `<ul>`, `<ol>`)
  all whitespace is ignored.
- Inside an inline element, an empty line is just two line breaks; paragraphs can only
  be separated at the block level.

## 2. Block elements

| Element | Attributes | Meaning |
|---|---|---|
| `<section>` | `title` | A titled block. Replaces `<header2>` + `<tab>`-indented lines. Preferred page-break position if the window has no `<pagebreak/>`; kept together when possible. |
| `<field>` | `name`, `unit`? | A key/value line. Consecutive fields form an aligned group. The value is highlighted; `unit` follows it unhighlighted. If all values of a group are numeric, they are right-aligned. |
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

### 2.2 Pagination
Targets with a size limit (AOML, BBML, lepanel) split a window into several pages. The source
can only give hints; it never decides where a page ends.

- `<pagebreak/>` marks a position where a page *may* end. It is not a forced break: pages are
  filled as far as possible and end at the last `<pagebreak/>` that still fits. A window that
  fits on one page is not split at all.
- If a window contains at least one `<pagebreak/>`, pages end **only** there. Only if the content
  between two of them does not fit on a page on its own, that part is split by the rules below.
- Without `<pagebreak/>`, pages end between blocks, preferably before a `<section>`, and between
  table rows.
- `<keep>` is never split, unless it does not fit on a page on its own. `<repeat>` and header rows
  of tables are repeated at the top of every page.
- Targets without pagination ignore `<pagebreak/>`; it renders nothing.

## 3. Inline elements

### 3.1 Semantic styles
| Element | Meaning |
|---|---|
| `<highlight>` | Emphasised value |
| `<on>`, `<off>` | Enabled / disabled state |
| `<neutral>`, `<omni>`, `<clan>`, `<unknown>` | Faction |
| `<match>` | Part matching a search |
| `<selected>` | The currently selected choice |
| `<syntax>` | Command syntax; the content is a bot command without prefix |
| `<u>`, `<i>` | Underline, italic |

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
| `<cmd>` | `command`?, `show`? | A command for this bot, without prefix. Without `command`, the content is the command (`<cmd>help syntax</cmd>`) and `show` decides the link text: `command` (default, `!help syntax`) or `tell` (`/tell Botname !help syntax`). With `command`, the content is the link text: `<cmd command="help">Help</cmd>`. The exporter adds `/tell <myname>` and the command prefix. |
| `<url>` | `href` | An external link. |
| `<img>` | `src` = `rdb:ID` or `tdb:NAME` | An inline image. |

### 3.4 Placeholders
`<myname/>`, `<myguild/>`, `<symbol/>` and the generic `<var name="…"/>`.
They are resolved at export time by a `PlaceholderProvider`.

## 4. Popups
A popup is a link that opens a window.

```xml
<popup title="Banlist (2)">…window content…</popup>
<popup title="History of Pigtail" label="History">…</popup>
<popup title="Ban" name="nadybot-ban">…</popup>
```

- `title`: the window title. `label`: the link text, defaults to `title`.
- `name`: the window name. Targets that support it reuse an open window of the same name.
- Popups may be nested. Targets that cannot nest render inner popups differently,
  e.g. as a command that fetches the inner window.

The parser **hoists** every popup into the document: the message keeps a
`<popup ref="…">label</popup>` reference and the window becomes a separate entry.
The serialised (canonical) form of a document with windows is therefore:

```xml
… <popup ref="w1">Banlist (2)</popup> …
<window id="w1" title="Banlist (2)">…</window>
```

Both forms are valid input.

## 5. Widgets
Widgets are registered elements with a mandatory fallback to core elements, see
[Architecture](Architecture.md#widgets). Built-in widgets:

| Widget | Attributes | Meaning |
|---|---|---|
| `<slider/>` | `pos`, `min`=0, `max`=100, `left`?, `right`?, `marker`? | A horizontal scale with a marker, e.g. the agg/def slider. |
| `<unavailable/>` | | Marks something as not available in game. |
| `<param>` | | A command parameter inside `<syntax>`, e.g. `<param>attack time</param>`. |

## 6. BBML
BBML, the Budabot markup language (`<highlight>…<end>`, `<tab>`, `<header2>`, `<myname>`,
`<a href="text://…">`, `itemref://`, `chatcmd://`, …), is what Budabot and Nadybot write today.
It is **not** AoXML, and it is not Funcom AOML either: AOML only knows tags like `<font>`,
`<a>`, `<u>`, `<i>` and `<img>`, and is a subset of BBML.

- **Import:** `BbmlImporter` reads BBML and therefore plain AOML too, and produces the same
  document tree. Colours (`<font color=…>`) stay colours; they are never guessed back into
  semantic styles. See the `legacy.bbml` files in `tests/Samples` for what it has to understand.
- **Export:** `BbmlExporter` writes BBML for relays: named styles stay named, so the receiving
  bot applies its own theme, while placeholders are resolved, since the receiver would fill in
  its own name. `AomlExporter` writes Funcom AOML with all colours resolved.
