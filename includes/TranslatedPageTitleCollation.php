<?php

namespace MediaWiki\Extension\TranslateTweaks;

use Collation;
use MalformedTitleException;

/**
 * Sort collation for Collection Pages, will sort Pages by their Translated Title rather than the pages Path
 */
class TranslatedPageTitleCollation extends Collation {
    private TranslateHelper $helper;

    public function __construct( TranslateHelper $helper ) {
        $this -> helper = $helper;
    }

    /**
     * Get the Sort Key for a specific input
     *
     * @param $string
     * @return string
     * @throws MalformedTitleException If a malformed translated title is passed
     */
    public function getSortKey( $string ) {
        // Get the translated title
        [ $language, $sortKey ] = $this -> getSortKeyAndLanguage( $string );

        return $language -> uc( $sortKey );
    }

    /**
     * Create a sort key for the given string, and determine its language
     * 
     * @param $string
     * @return array
     * @throws MalformedTitleException If a malformed translated title is passed
     */
    private function getSortKeyAndLanguage( $string ) {
        // If the string passed in contains a NEWLINE character "\n", the current Category and a display override
        if ( strstr( $string, PHP_EOL ) ) {
            [ $sortKey, $category ] = explode( PHP_EOL, $string, 2 );

            // Determine the language of the given category (Not the override)
            $languageCode = $this -> helper -> getPathLanguage( $category );
        } else {
            // Determine the languageCode from the given path
            [ $languageCode, $translated ] = $this -> getTranslatedTitleAndLanguageCode( $string );

            // Use the text of the Translated Title as the sortKey
            $sortKey = $translated -> getText();
        }

        // Get the Language object for running Localized functions on
        $language = $this -> helper -> getLanguage( $languageCode );

        return [ $language, $sortKey ];
    }

    /**
     * Get the First Letter used for sorted headings
     *
     * @param $string
     * @return string
     * @throws MalformedTitleException
     */
    public function getFirstLetter( $string ) {
        // Copied from UppercaseCollation
        if ( $string[0] == "\0" ) {
            $string = substr( $string, 1 );
        }

        [ $language, $sortKey ] = $this -> getSortKeyAndLanguage( $string );

        // Return the Uppercased first letter using the page language
        return $language -> ucfirst( $language -> firstChar( $sortKey ) );
    }

    /**
     * @param $string
     * @return array
     * @throws MalformedTitleException
     */
    private function getTranslatedTitleAndLanguageCode( $string ) {
        // Determine the languageCode from the given path
        $languageCode = $this -> helper -> getPathLanguage( $string );

        // Parse the given title using the titles language
        $title = $this -> helper -> parseTitle( $string, $languageCode );

        // Get the translated title
        $translated = $this -> helper -> getTranslatedTitle( $title );

        return [ $languageCode, $translated ];
    }
}
