<form method="post" enctype="multipart/form-data" class="ia-form">
    {preventCsrf}

    {include 'plans.tpl'}

    {capture name='general' append='fieldset_before'}
        {include 'tree.tpl'}

            {if $core.config.listing_crossed}
            <div class="fieldset">
                <div class="fieldset__header limit_label">
                    {lang key='crossed_categories'} <small>{lang key='limit'}: <span class="badge"></span></small>
                </div>
                <div class="fieldset__content">
                    <a href="#" id="change_crossed" class="categories-toggle js-categories-toggle" data-toggle="#tree-crossed">{lang key='open_close'}</a>
                    <div id="tree-crossed" class="tree categories-tree" style="display:none"></div>
                    <input type="hidden" id="crossed_links" name="crossed_links"{if isset($crossed)} value="{','|implode:array_keys($crossed)}"{/if}>

                    <p class="m-t">
                        {if isset($crossed)}
                            {lang key='prev_crossed'}:
                            {foreach $crossed as $entryId => $link}
                                <span class="label label-success js-checked-crossed-node" data-id="{$entryId}">{$link|escape}</span>{if !$link@last} {/if}
                            {/foreach}
                        {/if}
                    </p>
                    <p id="crossed_title" class="gap-top"></p>
                </div>
            </div>
        {/if}
    {/capture}

    {capture append='tabs_after' name='__all__'}
        {include 'captcha.tpl'}

        <div class="fieldset__actions">
            <button type="submit" class="btn btn-primary" name="data-listing">{lang key='save'}</button>
        </div>
    {/capture}

    {ia_hooker name='smartyListingSubmitBeforeFooter'}

    {include 'item-view-tabs.tpl'}
</form>

{$new_plans = $plans|json_encode}

{ia_add_media files='js:_IA_URL_modules/directory/js/front/listings'}
{ia_hooker name='smartyDirectoryListingSubmitAfterJs'}

{ia_add_js}
    $(function()
    {
        var $container = $('#js-plans-list');
        var plan_crossed_link = $("input[name='plan_id']:checked").val();

        if(plan_crossed_link == 0) {
            intelli.config.listing_crossed_limit = {$core.config.listing_crossed_limit};
        } else {
            intelli.config.listing_crossed_limit = getPlanOptionValue(plan_crossed_link);
        }
        $('.limit_label small span').text(intelli.config.listing_crossed_limit);

        var counter = parseInt($('.limit_label small span').text());
        $('input[type="radio"]', $container).on('click', function()
        {
            $('#crossed_title').html('');
            $('#tree-crossed').jstree("deselect_all");
            var plan_id = $(this).val();
            intelli.config.listing_crossed_limit = getPlanOptionValue(plan_id);

            $('.limit_label small span').text(intelli.config.listing_crossed_limit);
            counter = intelli.config.listing_crossed_limit;
        });

        $("#tree-crossed").on(
            "changed.jstree", function(evt, data){
                if(data.action = 'select_node') {
                    $('.limit_label small span').text(counter + data.selected.length)
                }
                if(data.action = 'deselect_node') {
                    $('.limit_label small span').text(counter - data.selected.length)
                }
            }
        );
    });

    function getPlanOptionValue(plan_id) {
        var plans = {$new_plans};
        var option_value = null;

        if(parseInt(plan_id)) {
            options = plans[plan_id]['options'];

            $.each(options, function(i, values) {
                $.each(values, function(k, value) {
                    if(value == 'multiple_cats_limit') {
                        option_value = values['value'];
                    }
                })
            });
        } else {
            option_value = {$core.config.listing_crossed_limit};
        }

        return option_value;
    }
{/ia_add_js}
