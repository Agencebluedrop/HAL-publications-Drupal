<?php

namespace Drupal\amu_hal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\amu_hal\Controller\AmuHalController;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class AmuHalConfigForm extends ConfigFormBase {
  use StringTranslationTrait;

  /**
   * The controller.
   *
   * @var \Drupal\amu_hal\Controller\AmuHalController
   */
  protected $controller;

  /**
   * Constructs a AmuHalExportForm object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\amu_hal\Controller\AmuHalController $controller
   *   The module controller.
   */
  public function __construct(TranslationInterface $string_translation, AmuHalController $controller) {
    $this->stringTranslation = $string_translation;
    $this->controller        = $controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('amu_hal_controller')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'amu_hal_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form    = parent::buildForm($form, $form_state);
    $config  = $this->config('amu_hal.settings');
    $portals = $this->getPortals();

    $amu_hal_url_ws               = $config->get('amu_hal_url_ws') ? $config->get('amu_hal_url_ws') : 'https://api.archives-ouvertes.fr/';
    $portal                       = $config->get('portal') ? $config->get('portal') : 'inserm';
    $publications_title           = $config->get('publications_title') ? $config->get('publications_title') : 'Publications HAL';
    $publications_user_page_title = $config->get('publications_user_page_title') ? $config->get('publications_user_page_title') : 'Publications de @fullname';
    $show_user_publications       = $config->get('show_user_publications') ? $config->get('show_user_publications') : '';

    $form['api_url'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('URL HAL'),
      '#default_value' => $amu_hal_url_ws,
      '#required'      => TRUE,
      '#disabled'      => TRUE,
    ];

    $form['portals'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Portail voulu'),
      '#default_value' => $portal,
      '#options'       => $portals,
    ];

    $form['show_user_publications'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Afficher la tabulation publications pour les utilisateurs'),
      '#default_value' => [$show_user_publications ? 1 : 0],
      '#options'       => [
        1 => $this->t("Afficher"),
      ],
    ];

    $form['publications_title'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t("Titre de l'onglet des publications dans la page utilisateur"),
      '#description'   => $this->t('ex: Publications HAL'),
      '#default_value' => $publications_title,
      '#required'      => TRUE,
    ];

    $form['publications_user_page_title'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Titre de la page publications dans la page utilisateur'),
      '#description'   => $this->t('ex: Publications de @fullname. <br /><br />
        Motifs de remplacements:<br />
        <ul>
          <li>
            @firstname: Prénom.
          </li>
          <li>
            @lastname: Nom.
          </li>
          <li>
            @fullname: Prénom Nom.
          </li>
          <li>
            @fullname_reversed: Nom Prénom.
          </li>
        </ul>'),
      '#default_value' => $publications_user_page_title,
      '#required'      => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('amu_hal.settings');
    $config->set('amu_hal_url_ws', $form_state->getValue('api_url'));
    $config->set('show_user_publications', $form_state->getValue('show_user_publications'));
    $config->set('portal', $form_state->getValue('portals'));
    $config->set('publications_title', $form_state->getValue('publications_title'));
    $config->set('publications_user_page_title', $form_state->getValue('publications_user_page_title'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'amu_hal.settings',
    ];
  }

  /**
   * Function to get the list of portals from the api.
   *
   * @return array
   *   : List of portals.
   */
  private function getPortals() {
    $portals = [];
    $config  = $this->config('amu_hal.settings');
    $url     = $config->get('amu_hal_url_ws') ? $config->get('amu_hal_url_ws') . 'ref/instance' : 'https://api.archives-ouvertes.fr/ref/instance';
    $result  = $this->controller->amuHalRequest($url, TRUE);

    foreach ($result['response']['docs'] as $value) {
      $portals[$value['code']] = $value['name'];
    }
    return $portals;
  }

}
