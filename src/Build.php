<?php declare(strict_types=1);

namespace AO\Markup;

use AO\Markup\Builder\{
	AoXmlBuilder,
	BuildContext,
	CellBuilder,
	ChoiceBuilder,
	ContainerBuilder,
	ContentConverter,
	DdBuilder,
	DlBuilder,
	DtBuilder,
	ElementBuilder,
	FormatBuilder,
	InlineBlockBuilder,
	LiBuilder,
	ListBuilder,
	OptionBuilder,
	TableBuilder,
	TextBuilder,
	TrBuilder,
	WidgetBuilder,
	WindowBuilder,
};
use AO\Markup\Node\{
	Align,
	Center,
	CharLink,
	ClientCommandLink,
	Color,
	CommandChannel,
	CommandDisplay,
	CommandLink,
	Field,
	Footer,
	Fragment,
	Heading,
	HorizontalRule,
	Image,
	ImageSource,
	Indent,
	ItemLink,
	Keep,
	LineBreak,
	NanoLink,
	Node,
	PageBreak,
	Paragraph,
	Placeholder,
	Repeat,
	Section,
	SkillLink,
	Style,
	StyleName,
	TableCell,
	UrlLink,
	WaypointLink,
};
use AO\Markup\Widget\Builtin\{Date, Datetime, Duration, Param, Slider, Time, Unavailable};
use AO\Markup\Widget\{Widget, WidgetNode};
use DateTimeInterface;
use Psr\Log\{LoggerInterface, NullLogger};
use Safe\DateTimeImmutable as SafeDateTimeImmutable;
use Stringable;

/**
 * Builds documents in code, see docs/Builder.md
 *
 *     use AO\Markup\Build as B;
 *
 * B::xyz() creates the AoXML element <xyz>. Content is a string or number (text, "\n" is a
 * line break), a node, a builder, or null (nothing).
 */
final class Build {
	private static ?LoggerInterface $logger = null;

	/** Where B::format() and B::aoxml() report problems; nowhere by default */
	public static function setLogger(?LoggerInterface $logger): void {
		self::$logger = $logger;
	}

	// ------------------------------------------------------------------
	// AoXML with arguments
	// ------------------------------------------------------------------

	/**
	 * Inline AoXML whose {name} slots are filled by the named arguments
	 *
	 *     B::format('No one matching <strong>{search}</strong> is banned.', search: $search)
	 *     B::format($format, ...$params)
	 *
	 * The format is parsed first, then the arguments are put in place of their slots, so
	 * they are never parsed. "{{" is a literal "{". Slots only work in content, not in
	 * attribute values. See docs/Builder.md.
	 */
	public static function format(string $format, null|string|int|float|Stringable|InlineContent ...$args): FormatBuilder {
		return new FormatBuilder($format, $args, self::$logger ?? new NullLogger());
	}

	/**
	 * AoXML blocks whose {name} slots are filled by the named arguments, like format()
	 *
	 * As content of a block container, its blocks are added in place.
	 */
	public static function aoxml(string $format, null|string|int|float|Stringable|Content ...$args): AoXmlBuilder {
		return new AoXmlBuilder($format, $args, self::$logger ?? new NullLogger());
	}

	// ------------------------------------------------------------------
	// Messages and windows
	// ------------------------------------------------------------------

	/**
	 * A document: the content is the message, the windows of all links in it are added
	 *
	 * Blocks stay blocks, each run of inline content between them is one paragraph.
	 * Windows are added in the order of their first link, each once. A window without
	 * an id, or whose id another window has, gets the next free "window1", "window2", ….
	 */
	public static function document(null|string|int|float|Stringable|Content ...$content): Document {
		$content = array_values($content);
		$links = new BuildContext();
		ContentConverter::flow($content, $links);
		$context = BuildContext::for($links->windows());
		$body = ContentConverter::flow($content, $context);
		$windows = [];
		foreach ($links->windows() as $window) {
			$id = $context->idOf($window);
			$windows[$id] = $window->window($id, $context);
		}
		return new Document(new Fragment(...$body), $windows);
	}

	/** A window; link() makes the popup link that opens it */
	public static function window(string $title, ?string $id=null): WindowBuilder {
		return new WindowBuilder($title, $id);
	}

	// ------------------------------------------------------------------
	// Blocks
	// ------------------------------------------------------------------

	/** A section; add() takes any blocks */
	public static function section(string $title, null|string|int|float|Stringable|Content ...$content): ContainerBuilder {
		return (new ContainerBuilder(static fn (Node ...$children): Node => new Section($title, ...$children)))->add(...$content);
	}

	public static function indent(null|string|int|float|Stringable|Content ...$content): ContainerBuilder {
		return (new ContainerBuilder(static fn (Node ...$children): Node => new Indent(...$children)))->add(...$content);
	}

	public static function center(null|string|int|float|Stringable|Content ...$content): ContainerBuilder {
		return (new ContainerBuilder(static fn (Node ...$children): Node => new Center(...$children)))->add(...$content);
	}

	public static function keep(null|string|int|float|Stringable|Content ...$content): ContainerBuilder {
		return (new ContainerBuilder(static fn (Node ...$children): Node => new Keep(...$children)))->add(...$content);
	}

	public static function repeat(null|string|int|float|Stringable|Content ...$content): ContainerBuilder {
		return (new ContainerBuilder(static fn (Node ...$children): Node => new Repeat(...$children)))->add(...$content);
	}

	/** A closing note of a window; inline content */
	public static function footer(null|string|int|float|Stringable|InlineContent ...$content): InlineBlockBuilder {
		return (new InlineBlockBuilder(static fn (Node ...$children): Node => new Footer(...$children)))->add(...$content);
	}

	/** A paragraph of these inline pieces */
	public static function p(null|string|int|float|Stringable|InlineContent ...$content): InlineBlockBuilder {
		return (new InlineBlockBuilder(static fn (Node ...$children): Node => new Paragraph(...$children)))->add(...$content);
	}

	public static function h1(null|string|int|float|Stringable|InlineContent ...$content): InlineBlockBuilder {
		return (new InlineBlockBuilder(static fn (Node ...$children): Node => new Heading(1, ...$children)))->add(...$content);
	}

	public static function h2(null|string|int|float|Stringable|InlineContent ...$content): InlineBlockBuilder {
		return (new InlineBlockBuilder(static fn (Node ...$children): Node => new Heading(2, ...$children)))->add(...$content);
	}

	public static function hr(): HorizontalRule {
		return new HorizontalRule();
	}

	/** A possible page break */
	public static function pagebreak(): PageBreak {
		return new PageBreak();
	}

	/** A table; add() takes only rows, see also addRow() and addHeaderRow() */
	public static function table(bool $border=false): TableBuilder {
		return new TableBuilder($border);
	}

	/** A table row; add() takes only cells */
	public static function tr(CellBuilder|TableCell ...$cells): TrBuilder {
		return new TrBuilder(...$cells);
	}

	/** A data cell; add() takes any blocks */
	public static function td(
		null|string|int|float|Stringable|Content $content=null,
		?Align $align=null,
		int $colspan=1,
	): CellBuilder {
		return (new CellBuilder(false, $align, $colspan))->add($content);
	}

	/** A header cell; add() takes any blocks */
	public static function th(
		null|string|int|float|Stringable|Content $content=null,
		?Align $align=null,
		int $colspan=1,
	): CellBuilder {
		return (new CellBuilder(true, $align, $colspan))->add($content);
	}

	/** A field list; add() takes only fields (B::dt()), see also addField() */
	public static function dl(DtBuilder|Field ...$fields): DlBuilder {
		return (new DlBuilder())->add(...$fields);
	}

	/** A field with this name; add() takes only values (B::dd()) */
	public static function dt(null|string|int|float|Stringable|InlineContent ...$name): DtBuilder {
		return new DtBuilder(...$name);
	}

	/** A value of a field */
	public static function dd(null|string|int|float|Stringable|InlineContent $content=null, ?string $unit=null): DdBuilder {
		return (new DdBuilder($unit))->add($content);
	}

	/** An unordered list; add() takes only items (B::li()), see also addItem() */
	public static function ul(): ListBuilder {
		return new ListBuilder();
	}

	/** An ordered list; add() takes only items (B::li()), see also addItem() */
	public static function ol(int $start=1): ListBuilder {
		return new ListBuilder(true, $start);
	}

	/**
	 * A list item; add() takes any blocks
	 *
	 * @param null|string|Image $icon An image reference (rdb:12345, tdb:GFX_…) or image
	 */
	public static function li(
		null|string|int|float|Stringable|Content $content=null,
		null|string|Image $icon=null,
	): LiBuilder {
		return (new LiBuilder(is_string($icon) ? self::img($icon) : $icon))->add($content);
	}

	// ------------------------------------------------------------------
	// Widgets
	// ------------------------------------------------------------------

	/** A choice between values; add() takes only options, see also addOption() */
	public static function choice(string $command, CommandChannel $via=CommandChannel::Tell, bool $inline=false): ChoiceBuilder {
		return new ChoiceBuilder($command, $via, $inline);
	}

	/** An option of a choice; the label is its content */
	public static function option(
		string|int $value,
		null|string|int|float|Stringable|InlineContent $label=null,
		bool $selected=false,
	): OptionBuilder {
		return (new OptionBuilder((string)$value, $selected))->add($label);
	}

	/** A horizontal scale with a marker */
	public static function slider(
		float $pos,
		float $min=0,
		float $max=100,
		?string $left=null,
		?string $right=null,
		?string $marker=null,
	): WidgetNode {
		return new WidgetNode(new Slider($pos, $min, $max, $left, $right, $marker));
	}

	/** Marks something as not available in game */
	public static function unavailable(): WidgetNode {
		return new WidgetNode(new Unavailable());
	}

	/**
	 * A point in time with date and time, shown in the bot's (or the reader's) time zone
	 *
	 * $when is a Unix time or a date; if it is null or 0, $else is returned instead (e.g. 'Never')
	 * and no element is made.
	 */
	public static function datetime(null|int|DateTimeInterface $when, null|string|int|float|Stringable|InlineContent $else=null): null|string|int|float|Stringable|InlineContent {
		$value = self::pointInTime($when);
		return $value === null ? $else : new WidgetNode(new Datetime($value));
	}

	/** A date, like B::datetime() */
	public static function date(null|int|DateTimeInterface $when, null|string|int|float|Stringable|InlineContent $else=null): null|string|int|float|Stringable|InlineContent {
		$value = self::pointInTime($when);
		return $value === null ? $else : new WidgetNode(new Date($value));
	}

	/** A time of day, like B::datetime() */
	public static function time(null|int|DateTimeInterface $when, null|string|int|float|Stringable|InlineContent $else=null): null|string|int|float|Stringable|InlineContent {
		$value = self::pointInTime($when);
		return $value === null ? $else : new WidgetNode(new Time($value));
	}

	/**
	 * A length of time in seconds, e.g. until a ban ends
	 *
	 * Only null is "none"; 0 seconds is a duration, too.
	 */
	public static function duration(?int $seconds, null|string|int|float|Stringable|InlineContent $else=null): null|string|int|float|Stringable|InlineContent {
		return $seconds === null ? $else : new WidgetNode(new Duration($seconds));
	}

	/** A command parameter inside B::syntax() */
	public static function param(null|string|int|float|Stringable|InlineContent ...$content): WidgetBuilder {
		return new WidgetBuilder(new Param(), ...$content);
	}

	/** Any widget with its content */
	public static function widget(Widget $widget, null|string|int|float|Stringable|Content ...$children): WidgetBuilder {
		return new WidgetBuilder($widget, ...$children);
	}

	// ------------------------------------------------------------------
	// Inline
	// ------------------------------------------------------------------

	/** A sequence of inline content */
	public static function text(null|string|int|float|Stringable|InlineContent ...$content): TextBuilder {
		return new TextBuilder(...$content);
	}

	public static function br(): LineBreak {
		return new LineBreak();
	}

	/** A style by name; content without a style if there is no style of that name */
	public static function style(StyleName|string $style, null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		$style = is_string($style) ? StyleName::tryFrom($style) : $style;
		return new ElementBuilder(
			static fn (Node ...$children): array => $style === null ? array_values($children) : [new Style($style, ...$children)],
			...$content
		);
	}

	/** Emphasis */
	public static function strong(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Strong, ...$content);
	}

	/** The colour of the window title */
	public static function header(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Header, ...$content);
	}

	/** The colour of section titles */
	public static function header2(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Header2, ...$content);
	}

	public static function on(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::On, ...$content);
	}

	public static function off(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Off, ...$content);
	}

	public static function neutral(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Neutral, ...$content);
	}

	public static function omni(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Omni, ...$content);
	}

	public static function clan(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Clan, ...$content);
	}

	public static function unknown(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Unknown, ...$content);
	}

	/** A part matching a search */
	public static function match(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Match, ...$content);
	}

	/** The currently selected choice */
	public static function selected(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Selected, ...$content);
	}

	/** Command syntax, a bot command without prefix */
	public static function syntax(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Syntax, ...$content);
	}

	/** Underline */
	public static function u(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Underline, ...$content);
	}

	/** Italic */
	public static function i(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Italic, ...$content);
	}

	public static function black(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Black, ...$content);
	}

	public static function white(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::White, ...$content);
	}

	public static function yellow(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Yellow, ...$content);
	}

	public static function blue(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Blue, ...$content);
	}

	public static function green(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Green, ...$content);
	}

	public static function red(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Red, ...$content);
	}

	public static function orange(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Orange, ...$content);
	}

	public static function grey(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Grey, ...$content);
	}

	public static function cyan(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Cyan, ...$content);
	}

	public static function violet(null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		return self::style(StyleName::Violet, ...$content);
	}

	/** A raw colour, #RRGGBB; content without a colour if it is not one */
	public static function color(string $hex, null|string|int|float|Stringable|InlineContent ...$content): ElementBuilder {
		$hex = \Safe\preg_match('/^#?([0-9A-Fa-f]{6})$/', $hex, $m) === 1 && isset($m[1]) ? '#' . strtoupper($m[1]) : null;
		return new ElementBuilder(
			static fn (Node ...$children): array => $hex === null ? array_values($children) : [new Color($hex, ...$children)],
			...$content
		);
	}

	/** An item; the content is the link text */
	public static function item(
		int $low,
		?int $high=null,
		int $ql=1,
		null|string|int|float|Stringable|InlineContent $label=null,
	): ElementBuilder {
		return new ElementBuilder(static fn (Node ...$children): array => [new ItemLink($low, $high ?? $low, $ql, ...$children)], $label);
	}

	/** A nano program; the content is the link text */
	public static function nano(int $id, null|string|int|float|Stringable|InlineContent ...$label): ElementBuilder {
		return new ElementBuilder(static fn (Node ...$children): array => [new NanoLink($id, ...$children)], ...$label);
	}

	/** A skill; the content is the link text */
	public static function skill(int $id, null|string|int|float|Stringable|InlineContent ...$label): ElementBuilder {
		return new ElementBuilder(static fn (Node ...$children): array => [new SkillLink($id, ...$children)], ...$label);
	}

	/** A character; without content, the name is the link text */
	public static function char(string $name, null|string|int|float|Stringable|InlineContent ...$label): ElementBuilder {
		return new ElementBuilder(static fn (Node ...$children): array => [new CharLink($name, ...$children)], ...$label);
	}

	/** A waypoint on playfield $pf; the content is the link text */
	public static function waypoint(
		int $x,
		int $y,
		int $pf,
		null|string|int|float|Stringable|InlineContent $label=null,
	): ElementBuilder {
		return new ElementBuilder(static fn (Node ...$children): array => [new WaypointLink($x, $y, $pf, ...$children)], $label);
	}

	/** A command for this bot, without prefix; without a label, the link text is generated */
	public static function cmd(
		string $command,
		null|string|int|float|Stringable|InlineContent $label=null,
		CommandDisplay $show=CommandDisplay::Command,
		CommandChannel $via=CommandChannel::Tell,
	): ElementBuilder {
		return new ElementBuilder(static fn (Node ...$children): array => [new CommandLink($command, $show, $via, ...$children)], $label);
	}

	/** A command for the game client; without content, the command is the link text */
	public static function client(string $command, null|string|int|float|Stringable|InlineContent ...$label): ElementBuilder {
		return new ElementBuilder(static fn (Node ...$children): array => [new ClientCommandLink($command, ...$children)], ...$label);
	}

	/** An external link */
	public static function url(string $href, null|string|int|float|Stringable|InlineContent ...$label): ElementBuilder {
		return new ElementBuilder(static fn (Node ...$children): array => [new UrlLink($href, ...$children)], ...$label);
	}

	/** An image, rdb:12345 or tdb:GFX_…; null (nothing) if the reference is invalid */
	public static function img(string $src): ?Image {
		if (\Safe\preg_match('/^(rdb|tdb):(.+)$/', $src, $m) !== 1 || !isset($m[1], $m[2])) {
			return null;
		}
		$source = ImageSource::from($m[1]);
		return ($source === ImageSource::Rdb && !ctype_digit($m[2])) ? null : new Image($source, $m[2]);
	}

	/** The bot's name */
	public static function myname(): Placeholder {
		return new Placeholder('myname');
	}

	/** The bot's guild */
	public static function myguild(): Placeholder {
		return new Placeholder('myguild');
	}

	/** The command prefix */
	public static function symbol(): Placeholder {
		return new Placeholder('symbol');
	}

	/** Any placeholder */
	public static function var(string $name): Placeholder {
		return new Placeholder($name);
	}

	// ------------------------------------------------------------------

	/** A number with a word: count(1, 'ban') is "1 ban", count(2, 'ban') is "2 bans" */
	public static function count(int|float $n, string $singular, ?string $plural=null): string {
		return "{$n} " . ((float)$n === 1.0 ? $singular : ($plural ?? "{$singular}s"));
	}

	/** A Unix time or a date as DateTimeImmutable; null and 0 are none */
	private static function pointInTime(null|int|DateTimeInterface $when): ?\DateTimeImmutable {
		if ($when === null || $when === 0) {
			return null;
		}
		if ($when instanceof DateTimeInterface) {
			return \DateTimeImmutable::createFromInterface($when);
		}
		return new SafeDateTimeImmutable("@{$when}");
	}
}
