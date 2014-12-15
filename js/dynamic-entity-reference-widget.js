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
          var entityId = $autocomplete.data('entity-id');
          if (!(basePath = $autocomplete.data('base-autocomplete-path'))) {
            // This is the first time this has run, copy the default value.
            var autocompletePath = $autocomplete.attr('data-autocomplete-path');
            entityId = autocompletePath.substring(autocompletePath.lastIndexOf('/') + 1, autocompletePath.length);
            if ($.isNumeric(entityId)) {
              // By default, the base path contains the default suffix, so cut
              // that off.
              basePath = autocompletePath.substring(0, autocompletePath.lastIndexOf('/'));
              basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);

            }
            else {
              entityId = null;
              // By default, the base path contains the default suffix, so cut
              // that off.
              basePath = autocompletePath.substring(0, autocompletePath.lastIndexOf('/') + 1);
            }
            // Store for subsequent calls.
            $autocomplete.data('base-autocomplete-path', basePath);
            $autocomplete.data('entity-id', entityId);
          }
          if (entityId) {
            $autocomplete.attr('data-autocomplete-path', basePath + $select.val() + '/' + entityId);
          }
          else {
            $autocomplete.attr('data-autocomplete-path', basePath + $select.val());
          }
        });
        $selects.change();
      }
    }
  };

})(jQuery);
