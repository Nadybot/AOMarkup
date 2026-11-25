# AOMarkup – notes for Claude

AOMarkup (`nadybot/aomarkup`, namespace `AO\Markup`, AGPL-3.0-or-later) is a bot-agnostic
library: it parses **AoXML** into a document tree and exports it to AOML (official client),
lepanel (Lost Eden), ANSI, plain text, HTML and Discord. Its first consumer is Nadybot
(`~/bot/Nadybot`), which today converts its AOML+ with regexes in five places
(`Core/Text.php`, `Core/Blob.php`, `CONSOLE/ConsoleCommandReply.php`,
`WEBSERVER_MODULE/WebChatConverter.php`, `DISCORD/DiscordController.php`).
Sister package: `nadybot/aolib` (namespace `AO\`, the chat client).

Read first: `docs/AoXML.md`, `docs/Architecture.md`, `tests/Samples/README.md`.

## Ground rules (decided)
- The document tree is the contract; AoXML is only its text form. The library knows
  nothing about a specific bot; bot-specific things come in through `RenderContext`
  (Theme, PlaceholderProvider, LinkResolver, PSR-3 logger).
- AoXML is our own format, tailored to our needs (e.g. pagination hints: `<pagebreak>`,
  `<keep>`, `<repeat>`), not a copy of any target format.
- Strict closing: `</green>` instead of `<end>`; `<tr>`, `<td>`, `<th>`, `<li>` are always closed.
  Only known void tags may omit the slash.
- No `<tab>`, no `<black>` padding, no layout with spaces. Use `<section>`, `<field>`,
  `<table>`, `<ul>`/`<ol>`, `<indent>`. Leading whitespace in the source is not significant.
- Game objects are semantic elements (`<item>`, `<nano>`, `<skill>`, `<char>`, `<cmd>`, `<url>`).
- Popups are hoisted into separate windows (`Document::$windows`), like the format Nadybot
  already sends to its web client; `<a href="text://…">` is legacy input only.
- Legacy AOML+ is read by `LegacyAomlImporter`, not by `AoXmlParser`.
- Importers never produce widgets.
- **No exceptions in renderers:** even one-off things like the agg/def bar are widgets.
  A widget is one class with constructor parameters as attributes and a mandatory
  `fallback()` that returns core nodes only; native renderers per exporter are optional.
- The `expected.*` files in `tests/Samples` are the specification. Change them first,
  then make the code follow.
- PHP `>=8.1.17` (no readonly classes), no I/O, errors as PSR-3 warnings instead of exceptions.

## Open questions
- `•` vs `-` as list bullet in Funcom AOML (width of `•` unknown in the AO font).
- Slider bar uses `|` (6 px) instead of today's `l` (4 px).
- Boolean attribute shorthand (`<table border>`) vs. canonical well-formed XML.
- Column alignment per column (`columns="left,right"`) vs. per cell (`align`).
- Short form `<item ref="low/high/ql">` vs. `lowid`/`highid`/`ql`.
- lepanel output is speculative until the Lost Eden devs settle their tags. Requested from them:
  `<table>` (layout vs. border), `<ul>`/`<ol>`, `<stat>` with static `value`/`unit`,
  `<bar>` with labels and marker, named windows that are reused (with back/forward), and later
  replies into the window a command was clicked in (request id round trip).
- Relays between bots on different servers must only carry Funcom AOML (or AoXML).

## Conventions
- Code, comments, docs and commit messages in English.
- Never add a `Co-Authored-By` trailer to commit messages.
- Checks: `composer tests` (PHPUnit, PHPStan level 8, phpcs, mago if installed);
  style via `vendor/bin/php-cs-fixer.phar fix`.
- `bin/show-sample <sample> [ansi|txt|aoml|lepanel|html|discord.json|aoxml]` shows a sample.
