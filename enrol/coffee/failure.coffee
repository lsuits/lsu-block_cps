$(document).ready ->

    grab = (buttonName) -> $("input[name='" + buttonName + "']")

    buttonCheck = ->
        selected = $("input[name^='ids']:checked")
        disabled = selected.length == 0

        $(grab 'reprocess').attr 'disabled', disabled
        $(grab 'delete').attr 'disabled', disabled

    $("input[name^='ids']").change buttonCheck

    $("input[name='select_all']").change ->
        selected = $(this).attr 'checked'
        selected = if selected then selected else false

        $("input[name^='ids']").attr 'checked', selected
        buttonCheck()
