{if isset($directory_categories_tree)}
    <div class="list-group">
        {foreach $directory_categories_tree as $sideCategory}
            <a class="list-group-item" href="{$sideCategory.link}">{$sideCategory.title}</a>
        {/foreach}
    </div>
{/if}