Ext.onReady(function () {
    if (Ext.get('js-grid-placeholder')) {
        var grid = new IntelliGrid({
            columns: [
                'selection',
                {name: 'title', title: _t('title'), width: 1, editor: 'text'},
                {
                    name: 'parent_title',
                    title: _t('parent_category'),
                    renderer: function (value, metadata, record) {
                        return (record.data.level < 1)
                            ? value
                            : '<a href="' + window.location.href + 'edit/' + record.data.parent_id + '/">' + value + '</a>'
                    },
                    width: 1
                },
                {name: 'slug', title: _t('path'), width: 1},
                {name: 'num_all_listings', title: _t('listings_num'), width: 140},
                {
                    name: 'locked',
                    title: _t('locked'),
                    width: 60,
                    align: intelli.gridHelper.constants.ALIGN_CENTER,
                    renderer: intelli.gridHelper.renderer.check,
                    editor: Ext.create('Ext.form.ComboBox', {
                        typeAhead: false,
                        editable: false,
                        lazyRender: true,
                        store: Ext.create('Ext.data.SimpleStore', {
                            fields: ['value', 'title'],
                            data: [[0, _t('no')], [1, _t('yes')]]
                        }),
                        displayField: 'title',
                        valueField: 'value'
                    })
                },
                {name: 'date_added', title: _t('date_added'), width: 100, hidden: true},
                {name: 'date_modified', title: _t('date_modified'), width: 100, hidden: true},
                'status',
                'update',
                'delete'
            ],
            fields: ['parent_id', 'level'],
            texts: {
                delete_multiple: _t('are_you_sure_to_delete_selected_categs'),
                delete_single: _t('are_you_sure_to_delete_selected_categ')
            }
        }, false);

        grid.toolbar = new Ext.Toolbar({
            items: [
                {
                    emptyText: _t('title'),
                    listeners: intelli.gridHelper.listener.specialKey,
                    name: 'title',
                    width: 250,
                    xtype: 'textfield'
                }, {
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
                }, {
                    handler: function () {
                        intelli.gridHelper.search(grid)
                    },
                    id: 'fltBtn',
                    text: '<i class="i-search"></i> ' + _t('search')
                }, {
                    handler: function () {
                        intelli.gridHelper.search(grid, true)
                    },
                    text: '<i class="i-close"></i> ' + _t('reset')
                }]
        });

        grid.init();

        var searchStatus = intelli.urlVal('status');
        if (searchStatus) {
            Ext.getCmp('fltStatus').setValue(searchStatus);
            intelli.gridHelper.search(grid);
        }
    }
    else {
        if ('#tree-crossed'.length) {
            var nodes = $('#crossed').val();
            nodes = nodes ? nodes.split(',') : [];

            $('#tree-crossed').jstree(
                {
                    core: {
                        data: {
                            data: function (n) {
                                var params = {};
                                if (n.id !== '#') params.id = n.id;

                                return params;
                            },
                            url: intelli.config.admin_url + '/directory/categories/tree.json?noroot&cid=' + intelli.cid
                        },
                        multiple: true
                    },
                    checkbox: {keep_selected_style: false, three_state: false},
                    plugins: ['checkbox']
                })
                .on('load_node.jstree', function (e, data) {
                    for (var i in nodes) data.instance.select_node(nodes[i]);
                })
                .on('click.jstree', function (e) {
                    var crossedJsTree = $('#tree-crossed').jstree(true);
                    var selectedNodes = crossedJsTree.get_selected();

                    if (selectedNodes == intelli.cid) {
                        crossedJsTree.deselect_node(e.target);
                    }
                    else {
                        $('#crossed').val(selectedNodes.join(','));

                        var titles = [];
                        for (var i in selectedNodes) {
                            var node = crossedJsTree.get_node(selectedNodes[i]);
                            titles.push('<span>' + node.text + '</span>');
                        }

                        $('#crossed-list').html(titles.join(', '));
                    }

                    var balance = selectedNodes.length;
                    if (balance >= 0) {
                        $('#crossed-limit').text(balance);
                    }
                });
        }
    }
});

intelli.titleCache = '';
intelli.fillUrlBox = function () {
    var slug = $('#field_categ_slug').val();
    var title = (slug ? slug : $('#field_categ_title').val());
    var category = $('#input-tree').val();
    var cache = title + '%%' + category;

    if ('' !== title && intelli.titleCache !== cache) {
        $.get(intelli.config.admin_url + '/directory/categories/slug.json', {
            title: title,
            category: category
        }, function (response) {
            if ('' !== response.data) {
                $('#title_url').text(response.data);
                $('#title_box').fadeIn();
            }
        });
    }

    intelli.titleCache = cache;
};

$(function() {
    $('#field_categ_title, #field_categ_slug').blur(intelli.fillUrlBox).blur();
});