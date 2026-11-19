# Analytics

Knowing what people actually do helps a team improve its approach rather than guess. The players report events without
collecting personal data themselves.

## How events are delivered

Each event is:
1. pushed to `window.dataLayer` **if the page already has one** (Google Tag Manager creates it), and
2. dispatched as a DOM event: `document.addEventListener('bbp:event', e => console.log(e.detail))`.

That means GTM, GA4, Matomo and custom scripts can all listen.

## Events

| `event` | When |
|---|---|
| `bbp_ready` | The player loaded and showed its first chapter or clip |
| `bbp_version_select` | The visitor changed country, language or version |
| `bbp_chapter_view` / `bbp_chapter_select` | A chapter was shown automatically / chosen by the visitor |
| `bbp_clip_view` / `bbp_clip_select` | The same for video clips |
| `bbp_play` | Playback started (`bbp_mode: listen_read` when started from the reader) |
| `bbp_complete` | A chapter or clip finished |
| `bbp_reflect_open` | The "Reflect and respond" panel was opened |
| `bbp_share` | A share link was used (`bbp_channel`: `whatsapp`, `telegram`, `facebook`, `copy`) |

Every event also carries: `bbp_player` (`reader`, `audio`, `video`), `bbp_country`, `bbp_bible`, `bbp_fileset`, `bbp_book`, `bbp_chapter`.

## Google Tag Manager (GA4) in four steps
1. Create a **Custom Event** trigger with the regex `^bbp_`.
2. Create **Data Layer Variables** for `bbp_player`, `bbp_bible`, `bbp_book`, `bbp_chapter`, `bbp_channel`.
3. Create a **GA4 Event** tag whose event name is the `{{Event}}` variable, with those variables as parameters.
4. Preview, then publish.

## Questions worth asking
- Which languages and versions are used most, and from which countries?
- Do people finish chapters (`bbp_complete` against `bbp_play`)?
- Where is content shared (`bbp_share` by channel), and does shared traffic return and listen?
- Do people open the reflection panel, and does a changed question change that?

## Privacy
The events contain no names, IP addresses or free text. Even so, your analytics tool and your site may need a consent
notice under local law, and in sensitive regions you may prefer to keep event data aggregated.
