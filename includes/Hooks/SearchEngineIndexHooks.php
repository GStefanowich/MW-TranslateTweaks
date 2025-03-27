<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use Article;
use Config;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\Extension\TranslateTweaks\Helpers\StaticRobotsPolicy;
use MediaWiki\MainConfigNames;
use ParserOptions;
use Wikimedia\Rdbms\IConnectionProvider;
use WikiPage;

/**
 * Controls Indexing of Translation pages
 */
class SearchEngineIndexHooks implements
    \MediaWiki\Page\Hook\ArticleParserOptionsHook,
    \MediaWiki\Page\Hook\ArticlePageDataBeforeHook,
    \MediaWiki\Hook\LinksUpdateCompleteHook
{
    public function __construct(
        private readonly Config $config,
        private readonly TranslateHelper $helper,
        private readonly IConnectionProvider $database
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

    /**
     * After running a LinksUpdate, run an INSERT on page_props table to disable search engine indexing 
     * 
     * @param LinksUpdate $linksUpdate
     * @param mixed $ticket The prior result of LBFactory::getEmptyTransactionTicket()
     * @return void
     */
    public function onLinksUpdateComplete( $linksUpdate, $ticket ): void {
        $title = $linksUpdate->getTitle();

        // Only check for subpages like '/en'
        if ( !$title->isSubpage() ) {
            return;
        }

        // Check that the page is in the Extension:Translate system
        if ( !$this->helper->getPage( $title ) ) {
            return;
        }

        // We're only skipping indexing English pages, so skip others
        if ( $this->helper->getPageLanguage($title) !== 'en' ) {
            return;
        }

        // Get the primary database for writing with
        $database = $this->database->getPrimaryDatabase();

        // Insert 'noindex' as a page property
        $database->newInsertQueryBuilder()
            ->table( 'page_props' )
            ->row( [
                'pp_page' => $title->getId(),
                'pp_propname' => 'noindex',
                'pp_value' => '1'
            ] )
            ->caller( __METHOD__ )
            ->execute();
    }
}