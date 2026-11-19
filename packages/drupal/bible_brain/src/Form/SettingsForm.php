<?php

namespace Drupal\bible_brain\Form;

use Drupal\bible_brain\BibleBrainService;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class SettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['bible_brain.settings'];
  }

  public function getFormId() {
    return 'bible_brain_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $c = $this->config('bible_brain.settings');

    $form['connect'] = [
      '#type' => 'details',
      '#title' => $this->t('1. Connect to Bible Brain'),
      '#open' => TRUE,
    ];
    $form['connect']['intro'] = [
      '#markup' => '<p>' . $this->t('Request your free API key at <a href="https://4.dbt.io/api_key/request" target="_blank" rel="noopener noreferrer">4.dbt.io/api_key/request</a>, paste it here and save. The key stays on your server. Visitors never see it. Please follow the Bible Brain license and copyright terms for the content you display.') . '</p>',
    ];
    $form['connect']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $c->get('api_key'),
      '#maxlength' => 128,
      '#attributes' => ['autocomplete' => 'off'],
    ];

    $path = \Drupal::service('extension.list.module')->getPath('bible_brain');
    $generator = Url::fromUserInput('/' . $path . '/generator/generator.html', ['query' => ['proxy' => BibleBrainService::apiBase()]]);
    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('2. Default versions'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['defaults']['help'] = [
      '#markup' => '<p>' . $this->t('Each player opens with this version unless a block says otherwise. To find the codes, open the <a href=":url" target="_blank">embed code generator</a>, choose a country, language and version, and copy the values it shows.', [':url' => (string) $generator->toString()]) . '</p>',
    ];
    foreach (['reader' => $this->t('Bible reader'), 'audio' => $this->t('Audio player'), 'video' => $this->t('Video player')] as $type => $label) {
      $form['defaults'][$type] = ['#type' => 'details', '#title' => $label, '#open' => FALSE];
      foreach (['country' => $this->t('Country code (for example IN)'), 'language' => $this->t('Language code (for example hin)'), 'bible' => $this->t('Bible (version) code (for example HINDOV)'), 'fileset' => $this->t('Edition (fileset ID)')] as $key => $title) {
        $form['defaults'][$type][$key] = [
          '#type' => 'textfield',
          '#title' => $title,
          '#default_value' => $c->get('defaults.' . $type . '.' . $key),
          '#size' => 24,
        ];
      }
    }

    $form['look'] = [
      '#type' => 'details',
      '#title' => $this->t('3. Look, reach and engagement'),
      '#open' => TRUE,
    ];
    $form['look']['accent'] = ['#type' => 'color', '#title' => $this->t('Accent color'), '#default_value' => $c->get('accent') ?: '#7E0E2A'];
    $form['look']['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => ['auto' => $this->t('Follow the visitor device'), 'light' => $this->t('Always light'), 'dark' => $this->t('Always dark')],
      '#default_value' => $c->get('theme'),
    ];
    $form['look']['allowed_countries'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit the country list (optional)'),
      '#default_value' => $c->get('allowed_countries'),
      '#description' => $this->t('Two-letter country codes separated by commas, for example IN, NP, BD, BT, LK, MV. Leave empty to show every country.'),
    ];
    $form['look']['share'] = ['#type' => 'checkbox', '#title' => $this->t('Show share buttons (WhatsApp, Facebook, Telegram, copy link)'), '#default_value' => $c->get('share')];
    $form['look']['listen'] = ['#type' => 'checkbox', '#title' => $this->t('Reader: offer "Listen to this chapter" with verse highlighting when audio exists'), '#default_value' => $c->get('listen')];
    $form['look']['reflect'] = ['#type' => 'checkbox', '#title' => $this->t('Show reflection prompts after listening or watching'), '#default_value' => $c->get('reflect')];
    $form['look']['reflect_prompts'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reflection prompts (one per line, up to 6)'),
      '#default_value' => $c->get('reflect_prompts'),
      '#rows' => 4,
    ];
    $form['look']['cache_hours'] = ['#type' => 'number', '#title' => $this->t('Cache catalog data (hours)'), '#default_value' => $c->get('cache_hours'), '#min' => 1, '#max' => 168];
    $form['look']['cdn_hls'] = ['#type' => 'checkbox', '#title' => $this->t('Load the hls.js video library from jsDelivr (needed for video in Chrome, Firefox and Edge)'), '#default_value' => $c->get('cdn_hls')];

    $form['about'] = [
      '#type' => 'details',
      '#title' => $this->t('About and links'),
      '#open' => FALSE,
    ];
    $links = [
      'Bible Brain' => 'https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain',
      'Developer documentation' => 'https://www.faithcomesbyhearing.com/bible-brain/developer-documentation',
      'API reference' => 'https://www.faithcomesbyhearing.com/bible-brain/api-reference',
      'Request a free API key' => 'https://4.dbt.io/api_key/request',
      'Available content' => 'https://www.faithcomesbyhearing.com/bible-brain-available-content',
      'License' => 'https://www.faithcomesbyhearing.com/bible-brain/license',
      'Terms and conditions' => 'https://www.faithcomesbyhearing.com/bible-brain/terms-conditions',
      'Copyright policy' => 'https://www.faithcomesbyhearing.com/copyright-policy',
    ];
    $items = [];
    foreach ($links as $label => $url) {
      $items[] = ['#markup' => '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $this->t($label) . '</a>'];
    }
    $form['about']['intro'] = ['#markup' => '<p>' . $this->t('Bible Brain Players is a free module developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Scripture content is provided by Bible Brain, a project of Faith Comes By Hearing. Please follow the license and copyright terms of every Bible version you display.') . '</p>'];
    $form['about']['links'] = ['#theme' => 'item_list', '#items' => $items];
    $form['about']['support'] = ['#markup' => '<p>' . $this->t('Questions about your key or about content licenses: support@digitalbibleplatform.com') . '</p>'];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('bible_brain.settings');
    $old_key = (string) $config->get('api_key');
    $key = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $form_state->getValue('api_key'));

    $codes = [];
    foreach (preg_split('/[\s,;]+/', strtoupper((string) $form_state->getValue('allowed_countries'))) as $code) {
      if (preg_match('/^[A-Z]{2}$/', $code)) {
        $codes[] = $code;
      }
    }
    $lines = array_slice(array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $form_state->getValue('reflect_prompts'))))), 0, 6);

    $config
      ->set('api_key', $key)
      ->set('accent', (string) $form_state->getValue('accent'))
      ->set('theme', (string) $form_state->getValue('theme'))
      ->set('allowed_countries', implode(',', array_unique($codes)))
      ->set('share', (bool) $form_state->getValue('share'))
      ->set('listen', (bool) $form_state->getValue('listen'))
      ->set('reflect', (bool) $form_state->getValue('reflect'))
      ->set('cdn_hls', (bool) $form_state->getValue('cdn_hls'))
      ->set('reflect_prompts', implode("\n", $lines))
      ->set('cache_hours', max(1, min(168, (int) $form_state->getValue('cache_hours'))));

    $defaults = $form_state->getValue('defaults');
    foreach (['reader', 'audio', 'video'] as $type) {
      $country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($defaults[$type]['country'] ?? '')));
      $config->set('defaults.' . $type . '.country', substr($country, 0, 2));
      foreach (['language' => 16, 'bible' => 24, 'fileset' => 40] as $k => $max) {
        $config->set('defaults.' . $type . '.' . $k, substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($defaults[$type][$k] ?? '')), 0, $max));
      }
    }
    $config->save();

    if ($key !== $old_key) {
      Cache::invalidateTags(['bible_brain']);
    }
    parent::submitForm($form, $form_state);
  }

}
