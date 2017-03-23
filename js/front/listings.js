$(function () {
    $('.js-categories-toggle').on('click', function (e) {
        e.preventDefault();
        $($(this).data('toggle')).toggle();
    });

    $('#tree-crossed').jstree(
        {
            core: {
                data: {
                    data: function (n) {
                        var params = {};
                        if (n.id != '#') {
                            params.id = n.id;
                        }

                        return params;
                    },
                    url: intelli.config.packages.directory.url + 'add/tree.json'
                },
                multiple: true
            },
            checkbox: {keep_selected_style: false},
            plugins: ['checkbox']
        })
        .on('loaded.jstree', function () {
            var tree = $('#tree-crossed').jstree(true),
                nodes = [];

            $('.js-checked-crossed-node').each(function () {
                nodes.push($(this).data('id'));
            });

            tree.select_node(nodes);
        })
        .on('click.jstree', crossedTreeClick);
});

function crossedTreeClick(e) {
    var crossedJsTree = $('#tree-crossed').jstree(true);
    var selectedNodes = crossedJsTree.get_selected();

    if (selectedNodes.length) {
        if (selectedNodes.length > intelli.config.listing_crossed_limit) {
            crossedJsTree.deselect_node(e.target);
            return false;
        }

        $('#crossed_links').val(selectedNodes.join(','));

        var titles = [];
        for (var i in selectedNodes) {
            var node = crossedJsTree.get_node(selectedNodes[i]);
            titles.push('<span class="label label-info" data-node-id="' + node.id + '">' + node.text + ' <a href="javascript:;" onclick="changeNodeState(this);return false;"><i class="icon-remove-circle"></i></a></span>');
        }

        $('#crossed_title').html(titles.join(' '));
    }
}

function changeNodeState(obj) {
    var $caller = $(obj).parent();

    $('#tree-crossed').jstree(true).deselect_node($caller.data('node-id'));
    $caller.remove();
}