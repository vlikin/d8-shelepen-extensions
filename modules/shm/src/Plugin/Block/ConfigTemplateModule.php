<?php

namespace Drupal\shm\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Language\LanguageManager;

/**
 * Provides a 'ConfigTemplateModule' block.
 *
 * @Block(
 *  id = "config_template_module",
 *  admin_label = @Translation("Config template module"),
 * )
 */
class ConfigTemplateModule extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Component\Uuid\UuidInterface definition.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;


  /**
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;
  /**
   * Constructs a new ConfigTemplateModule object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    UuidInterface $uuid,
    LanguageManager $languageManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->uuid = $uuid;
    $this->languageManager = $languageManager;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uuid'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state)
  {
    $form = parent::blockForm($form, $form_state);
    $form['language_aware'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Language aware'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['language_aware']
    ];
    $form['theme'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Theme'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['theme']
    ];
    $form['file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('file'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['file']
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['theme'] = $form_state->getValue('theme');
    $this->configuration['file'] = $form_state->getValue('file');
    $this->configuration['language_aware'] = $form_state->getValue('language_aware');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $dataFile = drupal_get_path('module', 'shm') . '/data/' . $this->configuration['file'] . '.yml';
    if (!file_exists($dataFile)) {
      $build['content']['#markup'] = $this->t('File not found!');
      return $build;
    }
    $content = file_get_contents($dataFile);
    $data = Yaml::decode($content);
    if ($this->configuration['language_aware']) {
      $languageId = $this->languageManager->getCurrentLanguage()->getId();
      $data = $data[$languageId];
    }
    $build['config_template'] = [
      '#theme' => $this->configuration['theme'],
      '#data' => $data
    ];

    return $build;
  }

}
