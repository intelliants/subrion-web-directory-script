{if isset($item.item) && 'listings' == $item.item}
    <div class="modal fade" id="report-listing-modal" tabindex="-1" role="dialog" aria-labelledby="report-listing">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="report-listing-form" class="ia-form">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel">{lang key='do_you_want_report_broken'}</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="report-listing-comment">{lang key='comment'}:</label>
                            <textarea name="report-listing-comment" id="report-listing-comment" class="form-control" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">{lang key='cancel'}</button>
                        <button type="submit" class="btn btn-primary">{lang key='submit'}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
{/if}