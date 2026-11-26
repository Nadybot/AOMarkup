# AOMarkup – Architecture

```
            ┌──────────────┐
 AoXML ────►│ AoXmlParser  │──┐
            └──────────────┘  │
            ┌──────────────┐  │     ┌──────────┐    ┌──────────────┐    ┌───────────┐
 BBML ─────►│ BbmlImporter │──┼────►│ Document │───►│ Transformers │───►│ Exporters │──► string
            │ (+ AOML)     │  │     │  (AST)   │    │ (AST → AST)  │    └───────────┘
            └──────────────┘  │     └──────────┘    └──────────────┘
            ┌──────────────┐  │          ▲
 Markdown ─►│ Markdown-    │──┘          │
            │ Importer     │        Builder API
            └──────────────┘
```

The document tree is the contract. AoXML is just its text form. Nothing in this
library knows about a specific bot: everything bot-specific comes in through the
`RenderContext`.

## Document
`Document` = the message body (a `Fragment`) + its windows (`array<string,Window>`).
Popups in the body are `PopupLink` nodes that reference a window by id. Windows never
contain `PopupLink`s: there is no nesting.

## Nodes (`AO\Markup\Node`)
Every node is an immutable value object. There are three kinds:

- **Block nodes**: `Paragraph`, `Section`, `FieldList`/`Field`/`FieldValue`, `Table`/`TableRow`/`TableCell`,
  `ListBlock`/`ListItem`, `Indent`, `Heading`, `Center`, `HorizontalRule`, `PageBreak`, `Footer`,
  `Keep`, `Repeat`
- **Inline nodes**: `Text`, `LineBreak`, `Style`, `Color`, `Image`, `Placeholder`,
  links (`ItemLink`, `NanoLink`, `SkillLink`, `CharLink`, `CommandLink`, `ClientCommandLink`,
  `WaypointLink`, `UrlLink`, `PopupLink`)
- **Widget nodes**: `WidgetNode`, which wraps a `Widget` instance plus its children

Block containers (the body, `Window`, `Section`, `ListItem`, `TableCell`, `Indent`,
`Center`, `Keep`, `Repeat`) contain block nodes only: inline content in them is always
wrapped in a `Paragraph`. `Field::$name`, `FieldValue`, `Heading`, `Footer` and all inline nodes contain inline nodes only.
A window's `Footer` is always its last child.

## Importers (`AO\Markup\Import`)
`Importer::import(string): Document`. Importers never produce widgets: guessing a
slider from `|||` text is exactly the heuristic we want to get rid of.

## Transformers (`AO\Markup\Transform`)
Pure `Document → Document` steps, combined per exporter:

| Transformer | Purpose |
|---|---|
| `PlaceholderResolver` | Replaces `<myname/>`, `<symbol/>`, … with text |
| `WidgetExpander` | Replaces widgets without a native renderer by their `fallback()` |
| `Paginator` | Splits a document into several complete messages, measured by rendering in the target format; ends pages only where needed, and only at `<pagebreak/>`s if there are any (`docs/AoXML.md` §2.2). Parts of a split table or field list (`Table::part()`, `FieldList::part()`) are measured on the whole, so columns line up on every page. |

## Exporters (`AO\Markup\Export`)
`Exporter::export(Document, RenderContext): string`.

| Exporter | Target |
|---|---|
| `AomlExporter` | AOML for the official (Funcom) client; tables and fields are aligned with spaces using `AoFontMetric` |
| `BbmlExporter` | BBML for relays: like AOML, but named styles stay (`<highlight>…<end>`) so the receiver applies its theme |
| `LePanelExporter` | AOML with `lepanel://v1/` windows for Lost Eden (draft) |
| `AnsiExporter` | Terminals: 24-bit colours, OSC 8 hyperlinks, monospace tables |
| `PlainExporter` | Plain UTF-8 text, monospace tables |
| `HtmlExporter` | Semantic HTML with `aom-*` CSS classes |
| `DiscordExporter` | JSON for a Discord message: content + embeds |
| `AoXmlSerializer` | Canonical AoXML |

## Widgets
A widget is one class:

```php
#[AsWidget('slider')]
final class Slider implements Widget {
	public function __construct(
		public readonly float $pos,
		public readonly float $min = 0,
		public readonly float $max = 100,
		public readonly ?string $left = null,
		public readonly ?string $right = null,
		public readonly ?string $marker = null,
	) {}

	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		// return core nodes only
	}
}
```

- **Parsing** is generic: the `WidgetRegistry` maps the element name to the class and
  hydrates the constructor parameters from the attributes (types, required, defaults).
  `#[AsWidget('name', void: …, block: …)]` says whether the widget has content and whether
  it stands on its own like a paragraph (`<slider>`) or flows within a line (`<unavailable/>`).
- **Serialising** is generic as well.
- **`fallback()` is mandatory** and returns core nodes only. With it, every widget works in
  every exporter right away. The `ExpansionContext` provides the target's `TextMetric`, so a
  fallback can align things (e.g. the slider marker).
- **Native renderers are optional**: `NativeRenderer` implementations registered on an
  exporter, e.g. lepanel `<bar>` or HTML `<meter>` for the slider.
- Bots can register their own widgets without a library release.

## RenderContext
Everything bot-specific:

| Part | Purpose |
|---|---|
| `Theme` | Maps style names (`strong`, `header`, `clan`, …) to colours |
| `PlaceholderProvider` | Values for `myname`, `symbol`, `myguild`, `var` |
| `LinkResolver` | URLs for items, nanos etc. outside the game |
| `LoggerInterface` | Warnings |

## Metrics (`AO\Markup\Metric`)
`TextMetric::width(string): int` and `spaceWidth(): int`.
`AoFontMetric` measures pixels in the default AO font; `MonospaceMetric` measures
terminal cells (`mb_strwidth`).
