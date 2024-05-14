# Translate Tweaks

This repo is for an Extension for [MediaWiki](https://www.mediawiki.org/wiki/MediaWiki), which adds some additional functionality for the [Translate Extension](https://www.mediawiki.org/wiki/Extension:Translate).

## Hooks Used

- `UserGetLanguageObject`
- `OutputPageAfterGetHeadLinksArray`
- WIP: `TranslatePrefillTranslation`

----

### User Interface Language

This Extension changes the Interface Language for users that are Signed Out (If `$wgULSAnonCanChangeLanguage` from [Universal Language Selector](https://www.mediawiki.org/wiki/Extension:UniversalLanguageSelector) is disabled). The interface language is changed to use that of the pages content. If visiting a `/de` page the interface will change to German, if visiting a `/fr` page the interface will change to French, and so on (To any languages Mediawiki supports). This will mimic the functionality of the `?uselang` query parameter (Without having to specify it)

----

### Alternative links

This extension will add `<link rel="alternate" href="..." hreflang="fr"/>` tags to the `<head></head>` of the page content. This allows for search engines to discover translated versions of pages instead of treating the different pages as entirely different content.
