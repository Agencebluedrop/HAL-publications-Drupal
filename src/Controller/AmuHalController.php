<?php

namespace Drupal\amu_hal\Controller;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * {@inheritdoc}
 */
class AmuHalController extends ControllerBase
{
  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The tempstore factory.
   *
   * @var Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

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
   * Constructs a AmuHalController object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(TranslationInterface $string_translation, RequestStack $request_stack, ClientInterface $http_client, PrivateTempStoreFactory $temp_store_factory, FormBuilderInterface $form_builder, ConfigFactoryInterface $config_factory, Connection $database)
  {
    $this->stringTranslation = $string_translation;
    $this->requestStack      = $request_stack;
    $this->httpClient        = $http_client;
    $this->tempStore         = $temp_store_factory;
    $this->formBuilder       = $form_builder;
    $this->configFactory     = $config_factory;
    $this->database          = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('string_translation'),
      $container->get('request_stack'),
      $container->get('http_client'),
      $container->get('tempstore.private'),
      $container->get('form_builder'),
      $container->get('config.factory'),
      $container->get('database')
    );
  }

  /**
   * Sends requests & returns the result.
   *
   * @param string $url
   *   : Request url.
   * @param bool $decode
   *   : JSON decoding.
   *
   * @return string
   *   : BibTex formatted string.
   */
  public function amuHalRequest($url, $decode = FALSE)
  {
    $client = $this->httpClient;
    $result = $client->post($url);
    if ($decode == TRUE) {
      return Json::decode($result->getBody()->getContents());
    }
    return $result->getBody()->getContents();
  }

  /**
   * Generate the BibTex document(s).
   *
   * @param string $id
   *   : Doc IDs concatenation.
   */
  public function amuHalBibtex($id)
  {
    $doc_id = explode("&", $id);
    $url    = $this->amuHalDocUrlGenerator($doc_id, 'bibtex');
    $data   = $this->amuHalRequest($url);

    header("Content-type: text/plain");
    header("Content-Disposition: attachment; filename=BibTex.txt");

    print $data;
    exit;
  }

  /**
   * Generates rtf file.
   *
   * @param string $id
   *   : Doc IDs concatenation.
   */
  public function amuHalRtf($id)
  {
    $doc_id  = explode("&", $id);
    $url     = $this->amuHalDocUrlGenerator($doc_id, 'json');
    $data    = $this->amuHalRequest($url, TRUE);
    $dataArr = [];
    foreach ($data['response']['docs'] as $value) {
      $dataArr[] = (array) $value;
    }
    $this->amuHalRtfExport($dataArr);
  }

  /**
   * Populates and downloads the rtf files.
   *
   * @param array $docs
   *   : Doc IDs.
   */
  public function amuHalRtfExport(array $docs)
  {
    $loc        = \Drupal::service('extension.list.module')->getPath('amu_hal') . "/includes/sample.rtf";
    $sample_loc = \Drupal::service('extension.list.module')->getPath('amu_hal') . "/includes/text.rtf";
    $new_rtf    = $this->amuHalPopulateRtf($docs, $loc, $sample_loc);
    $length = strlen($new_rtf);
    $public_path = \Drupal::service('file_system')->realpath('public://');
    $fr         = fopen($public_path . '/amu_hal.rtf', 'w');
    fwrite($fr, $new_rtf);
    fclose($fr);
    header("Content-type: application/rtf");
    header("Content-disposition: inline; filename=amu_hal.rtf");
    header("Content-length: " . $length);

    echo $new_rtf;
    exit;
  }

  /**
   * BibTex mass export function.
   */
  public function amuHalBibtexSession()
  {
    $session = $this->tempStore->get("amu_hal");
    $doc     = $session->get("amu-hal-publication-ids");

    $session->delete('amu-hal-publication-ids');

    $docs = explode("&", $doc);

    $url = $this->amuHalDocUrlGenerator($docs, 'bibtex');

    $data = $this->amuHalRequest($url);

    header("Content-type: text/plain");
    header("Content-Disposition: attachment; filename=BibTex.txt");

    print $data;
    exit;
  }

  /**
   * Rtf mass export function.
   */
  public function amuHalRtfSession()
  {
    $session = $this->tempStore->get("amu_hal");
    $doc     = $session->get('amu-hal-publication-ids');
    $session->delete('amu-hal-publication-ids');

    $docs = explode("&", $doc);
    $url  = $this->amuHalDocUrlGenerator($docs, 'json');

    $data = $this->amuHalRequest($url, TRUE);

    $dataArr = [];

    foreach ($data['response']['docs'] as $value) {
      $dataArr[] = (array) $value;
    }
    $this->amuHalRtfExport($dataArr);
  }

  /**
   * Gets a specific author publications.
   *
   * @param int $user
   *   : UID.
   *
   * @return array|mixed
   *   : Render array.
   */
  public function amuHalAuthorPub($user)
  {
    $database    = $this->database;
    $config      = $this->configFactory->getEditable('amu_hal.settings');
    $export_form = $this->formBuilder->getForm('Drupal\amu_hal\Form\AmuHalExportForm');

    if ($config->get("show_user_publications")[1] == "0") {
      throw new NotFoundHttpException();
    }

    // Query to retrieve a HAL ID from UID.
    $hal_id_query   = $database->query("SELECT field_identifiant_hal_value FROM user__field_identifiant_hal WHERE entity_id = :uid", [':uid' => $user]);
    $hal_id_results = $hal_id_query->fetchAll();
    if ($hal_id_results) {
      $hal_id = $hal_id_results[0]->field_identifiant_hal_value;
      $portal = $config->get('portal') ? $config->get('portal') : '';
      if ($portal) {
        $portal = "$portal/";
      }
      $url  = $config->get('amu_hal_url_ws') . 'search/' . $portal . '?fq=authIdHal_s:' . $hal_id . '&rows=2000&group.field=producedDateY_i&sort=producedDate_tdate%20desc&fl=authLastNameFirstName_s,title_s,journalTitle_s,producedDateY_i,volume_s,issue_s,page_s,publisherLink_s,halId_s,uri_s,files_s,journalSherpaCondition_s,label_xml';
      $data = $this->amuHalRequest($url, TRUE);
      $docs = $data["response"]["docs"];
    }

    return [
      '#theme'    => 'vancouver_no_et_al',
      '#docs'     => $docs,
      '#export'   => $export_form,
      '#display'  => 'teaser',
      '#attached' => [
        'library' => [
          'amu_hal/libs',
        ],
      ],
    ];
  }

  /**
   * Change page title.
   */
  public function amuHalSetTitle($user)
  {
    $database = $this->database;
    $config   = $this->configFactory->getEditable('amu_hal.settings');
    $namequery        = $database->query("SELECT user__field_prenom_hal.field_prenom_hal_value, user__field_nom_hal.field_nom_hal_value FROM user__field_prenom_hal, user__field_nom_hal WHERE user__field_prenom_hal.entity_id = user__field_nom_hal.entity_id AND user__field_prenom_hal.entity_id = :uid", [':uid' => $user]);
    $namequeryResult  = $namequery->fetchAll()[0];
    $first_name       = $namequeryResult->field_prenom_hal_value;
    $last_name        = $namequeryResult->field_nom_hal_value;
    $fullname         = $first_name . ' ' . $last_name;
    $reverse_fullname = $last_name . ' ' . $first_name;

    $title = $config->get('publications_user_page_title', '');

    if (strpos($title, '@firstname')) {
      $title = str_replace('@firstname', $first_name, $title);
    }
    if (strpos($title, '@lastname')) {
      $title = str_replace('@lastname', $last_name, $title);
    }
    if (strpos($title, '@fullname_reversed')) {
      $title = str_replace('@fullname_reversed', $reverse_fullname, $title);
    }
    if (strpos($title, '@fullname')) {
      $title = str_replace('@fullname', $fullname, $title);
    }

    return $title;
  }

  /**
   * Generates a document url depending on a specific output format type.
   *
   * @param array $doc_ids
   *   : Document IDs.
   * @param string $type
   *   : Format type.
   *
   * @return string
   *   : Url.
   */
  public function amuHalDocUrlGenerator(array $doc_ids, $type)
  {
    $config = $this->configFactory->getEditable('amu_hal.settings');
    $url    = $config->get('amu_hal_url_ws') . 'search/' . $config->get('portal') . '/?q=halId_s:';
    $count  = count($doc_ids);

    if ($count == "0") {
      return 0;
    } elseif ($count == "1") {
      $doc_id = $doc_ids[0];
      $url   .= $doc_id;
    } else {
      $url .= "(";
      foreach ($doc_ids as $key => $doc_id) {
        if ($key == $count - 1) {
          $url .= $doc_id;
        } else {
          $url .= "$doc_id+OR+";
        }
      }
      $url .= ")";
    }

    $url .= "&wt=$type&fl=authLastNameFirstName_s,title_s,journalTitle_s,journalPublisher_s,producedDateY_i,volume_s,issue_s,page_s,publisherLink_s,uri_s,files_s&rows=2000";
    $url .= '&sort=producedDate_tdate+desc';
    return $url;
  }

  /**
   * Used top pupoulate the sample rtf files with the publication data.
   *
   * @param array $docs
   *   : Doc values.
   * @param string $doc_file
   *   : The sample.rtf file location.
   * @param string $sample_file
   *   : The text.rtf file location.
   *
   * @return string
   *   : Rtf file as string.
   */
  public function amuHalPopulateRtf(array $docs, $doc_file, $sample_file)
  {
    $keys = [
      '%%authLastNameFirstName_s%%',
      '%%title_s%%',
      '%%uri_s%%',
      '%%journalTitle_s%%',
      '%%producedDateY_i%%',
      '%%volume_s%%',
      '%%issue_s%%',
      '%%page_s%%',
      '%%publisherLink_s%%',
    ];
    $replacements = [
      '\\' => "\\\\",
      '{'  => "\{",
      '}'  => "\}",
    ];

    $document = file_get_contents($doc_file);
    if (!$document) {
      return FALSE;
    }
    foreach ($docs as $vars) {
      $document .= file_get_contents($sample_file);
      if (!$document) {
        return FALSE;
      }
      foreach ($vars as $key => $value) {
        $search = "%%" . $key . "%%";

        switch ($key) {
          case 'authLastNameFirstName_s':
            $concat = "";
            foreach ($value as $k => $v) {
              if ($k == count($value) - 1) {
                $concat .= $v;
              } else {
                $concat .= $v . ", ";
              }
            }
            $value = $concat;
            break;

          case 'issue_s':
            if (!$vars['page_s']) {
              $value = "(" . $value[0] . ").";
            } else {
              $value = "(" . $value[0] . "):";
            }

            break;

          case 'title_s':
            $value = $value[0];
            if ((substr($value, -1) !== ".") && (substr($value, -1) !== "!") && (substr($value, -1) !== "?")) {
              $value .= ".";
            }
            break;

          case 'publisherLink_s':
            $value = " " . $value[0];
            break;

          case 'producedDateY_i':
            if (!$vars['volume_s'] && !$vars['issue_s'] && !$vars['page_s']) {
              $value = " " . $value . ".";
            } else {
              $value = " " . $value . ";";
            }
            break;

          case 'journalTitle_s':
          case 'page_s':
            $value .= ".";
            break;

          case 'volume_s':
            if (!$vars['issue_s'] && !$vars['page_s']) {
              $value .= ".";
            } elseif (!$vars['issue_s']) {
              $value .= ":";
            }
            break;
        }
        $chars = $this->split($value);
        foreach ($chars as $char) {
          if (mb_ord($char) == 160) {
            $value = mb_ereg_replace($char, chr(32), $value);
          }
          if (mb_ord($char) > 833 && mb_ord($char) < 1010) {
            $value = mb_ereg_replace($char, '\\u' . mb_ord($char) . '\\\'3f', $value);
          }
        }
        $value = mb_convert_encoding($value, "Windows-1252", "UTF-8");
        foreach ($replacements as $orig => $replace) {
          $value = str_replace($orig, (string) $replace, $value);
        }
        $document = str_replace((string) $search, (string) $value, $document);
        $document = str_replace("\\\\", '\\', $document);
        $document = str_replace("�", " ", $document);
      }
      $document = str_replace($keys, "", $document);
    }
    $document .= "}";
    return $document;
  }

  /**
   * Generates url to be sent to the api.
   *
   * @param object $config
   *   : Configuration.
   * @param bool $is_rows
   *   : True: URL for number of results only (may be used in pager).
   *
   * @return string
   *   : URL.
   */
  public function amuHalGenerateUrl($config, $is_rows = FALSE)
  {
    $module_config = $this->configFactory->getEditable('amu_hal.settings');
    $url           = $module_config->get('amu_hal_url_ws') . 'search/';
    $portal        = $module_config->get('portal') ? $module_config->get('portal') : '';
    if ($portal) {
      $portal = "$portal/";
    }
    $url .= $portal;
    if ($config['retrieval_method_select'] == 'by_userids') {

      $authidValues = '';

      if ($this->requestStack->getCurrentRequest()->query->get('author')) {
        $tabauthids = explode("+", $this->requestStack->getCurrentRequest()->query->get('author'));
      } else {
        $tabauthids = $this->amuHalIds($config['choices']);
      }

      if (count($tabauthids) > 1) {
        $authidValues = '(' . implode(' OR ', $tabauthids) . ')';
      } else {
        $authidValues = implode('', $tabauthids);
      }

      $url .= '?fq=authIdHal_s:' . urlencode($authidValues);

      if ($this->requestStack->getCurrentRequest()->query->get('year')) {
        $yearsArr = explode("+", $this->requestStack->getCurrentRequest()->query->get('year'));
        if (count($yearsArr) > 1) {
          $years = '(' . implode(' OR ', $yearsArr) . ')';
        } else {
          $years = implode('', $yearsArr);
        }
        $url .= '&fq=producedDateY_i:' . urlencode($years);
      }

      if ($this->requestStack->getCurrentRequest()->query->get('term')) {
        $url .= '&fq=text:' . urlencode($this->requestStack->getCurrentRequest()->query->get('term'));
      }
      if ($is_rows) {
        $url .= '&rows=0';
      } else {
        $url .= '&rows=2000';
      }
    }

    $url .= '&fl=authLastNameFirstName_s,title_s,journalTitle_s,producedDateY_i,volume_s,issue_s,page_s,publisherLink_s,halId_s,uri_s,files_s,journalSherpaCondition_s,label_xml';
    $url .= '&sort=producedDate_tdate+desc';

    return $url;
  }

  /**
   * Function to get the filters values.
   *
   * @param array $config
   *   : Configuration array.
   *
   * @return array
   *   : Filters values.
   */
  public function amuHalGetFilters(array $config)
  {
    $filters = [];

    // Authors IDs and full names from the users profiles.
    $filters['author'] = $this->amuHalIds($config['choices'], TRUE, TRUE);
    // Years from archives ouvertes api.
    $filters['year'] = $this->amuHalApiFacetRequest($config['choices'], 'producedDateY_i', TRUE);
    // None value for single select list authors.
    if ($config['multiple_authors'][1] == 0) {
      $filters['author'] = ["" => $this->t("Auteur")] + $filters['author'];
    }
    // None value for single select list years.
    if ($config['multiple_years'][1] == 0) {
      $filters['year'] = ["" => $this->t("Année")] + $filters['year'];
    }

    return $filters;
  }

  /**
   * Function to get any archives ouvertes api field options list for filters.
   *
   * @param array $choices
   *   : Team IDs.
   * @param string $facet
   *   : Facet id.
   * @param bool $reverse
   *   : True: results will be sorted in descendant order.
   *
   * @return array
   *   : Facets.
   */
  public function amuHalApiFacetRequest(array $choices, $facet, $reverse = FALSE)
  {
    $clean_facets = [];
    // Build the request.
    $url = $this->amuHalFacetsUrl($choices, $facet);
    // Send the request.
    $result = $this->amuHalRequest($url, TRUE);
    // Json_decode does not decode the request correctly,
    // Therefor these few lines of code are needed to fix the array.
    $unclean_facets = $result['facet_counts']['facet_fields'][$facet];

    foreach ($unclean_facets as $key => $value) {
      if ($key % 2 !== 0) {
        continue;
      }
      if ($unclean_facets[$key + 1] != 0) {
        $clean_facets[$value] = $value;
      }
    }

    if ($reverse) {
      arsort($clean_facets);
    } else {
      asort($clean_facets);
    }

    return $clean_facets;
  }

  /**
   * Creates request url for filters options list.
   *
   * @param array $choices
   *   : Team IDs.
   * @param string $field
   *   : Facet id.
   *
   * @return string
   *   : url string.
   */
  public function amuHalFacetsUrl(array $choices, $field)
  {
    $config = $this->configFactory->getEditable('amu_hal.settings');
    $url    = $config->get('amu_hal_url_ws') . 'search/' . $config->get('portal') . '/';

    $tabauthids   = $this->amuHalIds($choices);
    $authidValues = '';

    if (count($tabauthids) > 0) {
      $authidValues = '(' . implode(' OR ', $tabauthids) . ')';
    }

    $url .= '?fq=authIdHal_s:' . urlencode($authidValues);
    $url .= '&rows=0&facet=true&facet.field=' . $field;

    return $url;
  }

  /**
   * Retrieve HAL IDS.
   *
   * @param array $team_ids
   *   : Team IDs.
   * @param bool $plusNames
   *   : True: returns authors HAL IDs and names.
   *
   * @return array
   *   : HAL IDs and names if $plusNames is TRUE.
   */
  public function amuHalIds(array $team_ids = [], $plusNames = FALSE)
  {
    $database = $this->database;
    $config   = $this->configFactory->getEditable('amu_hal.settings');

    // Filtering HAL IDs by Team IDs if $team_ids is not empty.
    if (isset($team_ids) && $team_ids) {
      // Teams column name.
      // $team_column    = $team_machine_name . "_value";
      $team_ids_list  = implode(',', $team_ids);
      $filtered_query = $database->query("SELECT DISTINCT entity_id FROM user__field_equipe_hal WHERE field_equipe_hal_value IN (:team_ids_list)", [':team_ids_list' => $team_ids_list]);
    } else {
      $filtered_query = $database->query("SELECT DISTINCT entity_id FROM user__field_equipe_hal");
    }

    $filtered_records = $filtered_query->fetchAll();

    foreach ($filtered_records as $row) {
      $filter_ids[] = $row->entity_id;
    }

    if (isset($filter_ids) && $filter_ids) {
      $filter = implode(',', $filter_ids);
      $query  = $database->query(
        "SELECT field_identifiant_hal_value, entity_id
        FROM user__field_identifiant_hal
        JOIN users_field_data ON users_field_data.uid = user__field_identifiant_hal.entity_id
        WHERE users_field_data.status = 1
          AND field_identifiant_hal_value <> ''
          AND entity_id IN ($filter)"
      );
    } else {
      $query = $database->query(
        "SELECT field_identifiant_hal_value, entity_id
          FROM user__field_identifiant_hal
          JOIN users_field_data ON users_field_data.uid = user__field_identifiant_hal.entity_id
          WHERE users_field_data.status = 1
            AND field_identifiant_hal_value <> '' "
      );
    }

    $records = $query->fetchAll();

    $all_ids = [];

    foreach ($records as $record) {
      if ($plusNames) {
        $namequery       = $database->query("SELECT user__field_prenom_hal.field_prenom_hal_value, user__field_nom_hal.field_nom_hal_value FROM user__field_prenom_hal, user__field_nom_hal WHERE user__field_prenom_hal.entity_id = user__field_nom_hal.entity_id AND user__field_prenom_hal.entity_id = :id", [':id' => $record->entity_id]);
        $namequeryResult = $namequery->fetchAll()[0];
        $first_name      = $namequeryResult->field_prenom_hal_value;
        $last_name       = $namequeryResult->field_nom_hal_value;
        $name            = $last_name . " " . $first_name;
        $all_ids[str_replace(" ", "", $record->field_identifiant_hal_value)] = $name;
      } else {
        $all_ids[$record->entity_id] = str_replace(" ", "", $record->field_identifiant_hal_value);
      }
    }
    asort($all_ids);
    return $all_ids;
  }

  /**
   * Multi-byte character splitting .
   *
   * @param string $str
   *   : String.
   * @param int $len
   *   : Length.
   *
   * @return array
   *   : Array of characters.
   */
  public function split($str, $len = 1)
  {
    $arr    = [];
    $length = mb_strlen((string) $str, 'UTF-8');
    for ($i = 0; $i < $length; $i += $len) {
      $arr[] = mb_substr((string) $str, $i, $len, 'UTF-8');
    }
    return $arr;
  }
}
