# AOMarkup

Structured markup for Anarchy Online bots.

AOMarkup parses **AoXML**, an XML-shaped markup that describes *what* a message
contains (sections, key/value fields, tables, lists, items, commands, …) rather than
*how* it looks. Exporters then render the same document for every target:

- AOML for the official Anarchy Online client
- AOML with `lepanel://` windows for the Lost Eden private server
- ANSI terminals
- plain text
- HTML
- Discord

Legacy Nadybot/Budabot markup (`<highlight>…<end>`, `<tab>`, `text://` popups, …) and
Markdown can be imported into the same document tree.

> **Status:** skeleton. The language and the expected outputs are being designed; most
> classes are stubs. See `tests/Samples` for what the output is supposed to look like.

## Example

```php
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Export\AomlExporter;

$doc = (new AoXmlParser())->parse(<<<'XML'
	<popup title="Banlist (1)">
	  <section title="Evilguy">
	    <field name="By">Nadyita</field>
	    <field name="Ban ends">Never</field>
	  </section>
	</popup>
	XML);

echo (new AomlExporter())->export($doc, $context);
```

## Documentation
- [AoXML language reference](docs/AoXML.md)
- [Architecture](docs/Architecture.md)
- [Samples and expected outputs](tests/Samples/README.md)

## License
AGPL-3.0-or-later
