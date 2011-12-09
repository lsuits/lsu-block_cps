(function(){
  $(document).ready(function() {
    var params;
    params = {};
    $('.passed').each(function(i, elem) {
      params[$(elem).attr('name')] = $(elem).val();
      return params[$(elem).attr('name')];
    });
    return $.post('', params, function(html) {
      return $('.cps_loading').html(html);
    });
  });
})();
