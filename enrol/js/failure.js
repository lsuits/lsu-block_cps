(function(){
  $(document).ready(function() {
    var buttonCheck, grab;
    grab = function(buttonName) {
      return $("input[name='" + buttonName + "']");
    };
    buttonCheck = function() {
      var disabled, selected;
      selected = $("input[name^='ids']:checked");
      disabled = selected.length === 0;
      $(grab('reprocess')).attr('disabled', disabled);
      return $(grab('delete')).attr('disabled', disabled);
    };
    $("input[name^='ids']").change(buttonCheck);
    return $("input[name='select_all']").change(function() {
      var selected;
      selected = $(this).attr('checked');
      selected = selected ? selected : false;
      $("input[name^='ids']").attr('checked', selected);
      return buttonCheck();
    });
  });
})();
