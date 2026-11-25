<?php declare(strict_types=1);

namespace AO\Markup\Context;

/** Provides values for <myname/>, <myguild/>, <symbol/> and <var name="…"/> */
interface PlaceholderProvider {
	public const MY_NAME = 'myname';
	public const MY_GUILD = 'myguild';
	public const SYMBOL = 'symbol';

	public function get(string $name): ?string;
}
