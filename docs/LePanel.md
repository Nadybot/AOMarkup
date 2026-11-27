# lepanel (Lost Eden)

Lost Eden, a private Anarchy Online server, has its own window format next to Funcom's
`text://`. This page records what the Lost Eden developers told us (2026-11-27) and what it
means for `LePanelExporter`. The `expected.lepanel` files in `tests/Samples` were written
before this, from the tags we had asked for; they do not match it yet (see the end).

## What Lost Eden supports
There is no `letext://`. The scheme is **`lepanel://`**, with its own panels folder and a
`/panel` command. It is not a replacement for `text://`, but a tag-based page that can read
live game values.

### Links
- `lepanel://v1/<page>`: the page itself, `lepanel://v1z/<data>`: the page compressed,
  `lepanel://open/<name>`: opens one of your own saved panels.
- In chat, `<lep="name">` becomes one of those when it is sent.

### Layout
| Tag | Attributes | Meaning |
|---|---|---|
| `<panel>` | `title`, `width`, `height` | Window title and size |
| `<section>` | `title`, `open` | A foldable card |
| `<row>` | `grow` | Children side by side |
| `<column>` | `width`, `grow`, `scroll` | Children stacked |
| `<tabs>`, `<tab>` | `<tab title scroll>` | Tabbed pages |

### Text
- `<heading>`, `<p>`, `<text>`, `<tip>` (a boxed note).
- Fills in `{name}`, `{level}`, `{profession}`, `{stat:N}`.
- **Plain text only:** no inline bold, colours or links yet.

### Live game data
| Tag | Attributes | Meaning |
|---|---|---|
| `<stat>` | `id`, `label`, `format` | A stat's value, green/red against the stored value. `format`: `percent`, `signed`, `signedpercent`, `hundredths` |
| `<bar>` | `label`, `cur`, `max` | One stat against another as a bar |
| `<slots>` | `page`, `columns`, `list` | Grid of worn slots (weapons, armor, implants, social) |
| `<character>` | `height`, `bg` | Live character model, drag to turn |
| `<item>` | `id`, `high`, `ql` | Icon and name, tooltip on hover, details on right-click |

### Interaction
- `<button cmd>` runs a chat command; with `url='https://…'` it opens a web page.
- `{field:name}` in commands is the value of an input; `require` / `clear` on buttons.
- `<input name max hint>`, `<textarea name max height>`, `<choice name options>`.

### Navigation
- `<goal pf x y z label area radius>`: a waypoint with compass mark and guide line.
- `<route title>`: numbered goals in order.
- `<travel>`: a filterable list of destinations, routed through whompahs and doors.

### Media
- `<image src height>`: a picture from the panels folder or the client.
- `<rift left right>`: the rift graphic of the website.

Unknown tags are skipped. Missing compared to `text://`: inline formatting (colours, bold,
links in text) and tables; `<row>`/`<column>` lay things out, but there is no table tag yet.
The developers are happy to add what we need.

## What this means for LePanelExporter
| AoXML | lepanel | Notes |
|---|---|---|
| `<window title>` | `<panel title>` | |
| `<section title>` | `<section title open>` | Real foldable cards; open by default |
| `<h1>`/`<h2>` | `<heading>` | |
| paragraph | `<p>` | Inline styles are lost: plain text only |
| `<footer>` | `<tip>`? | Or `<text>`; to decide |
| `<item>` | `<item id high ql>` | Only as a block of its own, there are no links inside text |
| `<cmd>`, `<url>` | `<button cmd>`, `<button url>` | Only as blocks, see above |
| `<choice command>` | `<choice name options>` + `<button cmd="command {field:name}">` | lepanel's choice is an input; the button sends it |
| `<waypoint>` | `<goal pf x y label>` | `z` is not in AoXML |
| `<table>` | — | No table tag; `<row>` of `<column>`s would align columns, but rows can drift apart when cells wrap |
| `<ul>`/`<ol>` | — | Not supported; text with bullets |
| `<slider>` | — | `<bar>` shows live stats only, our slider has fixed values: the fallback is used |
| `<dl>` | — | Not supported; perhaps `<row>` with two `<column>`s |
| placeholders, `{…}` in text | — | Our text must not be read as `{name}` etc.: how is `{` escaped? |

Since lepanel has no inline formatting yet, everything inside a line (`<strong>`, colours,
item and command links in a sentence) is flattened to text until it does.

## Open questions for the Lost Eden developers
- **Tables:** a table tag (with header row, column alignment, optional border), or a reliable
  way to align `<row>`/`<column>`.
- **Inline formatting:** bold, colours and links (items, commands, URLs) inside text.
- **Lists:** `<ul>`/`<ol>`.
- **Static values:** `<stat>` and `<bar>` with fixed `value`/`unit` and labels, not only live
  stats (for results like `!aggdef`).
- **Escaping:** how to write a literal `{` (and `<`, `&`, quotes) in text and attributes.
- **Size:** the maximum length of a `lepanel://v1/` and a `lepanel://v1z/` link, and the
  compression of `v1z`.
- **Message text:** whether a `lepanel://` link can stand in a chat message next to normal text,
  like a `text://` link (`<a href="lepanel://v1/…">label</a>`).
- Still from our first list: named windows that are reused (with back/forward; `open/<name>`
  covers saved panels only), and replies into the window a command was clicked in.

## The current samples
The `expected.lepanel` files use tags that do not exist (`<table>`, `<ul>`, `<stat value unit>`,
`<bar left right marker>`, `<font color>`). They have to be rewritten with the real tags before
`LePanelExporter` is built.
