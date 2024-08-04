<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use MalformedTitleException;
use MediaWiki\Extension\TranslateTweaks\Helpers\L10nHtml;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\User\User;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\User\UserIdentity;
use Status;
use TextContent;
use Title;
use Config;
use IContextSource;
use CommentStoreComment;
use MediaWiki\Hook\UserGetLanguageObjectHook;
use MediaWiki\Hook\OutputPageAfterGetHeadLinksArrayHook;
use MediaWiki\Storage\Hook\MultiContentSaveHook;
//use MediaWiki\Extension\Translate\TranslatorInterface\Aid\PrefillTranslationHook;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use Wikimedia\Message\MessageValue;

class Hooks implements UserGetLanguageObjectHook, OutputPageAfterGetHeadLinksArrayHook, MultiContentSaveHook {
	private Config $config;
	private TranslateHelper $helper;

	public function __construct(
		Config $config,
        TranslateHelper $helper
	) {
		$this -> config = $config;
		$this -> helper = $helper;
	}

	/**
	 * Hook that will set the interface language to the page language if an interface language is not defined
	 *   Operates if UniversalLanguageSelect is disabled for signed-out users, users cannot change the interface language
	 *   the interface will always be in English despite the content being in another language, so this fixes that
	 *
	 * @param User           $user    Object of signed-in or signed-out page viewer
	 * @param string         $code    Ref to the interface code
	 * @param IContextSource $context Current view context
	 */
	public function onUserGetLanguageObject( $user, &$code, $context ) {
		// Check if the UniversalLanguageSelector is Enabled and Allowed for Anonymous Users
		if ( $this -> config -> get('ULSEnable') && $this -> config -> get('ULSAnonCanChangeLanguage') ) {
			return;
		}

		// Check if our user is signed in, if so just use their user language
		if ( $user -> isSafeToLoad() && $user -> isRegistered() ) {
			return;
		}

		// Get the language code from the message cache
		$language = $this -> helper -> getPageLanguageFromContext( $context );

		// If a language code is return (Not null)
		if ( $language ) {
			// Set the interface language to the language code
			$code = $language;
		}
	}

	/**
	 * After the Head has finished generating, get a list of defined languages for a page
	 *   and then add alternative hreflangs for SEO
	 *
	 * @param array[]    $tags   An array of the current head tags
	 * @param OutputPage $output The page being output
	 */
	public function onOutputPageAfterGetHeadLinksArray( &$tags, $output ) {
		$context = $output -> getContext();
		$title = $context -> getTitle();
		if ( !$title ) {
		    return;
		}

		$page = $this -> helper -> getPage( $title );
		if ( !$page ) {
			return;
		}

        // Get the title object of the TranslatablePage (Eg; $title: Main_Page/de -> $localized: Main_page)
		$localized = $page -> getTitle();

        // Get languages variations of the page that are in progress
		$status = $page -> getTranslationPages();
		if ( !$status ) {
			return;
		}

        $wikiLang = $this -> config -> get( MainConfigNames::LanguageCode );
        $isPageLocalizedSource = str_ends_with( $title -> getBaseText(), '/' . $wikiLang );

        if ( !$isPageLocalizedSource ) {
            // Create an alternate link to the root
            $tags[] = L10nHtml::linkTag( $localized, 'alternate', $wikiLang );
        }

		foreach( $status as $path ) {
		    // Get the language code of the given path
            $language = $this -> helper -> getPathLanguage( $path );

            // Create an alternate link to the subpage
			$href = $localized -> getSubpage( $language );

			// Don't add the alternate of the source lang (Unless we're on that page)
			if ( $wikiLang !== $language || $isPageLocalizedSource ) {
                $tags[] = L10nHtml::linkTag( $href, 'alternate', $language);
			}
		}

        // Add the "Default" as the Source page
        $tags[] = L10nHtml::linkTag( $localized, 'alternate', 'x-default' );

        // Define the Canonical URL for a page. If the path is '/en$' and the wiki
        //   uses 'en' the canonical URL will be without the language suffix
        $canonical = $wikiLang === $this -> helper -> getPathLanguage( $title )
            ? $localized : $title;

		$tags[] = L10nHtml::linkTag( $canonical, 'canonical' );
	}

    /**
     * Run verification on saved translations
     *
     * @param RenderedRevision $renderedRevision
     * @param UserIdentity $user
     * @param CommentStoreComment $summary
     * @param $flags
     * @param Status $status
     * @return bool Returns false is the translation is invalid
     */
	public function onMultiContentSave( $renderedRevision, $user, $summary, $flags, $status ) {
	    $record = $renderedRevision -> getRevision();
	    $page = $record -> getPageAsLinkTarget();

	    // Only when saving Translations
	    if ( !$page -> inNamespace(NS_TRANSLATIONS) ) {
	        return true;
	    }

        // When translating Page Titles, we want to ensure the translation has the proper Namespace
        //   $page should match "Translations:[page-path]/Page display title/[language-code]", eg; 'Main page' title is saved at '/wiki/Translations:Main page/Page display title/en'
        if ( preg_match( '/^(.+)\/Page display title(\/[a-z-]+)?$/', $page -> getText() ) ) {

            // If this check is disabled
            if ( !$this -> config -> get('TranslateTweaksForceNamespace') ) {
                return true;
            }

            // If the page being translated is in the ROOT Namespace
            $title = Title::newFromText( $page -> getText() );
            if ( !$title -> inNamespace(NS_MAIN) ) {

                // Get the language code for the saved translation
                $languageCode = $this -> helper -> getPageLanguage( $title );

                $slots = $record -> getSlots();

                foreach ($slots -> getSlots() as $slot) {
                    $content = $slot -> getContent();

                    if ( !$content instanceof TextContent ) {
                        continue;
                    }

                    try {
                        // Parse the translated title to check for the Translated version of the Namespace
                        /*
                         * TODO: Try an alternate way to parse, the MediaWiki built-in way relies on custom namespaces to be already translated in LocalSettings
                         *   Custom namespaces can't use 'MediaWiki:ns-' pages for ad hoc updating.
                         */
                        $translatedTitle = $this -> helper -> parseTitle( $content -> getText(), $languageCode );

                        // If the translated version Namespace doesn't match the English namespace
                        if ( $translatedTitle -> getNamespace() !== $title -> getNamespace() ) {
                            // Get the language so we can check the text of what the prefix should be
                            $language = $this -> helper -> getLanguage( $languageCode );

                            if ( $language ) {
                                // Error out to the user about the change
                                $status -> fatal(new MessageValue('translate-tweaks-bad-namespace-title', [ $language -> getNsText( $title -> getNamespace() ) . ':' ]));

                                return false;
                            }
                        }
                    } catch ( MalformedTitleException ) {
                        // Generic 'Invalid Title' message
                        $status -> fatal(new MessageValue('invalidtitle'));
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
