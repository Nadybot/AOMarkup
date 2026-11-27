# Builder API

`AO\Markup\Build` (used as `B` below) builds documents in code; the builders it returns
(`TableBuilder`, `WindowBuilder`, …) are in `AO\Markup\Builder`.
`Build::table()` reads as what it does; `B` is just a short alias.

```php
use AO\Markup\Build as B;
```

## Rules
- **Names are AoXML's.** `B::xyz(…)` creates the element `<xyz>`: `B::table()`, `B::td()`,
  `B::dl()`, `B::ul()`, `B::strong()`, `B::cmd()`, … Who knows AoXML knows the builder.
- **Nesting follows the tree.** Where AoXML only has an order (`<dd>`s after their `<dt>`), the
  builder nests (`B::dt()->add(B::dd())`), like the tree's `Field`. So wrong structures cannot be
  built: `B::dl()->add()` only takes `dt`s, `B::dt()->add()` only `dd`s.
- **`B::xyz()` creates, `->add(…)` adds and returns `$this`.** Block containers (window, section,
  indent, center, keep, repeat, list item, table cell) take any blocks in `add()`; each argument
  is one block, a string (or other inline content) becomes a paragraph of its own. `<footer>`,
  `<h1>`, `<h2>`, `<dt>`, `<dd>` and `<option>` have inline content, like in the tree. Containers with fixed children (table, row, list, field list,
  field, choice) take only those.
- **Short forms** exist for containers with fixed children; the long form always works, too.
- **Content** is anything: a string is text (`"\n"` is a line break), a number is text, a node or
  builder is markup, `null` is nothing. `addField()` leaves out `null` values and the whole field
  if none is left, `addItem()` leaves out an item without content; in `addRow()`, `null` is an
  empty cell.
- **Types:** content is `null|string|int|float|Stringable|Content` in block positions and
  `null|string|int|float|Stringable|InlineContent` inside a line. `AO\Markup\Content` is
  implemented by every node and builder, `AO\Markup\InlineContent` by inline nodes, fragments,
  widget nodes and inline builders.
- Builders are mutable; the tree is not. `->build()` makes the nodes; elements without content
  (`B::hr()`, `B::br()`, `B::img()`, `B::slider()`, `B::unavailable()`, placeholders) are nodes
  right away. Methods that take content (and `reply()` in a bot) build automatically.

## Blocks
| Builder | Creates | Notes |
|---|---|---|
| `B::window($title, id: null)` | `<window>` | `id` is generated if left out |
| `B::section($title)` | `<section title>` | |
| `B::h1(…)`, `B::h2(…)` | `<h1>`, `<h2>` | inline content |
| `B::indent(…)`, `B::center(…)`, `B::keep(…)`, `B::repeat(…)` | the element | block containers |
| `B::footer(…)` | `<footer>` | inline content |
| `B::hr()`, `B::pagebreak()` | `<hr/>`, `<pagebreak/>` | |
| `B::p(…)` | a paragraph | for inline content that should not be split; a string in `add()` is the same |
| `B::table(border: false)` | `<table>` | `->add(B::tr())`, short: `->addRow(…)`, `->addHeaderRow(…)` |
| `B::tr()` | `<tr>` | `->add(B::td(), B::th())` |
| `B::td(…, align: null, colspan: 1)`, `B::th(…)` | `<td>`, `<th>` | content: inline or blocks |
| `B::dl()` | `<dl>` | `->add(B::dt())`, short: `->addField($name, ...$values)` |
| `B::dt(…)` | `<dt>` | the name; `->add(B::dd())` |
| `B::dd(…, unit: null)` | `<dd>` | |
| `B::ul()`, `B::ol(start: 1)` | `<ul>`, `<ol>` | `->add(B::li())`, short: `->addItem(…)` (one item; its inline pieces form one paragraph) |
| `B::li(…, icon: null)` | `<li>` | block container |
| `B::choice($command, via: Tell, inline: false)` | `<choice>` | `->add(B::option())`, short: `->addOption($value, $label, selected: false)` |
| `B::option($value, …, selected: false)` | `<option>` | |
| `B::slider(pos: …, left: …, …)` | `<slider/>` | any widget: `B::widget(new MyWidget(…), …children)` |

## Inline
| Builder | Creates |
|---|---|
| `B::text(…)` | a sequence of inline content |
| `B::join($parts, separator: ', ', last: null)` | the parts with `$separator` between them and `$last` (if given) between the last two; `null` parts are skipped |
| `B::br()` | `<br/>` |
| `B::strong(…)`, `B::u(…)`, `B::i(…)`, `B::match(…)`, `B::selected(…)`, `B::syntax(…)` | the style |
| `B::green(…)`, `B::neutral(…)`, `B::header2(…)`, … | colour styles; `B::style('clan', …)` by name (an unknown name gives the content unstyled) |
| `B::color('#RRGGBB', …)` | `<color value>` (an invalid colour gives the content uncoloured) |
| `B::item(low: …, high: …, ql: …, …)`, `B::nano($id, …)`, `B::skill($id, …)` | game objects; content is the link text |
| `B::char($name, …)`, `B::waypoint(x: …, y: …, pf: …, …)` | |
| `B::cmd($command, show: Command, via: Tell, …)`, `B::client($command, …)`, `B::url($href, …)` | links; without content the link text is generated |
| `B::img('rdb:123')` | `<img/>` (`null`, i.e. nothing, for an invalid reference) |
| `B::myname()`, `B::myguild()`, `B::symbol()`, `B::var($name)` | placeholders |
| `B::unavailable()`, `B::param(…)` | the widgets |
| `B::datetime($when, $else = null)`, `B::date(…)`, `B::time(…)` | `<datetime/>`, `<date/>`, `<time/>`; `$when` is a Unix time or a `DateTimeInterface`. If it is `null` or `0`, `$else` is used instead (e.g. `'Never'`) and no element is made |
| `B::duration($seconds, $else = null)` | `<duration/>`; only `null` is "none" (`$else`), 0 seconds is a duration (`0 secs`) |

`B::count($n, $singular, $plural = null)` is not an element but a string: `B::count(1, 'ban')`
is `1 ban`, `B::count(2, 'ban')` is `2 bans` (`$plural` defaults to `$singular` + `s`).
Translations (gettext, ICU plural rules) are the bot's business, not the markup's.

## Messages and windows
- `$window->link(…)` is the `<popup>` that opens the window; without content its title is the
  link text.
- `B::document(…)` is a document: its content is the message, the windows of all `link()`s in
  it are added automatically, in the order of their first link, each once. Blocks stay blocks,
  inline pieces next to each other form one paragraph (like `B::p()`). A window without an id,
  or with the id of an earlier window, gets the next free `window1`, `window2`, … Links inside
  windows are not followed (popups are only allowed in the message).
- `$window->asDocument(link: null)` is short for `B::document($window->link(…))`, the usual
  reply: a message that is only the link. Without `link`, the window title is the link text,
  like `Text::makeBlob()` does today.

## Examples
```php
// !banlist
$window = B::window("Banlist ({$count})");
foreach ($banlist as $ban) {
	$window->add(
		B::section($ban->name)->add(
			B::dl()
				->addField('Date', B::datetime($ban->time))
				->addField('By', $ban->admin)
				->addField('Ban ends', B::datetime($ban->banend, 'Never'))
				->addField('Ends in', $ban->banend ? B::duration($ban->banend - time()) : null)
				->addField('Reason', $ban->reason ?: null)
		),
		B::pagebreak()
	);
}
$context->reply($window->asDocument());

// the same field, long form
B::dl()->add(B::dt('Attack')->add(B::dd('1.2', unit: 'second(s)')));

// !history
$table = B::table(border: true)->addHeaderRow(
	'Date', B::th('Level', align: Align::Right), B::th('AI', align: Align::Right), 'Faction', 'Breed', 'Guild (rank)'
);
foreach ($history->data as $e) {
	$date = $e->last_changed->format('Y-m-d');
	if ($e->deleted) {
		$table->addRow($date, B::td(B::red('DELETED'), colspan: 5));
		continue;
	}
	$guild = $e->guild_name === ''
		? 'Not in a guild'
		: B::text($e->guild_name, ' (', B::strong($e->guild_rank_name), ')');
	$table->addRow($date, $e->level, B::green($e->defender_rank), B::style($e->faction->lower(), $e->faction->value), $e->breed, $guild);
}
$context->reply(
	B::window("History of {$name} for {$dimension}")
		->add($table, B::footer('History provided by Auno.org, Chrisax, and Athen Paladins'))
		->asDocument()
);

// a message with text around the link
$bans = B::window('Bans')->add(/* … */);
$context->reply(B::document('There are ', $bans->link(B::strong(B::count($count, 'ban'))), '.'));

// !settings: a choice
$choice = B::choice("settings save {$setting->name}");
foreach ($setting->options as $value => $label) {
	$choice->addOption($value, $label, selected: $value === $setting->value);
}
$window->add(B::section('Predefined Options')->add($choice));
```

## AoXML with arguments: `B::format()` and `B::aoxml()`
For one-liners, AoXML text with named slots reads better than nested builders:

```php
$context->reply(B::format('No one matching <strong>{search}</strong> is banned.', search: $search));
$context->reply(B::format($fmt, ...$params));   // an associative array works the same
```

- **Parsed first, filled in afterwards.** The format string is parsed as AoXML (with the
  built-in widgets); only then are the arguments put into the tree in place of their slots.
  Arguments are never parsed, so data cannot break the document, and there is nothing to escape.
- **Arguments are content**, like everywhere else in the builder: a string or number is text, a
  node or builder is markup, `null` is nothing. So `{item}` can be `B::item(…)`, `{n}` can be
  `B::count($n, 'ban')`, `{bans}` can be `$window->link(…)`. Plural rules and formatting of
  numbers are the caller's job, not the format's.
- **Slots** are `{name}` with a PHP identifier as name (`[A-Za-z_][A-Za-z0-9_]*`), like in PSR-3
  log messages. Anything else in braces is text; `{{` is a literal `{` (`{{x}` is the text `{x}`).
- **Named arguments only.** They are PHP named arguments, so `...$array` with string keys works,
  too. There are no positional slots (`{0}`); positional arguments give a warning and are ignored.
  `format` is the name of the first parameter, so `{format}` cannot be used as a slot.
- **Slots only in content**, never in attribute values (attribute values are plain text):
  `<cmd command="ban {name}">` gives a warning and keeps `{name}` as text; pass
  `B::cmd("ban {$name}")` as an argument instead. A `<popup>` cannot be written in the format
  (its window is unknown there); pass `$window->link(…)` as an argument.
- **Warnings, no exceptions** (PSR-3, like the parser): a slot without an argument becomes
  nothing, an argument without a slot is ignored, broken AoXML is handled like in the parser.
  They go to the logger set once with `B::setLogger($logger)` (none by default) and are
  reported when `format()`/`aoxml()` is called, so they point to the right line in the bot.
- `B::format()` gives **inline content**, so it fits everywhere: `B::document()`, `B::p()`,
  table cells, `addField()`, `reply()`. Blocks in the format string give a warning and are
  dropped; an empty line is two line breaks.
- `B::aoxml($format, ...$args)` is the same for **blocks** (`<section>`, `<table>`, `<dl>`, …);
  in `add()` it adds its blocks in place. A block argument (`B::table()`) splits the paragraph
  its slot is in; inside a line (e.g. in `<strong>`) it is dropped with a warning. Windows (`<window>`) are not allowed in either; build
  them with `B::window()`.
- Format strings are nearly always constants, so their parsed trees are cached per string.

```php
B::format('{char} bought {item} for <strong>{price}</strong> credits.',
	char: B::char($name),
	item: B::item(low: $id, ql: $ql),
	price: number_format($credits),
);
$context->reply(B::format('There are {bans}.', bans: $bans->link(B::strong(B::count($count, 'ban')))));
```

## Open
- `B::document()` with windows that are not linked from the message (allowed in the tree).
