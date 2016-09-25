Ext.onReady(function()
{
	if (Ext.get('js-grid-placeholder'))
	{
		var grid = new IntelliGrid(
		{
			columns:[
				'selection',
				{name: 'title', title: _t('title'), width: 1, editor: 'text'},
				{name: 'title_alias', title: _t('title_alias'), width: 1},
				{name: 'category_title', title: _t('category'), width: 140},
				{name: 'member', title: _t('owner'), width: 140},
				{name: 'date_added', title: _t('date_added'), width: 100},
				{name: 'date_modified', title: _t('date_modified'), width: 100},
				'status',
				{name: 'reported_as_broken', title: _t('broken'), icon: 'info', click: function(node){
					Ext.MessageBox.alert(
						_t('reported_as_broken_comments'),
						node.data.reported_as_broken_comments.replace(/(?:\r\n|\r|\n)/g, '<br />')
					)
				}},
				'update',
				'delete'
			],
			fields: ['reported_as_broken_comments'],
			sorters: [{property: 'date_modified', direction: 'DESC'}],
			statuses: ['active', 'approval', 'banned', 'suspended'],
			texts: {
				delete_multiple: _t('are_you_sure_to_delete_selected_categs'),
				delete_single: _t('are_you_sure_to_delete_selected_categ')
			}
		}, false);

		grid.toolbar = new Ext.Toolbar({items:[
		{
			emptyText: _t('text'),
			listeners: intelli.gridHelper.listener.specialKey,
			name: 'text',
			width: 250,
			xtype: 'textfield'
		},{
			displayField: 'title',
			editable: false,
			emptyText: _t('status'),
			id: 'fltStatus',
			name: 'status',
			store: grid.stores.statuses,
			typeAhead: true,
			valueField: 'value',
			width: 100,
			xtype: 'combo'
		},{
			emptyText: _t('owner'),
			listeners: intelli.gridHelper.listener.specialKey,
			name: 'member',
			id: 'fltOwner',
			width: 150,
			xtype: 'textfield'
		},{
			boxLabel: _t('no_owner'),
			name: 'no_owner',
			xtype: 'checkbox',
			handler: function()
			{
				var cmpOwner = Ext.getCmp('fltOwner');
				this.checked ? cmpOwner.disable() : cmpOwner.enable();
			}
		},{
			boxLabel: _t('reported_as_broken'),
			name: 'reported_as_broken',
			xtype: 'checkbox'
		},{
			handler: function(){intelli.gridHelper.search(grid)},
			id: 'fltBtn',
			text: '<i class="i-search"></i> ' + _t('search')
		},{
			handler: function(){intelli.gridHelper.search(grid, true)},
			text: '<i class="i-close"></i> ' + _t('reset')
		}]});

		grid.init();

		var searchStatus = intelli.urlVal('status');
		if (searchStatus)
		{
			Ext.getCmp('fltStatus').setValue(searchStatus);
			intelli.gridHelper.search(grid);
		}
	}
	else
	{
		$('#field_title').keyup(function () {
			if ($(this).val()) {
				$('#tr_title_alias').show();
			}
		}).keyup();

		if ('#tree-crossed'.length)
		{
			var nodes = $('#crossed-links').val().split(',');

			$('#tree-crossed').jstree(
			{
				core:
				{
					data: {
						data: function(n)
						{
							var params = {};

							if(n.id != '#')
							{
								params.id = n.id;
							}
							else
							{
								params.id = 0;
							}

							return params;
						},
						url: intelli.config.ia_url + 'directory/categories/read.json?get=tree'
					},
					multiple: true
				},
				checkbox: {keep_selected_style: false, three_state: false},
				plugins: ['checkbox']
			})
			.on('load_node.jstree', function(e, data)
			{
				for (var i in nodes) data.instance.select_node(nodes[i]);
			})
			.on('click.jstree', function(e)
			{
				var crossedJsTree = $('#tree-crossed').jstree(true);
				var selectedNodes = crossedJsTree.get_selected();

				if (selectedNodes.length > intelli.config.listing_crossed_limit)
				{
					crossedJsTree.deselect_node(e.target);
				}
				else
				{
					$('#crossed-links').val(selectedNodes.join(','));

					var titles = [];
					for (var i in selectedNodes)
					{
						var node = crossedJsTree.get_node(selectedNodes[i]);
						titles.push('<span>' + node.text + '</span>');
					}

					$('#crossed-list').html(titles.join(', '));
				}

				var balance = intelli.config.listing_crossed_limit - selectedNodes.length;
				if (balance >= 0)
				{
					$('#crossed-limit').text(balance);
				}
			});
		}

		$('input[name="title"], input[name="title_alias"]').each(function()
		{
			$(this).blur(intelli.fillUrlBox);
		});

		intelli.fillUrlBox();
	}
});

intelli.titleCache = '';
intelli.fillUrlBox = function()
{
	var titleAlias = $('#field_title_alias').val();
	var title = ('' == titleAlias ? $('#field_title').val() : titleAlias);
	var category = $('#input-category').val();
	var id = $('#js-listing-id').val();

	var cache = title + '%%' + category;

	if ('' != title && intelli.titleCache != cache)
	{
		var params = {get: 'alias', title: title, category: category, id: id};

		if (titleAlias)
		{
			params.alias = 1;
		}

		$.get(intelli.config.ia_url + '/directory/listings/read.json?get=alias', params, function(response)
		{
			if (response.data)
			{
				$('#title_url').text(response.data);
				$('#title_box').fadeIn();
			}
		});
	}
	intelli.titleCache = cache;
};