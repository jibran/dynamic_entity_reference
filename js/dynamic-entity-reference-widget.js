/**
 * @file
 * Attaches entity-type selection behaviors to the widget form.
 */

(function ($) {

  "use strict";

  Drupal.behaviors.dynamicEntityReferenceWidget = {
    attach: function (context) {
      var $context = $(context);
      var $selects = $context.find('select.dynamic-entity-reference-entity-type').once('dynamic-entity-reference');
      if ($selects.length) {
        $selects.change(function() {
          var $select = $(this);
          var $autocomplete = $select.parents('.container-inline').find('.form-autocomplete');
          var basePath;
          if (!(basePath = $autocomplete.data('base-autocomplete-path'))) {
            // This is the first time this has run, copy the default value.
            basePath = $autocomplete.attr('data-autocomplete-path');
            // Store for subsequent calls.
            $autocomplete.data('base-autocomplete-path', basePath);
          }
          $autocomplete.attr('data-autocomplete-path', basePath + '/' + $select.val());
        });
        $selects.change();
      }
    }
  };

})(jQuery);
