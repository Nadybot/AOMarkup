<?php declare(strict_types=1);

namespace AO\Markup\Node;

enum ImageSource: string {
	/** Resource database, numeric id */
	case Rdb = 'rdb';

	/** Texture database, symbolic name like GFX_GUI_ICON_PROFESSION_1 */
	case Tdb = 'tdb';
}
