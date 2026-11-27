# Using AOMarkup in Nadybot (draft)

How Nadybot is going to use the library. This is a proposal to discuss, nothing here is built
yet. Code examples use names that do not exist yet.

## Today
```
module ──BBML string──► CmdContext::reply()
           │               └─► CommandReply (tell, priv, org, console, Discord, web, …)
           │                      └─► Nadybot::sendTell(): Blob::render() pages, formats colours
           │                             ├─► aolib (AOML, Latin-1)
           │                             └─► MessageHub::handle(RoutableMessage(string))
           │                                    └─► relays, Discord, web, console: regexes
Text::makeBlob() already splits into pages of max_blob_size, before anyone knows where the
message goes (so Discord gets AO pages, too)
```

The numbers: `->reply()` 1615 times in 160 files, `makeBlob` 452 times in 137 files,
`makeChatcmd` 495 times, `<highlight>` 2163 times, `<tab>` 1201 times. Five places convert BBML
with regexes (`CLAUDE.md`). (User input is HTML-escaped by the game client, so markup in it is
not a real risk; escaping in AoXML is about correct documents, e.g. a `<` in item names.)

## Idea: a Document travels, a string is only made at the end
```
module ──Document (or BBML string)──► CmdContext::reply()
           └─► CommandReply ─► sink of the channel:
                 game (tell/priv/org)  AomlExporter + Paginator (max_blob_size) ─► aolib
                 console               AnsiExporter
                 Discord               DiscordExporter (pages by Discord's limits)
                 web UI                HtmlExporter (or the tree itself, see questions)
                 relay NadyNative      BbmlExporter (AoXML for peers that understand it)
                 relay GrcV2           AomlExporter
```

- A BBML string is still allowed everywhere. The sink turns it into a `Document` with
  `BbmlImporter` (optionally with `detectTables`/`detectLists` for targets like the web UI).
  So step 1 changes no module at all, and the five regex converters go away.
- Pagination moves from the module to the sink: every target splits by its own limits.
- `RoutableMessage` carries the `Document`; `getData(): string` stays for old code (BBML).

## Nadybot side: one service
```php
final class Markup {           // Nadybot\Core\Markup, an injectable instance
	public function document(string|Document $message): Document;   // BBML strings are imported
	public function parse(string $aoxml): Document;                // AoXML
	/** @return list<string> */
	public function aoml(string|Document $message): array;         // pages for the game
	public function bbml(string|Document $message): array;
	public function ansi(string|Document $message): string;
	public function html(string|Document $message): string;
	/** @return list<array<string,mixed>> */
	public function discord(string|Document $message): array;
}
```

It builds the `RenderContext` from Nadybot's settings and config: a `Theme` from the
`default_*_color` settings, the placeholders `myname`, `myguild`, `symbol`, a `LinkResolver`
from the item database setting, and the logger; and a `WidgetRegistry` that modules can add
their own widgets to.

## Module side: how to write a Document
Modules build documents with the library's builder (`docs/Builder.md`):

```php
$window = B::window("Banlist ({$count})");
foreach ($banlist as $ban) {
	$window->add(B::section($ban->name)->add(
		B::dl()->addField('By', $ban->admin)->addField('Reason', $ban->reason ?: null)
	));
}
$context->reply($window->asDocument());
```

For one-liners, AoXML with escaped arguments may be shorter (to decide, see `docs/Builder.md`):

```php
$context->reply($this->markup->format('No one matching <strong>%s</strong> is banned.', $search));
```

## Migration
1. Sinks first: `reply()`, `sendTell()`, `sendPrivate()`, `sendGuild()`, `RoutableMessage`
   and the five converters take `string|Document`; strings are BBML and imported at the sink.
   No module changes; output should look the same (the samples show where it does not).
2. `Text::makeBlob()` etc. keep working for old code, but return BBML strings; new code uses
   the API above, module by module, starting with the ones that have tables.
3. Relays: AoXML between bots that both have AOMarkup (lossless), BBML/AOML for the others.

## Decided
- `string|Document` in the existing signatures; it is a transition, BBML strings go away.
- The web UI gets a converter to its `AOMsg` format (a `NadyUiExporter`); NadyUI may render
  AoXML itself later.
- Modules no longer decide page breaks: only `<pagebreak/>` hints; `makeBlob()`'s
  `$permanentHeader` becomes `<repeat>` or the header row of a table.

## Questions
1. **Writing documents:** the builder is decided (`docs/Builder.md`); is `format()` with
   escaped arguments wanted for one-liners as well?
