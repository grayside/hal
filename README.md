# HAL

This module is a HAL serializer for Drupal 7. HAL is a particular
[format of JSON](http://stateless.co/hal_specification.html) that emphasizes
principles of REST and Hypermedia. It is the default payload format for Drupal 8
REST module.

This project currently supports the [RestWS module](http://drupal.org/project/restws)
and is willing to accept patches for support of other modules.

## Dependencies

RestWS support by this module depends on https://drupal.org/node/2208745 to allow overriding of
certain format methods on the RestWSBaseFormat class.
