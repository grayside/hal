<?php

/**
 * @file
 * Contains \Drupal\hal\Normalizer\HalRestWSFormatHal.
 */


class HalRestWSFormatHal extends RestWSBaseFormat {
  public function serialize($values) {
    $values['_links'] = array();
    $values['_embedded'] = array();
    if (isset($values['list']) && is_array($values['list'])) {
      foreach ($values['list'] as $key => $entity) {
        $entity = $this->injectLinks($entity);
        $entity = $this->embedReferences($entity);
        $values['list'][$key] = $entity;
      }
    }
    else {
      $values = $this->embedReferences($values);
    }
    $values = $this->injectLinks($values);

    if (empty($values['_links'])) {
      unset($values['_links']);
    }
    if (empty($values['_embedded'])) {
      unset($values['_embedded']);
    }

    return drupal_json_encode($values);
  }

  public function unserialize($properties, $data) {
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
   * Insert Hypermedia links into the HAL '_links' element.
   *
   * @param array $values
   * @return array
   */
  protected function injectLinks($values) {
    $links = array();
    foreach ($this->hypermediaLinks() as $origin => $translated) {
      // Only set the link if a value exists. Regardless, remove the original element.
      if (isset($values[$origin])) {
        $links[$translated] = $this->makeLink($values[$origin]);
      }
      unset($values[$origin]);
    }

    // Convert the feed_nid to a more actionable URL.
    if (isset($links['feed'])) {
      $links['feed_importer'] = url('node/' . $values['_links']['feed_id'], array('absolute' => TRUE));
    }

    // Add a self link based on the current request. It should include any querystring.
    // The alias parameter is used to ensure the alias system does not process the path.
    $links['self'] = $this->makeLink(url(substr($_SERVER['REQUEST_URI'], 1), array('absolute' => TRUE, 'alias' => TRUE)));

    if (!isset($values['_links'])) {
      $values['_links'] = array();
    }
    $values['_links'] += $links;

    return $values;
  }

  /**
   * @param $values
   *
   * @return mixed
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
   *   The hashmap of HAL link options to be merged into the structure.
   *
   * @return array
   */
  protected function makeLink($uri, $options = array()) {
    return array('href' => $uri) + $options;
  }

  /**
   * Data elements to be considered as HAL hypermedia links.
   *
   * This is constructed via simply transcribing appropriate, non-reference data
   * elements. This is broken out from HalRestWSFormatHal::injectLinks() to try
   * some level of intelligent reversal on deserialization.
   */
  protected function hypermediaLinks() {
    return array(
      'edit_url' => 'edit',
      'last' => 'last',
      'first' => 'first',
      'self' => 'self',
      'next' => 'next',
      'feed_nid' => 'feed_importer',
    );
  }
}
