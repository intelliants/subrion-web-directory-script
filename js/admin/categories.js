Ext.onReady(function()
{
	if (Ext.get('js-grid-placeholder'))
	{
		intelli.categs = new IntelliGrid(
		{
			columns:[
				'selection',
				{name: 'title', title: _t('title'), width: 1, editor: 'text'},
				{name: 'title_alias', title: _t('path'), width: 1},
				{name: 'num_all_listings', title: _t('listings_num'), width: 140},
				{name: 'date_added', title: _t('date_added'), width: 100},
				{name: 'date_modified', title: _t('date_modified'), width: 100},
				'status',
				'update',
				'delete'
			],
			texts: {
				delete_multiple: _t('are_you_sure_to_delete_selected_categs'),
				delete_single: _t('are_you_sure_to_delete_selected_categ')
			}
		}, false);
		intelli.categs.toolbar = new Ext.Toolbar({items:[
		{
			emptyText: _t('title'),
			listeners: intelli.gridHelper.listener.specialKey,
			name: 'title',
			width: 250,
			xtype: 'textfield'
		},{
			displayField: 'title',
			editable: false,
			emptyText: _t('status'),
			name: 'status',
			store: intelli.categs.stores.statuses,
			typeAhead: true,
			valueField: 'value',
			width: 100,
			xtype: 'combo'
		},{
			handler: function(){intelli.gridHelper.search(intelli.categs)},
			id: 'fltBtn',
			text: '<i class="i-search"></i> ' + _t('search')
		},{
			handler: function(){intelli.gridHelper.search(intelli.categs, true)},
			text: '<i class="i-close"></i> ' + _t('reset')
		}]});

		intelli.categs.init();
	}
});

intelli.titleCache = '';
intelli.fillUrlBox = function()
{
	var alias = $('#field_title_alias').val();
	var title = ('' == alias ? $('#field_title').val() : alias);
	var category = $('#input-category').val();
	var cache = title + '%%' + category;

	if ('' != title && intelli.titleCache != cache)
	{
		var params = {get: 'alias', title: title, category: category};

		if ('' != alias)
		{
			params.alias = 1;
		}

		$.get(intelli.config.admin_url + '/directory/categories/read.json?get=tree', params, function(response)
		{
			if ('' != response.data)
			{
				$('#title_url').text(response.data);
				$('#title_box').fadeIn();
			}
		});
	}

	intelli.titleCache = cache;
};

$(function()
{
	$('input[name="title"], input[name="alias"]').blur(intelli.fillUrlBox).blur();
});