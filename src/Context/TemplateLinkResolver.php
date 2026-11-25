<?php declare(strict_types=1);

namespace AO\Markup\Context;

/** Builds URLs from templates with {id} and {ql} */
final class TemplateLinkResolver implements LinkResolver {
	public function __construct(
		private string $itemTemplate='https://auno.org/ao/db.php?id={id}&ql={ql}',
		private string $nanoTemplate='https://auno.org/ao/db.php?id={id}',
	) {
	}

	public function itemUrl(int $lowId, int $highId, int $ql): string {
		return str_replace(['{id}', '{ql}'], [(string)$lowId, (string)$ql], $this->itemTemplate);
	}

	public function nanoUrl(int $id): string {
		return str_replace('{id}', (string)$id, $this->nanoTemplate);
	}
}
