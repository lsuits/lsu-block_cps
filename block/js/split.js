(function() {
  $(document).ready(function() {
    var available;
    available = $("select[name^='before']");
    return $("#id_save").click(function() {
      var value;
      if (available && $(available).children().length > 0) {
        $("#split_error").text("You must split all sections.");
        return false;
      } else if (available) {
        value = true;
        $("select[name^='shell_']").each(function(index, select) {
          return value = value && $(select).children().length >= 1;
        });
        if (!value) {
          $("#split_error").text("Each shell must have at least one section.");
        }
        return value;
      } else {
        return true;
      }
    });
  });
}).call(this);
