<?php

namespace MediaWiki\Extension\TranslateTweaks;

class TranslateTweaks {
    public const CATEGORY_COLLATION = 'TranslatedPageName';

    public static function extensionRegister() {
        global $wgCategoryCollation;

        // Change the configured Category Collation to the one that we define.
        $wgCategoryCollation = self::CATEGORY_COLLATION;
    }
}