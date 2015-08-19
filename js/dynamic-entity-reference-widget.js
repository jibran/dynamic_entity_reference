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
          var $proxy = $('#' + $autocomplete.attr('id') + '-autocomplete');
          var basePath, basePathParts;
          if (!(basePath = $autocomplete.data('base-autocomplete-path'))) {
            // This is the first time this has run, copy the default value.
            basePath = $proxy.val();
            $autocomplete.data('base-autocomplete-path', basePath);
          }
          $proxy.val(basePath + '/' + $select.val()).removeClass('autocomplete-processed');
          $autocomplete.unbind('keydown').unbind('keyup').unbind('blur');
          Drupal.behaviors.autocomplete.attach($select.parents());
        });
        $selects.change();
      }
    }
  };

})(jQuery);
