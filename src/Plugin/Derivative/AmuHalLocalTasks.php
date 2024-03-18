<?php

namespace Drupal\amu_hal\Plugin\Derivative;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines dynamic local tasks.
 */
class AmuHalLocalTasks extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a AmuHalLocalTasks object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(TranslationInterface $string_translation, ConfigFactoryInterface $config_factory) {
    $this->stringTranslation = $string_translation;
    $this->configFactory     = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('string_translation'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $config = $this->configFactory->getEditable('amu_hal.settings');
    $title  = $config->get("publications_title");
    
    if (is_array($config->get("show_user_publications"))) {
      $addTab = $config->get("show_user_publications")[1];
    }

    if (isset($addTab) && $addTab == 1) {
      $this->derivatives['amu_hal.user.publications']               = $base_plugin_definition;
      $this->derivatives['amu_hal.user.publications']['title']      = $this->t($title);
      $this->derivatives['amu_hal.user.publications']['route_name'] = 'amu_hal.publications.user';
      $this->derivatives['amu_hal.user.publications']['base_route'] = 'entity.user.canonical';
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
