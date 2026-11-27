# Builder API (draft)

`AO\Markup\Build` (used as `B` below) builds documents in code. Nothing here is built yet.

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
  indent, center, keep, repeat, footer, list item, table cell) take any blocks in `add()`; a
  string becomes a paragraph. Containers with fixed children (table, row, list, field list,
  field, choice) take only those.
- **Short forms** exist for containers with fixed children; the long form always works, too.
- **Content** is anything: a string is text, a number is text, a node or builder is markup,
  `null` is nothing. A short form whose value is `null` leaves out the whole field or row.
- Builders are mutable; the tree is not. `->build()` makes the nodes; methods that take content
  (and `reply()` in a bot) build automatically.

## Blocks
| Builder | Creates | Notes |
|---|---|---|
| `B::window($title, id: null)` | `<window>` | `id` is generated if left out |
| `B::section($title)` | `<section title>` | |
| `B::h1(…)`, `B::h2(…)` | `<h1>`, `<h2>` | inline content |
| `B::indent()`, `B::center()`, `B::keep()`, `B::repeat()`, `B::footer()` | the element | block containers |
| `B::hr()`, `B::pagebreak()` | `<hr/>`, `<pagebreak/>` | |
| `B::p(…)` | a paragraph | for inline content that should not be split; a string in `add()` is the same |
| `B::table(border: false)` | `<table>` | `->add(B::tr())`, short: `->addRow(…)`, `->addHeaderRow(…)` |
| `B::tr()` | `<tr>` | `->add(B::td(), B::th())` |
| `B::td(…, align: null, colspan: 1)`, `B::th(…)` | `<td>`, `<th>` | content: inline or blocks |
| `B::dl()` | `<dl>` | `->add(B::dt())`, short: `->addField($name, ...$values)` |
| `B::dt(…)` | `<dt>` | the name; `->add(B::dd())` |
| `B::dd(…, unit: null)` | `<dd>` | |
| `B::ul()`, `B::ol(start: 1)` | `<ul>`, `<ol>` | `->add(B::li())`, short: `->addItem(…)` |
| `B::li(…, icon: null)` | `<li>` | block container |
| `B::choice($command, via: Tell, inline: false)` | `<choice>` | `->add(B::option())`, short: `->addOption($value, $label, selected: false)` |
| `B::option($value, …, selected: false)` | `<option>` | |
| `B::slider(pos: …, left: …, …)` | `<slider/>` | any widget: `B::widget(new MyWidget(…), …children)` |

## Inline
| Builder | Creates |
|---|---|
| `B::text(…)` | a sequence of inline content |
| `B::br()` | `<br/>` |
| `B::strong(…)`, `B::u(…)`, `B::i(…)`, `B::match(…)`, `B::selected(…)`, `B::syntax(…)` | the style |
| `B::green(…)`, `B::neutral(…)`, `B::header2(…)`, … | colour styles; `B::style('clan', …)` by name |
| `B::color('#RRGGBB', …)` | `<color value>` |
| `B::item(low: …, high: …, ql: …, …)`, `B::nano($id, …)`, `B::skill($id, …)` | game objects; content is the link text |
| `B::char($name, …)`, `B::waypoint(x: …, y: …, pf: …, …)` | |
| `B::cmd($command, show: Command, via: Tell, …)`, `B::client($command, …)`, `B::url($href, …)` | links; without content the link text is generated |
| `B::img('rdb:123')` | `<img/>` |
| `B::myname()`, `B::myguild()`, `B::symbol()`, `B::var($name)` | placeholders |
| `B::unavailable()`, `B::param(…)` | the widgets |

## Messages and windows
- `$window->link(…)` is the `<popup>` that opens the window; without content its title is the
  link text.
- `B::message(…)` is a document: its content is the message, the windows of all `link()`s in it
  are added automatically.
- `$window->asMessage()` is short for `B::message($window->link())`, the usual reply.

## Examples
```php
// !banlist
$window = B::window("Banlist ({$count})");
foreach ($banlist as $ban) {
	$window->add(
		B::section($ban->name)->add(
			B::dl()
				->addField('Date', isset($ban->time) ? Util::date($ban->time) : null)
				->addField('By', $ban->admin)
				->addField('Ban ends', $ban->banend ? Util::unixtimeToReadable($ban->banend - time(), false) : 'Never')
				->addField('Reason', $ban->reason ?: null)
		),
		B::pagebreak()
	);
}
$context->reply($window->asMessage());

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
		->asMessage()
);

// a message with text around the link
$bans = B::window('Bans')->add(/* … */);
$context->reply(B::message('There are ', $bans->link(B::strong($count), ' bans'), '.'));

// !settings: a choice
$choice = B::choice("settings save {$setting->name}");
foreach ($setting->options as $value => $label) {
	$choice->addOption($value, $label, selected: $value === $setting->value);
}
$window->add(B::section('Predefined Options')->add($choice));
```

## Open
- `B::message()` with windows that are not linked from the message (allowed in the tree).
- Whether to add `B::aoxml('…')` for a piece of AoXML inside a builder, and `format()` with
  escaped arguments for one-liners (see `docs/Nadybot.md`).
