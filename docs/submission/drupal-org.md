# Drupal.org

This guide lists the Drupal module as a project on Drupal.org. Rules summarized here come from Drupal.org's [security advisory coverage checklist](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/security-coverage/opting-into/application-checklist) and the [announcement that replaced project applications with an opt-in](https://www.drupal.org/drupalorg/blog/goodbye-project-applications-hello-security-advisory-opt-in). Read the current pages before you start.

## How it works today

* **Anyone with an account can publish a module as a project.** No review is needed to create the project page and put code on it. The module is then visible, but its releases do not yet carry the green "covered by the Drupal Security Team" shield.
* **Security advisory coverage is optional and comes from a one-time review.** A reviewer reads the code for security and for correct use of Drupal's API. After the first approval you can opt in other projects without another review.

## Before you create the project

| Item | Status |
|---|---|
| Licence GPL version 2 or later | Yes. Do not add a `LICENSE` file; Drupal.org adds its own when it packages the module. |
| Contains enough PHP (more than a single hook) | Yes: a controller, a settings form, a block plugin, a service and a cache class. |
| Core compatibility declared | `core_version_requirement: ^10.2 \|\| ^11` in `bible_brain.info.yml`. Test on the versions you claim. |
| No `version:` or `project:` keys in the info file | Correct: Drupal.org adds them when packaging. |
| Third-party libraries not bundled | The module bundles only its own player script (`js/bible-brain-players.js`). It does not bundle hls.js. |
| Coding standards (Drupal and DrupalPractice) | **Not yet run.** Run `phpcs --standard=Drupal,DrupalPractice` and fix the findings (see below). |
| Branch name | Use `1.0.x`, never `master`. |
| Test on a real site | **Not yet done.** This repository could not run Drupal. |

## Steps

1. **Account.** Create a personal account on Drupal.org (one person per account; shared accounts are not allowed for the security opt-in).
2. **Project name.** The machine name is `bible_brain`. Check that no module with that name already exists. For the project title, keep to the rule "the brand is not misleading": for an independent publisher use a title such as "Bible Brain Players" with the sentence "Not an official Faith Comes By Hearing project" at the top of the page. If Faith Comes By Hearing publishes it, say so and remove that sentence.
3. **Create the project.** Choose Add content > Project > Module. Paste the text below. A project page needs a clear description, a link to the documentation and an issue queue.
4. **Push the code.** Drupal.org gives a GitLab repository. Push the contents of `packages/drupal/bible_brain/` as the root of the repository on branch `1.0.x`. The folder must contain `bible_brain.info.yml` at the top.
5. **Run the checks.** Use the GitLab CI template the project provides, and locally:

   ```bash
   composer require --dev drupal/coder
   vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,info,yml modules/custom/bible_brain
   ```
6. **Create a release.** Tag `1.0.0` (or `1.0.0-alpha1` while you are still testing). A release makes the module installable with Composer: `composer require drupal/bible_brain`.
7. **Optional: ask for security advisory coverage.** Follow the opt-in checklist: use a single-person account, mark the project status as "Needs review" only when it is ready, and answer reviewer questions in the issue.

## Project page text (copy and adapt)

**Title:** Bible Brain Players

**Short description:** Bible Reader, Audio Player and Video Player powered by the free Bible Brain API. Visitors choose country, language and version.

**Body:**

> Bible Brain Players adds three mobile-first players to a Drupal site: an audio Bible player, a video player for Gospel films and Bible clips, and a Bible text reader. Visitors choose a country, then a language, then a Bible version. The module is built for communities that listen more than they read, so audio and video come first.
>
> **Features**
> * A block that shows the audio, video or reader player wherever you place it.
> * A server-side proxy keeps the API key private and caches answers.
> * Verse highlighting with audio when the version provides timing.
> * Share buttons (WhatsApp, Telegram, Facebook, copy link), optional reflection prompts and optional analytics events.
> * Right-to-left support and draft interface translations for Hindi, Bengali, Nepali, Sinhala, Urdu and Malayalam.
>
> **Requirements**
> * Drupal 10.2 or 11, PHP 7.4 or newer.
> * A free Bible Brain API key: https://4.dbt.io/api_key/request
>
> **External service.** The module contacts the Bible Brain API (https://4.dbt.io/api, run by Faith Comes By Hearing) from your server to get Bible text, audio and video links. Terms: https://www.faithcomesbyhearing.com/bible-brain/terms-conditions . Each Bible version has its own license; check it before you use a version.
>
> **Installation and configuration:** see the README in the repository.

## Things to expect from a security reviewer

* Output is escaped and routes check input. The proxy accepts a fixed list of endpoints only and never an arbitrary URL.
* The settings form needs the "Administer Bible Brain Players" permission (see `bible_brain.permissions.yml`), and the key is stored in configuration, not printed on pages.
* Configuration storing a secret: some reviewers prefer the Key module or an environment variable for production. Mention in the README how to use `settings.php` overrides if the site needs that.
* The module has not been tested inside Drupal in this repository. Do that, and fix what the tests find, before you ask for a review.
