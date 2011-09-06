(function() {
  $(document).ready(function() {
    return $("#id_save").click(function() {
      var validated, value;
      value = true;
      validated = [];
      $("input[name^='shell_name_']").each(function(index, name) {
        var n, _fn, _i, _len;
        if ($(name).attr('type' === 'hidden')) {
          _fn = function(check) {
            if (check === $(name).val()) {
              return value = false;
            }
          };
          for (_i = 0, _len = validated.length; _i < _len; _i++) {
            n = validated[_i];
            _fn(check);
          }
        }
        return validated.push($(name).val());
      });
      if (!value) {
        $("#split_error").text("Each shell should have a unique name.");
      }
      return value;
    });
  });
}).call(this);
