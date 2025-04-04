<?php

namespace MediaWiki\Extension\TranslateTweaks\Helpers;

use Html;
use Language;
use MediaWiki\Title\Title;

class L10nHtml {
    public static function linkTag( Title $link, string $relationship, ?string $languageCode = null ): string {
        $info = [
            'rel'  => $relationship,
            'href' => $link->getFullURL()
        ];

        if ( $languageCode )
            $info['hreflang'] = $languageCode;

        return Html::rawElement( 'link', $info );
    }

    public static function rawElement( string $tag, Language $language, array $attribs, string $contents = '' ): string {
        $attribs['lang'] = $language->getHtmlCode();
        $attribs['dir']  = $language->getDir();

        return Html::rawElement( $tag, $attribs, $contents );
    }
}