<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use Title;
use MediaWiki\Extension\TranslateTweaks\TranslateTweaks;
use MediaWiki\Hook\CategoryViewer__generateLinkHook;
use MediaWiki\Hook\Collation__factoryHook;
use MediaWiki\Hook\OutputPageMakeCategoryLinksHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\Extension\TranslateTweaks\TranslatedPageTitleCollation;

class CategoryHooks implements OutputPageMakeCategoryLinksHook, CategoryViewer__generateLinkHook, Collation__factoryHook {
    private LinkRenderer $links;
    private TranslateHelper $helper;

    public function __construct(
        LinkRenderer $links,
        TranslateHelper $helper
    ) {
        $this -> links = $links;
        $this -> helper = $helper;
    }

    public function onOutputPageMakeCategoryLinks( $out, $categories, &$links ) {
        // Get the pages title
        $title = $out -> getTitle();
        if ( !$title ) {
            return true;
        }

        foreach ($categories as $category => $type) {
            // Create an empty array if one doesn't exist
            $links[$type] ??= [];

            // Create a title object
            $title = Title::newFromText( $category, NS_CATEGORY );
            $translated = $this -> helper -> getTranslatedTitle( $title );
            $link = $this -> links -> makeLink( $title, $translated -> getText() );

            // Add our link to the categories
            $links[$type][] = $link;
        }

        return false;
    }

    public function onCategoryViewer__generateLink( $type, $title, $html, &$link ) {
        $translated = $this -> helper -> getTranslatedTitle( $title );
        $link = $this -> links -> makeLink( $title, $translated -> getText() );
    }

    public function onCollation__factory( $categoryName, &$categoryObj ) {
        if ( $categoryName === TranslateTweaks::CATEGORY_COLLATION ) {
            $categoryObj = new TranslatedPageTitleCollation( $this -> helper );

            return false;
        }

        return true;
    }
}
