<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use Html;
use Language;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\Hook\SiteNoticeBeforeHook;
use MediaWiki\MainConfigNames;
use OutputPage;
use Skin;
use Title;
use User;
use WANObjectCache;

class SiteNoticeHooks implements SiteNoticeBeforeHook {
    private WANObjectCache $cache;
    private TranslateHelper $helper;

    public function __construct( WANObjectCache $cache, TranslateHelper $helper ) {
        $this -> cache = $cache;
        $this -> helper = $helper;
    }

    /**
     * @param string $siteNotice The SiteNotice that will be displayed
     * @param Skin   $skin       The current users skin object
     * @return false             Return false to prevent default behavior
     */
    public function onSiteNoticeBefore( &$siteNotice, $skin ) {
        $translated = new TranslatedSiteNotice(
            $this -> cache,
            $this -> helper,
            $skin
        );

        // Update the relative value and return false to prevent default behavior
        $siteNotice = $translated -> getSiteNotice();
        return false;
    }
}

class TranslatedSiteNotice {
    private WANObjectCache $cache;
    private TranslateHelper $helper;
    private Skin $skin;

    public function __construct( WANObjectCache $cache, TranslateHelper $helper, Skin $skin ) {
        $this -> cache = $cache;
        $this -> helper = $helper;
        $this -> skin = $skin;
    }

    public function getSiteNotice(): string {
        if ( $this -> getUser() -> isRegistered() ) {
            $siteNotice = $this -> getCachedNotice( 'sitenotice' );
        } else {
            $anonNotice = $this -> getCachedNotice( 'anonnotice' );
            if ( $anonNotice === false ) {
                $siteNotice = $this -> getCachedNotice( 'sitenotice' );
            } else {
                $siteNotice = $anonNotice;
            }
        }
        if ( $siteNotice === false ) {
            $siteNotice = $this -> getCachedNotice( 'default' ) ?: '';
        }
        if ( $this -> skin -> canUseWikiPage() ) {
            $ns = $this -> skin -> getWikiPage() -> getNamespace();
            $nsNotice = $this -> getCachedNotice( "namespacenotice-$ns" );
            if ( $nsNotice ) {
                $siteNotice .= $nsNotice;
            }
        }
        if ( $siteNotice !== '' ) {
            $siteNotice = Html::rawElement( 'div', [ 'id' => 'localNotice', 'data-nosnippet' => '' ], $siteNotice );
        }

        return $siteNotice;
    }

    private function getCachedNotice( string $name ): string|bool {
        $config = $this -> skin -> getConfig();
        $language = $this -> getLanguage();
        if ( !$language ) {
            return false;
        }

        if ( $name === 'default' ) {
            // special case
            $notice = $config -> get( MainConfigNames::SiteNotice );
        } else {
            // Create a title object using the name passed in, in the 'interface' namespace
            $title = Title::newFromText( $name, NS_MEDIAWIKI );
            if ( !$title ) {
                return false;
            }

            $page = $this -> helper -> getPage( $title );
            if ( !$page ) {
                return false;
            }

            // Get the translated content
            $content = $page -> getTranslationPage( $language -> getCode() )
                -> getPageContent();

            // If empty, return emptystring
            if ( $content -> isEmpty() ) {
                return '';
            }

            // Load the wikitext of the translated page
            $notice = $content -> getWikitextForTransclusion();
        }

        // If the SiteNotice is empty or undefined
        if ( !$notice ) {
            return false;
        }

        $parsed = $this -> cache -> getWithSetCallback(
            // Use the extra hash appender to let eg SSL variants separately cache
            // Key is verified with md5 hash of unparsed wikitext
            $this -> cache -> makeKey(
                $name . '/' . $language -> getCode(),
                $config -> get( MainConfigNames::RenderHashAppend ),
                md5( $notice )
            ),
            // TTL in seconds
            600,
            function () use ( $notice ) {
                return $this -> getOutput() -> parseAsInterface( $notice );
            }
        );

        return Html::rawElement(
            'div',
            [
                'class' => $name,
                'lang' => $language -> getHtmlCode(),
                'dir' => $language -> getDir()
            ],
            $parsed
        );
    }

    public function getUser(): User {
        return $this -> skin -> getUser();
    }

    public function getTitle(): ?Title {
        return $this -> skin -> getTitle();
    }

    public function getLanguage(): ?Language {
        $languageCode = $this -> getLanguageCode();
        if ( $languageCode ) {
            return $this -> helper -> getLanguage( $languageCode );
        }
        return null;
    }

    public function getLanguageCode(): ?string {
        return $this -> helper -> getPageLanguage( $this -> getTitle() );
    }

    public function getOutput(): OutputPage {
        return $this -> skin -> getOutput();
    }
}