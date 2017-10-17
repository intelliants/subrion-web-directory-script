<div class="ia-item ia-item--{$listing.status}{if $listing.sponsored} ia-item--sponsored{/if}{if $listing.featured} ia-item--featured{/if} has-panel" id="listing-{$listing.id}">
    {if $core.config.directory_enable_thumbshots}
        <div class="ia-item__image">
            <img src="http://free.pagepeeker.com/v2/thumbs.php?size=m&url={$listing.url|escape:url}" class="img-responsive">
            {if $listing.rank}
                {section name=star loop=$listing.rank}<span class="fa fa-star text-warning"></span> {/section}
            {/if}
        </div>
    {/if}

    <div class="ia-item__labels">
        {if $member && $member.id == $listing.member_id && iaCore::STATUS_ACTIVE != $listing.status}
            <span class="label label-{$listing.status}" title="{lang key=$listing.status default=$listing.status}"><span class="fa fa-warning"></span> {lang key=$listing.status default=$listing.status}</span>
        {/if}
        {if $listing.sponsored}<span class="label label-warning" title="{lang key='sponsored'}"><span class="fa fa-star"></span> {lang key='sponsored'}</span>{/if}
        {if $listing.featured}<span class="label label-info" title="{lang key='featured'}"><span class="fa fa-star-o"></span> {lang key='featured'}</span>{/if}
    </div>

    <div class="ia-item__content">
        <div class="ia-item__actions">
            {printFavorites item=$listing itemtype='listings' guests=true}
            {accountActions item=$listing itemtype='listings'}

            <a href="{$listing.link}">{lang key='details'} <span class="fa fa-angle-double-right"></span></a>
        </div>

        <div class="ia-item__title">
            {* if !empty($listing.crossed)}@ {/if*}
            {if !$core.config.directory_redirect_to_site}
                {ia_url type='link' item='listings' data=$listing text=$listing.title}
            {else}
                <a href="{$listing.url}" target="_blank">{$listing.title|escape}</a>
            {/if}
        </div>

        <div class="ia-item__additional">
            <p class="text-overflow"><span class="fa fa-link"></span> <a href="{$listing.url}" class="url">{$listing.url}</a></p>
            {if isset($listing.breadcrumb) && !isset($category) || $listing.category_id != $category.id}
                <div class="clearfix"></div>
                <p>
                    <span class="fa fa-folder-o"></span>
                    {foreach $listing.breadcrumb as $title => $url}
                        {if !$url@first} <span class="fa fa-angle-right"></span> {/if}
                        <a href="{$smarty.const.IA_URL}{$url}">{$title|escape}</a>
                    {/foreach}
                </p>
                <div class="clearfix"></div>
            {/if}
            <p><span class="fa fa-clock-o"></span> {$listing.date_added|date_format}</p>
            <p><span class="fa fa-eye"></span> {$listing.views_num} {lang key='views'}</p>
        </div>

        <p>{$listing.description|strip_tags|truncate:200:'...'}</p>
    </div>

    <div class="ia-item__panel">
        {if $core.config.directory_enable_alexarank && $listing.alexa_rank}
            <span class="ia-item__panel__item">
                <span class="fa fa-globe"></span> {lang key='alexa_rank'} <a href="http://www.alexa.com/siteinfo/{$listing.domain}#">{$listing.alexa_rank}</a></li>
            </span>
        {/if}
        <span class="ia-item__panel__item pull-right">
            <span class="fa fa-user"></span>
            {if $listing.member}
                {if $core.config.members_enabled}
                    <a href="{$smarty.const.IA_URL}member/{$listing.account_username}.html">{$listing.member}</a>
                {else}
                    {$listing.member}
                {/if}
            {else}
                {lang key='guest'}
            {/if}
        </span>
    </div>
</div>