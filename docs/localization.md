# Localization

## What is translated
The interface of the players: labels, buttons, messages and the three reflection questions. **Scripture itself is not
translated here**, it comes from Bible Brain in the language the visitor chooses.

## Bundled languages (draft)

| Code | Language | Direction |
|---|---|---|
| `hi` | Hindi | LTR |
| `bn` | Bengali | LTR |
| `ne` | Nepali | LTR |
| `si` | Sinhala | LTR |
| `ur` | Urdu | RTL |
| `ml` | Malayalam | LTR |

Every file in `shared/i18n/` is marked `"status": "draft - needs native speaker review"`. They were produced without a native
reviewer and may contain unnatural phrasing. Please help review them.

## How the language is chosen
In this order:
1. `data-lang` on the element, or `lang` in the JSON configuration
2. the page's `<html lang="...">` (regional tags like `hi-IN` match `hi`)
3. English

Labels are global on a page, so one interface language per page.

## Overriding single words
Provide only what you want to change:
```html
<script>window.BBP_I18N = { country: "Region", share: "Tell a friend" };</script>
```
or per element: `data-bbp='{"type":"audio", "i18n": {"play":"Listen"} ...}'`. Joomla has an "Interface labels" JSON box in the plugin,
WordPress passes gettext strings.

## Adding or improving a language
1. Copy `shared/i18n/hi.json` to `shared/i18n/<code>.json` (or edit an existing file).
2. Translate each value. Keep the keys unchanged. Keep `"rtl": true` for right-to-left scripts.
3. Replace the three `prompts` with natural, culturally fitting questions, not word-for-word translations.
4. Run `python scripts/build_locales.py` and `python scripts/sync_shared.py`.
5. Run `python scripts/test_all.py`, then open a pull request. Tell us whether you are a native speaker, and remove `draft` from
   `status` only after a native speaker has reviewed it.

Do not use machine translation without review. Scripture-related wording is sensitive: choose the terms that local believers
actually use for God, prayer and church.
