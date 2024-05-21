<?php

namespace MediaWiki\Extension\TranslateTweaks;

use Html;
use Title;
use Config;
use IContextSource;
use OutputPage;
use MessageHandle;
use MediaWiki\MediaWikiServices;
use MediaWiki\Hook\UserGetLanguageObjectHook;
use MediaWiki\Hook\OutputPageAfterGetHeadLinksArrayHook;
//use MediaWiki\Extension\Translate\TranslatorInterface\Aid\PrefillTranslationHook;

class Hooks implements UserGetLanguageObjectHook, OutputPageAfterGetHeadLinksArrayHook {
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
	 * @param User           Object of signed-in or signed-out page viewer
	 * @param string         Ref to the interface code
	 * @param IContextSource Current view context
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
		$language = $this -> helper -> getPageLanguageFromContext($context);

		// If a language code is return (Not null)
		if ( $language ) {
			// Set the interface language to the language code
			$code = $language;
		}
	}

	/**
	 * TODO:
	 *
	 * @param string|null   $translation The current translation
	 * @param MessageHandle $handle      Translation handle
	 */
	public function onTranslatePrefillTranslation( ?string &$translation, MessageHandle $handle ) {
	    $translation = 'Hello World!';
	    //error_log(get_class($handle));
	    return true;
	}

	/**
	 * After the Head has finished generating, get a list of defined languages for a page
	 *   and then add alternative hreflangs for SEO
	 *
	 * @param array[]    $tags   An array of the current head tags
	 * @param PageOutput $output The page being output
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

		$status = $page -> getTranslationPercentages();
		if ( !$status ) {
			return;
		}

		// Get the language code of the wiki
		$source = $this -> config -> get('LanguageCode');

		foreach($status as $code => $perc) {
			// Get the title from the TranslatablePage and not the $title, it'll strip away any existing language code (For appending onto)
			$path = $page -> getTitle() -> getDBkey();

			// Append the language code to path as long as it's not the global wiki language
			if ( $source !== $code ) {
				$path .= '/' . $code;
			}

			// Generate a new title object with the title inside of the title namespace
			$href = Title::makeTitle($title -> getNamespace(), $path);
			$tags[] = Html::rawElement('link', [
				'rel'      => 'alternate',
				'href'     => $href -> getFullURL(),
				'hreflang' => $code
			]);
		}
	}
}
