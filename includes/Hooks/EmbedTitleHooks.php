<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\Html\Html;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Registration\ExtensionRegistry;

/**
 * Updates OpenGraph tags (Commonly used for Embeds in Discord, Facebook, BSky, etc.)
 *     eg; Replaces 'Milk/nl' og:title tag with 'Melk'
 */
class EmbedTitleHooks
    implements OutputPageParserOutputHook
{
    public function __construct(
        private readonly ExtensionRegistry $extensions,
        private readonly TranslateHelper $helper
    ) {}

    /**
     * @param $outputPage
     * @param $parserOutput
     * @return void
     */
    public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
        $title = $outputPage->getTitle();

        // Only works on subpages, like '/en', '/fr', '/nl', '/de', '/pl', etc
        if ( !$title->isSubpage() ) {
            return;
        }

        // Check that OpenGraphMeta is loaded, we're not handling all OG tags alone
        if ( !$this->extensions->isLoaded('OpenGraphMeta') ) {
            return;
        }

        // Check if the page is a localized one
        $localized = $this->helper->getPage($title);

        if ( $localized ) {
            $base = $title->getBaseTitle();
            $display = '';

            if ( !$base->isMainPage() ) {
                // Use the display title provided by Extension:Translate
                $display = $parserOutput->getDisplayTitle();
            } else {
                $languageCode = $this->helper->getPageLanguage( $title );

                // If we check 'Allow translating title' in Page translation settings, get that title
                if ( $localized->hasPageDisplayTitle() ) {
                    $display = $localized->getPageDisplayTitle( $languageCode );
                } else {
                    // Use the Extension:OpenGraphMeta localization key
                    $display = $outputPage->msg('opengraphmeta-site-name')
                        ->inLanguage( $languageCode )
                        ->plain();
                }
            }

            // Override the property that might be set by 'OpenGraphMeta' extension
            $outputPage->addHeadItem( 'meta:property:og:title', Html::element( 'meta', [
                'property' => 'og:title',
                'content' => $display
            ] ) );
        }
    }
}