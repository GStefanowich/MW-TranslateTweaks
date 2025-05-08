<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialHooks implements
    \MediaWiki\Hook\SpecialRandomGetRandomTitleHook,
    \MediaWiki\Hook\SidebarBeforeOutputHook
{
    public function __construct(
        private readonly TranslateHelper $helper
    ) {}

    /**
     * @inheritdoc
     */
    public function onSpecialRandomGetRandomTitle( &$randstr, &$isRedir, &$namespaces, &$extra, &$title ): void {}

    /**
     * @inheritdoc
     */
    public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
        $language = $this->helper->getPageLanguage($skin->getTitle());

        // If the toolbox is in the sidebar (Override for non-english)
        if ( $language !== 'en' && array_key_exists('TOOLBOX', $sidebar) ) {
            $toolbox = &$sidebar['TOOLBOX'];

            // If 'specialpages' is in the toolbox, override the href
            if ( array_key_exists('specialpages', $toolbox) ) {
                $special = &$toolbox['specialpages'];

                $special['href'] = SpecialPage::getTitleFor('Specialpages', 'nl')
                    ->getLocalURL();
            }
        }
    }
}