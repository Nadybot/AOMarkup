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
  (`<br>`, `<hr>`, `<myname>`, `<myguild>`, `<symbol>`, `<pagebreak>`) may omit the slash.
- Attribute values are quoted with `"` or `'`. Boolean attributes may be written without a
  value: `<table border>` is the same as `<table border="border">`.
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

## 2. Block elements

| Element | Attributes | Meaning |
|---|---|---|
| `<section>` | `title` | A titled block. Replaces `<header2>` + `<tab>`-indented lines. Preferred page-break position; kept together when possible. |
| `<field>` | `name`, `unit`? | A key/value line. Consecutive fields form an aligned group. The value is highlighted; `unit` follows it unhighlighted. If all values of a group are numeric, they are right-aligned. |
| `<table>` | `border`?, `columns`? | A table. Without `border` it is a pure layout table. `columns` is a comma separated list of `left`, `right`, `center` per column. |
| `<tr>` | | A row. |
| `<th>`, `<td>` | `align`?, `colspan`? | Header and data cells. Rows made only of `<th>` are repeated on every page. |
| `<ul>`, `<ol>` | `start`? (ol) | Unordered and ordered lists. Lists can be nested. |
| `<li>` | `icon`? | A list item. `icon` is an image reference (`rdb:12345`, `tdb:GFX_…`). |
| `<indent>` | | Indent its content by one level. |
| `<h1>`, `<h2>` | | Headings. |
| `<center>` | | Centered content. |
| `<hr/>` | | A horizontal separator. |
| `<pagebreak/>` | | Start a new page here, if the target paginates. |
| `<keep>` | | Never split this content across pages. |
| `<repeat>` | | Repeat this content at the top of every page of the current popup. |

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
| `<item>` | `lowid`, `highid`, `ql` (or `ref="low/high/ql"`) | An item. Content = link text. |
| `<nano>` | `id` | A nano program. |
| `<skill>` | `id` | A skill. |
| `<char>` | `name` | A character. Empty content = name. |
| `<cmd>` | `show`? = `command` (default), `tell`, or text in content | A command for this bot. The exporter adds `/tell <myname>` and the command prefix. |
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

## 6. Legacy AOML+
Nadybot/Budabot-style markup (`<highlight>…<end>`, `<tab>`, `<header2>`, `<font color=…>`,
`<a href="text://…">`, `itemref://`, `chatcmd://`, …) is **not** AoXML. It is read by the
`LegacyAomlImporter`, which produces the same document tree. See the `legacy.aoml`
files in `tests/Samples` for what it has to understand.
