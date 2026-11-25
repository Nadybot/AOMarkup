<?php declare(strict_types=1);

namespace AO\Markup\Context;

/** Creates URLs for game objects, for targets outside the game client */
interface LinkResolver {
	public function itemUrl(int $lowId, int $highId, int $ql): ?string;

	public function nanoUrl(int $id): ?string;
}
