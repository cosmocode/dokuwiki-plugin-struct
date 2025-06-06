jQuery(function () {
    /* DOKUWIKI:include script/functions.js */
    /* DOKUWIKI:include script/EntryEditor.js */
    /* DOKUWIKI:include script/SchemaEditor.js */
    /* DOKUWIKI:include script/AggregationEditor.js */
    /* DOKUWIKI:include script/InlineEditor.js */
    /* DOKUWIKI:include script/StructFilter.js */
    /* DOKUWIKI:include_once script/vanilla-combobox.js */

    function init() {
        EntryEditor(jQuery('#dw__editform, form.bureaucracy__plugin'));
        SchemaEditor();
        jQuery('div.structaggregationeditor table').each(AggregationEditor);
        InlineEditor(jQuery('div.structaggregation table, #plugin__struct_output table'));
    }

    jQuery(init);

    jQuery(window).on('fastwiki:afterSwitch', function (evt, viewMode, isSectionEdit, prevViewMode) {
        if (viewMode == "edit" || isSectionEdit) {
            EntryEditor(jQuery('#dw__editform, form.bureaucracy__plugin'));
        }
    });
});
