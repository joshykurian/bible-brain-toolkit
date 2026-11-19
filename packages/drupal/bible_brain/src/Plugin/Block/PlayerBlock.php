<?php

namespace Drupal\bible_brain\Plugin\Block;

use Drupal\bible_brain\BibleBrainService;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Places a Bible reader, audio player or video player.
 */
#[Block(
  id: 'bible_brain_player',
  admin_label: new TranslatableMarkup('Bible Brain player'),
  category: new TranslatableMarkup('Bible Brain'),
)]
class PlayerBlock extends BlockBase {

  public function defaultConfiguration() {
    return [
      'player' => 'audio',
      'country' => '',
      'language' => '',
      'bible' => '',
      'fileset' => '',
      'book' => '',
      'chapter' => '',
      'selector' => 1,
      'share' => 'default',
      'reflect' => 'default',
      'listen' => 'default',
      'player_title' => '',
    ];
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $c = $this->configuration;
    $tri = [
      'default' => $this->t('Use the site setting'),
      'yes' => $this->t('Yes'),
      'no' => $this->t('No'),
    ];
    $form['player'] = [
      '#type' => 'select',
      '#title' => $this->t('Player'),
      '#options' => ['audio' => $this->t('Audio player'), 'reader' => $this->t('Bible reader'), 'video' => $this->t('Video player')],
      '#default_value' => $c['player'],
    ];
    $form['country'] = ['#type' => 'textfield', '#title' => $this->t('Country code'), '#default_value' => $c['country'], '#size' => 4, '#maxlength' => 2, '#description' => $this->t('Two letters, for example IN. Leave empty to use the site default.')];
    $form['language'] = ['#type' => 'textfield', '#title' => $this->t('Language code'), '#default_value' => $c['language'], '#size' => 8, '#description' => $this->t('For example hin.')];
    $form['bible'] = ['#type' => 'textfield', '#title' => $this->t('Bible (version) code'), '#default_value' => $c['bible'], '#size' => 16, '#description' => $this->t('For example HINDOV.')];
    $form['fileset'] = ['#type' => 'textfield', '#title' => $this->t('Edition (fileset ID)'), '#default_value' => $c['fileset'], '#size' => 24, '#description' => $this->t('Optional. Use the embed code generator that comes with this module to find it.')];
    $form['book'] = ['#type' => 'textfield', '#title' => $this->t('Start at book'), '#default_value' => $c['book'], '#size' => 4, '#description' => $this->t('Optional three-letter code, for example JHN.')];
    $form['chapter'] = ['#type' => 'number', '#title' => $this->t('Start at chapter'), '#default_value' => $c['chapter'], '#min' => 0];
    $form['selector'] = ['#type' => 'checkbox', '#title' => $this->t('Visitors can change country, language and version'), '#default_value' => $c['selector']];
    $form['share'] = ['#type' => 'select', '#title' => $this->t('Share buttons'), '#options' => $tri, '#default_value' => $c['share']];
    $form['reflect'] = ['#type' => 'select', '#title' => $this->t('Reflection prompts'), '#options' => $tri, '#default_value' => $c['reflect']];
    $form['listen'] = ['#type' => 'select', '#title' => $this->t('Listen while reading (reader only)'), '#options' => $tri, '#default_value' => $c['listen']];
    $form['player_title'] = ['#type' => 'textfield', '#title' => $this->t('Title above the player'), '#default_value' => $c['player_title']];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    foreach (array_keys($this->defaultConfiguration()) as $key) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
  }

  public function build() {
    $c = $this->configuration;
    return BibleBrainService::playerElement([
      'type' => $c['player'],
      'country' => $c['country'],
      'language' => $c['language'],
      'bible' => $c['bible'],
      'fileset' => $c['fileset'],
      'book' => $c['book'],
      'chapter' => $c['chapter'],
      'selector' => !empty($c['selector']),
      'share' => $c['share'],
      'reflect' => $c['reflect'],
      'listen' => $c['listen'],
      'title' => $c['player_title'],
    ]);
  }

}
