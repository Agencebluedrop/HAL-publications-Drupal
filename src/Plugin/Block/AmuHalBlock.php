<?php

namespace Drupal\amu_hal\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\amu_hal\Controller\AmuHalController;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'HAL Publications' Block.
 *
 * @Block(
 *   id = "hal_publications",
 *   admin_label = @Translation("HAL Publications"),
 *   category = @Translation("AMU HAL"),
 * )
 */
class AmuHalBlock extends BlockBase implements ContainerFactoryPluginInterface
{
  use StringTranslationTrait;

  /**
   * The string translation.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The controller.
   *
   * @var \Drupal\amu_hal\Controller\AmuHalController
   */
  protected $controller;

  /**
   * Constructs a AmuHalBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\amu_hal\Controller\AmuHalController $controller
   *   The module controller.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationInterface $string_translation, FormBuilderInterface $form_builder, ConfigFactoryInterface $config_factory, Connection $database, AmuHalController $controller)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stringTranslation = $string_translation;
    $this->formBuilder       = $form_builder;
    $this->configFactory     = $config_factory;
    $this->database          = $database;
    $this->controller        = $controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('form_builder'),
      $container->get('config.factory'),
      $container->get('database'),
      $container->get('amu_hal_controller')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build()
  {
    // Load configuration.
    $config = $this->getConfiguration();
    // Load filters values.
    $filters = $this->controller->amuHalGetFilters($config);
    // GENERATE API REQUEST URL.
    $url = $this->controller->amuHalGenerateUrl($config);
    // GET RESULTS FROM API.
    $data  = $this->controller->amuHalRequest($url, TRUE);
    $theme = $config['display_mode'];

    if (isset($config['filtres']['1']) && $config['filtres']['1'] == "1") {
      $filters_form = $this->formBuilder->getForm('Drupal\amu_hal\Form\AmuHalFiltersForm', $filters, $config);
    } else {
      $filters_form = NULL;
    }

    $export_form = $this->formBuilder->getForm('Drupal\amu_hal\Form\AmuHalExportForm');

    foreach ($data["response"]["docs"] as &$doc) {
      $xml        = simplexml_load_string($doc["label_xml"]);
      $date       = $xml->text->body->listBibl->biblFull->editionStmt->edition->date[4];
      $embargo    = $this->t("The file is embargoed untill") . " " . $date;
      $today_unix = time();
      if ($date) {
        $date_unix  = strtotime($date);
      }

      $doc["today_unix"] = $today_unix;
      $doc["date_unix"]  = isset($date_unix) ? $date_unix : "";
      $doc["embargo"]    = $embargo;
    }

    return [
      '#theme'   => $theme,
      '#docs'    => $data["response"]["docs"],
      '#display' => 'teaser',
      '#config'  => $config,
      '#filters' => $filters_form,
      '#export'  => $export_form,
      '#attached' => [
        'library' => [
          'amu_hal/libs',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state)
  {
    $form          = parent::blockForm($form, $form_state);
    $config        = $this->getConfiguration();
    $module_config = $this->configFactory->getEditable('amu_hal.settings');
    $database      = $this->database;

    $form['retrieval_method_select'] = [
      '#type'          => 'select',
      '#title'         => $this->t("Méthode d'importation"),
      "#options"       => [
        'by_userids' => $this->t('Publications par équipe (plusieurs auteur(e)(s)'),
      ],
      '#default_value' => $config['retrieval_method_select'],
    ];

    // Array creation to save the options.
    $team_options = [];
    // Query to retrieve team names and IDs.
    $teams_query   = $database->query("SELECT * FROM config WHERE name = 'field.storage.user.field_equipe_hal'");
    $teams_results = $teams_query->fetchAll();
    $teams_results = unserialize($teams_results[0]->data)['settings']['allowed_values'];

    foreach ($teams_results as $team) {
      $team_options[$team['value']] = $team['label'];
    }

    $form['filtres'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Afficher les filtres'),
      '#default_value' => $config["filtres"],
      '#options'       => [
        1 => $this->t("Afficher"),
      ],
    ];

    $form['multiple_authors'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Liste de séléction multiple pour les auteurs'),
      '#states'        => [
        'visible' => [
          ':input[name="filtres"]' => [
            'value' => 1,
          ],
        ],
      ],
      '#default_value' => $config["multiple_authors"],
      '#options'       => [
        1 => "Oui",
      ],
    ];

    $form['multiple_years'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Liste de séléction multiple pour les années'),
      '#states'        => [
        'visible' => [
          ':input[name="filtres"]' => [
            'value' => 1,
          ],
        ],
      ],
      '#default_value' => $config["multiple_years"],
      '#options'       => [
        1 => "Oui",
      ],
    ];

    // Checkbox list for teams.
    $form['choices'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Équipe(s)  sélectionnée(s)'),
      '#states'        => [
        'visible' => [
          ':input[name="retrieval_method_select"]' => [
            'value' => 'by_userids',
          ],
        ],
      ],
      '#default_value' => $config["choices"],
      '#options'       => $team_options,
    ];

    $form['display'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Affichage'),
    ];

    $form['display']['display_mode'] = [
      '#type'          => 'select',
      '#title'         => $this->t("Type d'affichage"),
      '#description'   => $this->t('Academic display est un affichage sous forme de <strong>liste</strong>. Fancy display est un affichage sous forme de cartes colorées.'),
      "#options"       => [
        'vancouver_no_et_al' => $this->t('Vancouver (brackets, no "et al.")'),
      ],
      '#default_value' => $config['display_mode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state)
  {
    parent::blockSubmit($form, $form_state);
    $this->configuration['retrieval_method_select'] = $form_state->getValue('retrieval_method_select');
    $this->configuration['display_mode']            = $form_state->getValue('display')["display_mode"];
    $this->configuration['filtres']                 = $form_state->getValue('filtres');
    $this->configuration['multiple_authors']        = $form_state->getValue('multiple_authors');
    $this->configuration['multiple_years']          = $form_state->getValue('multiple_years');
    $this->configuration['choices']                 = $form_state->getValue('choices');
  }
}
