<?php

namespace MediaWiki\Extension\TranslateTweaks;

use LogicException;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWiki\Config\Config;
use MediaWiki\Language\Language;
use MessageCache;
use MediaWiki\Context\IContextSource;
use MediaWiki\Title\MediaWikiTitleCodec;
use MediaWiki\MediaWikiServices;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;

class TranslateHelper {
    public const SERVICE_NAME = 'ExtTranslateTweaks';
    private array $titleParser = [];

    public function __construct(
        private readonly MediaWikiServices $services,
        private readonly Config $config,
        private readonly MessageCache $messages,
        private readonly LanguageFactory $languages
    ) {}

    /**
     * Get the language object for a given language code
     * 
     * @param string $languageCode
     * @return Language|null
     */
    public function getLanguage( string $languageCode ): ?Language {
        try {

            // Set the interface language to the language code
            return $this->languages->getLanguage( $languageCode );

        } catch (LogicException) {

            // Eat the exception if the language code is invalid, return null
            return null;

        }
    }

    /**
     * Read the language code from a URL request path
     * 
     * @param $path
     * @return string|null
     */
    public function getPathLanguage( $path ): ?string {
        // Get the language code from the message cache
        [ /* Discard */, $language ] = $this->messages->figureMessage( $path );

        // If a language is returned, and it exists in the language factory
        if ( $language && $this->getLanguage( $language ) ) {
            // Set the interface language to the language code
            return $language;
        }

        return null;
    }

    /**
     * Detect the LanguageCode using the page title
     *
     * @param Title $title
     * @return ?string A language code, if the title contains one
     */
    public function getPageLanguage( LinkTarget $title ): ?string {
        return $this->getPathLanguage( $title->getText() );
    }

    /**
     * Get the LanguageCode being used in the provided Context
     *  falls back to the main wiki language if no page is being viewed
     * 
     * @param IContextSource $context
     * @return ?string
     */
    public function getPageLanguageFromContext( IContextSource $context ): ?string {
        $title = $context->getTitle();

        // If the Context isn't on a page (Eg; a script) return the sites Language code
        if ( !$title ) {
            return (string) $this->config->get( MainConfigNames::LanguageCode );
        }

        return $this->getPageLanguage( $title );
    }

    /**
     * Get a Translatable Wiki Page using a link to the page, or null if the given title is not translatable
     * 
     * @param LinkTarget $title
     * @return ?TranslatablePage
     */
    public function getPage( LinkTarget $title ): ?TranslatablePage {
        // TranslatePage::newFromTitle requires a 'Title', 'TitleValue' not allowed
        if ( !( $title instanceof Title ) ) {
            $title = Title::newFromLinkTarget( $title );
        }

        $page = TranslatablePage::newFromTitle( $title );
        if ( $page->getMarkedTag() === null ) {
            $page = TranslatablePage::isTranslationPage( $title );
        }

        if ( $page === false || $page->getMarkedTag() === null ) {
            return null;
        }

        $status = $page->getTranslationPercentages();
        if ( !$status ) {
            return null;
        }

        return $page;
    }

    /**
     * Fetch the Translated 
     * 
     * @param LinkTarget $title
     * @return LinkTarget
     */
    public function getTranslatedTitle( LinkTarget $title ): LinkTarget {
        // Get the language of the current page
        $languageCode = $this->getPageLanguage( $title );
        $page = $this->getPage( $title );

        if ( /* If the category exists as a translation */ $page ) {
            $translation = $page->getPageDisplayTitle( $languageCode );

            if ( $translation ) {
                try {
                    // Use the Translation helper to strip the translated namespace of the Translation
                    return $this->parseTitle( $translation, $languageCode, NS_CATEGORY );
                } catch ( MalformedTitleException ) {
                    
                    // In the case that the translated title fails to parse, return the original title
                    return $title;
                    
                }
            }
        }

        return $title;
    }

    /**
     * Parse a string title using the TitleParser of the given Language
     *
     * @param string $title            A raw title string to parse
     * @param string $languageCode     The language code used by the page
     * @param ?int   $defaultNamespace The namespace to use if one is not provided in the $title string
     * @return TitleValue
     * @throws MalformedTitleException
     */
    public function parseTitle( string $title, string $languageCode, ?int $defaultNamespace = null ): LinkTarget {
        $parser = $this->getTitleParser( $languageCode );

        if ( !$parser ) {
            return Title::newFromText( $title, $defaultNamespace );
        }

        return $parser->parseTitle( $title, $defaultNamespace );
    }

    /**
     * Create a TitleParser for a language that isn't for the $wgLanguageCode (The language of the whole wiki)
     *
     * @param string $languageCode
     * @return ?TitleParser
     */
    private function getTitleParser( string $languageCode ): ?TitleParser {
        $parser = $this->titleParser[ $languageCode ] ?? null;

        if ( !$parser ) {
            $language = $this->getLanguage( $languageCode );

            if ( !$language ) {
                return null;
            }

            $parser = new MediaWikiTitleCodec(
                $language,
                $this->services->getGenderCache(), // TODO: Recreate the global GenderCache? Not currently needed, but the global GenderCache has it's own $language
                [], // Currently don't need local interwiki links, so empty
                $this->services->getInterwikiLookup(),
                $this->services->getNamespaceInfo()
            );
        }

        return $parser;
    }
}