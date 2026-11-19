<?php

namespace Drupal\bible_brain;

use Drupal\Core\Url;

require_once __DIR__ . '/../lib/BibleBrainCore.php';

/**
 * Builds the Bible Brain core from Drupal settings and renders player elements.
 */
class BibleBrainService {

  protected static $counter = ['reader' => 0, 'audio' => 0, 'video' => 0];

  public static function config() {
    return \Drupal::config('bible_brain.settings');
  }

  public static function core() {
    $c = self::config();
    $client = \Drupal::httpClient();
    $http = function ($url) use ($client) {
      try {
        $r = $client->request('GET', $url, [
          'http_errors' => FALSE,
          'timeout' => 25,
          'headers' => ['Accept' => 'application/json'],
        ]);
        return [$r->getStatusCode(), (string) $r->getBody()];
      }
      catch (\Exception $e) {
        return [0, ''];
      }
    };
    return new \BibleBrain\Core((string) $c->get('api_key'), $http, new DrupalCache(), [
      'cache_hours' => $c->get('cache_hours'),
      'allowed_countries' => $c->get('allowed_countries'),
    ]);
  }

  /**
   * Base URL of the API proxy, ending with a slash.
   */
  public static function apiBase() {
    $url = (string) Url::fromRoute('bible_brain.api', ['endpoint' => 'countries'])->toString();
    return substr($url, 0, -strlen('countries'));
  }

  public static function strings() {
    return [
      'country' => (string) t('Country'),
      'language' => (string) t('Language'),
      'version' => (string) t('Version'),
      'recording' => (string) t('Edition'),
      'book' => (string) t('Book'),
      'chapter' => (string) t('Chapter'),
      'loading' => (string) t('Loading...'),
      'error' => (string) t('Something went wrong. Please try again.'),
      'noversions' => (string) t('No versions were found for this selection.'),
      'noconfig' => (string) t('No default version has been set for this player.'),
      'prev' => (string) t('Previous'),
      'next' => (string) t('Next'),
      'smaller' => (string) t('Smaller text'),
      'larger' => (string) t('Larger text'),
      'speed' => (string) t('Speed'),
      'autoplay' => (string) t('Play the next chapter automatically'),
      'autoclip' => (string) t('Play the next clip automatically'),
      'listen' => (string) t('Listen to this chapter'),
      'clips' => (string) t('Clips'),
      'nowplaying' => (string) t('Now playing'),
      'reflect' => (string) t('Reflect and respond'),
      'share' => (string) t('Share this'),
      'copylink' => (string) t('Copy link'),
      'copied' => (string) t('Link copied'),
      'poweredby' => (string) t('Scripture content provided by Bible Brain (Faith Comes By Hearing).'),
      'chapterlabel' => (string) t('Chapter'),
      'play' => (string) t('Play'),
      'pause' => (string) t('Pause'),
      'back' => (string) t('Back 15 seconds'),
      'forward' => (string) t('Forward 15 seconds'),
      'seek' => (string) t('Seek'),
    ];
  }

  protected static function clean($value, $max) {
    return substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $value), 0, $max);
  }

  protected static function flag($value, $fallback) {
    if ($value === 'yes') {
      return TRUE;
    }
    if ($value === 'no') {
      return FALSE;
    }
    return (bool) $fallback;
  }

  /**
   * Render array for one player.
   *
   * @param array $s
   *   Keys: type, country, language, bible, fileset, book, chapter, selector,
   *   share, reflect, listen (yes|no|default), title.
   */
  public static function playerElement(array $s) {
    $c = self::config();
    $type = (isset($s['type']) && in_array($s['type'], ['reader', 'audio', 'video'], TRUE)) ? $s['type'] : 'audio';

    if (trim((string) $c->get('api_key')) === '') {
      if (\Drupal::currentUser()->hasPermission('administer bible brain')) {
        $link = (string) Url::fromRoute('bible_brain.settings')->toString();
        return [
          '#markup' => '<div class="bbp-notice">' . t('Bible Brain Players needs an API key. <a href=":url">Add your free key in the settings</a>. Only administrators see this message.', [':url' => $link]) . '</div>',
          '#cache' => ['contexts' => ['user.permissions'], 'tags' => ['config:bible_brain.settings']],
        ];
      }
      return ['#cache' => ['contexts' => ['user.permissions'], 'tags' => ['config:bible_brain.settings']]];
    }

    $defaults = $c->get('defaults.' . $type);
    $defaults = is_array($defaults) ? $defaults : [];
    foreach (['country', 'language', 'bible', 'fileset'] as $k) {
      if (empty($s[$k]) && !empty($defaults[$k])) {
        $s[$k] = $defaults[$k];
      }
    }
    self::$counter[$type]++;
    $prompts = array_values(array_filter(array_map('trim', explode("\n", (string) $c->get('reflect_prompts')))));

    $cfg = [
      'type' => $type,
      'uid' => self::$counter[$type],
      'rest' => self::apiBase(),
      'country' => strtoupper(self::clean(isset($s['country']) ? $s['country'] : '', 2)),
      'language' => strtolower(self::clean(isset($s['language']) ? $s['language'] : '', 16)),
      'bible' => self::clean(isset($s['bible']) ? $s['bible'] : '', 24),
      'fileset' => self::clean(isset($s['fileset']) ? $s['fileset'] : '', 40),
      'book' => strtoupper(self::clean(isset($s['book']) ? $s['book'] : '', 4)),
      'chapter' => max(0, (int) (isset($s['chapter']) ? $s['chapter'] : 0)),
      'selector' => !empty($s['selector']),
      'share' => self::flag(isset($s['share']) ? $s['share'] : 'default', $c->get('share')),
      'reflect' => self::flag(isset($s['reflect']) ? $s['reflect'] : 'default', $c->get('reflect')),
      'listen' => $type === 'reader' ? self::flag(isset($s['listen']) ? $s['listen'] : 'default', $c->get('listen')) : FALSE,
      'prompts' => $prompts,
      'title' => isset($s['title']) ? strip_tags((string) $s['title']) : '',
      'i18n' => self::strings(),
    ];

    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c->get('accent')) ? $c->get('accent') : '#7E0E2A';
    $theme = in_array($c->get('theme'), ['auto', 'light', 'dark'], TRUE) ? $c->get('theme') : 'auto';
    $libraries = ['bible_brain/players'];
    if ($c->get('cdn_hls') && $type !== 'reader') {
      $libraries[] = 'bible_brain/hls';
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['bbp', 'bbp-' . $type],
        'data-theme' => $theme,
        'style' => '--bbp-accent:' . $accent . ';',
        'data-bbp' => json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ],
      '#attached' => ['library' => $libraries],
      '#cache' => ['tags' => ['config:bible_brain.settings'], 'contexts' => ['user.permissions']],
    ];
  }

}
