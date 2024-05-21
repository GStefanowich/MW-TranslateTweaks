<?php

namespace MediaWiki\Extension\TranslateTweaks;

use Title;
use Config;
use MessageCache;
use IContextSource;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;

class TranslateHelper {
    public const SERVICE_NAME = 'ExtTranslateTweaks';

    private Config $config;
    private MessageCache $messages;
    private LanguageFactory $languages;

    public function __construct(
        Config $config,
        MessageCache $cache,
        LanguageFactory $languages
    ) {
        $this -> config    = $config;
        $this -> messages  = $cache;
        $this -> languages = $languages;
    }

    public function getPageLanguage( Title $title ) {
        // Get the language code from the message cache
        [ /* Discard */, $language ] = $this -> messages -> figureMessage( $title -> getText() );

        // If a language is returned, and it exists in the language factory
        if ( $language && $this -> languages -> getLanguage( $language ) ) {
            // Set the interface language to the language code
            return $language;
        }

        return null;
    }

    public function getPage( Title $title ): ?TranslatablePage {
        $page = TranslatablePage::newFromTitle( $title );
        if ( $page -> getMarkedTag() === null ) {
            $page = TranslatablePage::isTranslationPage( $title );
        }

        if ( $page === false || $page -> getMarkedTag() === null ) {
            return null;
        }

        $status = $page -> getTranslationPercentages();
        if ( !$status ) {
            return null;
        }

        return $page;
    }

    public function getPageLanguageFromContext( IContextSource $context ) {
        $title = $context -> getTitle();

        // If the Context isn't on a page (Eg; a script) return the sites Language code
        if ( !$title ) {
            return $this -> config -> get('LanguageCode');
        }

        return $this -> getPageLanguage( $title );
    }
}