<form method="post" enctype="multipart/form-data" class="sap-form form-horizontal">
    {preventCsrf}

    <input type="hidden" id="js-listing-id" value="{if iaCore::ACTION_EDIT == $pageAction}{$id}{/if}">

    {capture name='general' append='fieldset_before'}
        {include 'tree.tpl'}

        {if $core.config.listing_crossed}
            <div id="crossed_fieldzone" class="row">
                <label class="col col-lg-2 control-label">
                    {lang key='crossed_categories'} <span class="label label-info" id="crossed-limit">{$core.config.listing_crossed_limit - count($crossed)|default:0}</span><br>
                    <a href="#" class="categories-toggle js-categories-toggle" data-toggle="#tree-crossed">{lang key='open_close'}</a>
                </label>
                <div class="col col-lg-4" style="margin: 8px 0">
                    <div id="crossed-list">
                        {if $crossed}
                            {foreach $crossed as $crid => $link}
                                <span data-id="{$crid}">{$link|escape}</span>{if !$link@last}, {/if}
                            {/foreach}
                        {else}
                            <div class="alert alert-info">{lang key='no_crossed_categories'}</div>
                        {/if}
                    </div>

                    <div id="tree-crossed" class="tree categories-tree"{if $crossed || iaCore::ACTION_EDIT == $pageAction} style="display: none"{/if}></div>
                    <input type="hidden" id="crossed-links" name="crossed_links" value="{if $crossed}{','|implode:array_keys($crossed)}{elseif isset($smarty.post.crossed_links)}{$smarty.post.crossed_links}{/if}">
                </div>
            </div>
        {/if}
    {/capture}

    {capture name='title' append='field_after'}
        <div id="slug" class="row">
            <label for="field_listing_slug" class="col col-lg-2 control-label">{lang key='slug'} <a href="#" class="js-tooltip" title="{$tooltips.slug_literal}"><i class="i-info"></i></a></label>
            <div class="col col-lg-4">
                <input type="text" name="slug" id="field_listing_slug" value="{if isset($item.slug)}{$item.slug}{/if}">
                <p class="help-block text-break-word">{lang key='page_url_will_be'}: <span class="text-danger" id="title_url">{$smarty.const.IA_URL}{if isset($item.slug) && isset($category.slug)}{$category.slug}{$item.slug}{/if}</span></p>
            </div>
        </div>
    {/capture}

    {capture name='general' append='fieldset_after'}
        <div id="rank" class="row">
            <label class="col col-lg-2 control-label">{lang key='rank'}</label>
            <div class="col col-lg-4">
                <select name="rank" id="field_rank">
                {section name=star loop=6}
                    <option value="{$smarty.section.star.index}"{if isset($item.rank) && $item.rank == $smarty.section.star.index} selected="selected"{/if}>{$smarty.section.star.index}</option>
                {/section}
                </select>
            </div>
        </div>
        {if iaCore::ACTION_EDIT == $pageAction}
            <div id="reported-as-broken" class="row">
                <label class="col col-lg-2 control-label">{lang key='reported_as_broken'}</label>
                <div class="col col-lg-4">
                    {html_radio_switcher name='reported_as_broken' value=$item.reported_as_broken}
                </div>
            </div>
            {if $item.reported_as_broken && isset($item.reported_as_broken_comments) && $item.reported_as_broken_comments}
                <div id="reported-as-broken-comments" class="row">
                    <label class="col col-lg-2 control-label">{lang key='reported_as_broken_comments'}</label>
                    <div class="col col-lg-4">
                        {$item.reported_as_broken_comments|strip_tags|nl2br}
                    </div>
                </div>
            {/if}
        {/if}
    {/capture}

    {ia_hooker name='smartyAdminSubmitItemBeforeFields'}

    {include 'field-type-content-fieldset.tpl' isSystem=true statuses=$statuses}
</form>

{ia_hooker name='smartyAdminSubmitItemBeforeFooter'}
{ia_add_media files='js:_IA_URL_modules/directory/js/admin/listings'}