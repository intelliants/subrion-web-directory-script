<div class="btn-toolbar items-sorting text-center" id="js-listings-sorting-options">
    <p class="btn-group">
        <span class="btn btn-small disabled">{lang key='sort_by'}:</span>
        <a class="btn btn-small active" href="#" data-field="date">{lang key='date_added'}</a>
        <a class="btn btn-small" href="#" data-field="title">{lang key='title'}</a>
        <a class="btn btn-small" href="#" data-field="rank">{lang key='rank'}</a>
    </p>
    <p class="btn-group">
        <a class="btn btn-small" href="#" data-order="asc">{lang key='asc'}</a>
        <a class="btn btn-small active" href="#" data-order="desc">{lang key='desc'}</a>
    </p>
</div>
{ia_add_js}
$(function()
{
    $('a', '#js-listings-sorting-options').on('click', function(e)
    {
        var $this = $(this);
        $this.parent().find('a').not($this).removeClass('active');
        $this.addClass('active');
    });
});
{/ia_add_js}