<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;

class TrackingHooks implements \MediaWiki\Hook\ParserAfterTidyHook {
    public function __construct(
        private readonly TranslateHelper $helper
    ) {}

    /**
     * @inheritdoc
     */
    public function onParserAfterTidy( $parser, &$text ): void {
        // Skip interface messages
        if ( $parser->getOptions()->getInterfaceMessage() ) {
            return;
        }

        $page = $parser->getPage();

        // PageReference must be a title
        if (!( $page instanceof Title )) {
            return;
        }

        // Only track pages in the Translations category
        if ( $page->getNamespace() !== NS_TRANSLATIONS ) {
            return;
        }

        // Get the language of the Translation
        $pageLanguage = $this->helper->getPageLanguage( $page );

        // Get links from the output
        $output = $parser->getOutput();
        $results = $output->getLinkList( ParserOutputLinkTypes::LOCAL );

        // Iterate over the links
        foreach ( $results as $result ) {
            $link = $result['link'] ?? null;

            // Check for title
            if ( $link instanceof TitleValue && !$link->getInterwiki() ) {
                $linkLanguage = $this->helper->getPageLanguage( $link );

                // Check if the languages are not equal
                if ( $linkLanguage !== $pageLanguage ) {
                    $parser->addTrackingCategory( 'translate-tweaks-another-language-category' );
                }
            }
        }
    }
}