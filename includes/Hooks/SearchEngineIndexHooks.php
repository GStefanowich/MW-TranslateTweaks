<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use Article;
use Config;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\Extension\TranslateTweaks\Helpers\StaticRobotsPolicy;
use MediaWiki\MainConfigNames;
use ParserOptions;
use WikiPage;

/**
 * Controls Indexing of Translation pages
 */
class SearchEngineIndexHooks implements
    \MediaWiki\Page\Hook\ArticleParserOptionsHook,
    \MediaWiki\Page\Hook\ArticlePageDataBeforeHook
{
    public function __construct(
        private readonly Config $config,
        private readonly TranslateHelper $helper
    ) {}

    /**
     * After getting the ParserOptions for an Article, check if that Article is in the RobotPolicies, and copy the policy to translated pages
     *   $wgArticleRobotPolicies doesn't support wild cards, and translated pages can really add up. This method only runs once per subpage,
     *   it'll check on translated pages such as "/nl", and check the root page. It won't run during configuration to check the root page and apply to all subpages
     *
     * @param Article $article
     * @param ParserOptions $opts
     * @return void
     */
    public function onArticleParserOptions( Article $article, ParserOptions $opts ): void {
        // Convert the translated path to get the root TranslatablePage
        $title = $article->getTitle();
        $translated = $this->helper->getPage( $title );

        // If the Article is a TranslatablePage
        if ( $translated ) {
            // Check the source page if it has a present RobotPolicy
            $policy = StaticRobotsPolicy::get( $translated->getTitle() );

            // Apply the policy to the current (translated) article
            if ( $policy ) {
                StaticRobotsPolicy::set( $title, $policy );
            }
        }
    }

    /**
     * Before any page actions are called, apply the 'noindex' policy on pages if the url ends with the source wiki languages
     *   Pages on 'My Wiki' and 'My Wiki/en' will both contain the same content, so we want to make sure that we don't index the '/en' variant
     * 
     * @param WikiPage $wikiPage
     * @param array &$fields
     * @param array &$tables
     * @param array &$joinConds
     * @return void
     */
    public function onArticlePageDataBefore( $wikiPage, &$fields, &$tables, &$joinConds ): void {
        $wikiLang = $this->config->get( MainConfigNames::LanguageCode );
        $title = $wikiPage->getTitle();
        if ( str_ends_with( $title->getPrefixedText(), '/' . $wikiLang ) ) {
            StaticRobotsPolicy::set( $title, 'noindex' );
        }
    }
}