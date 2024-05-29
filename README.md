# Translate Tweaks

This repo is for an Extension for [MediaWiki](https://www.mediawiki.org/wiki/MediaWiki), which adds some additional functionality for the [Translate Extension](https://www.mediawiki.org/wiki/Extension:Translate).

# Modus Operandi

These changes are designed to improve the Quality of Life provided by the [Translate Extension](https://www.mediawiki.org/wiki/Extension:Translate). The Translate Extension itself gives an easy way for translators to provide translations for wikis, generally requiring minimal knowledge of the inner workings of the wiki itself (Eg; it's nuanced templates, css styles, etc).

Many multi-language (or language-separate) wikis suffer from content parity issues. Contributors may not realize that other languages need to be taken into consideration; Translators may not know that a source page has been updated. Updates to a game may have been made, and only one language is active enough to make the changes to their languages pages. In the end the wiki visitors suffer, and ultimately the wiki suffers.

There are some things about the Translate Extension that don't offer a full and complete experience to visitors that don't use the wikis source language, and this Extension is here to rectify that.

# Implementations

## User Interface Language (in [Hooks.php](https://github.com/GStefanowich/MW-TranslateTweaks/blob/main/includes/Hooks/Hooks.php))

This Extension changes the Interface Language for users that are Signed Out (If `$wgULSAnonCanChangeLanguage` from [Universal Language Selector](https://www.mediawiki.org/wiki/Extension:UniversalLanguageSelector) is disabled). The interface language is changed to use that of the pages content. If visiting a `/de` page the interface will change to German, if visiting a `/fr` page the interface will change to French, and so on (To any languages Mediawiki supports). This will mimic the functionality of the `?uselang` query parameter (Without having to specify it)

### Considerations

The [Universal Language Selector](https://www.mediawiki.org/wiki/Extension:UniversalLanguageSelector) (ULS) Extension has built-in functionality to detect a visitors language and output the correct interface language. If your browser is set to `nl`, the interface would be in Dutch.

However, this is often defeated by many means of caching, and is even disabled on MediaWikis own wikis. If every anonymous user sees the same cached version of the page, and the first visitor has their language in German, every subsequent visitor would also see the interface in German. This is terrible for the user experience, as not everyone speaks German!

Instead, we want to show the interface in Dutch to Dutch visitors, and in German to German visitors. Which means we should apply the interface language based on the content language.

### Hooks Used

- `UserGetLanguageObject`

----

## Alternative links (in [Hooks.php](https://github.com/GStefanowich/MW-TranslateTweaks/blob/main/includes/Hooks/Hooks.php))

This extension will add `<link rel="alternate" href="..." hreflang="fr"/>` tags to the `<head></head>` of the page content. This allows for search engines to discover translated versions of pages instead of treating the different pages as entirely different content.

### Hooks Used

- `OutputPageAfterGetHeadLinksArray`

----

## Localized Category Names (in [CategoryHooks.php](https://github.com/GStefanowich/MW-TranslateTweaks/blob/main/includes/Hooks/CategoryHooks.php))

When operating a single-language wiki, namespaces are always in that language. This doesn't change when a wiki become multi-language, namespaces will always be in the primary language and categories just become more complex.

Usually the go-to implementation for applying categories is to append the parser function `{{#translation:}}`, which gives us `[[Category:Cats{{#translation:}}]]`. This will put our main page into `Cats`, `en` into `Cats/en`, `nl` into `Cats/nl`, and so forth.

Every wiki page shows that pages categories at the bottom of the page, so while English users will see nice categories like `Cats` or `Dogs`, `nl` users will see `Cats/nl` or `Dogs/nl`. This isn't helpful in the slightest for users that don't speak the source-language of the wiki (In this case, English).

### Hooks Used

- `OutputPageMakeCategoryLinks`
- `CategoryViewer::generateLink`
- `Collation::factory`

----

## Localized Namespace Titles (in [Hooks.php](https://github.com/GStefanowich/MW-TranslateTweaks/blob/main/includes/Hooks/Hooks.php))

The Translate Extension offers translating sections of the page, but also allows translation administrators to enable translating page titles. This allows your `Bread` page to become the `Brood` page for a translated version. This functionality also works in non-main namespaces as well such as `Category` or `Template`. Translating Template names is purely cosmetic as it doesn't introduce any additional template calls like `{{Brood}}`, but cosmetic is exactly what we want for the `Category` namespace.

With this setting enabled (`true` by default), translators will be required to enter the translated namespace as part of the translation otherwise it will fail. `Category:Animals` won't be able to be translated as `Tiere` but will be required to be saved as `Kategorie:Tiere` (in German).

### Configurations

- `$wgTranslateTweaksForceNamespace`: `true`

### Hooks Used

- `MultiContentSave`

----

## Copy Robot Policies to Translated Pages (in [Hooks.php](https://github.com/GStefanowich/MW-TranslateTweaks/blob/main/includes/Hooks/Hooks.php))

Pages can be configured for robot crawlers using `$wgArticleRobotPolicies`, which outputs a `<meta name="robots" content="..."/>` tag when visiting that page. The array keys are the Pages paths, and don't support any kind of wildcard. So to configure a page and all of its language variants we'd have to specify each one in the array, which can add up and is just a lot of unnecessary work.

### Considerations

Rather than iterating the `ArticleRobotPolicies` config, we're using the `ArticleParserOptions` hook that runs before `Article::generateContentOutput` and `Article::doOutputMetaData`, where the robot meta tag is outputted. Iterating the config option could make a lot of database calls for each entry to get its translated pages, so instead we save by running that database call on an individual page basis.

### Hooks Used

- `ArticleParserOptions`

----

## TODO: Prefilled Translations

Some translations may be considered "Common Translations". Since the Translate Extension breaks down translations into translatable chunks a translator may end up having to translate single word (or small phrased) translations hundreds to thousands of times. Have a game wiki with Item pages where you have the "Description" heading, and your game has 9,000 items? Well that's 9,000 repeat monotonous translations.

Break the repetition, prefill.

### Considerations

The `TranslatePrefillTranslation` hook only prefills translations into an input box when translation work is being performed by a Translator. Translations aren't retroactively applied, so if there are 9,000 duplicate translations, at least 9,000 "Confirm Translation" clicks are required.

When a "Common Translation" is saved, it should store for all matching, unfilled, translation keys. This could cause a lot of work in the Job Queue, and should be carefully avoided from making a mess.

### Hooks Used

- `TranslatePrefillTranslation`

----

## TODO: Page Search

MediaWikis search works using page Titles (The URL Titles). Overriding the base title using any method doesn't override how Titles show up in Search. The `Main Page` will always show up by searching `Main Page/nl` instead of being searchable by the Translated Title.

### Considerations

Functionality for this needs to be deeply investigated, as implementing it outright might confuse regular users. An entirely English user may see `Brood` in the search and thing that it's some unknown item, instead of just being a translated version of `Bread`.

Choices also need to be made about how duplicate titles are handled. If you've got `Wiki` and `Wiki/nl`, "Wiki" is still going to be "Wiki". Do both show up as such? Do we show the title as `Wiki/nl`, `Wiki (Dutch)`/`Wiki (Nederlands)`?

How should a search function? Should only pages in the Users language be searchable?

### Hooks Used

- `ApiOpenSearchSuggest`

Considerations also need to be made for CirrusSearch

----

# Other Considerations

## Passing Translations into Templates

It is common to create Templates that help with formatting (Maybe your wiki has a `{{Quote}}` template). The Translate extension frequently breaks passing translations into Templates due to [how untranslated content is escaped](https://github.com/wikimedia/mediawiki-extensions-Translate/blob/8ef845872821c47bab1977226efa3ab22e43e484/src/PageTranslation/TranslationUnit.php#L211-L215).

When a page is partially translated, the language is set on the html attribute `<html lang="en">`, content that untranslated and not yet available in the language get wrapped in either a **div** or a **span** depending on if the content is inlined or not, as seen in the code below. This allows website crawlers and screen readers to know about a change in language.

```php
if ( $this -> canWrap() && $attributes ) {
    $tag = $this -> isInline() ? 'span' : 'div';
    $content = $this -> isInline() ? $content : "\n$content\n";
    $content = Html::rawElement( $tag, $attributes, $content );
}
```

Since the content is wrapped, untranslated content has changed from something like `{{Quote|Something was said}}` to the wrapped version `{{Quote|<span lang="en" dir="ltr" class="mw-content-ltr">Something was said</span>}}`. This creates an immediate problem: the equal sign (`=`). By using raw HTML elements the equal sign converts our template input parameter from being the `1` parameter, to being the named `<span lang` parameter.

