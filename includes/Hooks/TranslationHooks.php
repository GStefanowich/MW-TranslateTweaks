<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use Article;
use MediaWiki\Config\Config;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;

class TranslationHooks
    implements \MediaWiki\Page\Hook\BeforeDisplayNoArticleTextHook
{
    public function __construct(
        private readonly Config $config,
        private readonly TranslateHelper $helper
    ) {}

    /**
     * On new pages, replace the "You can create this article" text with a link to begin translating
     * 
     * @param Article $article
     * @return void
     */
    public function onBeforeDisplayNoArticleText( $article ): void {
        // TODO: Replace
    }
}