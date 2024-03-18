<?php

namespace Drupal\amu_hal\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class AmuHalExportForm extends FormBase {
  use StringTranslationTrait;

  /**
   * The tempstore factory.
   *
   * @var Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * Constructs a AmuHalExportForm object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   */
  public function __construct(TranslationInterface $string_translation, PrivateTempStoreFactory $temp_store_factory) {
    $this->stringTranslation = $string_translation;
    $this->tempStore         = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'halExport';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['publications-ids'] = [
      '#type' => 'hidden',
    ];

    $form['export-bibtex'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Export BibTex'),
      '#id'     => 'export-bibtex',
      '#submit' => [
        '::amuHalBibtexSubmit',
      ],
    ];

    $form['export-rtf'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Export RTF'),
      '#id'     => 'export-rtf',
      '#submit' => [
        '::amuHalRtfSubmit',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return parent::submitForm($form, $form_state);
  }

  /**
   * Rtf button submit handler.
   *
   * @param array $form
   *   : Form.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   : Form state.
   */
  public function amuHalRtfSubmit(array $form, FormStateInterface $form_state) {
    $session = $this->tempStore->get("amu_hal");
    $session->set('amu-hal-publication-ids', $form_state->getValue('publications-ids'));
    $form_state->setRedirect('amu_hal.export.rtf.mass');
  }

  /**
   * BibTex button submit handler.
   *
   * @param array $form
   *   : Form.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   : Form state.
   */
  public function amuHalBibtexSubmit(array $form, FormStateInterface $form_state) {
    $session = $this->tempStore->get("amu_hal");
    $session->set('amu-hal-publication-ids', $form_state->getValue('publications-ids'));
    $form_state->setRedirect('amu_hal.export.bibtex.mass');
  }

}
