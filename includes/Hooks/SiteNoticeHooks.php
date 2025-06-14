<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Extension\TranslateTweaks\Helpers\L10nHtml;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Skin;
use Wikimedia\ObjectCache\WANObjectCache;

class SiteNoticeHooks implements
    \MediaWiki\Hook\SiteNoticeBeforeHook
{
    public function __construct(
        private readonly Config $config,
        private readonly ParserFactory $parserFactory,
        private readonly WANObjectCache $cache,
        private readonly TranslateHelper $helper
    ) {}

    /**
     * @param string $siteNotice The SiteNotice that will be displayed
     * @param Skin   $skin       The current users skin object
     * @return false             Return false to prevent default behavior
     */
    public function onSiteNoticeBefore( &$siteNotice, $skin ) {
        $translated = new TranslatedSiteNotice(
            $this->parserFactory,
            $this->cache,
            $this->helper,
            $skin
        );

        // Update the relative value and return false to prevent default behavior
        $siteNotice = $translated->getSiteNotice();

        // If a translated sitenotice was returned, and we don't have fallback enabled
        return $siteNotice === '' && $this->config->get( 'TranslateTweaksFallbackSitenotice' );
    }
}

class TranslatedSiteNotice {
    public function __construct(
        private readonly ParserFactory $parserFactory,
        private readonly WANObjectCache $cache,
        private readonly TranslateHelper $helper,
        private readonly Skin $skin
    ) {}

    /**
     * Get the sitenotice that should be displayed above the current viewed page
     * 
     * @see Skin::getSiteNotice Slightly modified from the base Skin method
     * @return string HTML plain string of the SiteNotice
     */
    public function getSiteNotice(): string {
        if ( $this->getUser()->isRegistered() ) {
            $siteNotice = $this->getCachedNotice( 'sitenotice' );
        } else {
            $anonNotice = $this->getCachedNotice( 'anonnotice' );
            if ( $anonNotice === false ) {
                $siteNotice = $this->getCachedNotice( 'sitenotice' );
            } else {
                $siteNotice = $anonNotice;
            }
        }
        if ( $siteNotice === false ) {
            $siteNotice = $this->getCachedNotice( 'default' ) ?: '';
        }
        if ( $this->skin->canUseWikiPage() ) {
            $ns = $this->skin->getWikiPage()->getNamespace();
            $nsNotice = $this->getCachedNotice( "namespacenotice-$ns" );
            if ( $nsNotice ) {
                $siteNotice .= $nsNotice;
            }
        }
        if ( $siteNotice !== '' ) {
            $siteNotice = Html::rawElement( 'div', [ 'id' => 'localNotice', 'data-nosnippet' => '' ], $siteNotice );
        }

        return $siteNotice;
    }

    /**
     * Get (and save) the $name'd sitenotice
     * 
     * @see Skin::getCachedNotice() Slightly modified from the base Skin method to Cache using the language code
     * @param string $name The name of the sitenotice that we are saving/fetching
     * @return string|bool The cached sitenotice, or false
     */
    private function getCachedNotice( string $name ): string|bool {
        $config = $this->skin->getConfig();
        $language = $this->getLanguage();

        if ( !$language ) {
            return false;
        }

        // Check if the current Title is a TranslatablePage to cache using '/en', '/fr', '/nl', ...etc
        //   If this is a source page (eg; '/') don't share a cache with the Wiki Language (eg; '/en'),
        //   can lead to odd linking to '/en' pages
        $useLanguageCode = str_ends_with( $this->getTitle()->getPrefixedText(), '/' . $language->getCode() );

        if ( $name === 'default' ) {
            // special case
            $notice = $config->get( MainConfigNames::SiteNotice );
        } else {
            // Create a title object using the name passed in, in the 'interface' namespace
            $title = Title::newFromText( $name, NS_MEDIAWIKI );
            if ( !$title ) {
                return false;
            }

            $page = $this->helper->getPage( $title );
            if ( !$page ) {
                return false;
            }

            // Get the translated content
            $content = $page->getTranslationPage( $useLanguageCode ? $language->getCode() : 'en' )
                ->getPageContent( $this->parserFactory->getInstance() );

            // If empty, return emptystring
            if ( $content->isEmpty() ) {
                return '';
            }

            // Load the wikitext of the translated page
            $notice = $content->getWikitextForTransclusion();
        }

        // If the SiteNotice is empty or undefined
        if ( !$notice ) {
            return false;
        }

        $parsed = $this->cache->getWithSetCallback(
            // Use the extra hash appender to let eg SSL variants separately cache
            // Key is verified with md5 hash of unparsed wikitext
            $this->cache->makeKey(
                $name . ( $useLanguageCode ? '/' . $language->getCode() : '' ),
                $config->get( MainConfigNames::RenderHashAppend ),
                md5( $notice )
            ),
            // TTL in seconds
            600,
            function () use ( $notice ) {
                return $this->getOutput()->parseAsInterface( $notice );
            }
        );

        return L10nHtml::rawElement(
            'div', $language,
            [
                'class' => $name
            ],
            $parsed
        );
    }

    public function getUser(): User {
        return $this->skin->getUser();
    }

    public function getTitle(): ?Title {
        return $this->skin->getTitle();
    }

    public function getLanguage(): ?Language {
        $languageCode = $this->getLanguageCode();
        if ( $languageCode ) {
            return $this->helper->getLanguage( $languageCode );
        }
        return null;
    }

    public function getLanguageCode(): ?string {
        return $this->skin->getLanguageCode()
            ->toBcp47Code();
    }

    public function getOutput(): OutputPage {
        return $this->skin->getOutput();
    }
}