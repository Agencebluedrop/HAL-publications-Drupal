<?php

namespace Drupal\amu_hal\Form;

use Drupal\Core\Form\FormBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class AmuHalFiltersForm extends FormBase {
  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Constructs a AmuHalFiltersForm object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(TranslationInterface $string_translation, RequestStack $request_stack, RouteMatchInterface $route_match) {
    $this->stringTranslation = $string_translation;
    $this->requestStack      = $request_stack;
    $this->currentRouteMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('request_stack'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'halFilters';
  }

  /**
   * Filters form.
   *
   * @param array $form
   *   : Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   : From state.
   * @param array $filters
   *   : Filters.
   * @param array $config
   *   : Block configuration.
   *
   * @return array
   *   : Form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $filters = NULL, array $config = NULL) {
    $term   = $this->requestStack->getCurrentRequest()->query->get('term');
    if($this->requestStack->getCurrentRequest()->query->get('author')){
      $author = explode('+', $this->requestStack->getCurrentRequest()->query->get('author'));
    }
    if ($this->requestStack->getCurrentRequest()->query->get('year')) {
      $year = explode('+', $this->requestStack->getCurrentRequest()->query->get('year'));
    }

    // Text filter.
    $form['term'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Mot(s) présent(s) dans le titre, résumé etc.'),
      '#default_value' => $term,
    ];

    // Authors filter.
    $form['author'] = [
      '#type'          => 'select',
      '#id'            => 'edit-author',
      '#multiple'      => $config['multiple_authors'][1] == "1" ? TRUE : FALSE,
      '#title'         => $this->t('Auteur(s)'),
      '#default_value' => isset($author) ? $author : "",
      '#options'       => $filters['author'],
    ];

    // Years filter.
    $form['year'] = [
      '#type'          => 'select',
      '#id'            => 'edit-year',
      '#multiple'      => $config['multiple_years'][1] == "1" ? TRUE : FALSE,
      '#title'         => $this->t('Année(s)'),
      '#default_value' => isset($year) ? $year : "",
      '#options'       => $filters['year'],
    ];

    // Filter button.
    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Rechercher'),
    ];

    // Reset button is only created if filters have already been chosen.
    if ($this->requestStack->getCurrentRequest()->query->get('term') || $this->requestStack->getCurrentRequest()->query->get('author') || $this->requestStack->getCurrentRequest()->query->get('year')) {
      // Reset button.
      $form['reset'] = [
        '#type'   => 'submit',
        '#value'  => $this->t('Réinitialiser'),
        '#submit' => [
          '::amuHalFiltersReset',
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $multiple_authors = $form_state->getBuildInfo()['args'][1]['multiple_authors'][1];
    $multiple_years   = $form_state->getBuildInfo()['args'][1]['multiple_years'][1];

    $options = [];

    if ($form_state->getValue('term')) {
      $term = $form_state->getValue('term');
    }

    if ($form_state->getValue('author')) {
      $authors = "";
      if ($multiple_authors == 0) {
        $authors = $form_state->getValue('author');
      }
      else {
        $last_author = end($form_state->getValue('author'));
        foreach ($form_state->getValue('author') as $author) {
          if ($last_author == $author) {
            $authors .= $author;
          }
          else {
            $authors .= $author . "+";
          }
        }
      }
    }

    if ($form_state->getValue('year')) {
      $years = "";
      if ($multiple_years == "0") {
        $years = $form_state->getValue('year');
      }
      else {
        $last_year = end($form_state->getValue('year'));
        foreach ($form_state->getValue('year') as $year) {
          if ($last_year == $year) {
            $years .= $year;
          }
          else {
            $years .= $year . "+";
          }
        }
      }
    }

    if (isset($term)) {
      $options["query"]["term"] = $term;
    }

    if (isset($authors)) {
      $options["query"]["author"] = $authors;
    }

    if (isset($years)) {
      $options["query"]["year"] = $years;
    }

    $node = $this->currentRouteMatch->getParameter('node');

    if ($node instanceof NodeInterface) {
      $form_state->setRedirect($this->currentRouteMatch->getRouteName(), ['node' => $node->id()], $options);
    }
    else {
      $form_state->setRedirect($this->currentRouteMatch->getRouteName(), [], $options);
    }
  }

  /**
   * Resets all filters.
   *
   * @param array $form
   *   : Form.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   : From state.
   */
  public function amuHalFiltersReset(array $form, FormStateInterface &$form_state) {
    $node = $this->currentRouteMatch->getParameter('node');

    if ($node instanceof NodeInterface) {
      $form_state->setRedirect($this->currentRouteMatch->getRouteName(), ['node' => $node->id()]);
    }
    else {
      $form_state->setRedirect($this->currentRouteMatch->getRouteName());
    }
  }

}
