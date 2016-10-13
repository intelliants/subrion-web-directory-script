<div class="form-group">
	<label>{lang key='keywords'}</label>
	<input type="text" name="keywords" placeholder="{lang key='keywords'}" class="form-control"{if isset($filters.params.keywords)} value="{$filters.params.keywords|escape:'html'}"{/if}>
</div>
<div class="form-group">
	<label>{lang key='category'}</label>
	<select name="c" class="form-control no-js" id="js-l-c">
		<option value="">{lang key='any'}</option>
		{foreach $directoryFiltersCategories as $entry}
			<option value="{$entry.id}"{if isset($filters.params.c) && $filters.params.c == $entry.id} selected{/if}>{$entry.title|escape:'html'}</option>
		{/foreach}
	</select>
</div>
<div class="form-group">
	<label>{lang key='subcategory'}</label>
	<select name="sc" class="form-control no-js" id="js-l-sc" disabled{if !empty($filters.params.sc)} data-value="{$filters.params.sc|intval}"{/if}>
		<option value="">{lang key='any'}</option>
	</select>
</div>
{ia_print_js files='_IA_URL_packages/directory/js/front/filters'}