<?php

namespace MediaWiki\Extension\TranslateTweaks\Hooks;

use Article;
use MediaWiki\Page\Hook\ArticleParserOptionsHook;
use ParserOptions;
use User;
use Html;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\User\UserIdentity;
use Status;
use TextContent;
use Title;
use Config;
use IContextSource;
use CommentStoreComment;
use MessageHandle;
use MediaWiki\Hook\UserGetLanguageObjectHook;
use MediaWiki\Hook\OutputPageAfterGetHeadLinksArrayHook;
use MediaWiki\Storage\Hook\MultiContentSaveHook;
//use MediaWiki\Extension\Translate\TranslatorInterface\Aid\PrefillTranslationHook;
use MediaWiki\Extension\TranslateTweaks\TranslateHelper;
use Wikimedia\Message\MessageValue;

class Hooks implements UserGetLanguageObjectHook, OutputPageAfterGetHeadLinksArrayHook, MultiContentSaveHook, ArticleParserOptionsHook {
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

		$status = $page -> getTranslationPages();
		if ( !$status ) {
			return;
		}

		foreach( $status as $path ) {
			// Generate a new title object with the title inside of the title namespace
			$href = Title::makeTitle( $title -> getNamespace(), $path );
			$tags[] = Html::rawElement('link', [
				'rel'      => 'alternate',
				'href'     => $href -> getFullURL(),
				'hreflang' => $this -> helper -> getPathLanguage( $path )
			]);
		}
	}

    /**
     * Run verification on saved translations
     * 
     * @param RenderedRevision     $renderedRevision
     * @param UserIdentity         $user
     * @param CommentStoreComment $summary
     * @param $flags
     * @param Status $status
     * @return bool
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

                    // Parse the translated title to check for the Translated version of the Namespace
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
                }
            }
        }

        return true;
    }

    /**
     * After getting the ParserOptions for an Article, check if that Article is in the RobotPolicies, and copy the policy to translated pages
     *   $wgArticleRobotPolicies doesn't support wild cards, and translated pages can really add up. This method only runs once per subpage,
     *   it'll check on translated pages such as "/nl", and check the root page. It won't run during configuration to check the root page and apply to all subpages
     * 
     * @param Article $article
     * @param ParserOptions $opts
     * @return void
     */
    public function onArticleParserOptions( Article $article, ParserOptions $opts ): void {
        global $wgArticleRobotPolicies; // Could use $this -> config but a setter isn't available

        // Convert the translated path to get the root TranslatablePage
        $title = $article -> getTitle();
        $translated = $this -> helper -> getPage( $title );

        // If the Article is a TranslatablePage
        if ( $translated ) {
            // Check the source page if it has a present RobotPolicy
            $source = $translated -> getTitle() -> getText();
            $policy = $wgArticleRobotPolicies[ $source ] ?? null;

            // Apply the policy to the current (translated) article
            if ( $policy ) {
                $wgArticleRobotPolicies[ $title -> getText() ] = $policy;
            }
        }
    }
}
