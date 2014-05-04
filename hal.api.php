<?php
/**
 * @addtogroup hooks
 * @{
 */

/**
 * Modify the list of entity properties to be converted to hypermedia links.
 *
 * Properties listed here will be removed from their existing position in the
 * entity payload and placed in the _links structure.
 *
 * The value of each property must be an absolute URL or path.
 *
 * @param array $items
 *   The key of this array is the key of the entity property to move. The value
 *   is the new key to use in the _links structure.
 */
function hook_hal_hypermedia_alter(&$items) {
  $items['field_canonical_url'] = 'field_canonical_url';
}

/**
 * @} End of "addtogroup hooks".
 */