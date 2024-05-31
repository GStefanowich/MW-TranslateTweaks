<?php

namespace MediaWiki\Extension\TranslateTweaks;

use Collation;

/**
 * Sort collation for Collection Pages, will sort Pages by their Translated Title rather than the pages Path
 */
class TranslatedPageTitleCollation extends Collation {
    private TranslateHelper $helper;

    public function __construct( TranslateHelper $helper ) {
        $this -> helper = $helper;
    }

    public function getSortKey( $string ) {
        // Determine the languageCode from the given path
        $languageCode = $this -> helper -> getPathLanguage( $string );

        // Parse the given title using the titles language
        $title = $this -> helper -> parseTitle( $string, $languageCode );

        // Get the translated title
        $translated = $this -> helper -> getTranslatedTitle( $title );

        return $translated -> getText();
    }

    public function getFirstLetter( $string ) {
        // Determine the languageCode from the given path
        $languageCode = $this -> helper -> getPathLanguage( $string );

        // Parse the given title using the titles language
        $title = $this -> helper -> parseTitle( $string, $languageCode );

        // Get the translated title
        $translated = $this -> helper -> getTranslatedTitle( $title );

        // Get the Language object for running Localized functions on
        $language = $this -> helper -> getLanguage( $languageCode );

        return $language -> ucfirst( $language -> firstChar( $translated -> getText() ) );
    }
}
