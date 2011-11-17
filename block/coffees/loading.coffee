$(document).ready ->

    params = {}

    $('.passed').each (i, elem) ->
        params[$(elem).attr 'name'] = $(elem).val()

    $.post '', params, (html) ->
        $('.cps_loading').html(html)
