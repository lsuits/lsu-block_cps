(function() {
  $(document).ready(function() {
    var available, bucket, changed, move_selected, selected;
    $("input[name^='shell_name_']").keyup(function() {
      var id, value;
      value = $(this).val();
      id = $(this).attr("name");
      $("#" + id).text(value);
      return $("input[name='" + id + "_hidden']").val(value);
    });
    $("a[href^='shell_']").click(function() {
      var display, id, name;
      id = $(this).attr("href").split("_")[1];
      name = $("input[name='shell_name_" + id + "']");
      display = $(name).css("display");
      if (display === "none") {
        $(name).css("display", "block");
        $(name).focus();
        $(name).select();
      } else {
        $(name).css("display", "none");
      }
      return false;
    });
    selected = function() {
      return $(":checked").attr("value");
    };
    available = $("select[name^='before']");
    bucket = function() {
      return $("select[name^='shell_" + selected() + "']");
    };
    move_selected = function(from, to, post) {
      return function() {
        var children;
        children = $(from).children(":selected");
        $(to).append(children);
        return post();
      };
    };
    changed = function(select) {
      return function() {
        var compressed, id, toValue, values;
        id = selected();
        values = $("input[name='shell_values_" + id + "']");
        toValue = function(i, child) {
          return $(child).val();
        };
        compressed = $(select).children().map(toValue);
        return values.val($(compressed).toArray().join(","));
      };
    };
    $("input[name='move_right']").click(move_selected(available, bucket(), changed(bucket())));
    $("input[name='move_left']").click(move_selected(bucket(), available, changed(bucket())));
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
        return value;
      } else {
        return true;
      }
    });
  });
}).call(this);
