<?php declare(strict_types=1);

namespace AO\Markup\Tests\Builder;

use function Safe\file_get_contents;

use AO\Markup\Build as B;
use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Document;
use AO\Markup\Export\AoXmlSerializer;
use AO\Markup\Node\Align;
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Tests\Support\TreeDump;
use AO\Markup\Widget\WidgetRegistry;
use PHPUnit\Framework\TestCase;

/** The samples, built with the builder, are exactly their input.aoxml */
final class BuildSamplesTest extends TestCase {
	public function testBanlist(): void {
		$bans = [
			['name' => 'Evilguy', 'date' => '2026-11-20 14:02', 'by' => 'Nadyita', 'ends' => '6 days, 3 hrs.', 'reason' => 'Ninja-looting in "APF"'],
			['name' => 'Badguy', 'date' => null, 'by' => 'Nadyita', 'ends' => 'Never', 'reason' => ''],
		];
		$window = B::window('Banlist (' . count($bans) . ')', id: 'banlist');
		foreach ($bans as $ban) {
			$window->add(
				B::section($ban['name'])->add(
					B::dl()
						->addField('Date', $ban['date'])
						->addField('By', $ban['by'])
						->addField('Ban ends', $ban['ends'])
						->addField('Reason', $ban['reason'] ?: null)
				)
			);
		}
		$this->assertSample('01-banlist', $window->asDocument());
	}

	public function testHistory(): void {
		$table = B::table(border: true)->addHeaderRow(
			'Date',
			B::th('Level', align: Align::Right),
			B::th('AI', align: Align::Right),
			'Faction',
			'Breed',
			'Guild (rank)'
		);
		foreach (self::history() as [$date, $level, $ai, $faction, $breed, $guild, $rank, $deleted]) {
			if ($deleted) {
				$table->addRow($date, B::td(B::red('DELETED'), colspan: 5));
				continue;
			}
			$table->addRow(
				$date,
				$level,
				B::green($ai),
				B::style(strtolower($faction), $faction),
				$breed,
				$guild === '' ? 'Not in a guild' : B::text($guild, ' (', B::strong($rank), ')'),
			);
		}
		$document = B::window('History of Pigtail for RK1 (Atlantean)', id: 'history')
			->add($table, B::footer('History provided by Auno.org, Chrisax, and Athen Paladins'))
			->asDocument();
		$this->assertSample('02-history', $document);
	}

	public function testItems(): void {
		$window = B::window('Item Search Results (3)', id: 'items')->add(
			B::ul()
				->addItem(B::item(247115, label: 'Basic First Aid Kit'), ' (QL 1)')
				->addItem(
					'First Aid Kit - QL ',
					B::match(B::item(246800, 246801, 1, '1'), ', ', B::item(246800, 246801, 100, '100')),
					', ',
					B::item(246802, 246803, 200, '200'),
				)
				->addItem('Prototype First Aid Kit ', B::unavailable(), ' - QL ', B::selected(B::item(299000, ql: 50, label: 50))),
			B::strong('*Results have been limited to the first 3 results.'),
			B::footer('Item DB rips created using the AODB tool'),
		);
		$this->assertSample('04-items', $window->asDocument());
	}

	public function testSettings(): void {
		$setting = (object)[
			'name' => 'online_show_relay',
			'module' => 'ONLINE_MODULE',
			'description' => 'Include players from your relay(s) by default',
			'value' => 0,
			'options' => [0 => 'No', 1 => 'Always', 2 => 'In a separate message'],
		];
		$window = B::window("Settings Info for {$setting->name}", id: 'settings')->add(
			B::section('Basic Info')->add(
				B::dl()
					->addField('Name', $setting->name)
					->addField('Module', $setting->module)
					->addField('Description', $setting->description)
					->addField('Current Value', $setting->options[$setting->value])
			),
			'For this setting you must choose one of the options from the list below.',
		);
		$choice = B::choice("settings save {$setting->name}");
		foreach ($setting->options as $value => $label) {
			$choice->addOption($value, $label, selected: $value === $setting->value);
		}
		$window->add(B::section('Predefined Options')->add($choice));
		$this->assertSample('06-settings', $window->asDocument());
	}

	/** @return list<array{string,int,int,string,string,string,string,bool}> Date, level, AI rank, faction, breed, guild, rank, deleted */
	private static function history(): array {
		return [
			['2010-02-24', 195, 0, 'Neutral', 'Atrox', 'Wolf Brigade', 'Applicant', false],
			['2009-05-18', 195, 0, 'Neutral', 'Atrox', 'Wolf Brigade', 'Member', false],
			['2009-04-11', 187, 12, 'Clan', 'Solitus', '', '', false],
			['2008-01-02', 0, 0, '', '', '', '', true],
		];
	}

	/** Same AoXML as the sample, and the same tree as parsing it */
	private function assertSample(string $sample, Document $document): void {
		$input = file_get_contents(__DIR__ . "/../Samples/{$sample}/input.aoxml");
		$aoxml = (new AoXmlSerializer())->export($document, new RenderContext(new ArrayPlaceholderProvider([])));
		$this->assertSame($input, $aoxml);
		$parsed = (new AoXmlParser(WidgetRegistry::withBuiltins()))->parse($input);
		$this->assertSame(TreeDump::document($parsed), TreeDump::document($document));
	}
}
