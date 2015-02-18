/**
 * @file
 * Attaches entity-type selection behaviors to the widget form.
 */

(function ($) {

  "use strict";

  Drupal.behaviors.dynamicEntityReferenceWidget = {
    attach: function (context, settings) {
      function dynamicEntityReferenceWidgetSelect(e) {
        var data = e.data;
        var $select = $(data.select);
        var $autocomplete = $select.parents('.container-inline').find('.form-autocomplete');
        var entityTypeId = $select.val();
        $autocomplete.attr('data-autocomplete-path', settings.dynamic_entity_reference[$select[0].name][entityTypeId]);
      }
      Object.keys(settings.dynamic_entity_reference).forEach(function(fieldName){
        var select = 'select[name="' + fieldName + '"]';
        $(select)
          .once('dynamic-entity-reference')
          .on('change', {select: select}, dynamicEntityReferenceWidgetSelect)
          .trigger('change');
      });
    }
  };

})(jQuery);
