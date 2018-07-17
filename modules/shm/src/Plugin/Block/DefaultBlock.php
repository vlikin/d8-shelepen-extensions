<?php

namespace Drupal\shm\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a 'DefaultBlock' block.
 *
 * @Block(
 *  id = "shm_header_block",
 *  admin_label = @Translation("Header block"),
 * )
 */
class DefaultBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var string
   */
  private $storageId;

  /**
   * @var Drupal\Component\Uuid\UuidInterface;
   */
  public $uuid;

  /**
   * @var Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  public $tempStoreFactory;

  /**
   * @var Drupal\Core\TempStore\PrivateTempStore
   */
  public $tempStore;

  /**
   * Ajax action to process while the form is building.
   *
   * @var string
   */
  public $ajaxAction;

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uuid'),
      $container->get('user.private_tempstore')
    );
  }

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    UuidInterface $uuid,
    PrivateTempStoreFactory $tempStoreFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->uuid = $uuid;
    $this->tempStoreFactory = $tempStoreFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['content'] = [
      '#theme' => 'shm.header',
      '#var1' => 'var1',
      '#var2' => ['#markup' => 'var2']
    ];

    return $build;
  }

  public function getEmptyLink() {
    return [
      'weight' => 0,
      'link' => [
        'title' =>  [''],
        'anchor' =>  [''],
      ]
    ];
  }

  public function getStorageId() {
    if (!$this->storageId) {
      $this->storageId = 'storageId-' . $this->uuid->generate();
      $this->tempStore = $this->tempStoreFactory->get($this->storageId);
    }

    return $this->storageId;
  }

  public function getStore() {
    if (!$this->tempStore) {
      $this->tempStore = $this->tempStoreFactory->get($this->getStorageId());
    }

    return $this->tempStore;
  }

  public function getLinks() {
    return $this->getStore()->get('links');
  }

  public function setLinks($links) {
    $this->getStore()->set('links', $links);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // Updates storageId if it is passed by the request.
    $values = $form_state->getUserInput();
    if ($values['settings'] && $values['settings']['storage_id']) {
      $this->storageId = $values['settings']['storage_id'];
    }

    // Builds form.
    $form = parent::blockForm($form, $form_state);
    $form['storage_id'] = [
      '#type' => 'hidden',
      '#value' => $this->getStorageId()
    ];
    $links = $this->getLinks();
    if (!$links) {
      $config = $this->getConfiguration();
      if ($config['links']) {
        $links = $config['links'];
      }
      else {
        $linkUuid = $this->uuid->generate();
        $links = [
          'key-' . $linkUuid => $this->getEmptyLink()
        ];
        $this->setLinks($links);
      }
    }
    $form['links'] = [
      '#type' => 'table',
      '#caption' => $this
        ->t('Sample Table'),
      '#header' => ['w', 'd'],
      '#prefix' => '<div id="links-wrapper">',
      '#suffix' => '</div>',
      '#tableselect' => TRUE,
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'order-weight',
        ),
      ),
      '#value_callback' => array($this, 'valueCallback'),
    ];
    foreach($links as $linkUuid => $row) {
      $form['links'][$linkUuid] = [
        '#attributes' => [ 'class' => ['draggable']],
        '#weight' => $row['weight'],
        'weight' => [
          '#type' => 'weight',
          '#title' => t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $row['weight'],
          // Classify the weight element for #tabledrag.
          '#attributes' => array('class' => array('order-weight')),
        ],
        'link' => [
          '#type' => 'container',
          'title' => [
            [
              '#type' => 'textfield',
              '#title' => $this->t('Title'),
              // '#required' => true,
              '#default_value' => $row['link']['title'][0]
            ]
          ],
          'anchor' => [
            [
              '#type' => 'textfield',
              '#title' => $this->t('Anchor'),
              // '#required' => true,
              //'#default_value' => $row['link']['anchor'][0],
              '#default_value' => $linkUuid
            ]
          ],
          'remove' => [
            '#type' => 'submit',
            '#value' => $this->t('Remove'),
            '#name' => $linkUuid,
            '#uuid' => $linkUuid,
            '#submit' => [
              [$this, 'removeOne'],
            ],
            '#ajax' => [
              'callback' => array($this, 'linksFetchCallback'),
              'wrapper' => 'links-wrapper',
            ],
          ]
        ]
      ];
    }
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => [
        [$this, 'addOne'],
      ],
      '#ajax' => [
        'callback' => array($this, 'linksFetchCallback'),
        'wrapper' => 'links-wrapper',
      ],
    ];
    $form_state->setCached(FALSE);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return $input;
  }

  public function addOne(array &$form, FormStateInterface $form_state) {
    $links = $form_state->getValue('settings')['links'];
    if (!empty($links)) {
      $uuid = 'key-' . $this->uuid->generate();
      $links[$uuid] = $this->getEmptyLink();
    }
    $this->setLinks($links);
    $form_state->setRebuild();
  }

  public function linksFetchCallback(array &$form, FormStateInterface $form_state) {
    return $form['settings']['links'];
  }

  public function removeOne(array &$form, FormStateInterface $form_state) {
    $links = $form_state->getValue('settings')['links'];
    $uuid = $form_state->getTriggeringElement()['#uuid'];
    unset($links[$uuid]);
    $this->setLinks($links);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    uasort($values['links'], ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    $this->setConfiguration($values);
  }

}
