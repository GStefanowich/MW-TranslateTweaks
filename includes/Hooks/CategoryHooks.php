<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use Collation;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Title\Title;
use MediaWiki\Extension\TranslateTweaks\TranslateTweaks;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\Extension\TranslateTweaks\TranslatedPageTitleCollation;

class CategoryHooks implements
    \MediaWiki\Output\Hook\OutputPageRenderCategoryLinkHook,
    \MediaWiki\Hook\CategoryViewer__generateLinkHook,
    \MediaWiki\Hook\Collation__factoryHook
{
    public function __construct(
        private readonly LinkRenderer $links,
        private readonly TranslateHelper $helper
    ) {}

    /**
     * Generate the links to category pages on the bottom of a given page
     * 
     * @param OutputPage $outputPage The current page
     * @param ProperPageIdentity $categoryTitle The page identity for the category
     * @param string $text Current text value of the category
     * @param ?string $link Out value link replacement
     * @return void
     */
    public function onOutputPageRenderCategoryLink(
        OutputPage $outputPage,
        ProperPageIdentity $categoryTitle,
        string $text,
        ?string &$link
    ): void {
        if ( $categoryTitle->exists() ) {
            // Create a title object
            $title = Title::newFromPageIdentity( $categoryTitle );
            $translated = $this->helper->getTranslatedTitle( $title );

            $link = $this->links->makeLink( $title, $translated->getText() );
        }
    }

    /**
     * Generate the links for a Category page, link displays will use the translated titles
     * 
     * @param string $type Category type, either 'page', 'img', or 'subcat'
     * @param Title $title Categorized page
     * @param string $html
     * @param string $link HTML link representation
     * @return false Stops other hooks from running
     */
    public function onCategoryViewer__generateLink( $type, $title, $html, &$link ) {
        $translated = $this->helper->getTranslatedTitle( $title );
        if ( $type === 'page' && $translated instanceof Title ) {
            $text = $translated->getFullText();
        } else {
            $text = $translated->getText();
        }

        $link = $this->links->makeLink( $title, $text );
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
            $collationObject = new TranslatedPageTitleCollation( $this->helper );

            return false;
        }

        return true;
    }
}
