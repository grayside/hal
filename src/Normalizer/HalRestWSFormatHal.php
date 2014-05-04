<?php

/**
 * @file
 * Contains \Drupal\hal\Normalizer\HalRestWSFormatHal.
 */


class HalRestWSFormatHal extends RestWSBaseFormat {

  /**
   * Pulled in for read operations.
   *
   * @var RestWSResourceControllerInterface
   */
  var $resourceController;

  /**
   * {@inheritdoc}
   */
  public function viewResource($resourceController, $id) {
    $this->resourceController = $resourceController;
    return parent::viewResource($resourceController, $id);
  }

  /**
   * {@inheritdoc}
   */
  public function queryResource($resourceController, $payload) {
    $this->resourceController = $resourceController;
    return parent::queryResource($resourceController, $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function getData($wrapper) {
    $data = parent::getData($wrapper);
    // For items that represent actual Drupal content entities, generate a URL
    // usable by the API. Class type introspection is used by the
    // RestWSBaseFormat::getData() method.
    if (($wrapper instanceof EntityDrupalWrapper) && !isset($data['self'])) {
      $data['self'] = restws_resource_uri($this->resourceController->resource(), $wrapper->getIdentifier());
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function serialize($values) {
    if (isset($values['list']) && is_array($values['list'])) {
      foreach ($values['list'] as $key => $entity) {
        $values['list'][$key] = $this->serializeItem($entity);
      }
      // Convert top-level list links to HAL-structured links.
      $values = $this->injectLinks($values);
    }
    else {
      $values = $this->serializeItem($values);
    }

    return drupal_json_encode($values);
  }

  /**
   * {@inheritdoc}
   */
  public function unserialize($properties, $data) {
    // Place hypermedia links back in their original positions. Drupal 8 does
    // not do this, but we have an alter hook that allows sliding potentially
    // needed data into the _links structure.
    foreach (static::hypermediaLinks() as $original => $serialized) {
      if (!isset($data['_embedded'][$serialized]) && isset($data['_links'][$serialized])) {
        $data[$original] = $data['_links'][$serialized];
      }
    }

    // Remove links from data array.
    unset($data['_links']);

    // Get embedded resources and remove from data array.
    $embedded = array();
    if (isset($data['_embedded'])) {
      $embedded = $data['_embedded'];
      unset($data['_embedded']);
    }
    $data += $embedded;

    $values = drupal_json_decode($data);
    $this->getPropertyValues($values, $properties);
    return $values;
  }


  /**
   * Handles HAL serialization of an individual entity in a HAL response.
   *
   * The method processes a single item, either from the whole response or
   * an individual item from a list.
   *
   * @param $values
   *
   * @return mixed
   */
  protected function serializeItem($values) {
    // Apply the HAL structural handling.
    $values = $this->injectLinks($values);
    $values = $this->embedReferences($values);

    // Remove these elements if they end up unused.
    if (empty($values['_links'])) {
      unset($values['_links']);
    }
    if (empty($values['_embedded'])) {
      unset($values['_embedded']);
    }

    return $values;
  }

  /**
   * Insert general hypermedia links into the HAL '_links' element.
   *
   * @param array $values
   *
   * @throws InvalidArgumentException
   * @return array
   */
  protected function injectLinks($values) {
    $links = array();
    foreach (static::hypermediaLinks() as $origin => $translated) {
      // Only set the link if a value exists. Regardless, remove the original element.
      if (isset($values[$origin])) {
        if (valid_url($values[$origin])) {
          $links[$translated] = $this->makeLink($values[$origin]);
          unset($values[$origin]);
        }
        else {
          throw new InvalidArgumentException(t('The element !origin does not provide a valid URL or path and cannot be used as a hypermedia link.',
            array('!origin' => $origin)), 500);
        }
      }
    }

    $values['_links'] = isset($values['_links']) ? $values['_links'] + $links : $links;
    return $values;
  }

  /**
   * All references should be placed as a link and embed.
   *
   * @param $values
   *
   * @return array
   */
  function embedReferences($values) {
    foreach ($values as $key => $value) {
      if (is_array($value)) {
        if (isset($value['resource'])) {
          $values['_embedded'][$key] = $value;
          $values['_links'][$key] = $this->makeLink($value['uri']);
          unset($values[$key]);
        }
        elseif (isset($value[0]['resource'])) {
          $values['_embedded'][$key] = $value;
          foreach ($value as $item) {
            $values['_links'][$key][] = $this->makeLink($item['uri']);
          }
          unset($values[$key]);
        }
      }
    }

    return $values;
  }

  /**
   * Generate a HAL-style link.
   *
   * @param string $uri
   *   The URL to be linked to.
   * @param array $options
   *   The hashmap of HAL link options to be merged into the structure. This is
   *   not currently in use anywhere but in theory could be used in
   *   hook_restws_response_alter() to pre-create the _links section with some
   *   elements.
   *
   * @return array
   *   The array return value must match the specification for a HAL '_links'
   *   element.
   */
  public static function makeLink($uri, $options = array()) {
    return array('href' => $uri) + $options;
  }

  /**
   * Data elements to be considered as HAL hypermedia links.
   *
   * This is constructed via simply transcribing appropriate, non-reference data
   * elements. This is broken out from HalRestWSFormatHal::injectLinks() to try
   * some level of intelligent reversal on unserialization.
   */
  public static function hypermediaLinks() {
    $items = array(
      'edit_url' => 'edit',
      'last' => 'last',
      'first' => 'first',
      'self' => 'self',
      'next' => 'next',
      'prev' => 'prev',
    );
    drupal_alter('hal_elements', $items);

    return $items;
  }
}
