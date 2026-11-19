<?php

namespace Drupal\bible_brain;

/**
 * Adapter that lets the Bible Brain core use Drupal's cache backend.
 */
class DrupalCache {

  public function get($key) {
    $item = \Drupal::cache()->get('bible_brain:' . $key);
    return $item ? $item->data : NULL;
  }

  public function set($key, $value, $ttl) {
    \Drupal::cache()->set('bible_brain:' . $key, $value, time() + (int) $ttl, ['bible_brain']);
  }

}
