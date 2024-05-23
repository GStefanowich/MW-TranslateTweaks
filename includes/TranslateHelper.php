<?php

namespace MediaWiki\Extension\TranslateTweaks;

use Title;
use TitleParser;
use TitleValue;
use Config;
use Language;
use MessageCache;
use IContextSource;
use MediaWikiTitleCodec;
use MediaWiki\MediaWikiServices;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;

class TranslateHelper {
    public const SERVICE_NAME = 'ExtTranslateTweaks';

    private MediaWikiServices $services;
    private Config $config;
    private MessageCache $messages;
    private LanguageFactory $languages;

    private array $titleParser = [];

    public function __construct(
        MediaWikiServices $services,
        Config $config,
        MessageCache $cache,
        LanguageFactory $languages
    ) {
        $this -> services  = $services;
        $this -> config    = $config;
        $this -> messages  = $cache;
        $this -> languages = $languages;
    }

    public function getLanguage( string $languageCode ): ?Language {
        // Set the interface language to the language code
        return $this -> languages -> getLanguage( $languageCode );
    }

    public function getPathLanguage( $path ): ?string {
        // Get the language code from the message cache
        [ /* Discard */, $language ] = $this -> messages -> figureMessage( $path );

        // If a language is returned, and it exists in the language factory
        if ( $language && $this -> languages -> getLanguage( $language ) ) {
            // Set the interface language to the language code
            return $language;
        }

        return null;
    }

    /**
     * Detect the LanguageCode using the page title
     * @param Title $title
     * @return ?string A language code, if the title contains one
     */
    public function getPageLanguage( LinkTarget $title ): ?string {
        return $this -> getPathLanguage( $title -> getText() );
    }

    public function getPageLanguageFromContext( IContextSource $context ) {
        $title = $context -> getTitle();

        // If the Context isn't on a page (Eg; a script) return the sites Language code
        if ( !$title ) {
            return $this -> config -> get('LanguageCode');
        }

        return $this -> getPageLanguage( $title );
    }

    public function getPage( LinkTarget $title ): ?TranslatablePage {
        // TranslatePage::newFromTitle requires a 'Title', 'TitleValue' not allowed
        if ( !( $title instanceof Title ) ) {
            $title = Title::newFromLinkTarget( $title );
        }

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

    public function getTranslatedTitle( LinkTarget $title ): LinkTarget {
        // Get the language of the current page
        $languageCode = $this -> getPageLanguage( $title );
        $page = $this -> getPage( $title );

        if ( /* If the category exists as a translation */ $page ) {
            $translation = $page -> getPageDisplayTitle( $languageCode );

            if ( $translation ) {
                // Use the Translation helper to strip the translated namespace of the Translation
                return $this -> parseTitle( $translation, $languageCode, NS_CATEGORY );
            }
        }

        return $title;
    }

    /**
     * Parse a string title using the TitleParser of the given Language
     * @param string $title
     * @param string $languageCode
     * @param int $index
     * @return TitleValue
     */
    public function parseTitle( string $title, string $languageCode, ?int $index = null ): LinkTarget {
        $parser = $this -> getTitleParser( $languageCode );

        if ( !$parser ) {
            return Title::newFromText( $title, $index );
        }

        return $parser -> parseTitle( $title, $index );
    }

    /**
     * Create a TitleParser for a language that isn't for the $wgLanguageCode (The language of the whole wiki)
     * @param string $languageCode
     * @return ?TitleParser
     */
    private function getTitleParser( string $languageCode ): ?TitleParser {
        $parser = $this -> titleParser[ $languageCode ] ?? null;

        if ( !$parser ) {
            $language = $this -> languages -> getLanguage( $languageCode );

            if ( !$language ) {
                return null;
            }

            $parser = new MediaWikiTitleCodec(
                $language,
                $this -> services -> getGenderCache(), // TODO: Recreate the global GenderCache? Not currently needed, but the global GenderCache has it's own $language
                [], // Currently don't need local interwiki links, so empty
                $this -> services -> getInterwikiLookup(),
                $this -> services -> getNamespaceInfo()
            );
        }

        return $parser;
    }
}