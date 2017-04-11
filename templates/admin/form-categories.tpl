<form method="post" enctype="multipart/form-data" class="sap-form form-horizontal">
    {preventCsrf}

    <input type="hidden" name="id" value="{if iaCore::ACTION_EDIT == $pageAction}{$id}{/if}">

    {if $item[iaCateg::COL_PARENT_ID]}
        {capture name='general' append='fieldset_before'}
            {include 'tree.tpl'}

            <div id="crossed_fieldzone" class="row">
                <label class="col col-lg-2 control-label">
                    {lang key='crossed_categories'} <span class="label label-info" id="crossed-limit">{count($crossed)|default:0}</span><br>
                    <a href="#" class="categories-toggle js-categories-toggle" data-toggle="#tree-crossed">{lang key='open_close'}</a>
                </label>
                <div class="col col-lg-4" style="margin: 8px 0">
                    <div id="crossed-list">
                        {if isset($crossed) && $crossed}
                            {foreach $crossed as $crid => $link}
                                <span data-id="{$crid}">{$link}</span>{if !$link@last}, {/if}
                            {/foreach}
                        {else}
                            <div class="alert alert-info">{lang key='no_crossed_categories'}</div>
                        {/if}
                    </div>

                    <div id="tree-crossed" class="tree categories-tree"{if (isset($crossed) && $crossed) || iaCore::ACTION_EDIT == $pageAction} style="display:none"{/if}></div>
                    <input type="hidden" id="crossed" name="crossed" value="{if isset($crossed) && $crossed}{','|implode:array_keys($crossed)}{elseif isset($smarty.post.crossed)}{$smarty.post.crossed}{/if}">
                </div>
            </div>
            {ia_add_js}
intelli.cid = {$id|default:0}
            {/ia_add_js}
        {/capture}

        {capture name='title' append='field_after'}
            <div id="title_alias" class="row">
                <label for="" class="col col-lg-2 control-label">{lang key='title_alias'} <a href="#" class="js-tooltip" title="{$tooltips.slug_literal}"><i class="i-info"></i></a></label>
                <div class="col col-lg-4">
                    <input type="text" name="title_alias" id="field_title_alias" value="{if isset($item.title_alias)}{$item.title_alias}{/if}">
                    <p class="help-block text-break-word">{lang key='page_url_will_be'}: <span class="text-danger" id="title_url">{$smarty.const.IA_URL}{if isset($item.title_alias) && isset($parent.title_alias)}{$parent.title_alias}{$item.title_alias}/{/if}</span></p>
                </div>
            </div>
        {/capture}

        {$exceptions = []}
    {else}
        <input type="hidden" name="tree_id" value="0">

        {$exceptions = ['meta_description', 'meta_keywords', 'icon']}
    {/if}

    {include 'field-type-content-fieldset.tpl' isSystem=true exceptions=$exceptions}
</form>
{ia_add_media files='tree, js:_IA_URL_modules/directory/js/admin/categories'}