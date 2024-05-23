<?php

use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\MediaWikiServices;

return [
    TranslateHelper::SERVICE_NAME => static function(
        MediaWikiServices $services
    ): TranslateHelper {
        return new TranslateHelper(
            $services,
            $services -> getMainConfig(),
            $services -> getMessageCache(),
            $services -> getLanguageFactory()
        );
    }
];