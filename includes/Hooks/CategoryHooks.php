<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use Collation;
use MalformedTitleException;
use OutputPage;
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

    /**
     * Generate the links to category pages on the bottom of a given page
     * 
     * @param OutputPage $out The current page
     * @param array $categories An assoc array of categories where the key is the category name and the value is the type of category (eg; 'hidden')
     * @param array $links An assoc array of links to categories (Where the key is the type of category (eg; 'hidden') and the value is an array of links 
     * @return bool If the hook shouldn't prevent the default behavior
     * @throws MalformedTitleException If one of the $categories given is not a valid parseable title
     */
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

    /**
     * Generate the links for a Category page, link displays will use the translated titles
     * 
     * @param string $type Category type, either 'page', 'img', or 'subcat'
     * @param Title $title Categorized page
     * @param string $html
     * @param string $link HTML link representation
     * @return false Stops other hooks from running
     * @throws MalformedTitleException If the title given is not in the correct format
     */
    public function onCategoryViewer__generateLink( $type, $title, $html, &$link ) {
        $translated = $this -> helper -> getTranslatedTitle( $title );
        $link = $this -> links -> makeLink( $title, $translated -> getText() );
        return false;
    }

    /**
     * Create the Collation used for sorting pages by their Translated Title
     * 
     * @param string $collationName The name of the collation being requested
     * @param ?Collation $collationObject The created collation object
     * @return bool If we've created the $collationObject
     */
    public function onCollation__factory( $collationName, &$collationObject ) {
        if ( $collationName === TranslateTweaks::CATEGORY_COLLATION ) {
            $collationObject = new TranslatedPageTitleCollation( $this -> helper );

            return false;
        }

        return true;
    }
}
