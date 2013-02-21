$(function () {
    $('.dropdown-toggle').dropdown();

    if ($('#sourcecode').length) {
        var value = $('#sourcecode').text();
        var mode = $('#sourcecode').attr('language');
        var pre = $('#sourcecode').get(0);
        var viewer = CodeMirror(function(elt) {
            pre.parentNode.replaceChild(elt, pre);
        }, {
            value: value,
            lineNumbers: true,
            matchBrackets: true,
            lineWrapping: true,
            readOnly: true,
            mode: mode,
            lineNumberFormatter: function(ln) {
                return '<a name="L'+ ln +'"></a><a href="#L'+ ln +'">'+ ln +'</a>';
            }
        });
    }

    //CodeMirror as an editor
    if ($('#sourcecode_edit').length) {
        var value = $('#sourcecode_edit').text();
        var mode = $('#sourcecode_edit').attr('language');
        var pre = $('#sourcecode_edit').get(0);

        //Differentiate modes for codefolding option
        var rangeFinder = null;
        if (mode == "text" || mode == "markdown") {
            foldFunc = null;
        }
        else if (mode == "html" || mode == "xml") {
            foldFunc = CodeMirror.newFoldFunction(CodeMirror.tagRangeFinder);
        }
        else {
            foldFunc = CodeMirror.newFoldFunction(CodeMirror.braceRangeFinder);           
        }

        /* CodeMirror Initiation and Options 
        *  Codefolding and highlighting of selected text are enabled
        *  Addeds keyboard shortcut Ctrl-Q for codefolding
        */
        var editor = CodeMirror.fromTextArea(pre
        , {
            value: value,
            lineNumbers: true,
            matchBrackets: true,
            lineWrapping: true,
            mode: mode,
            autofocus: true,
            onGutterClick: foldFunc,
            extraKeys: {"Ctrl-Q": function(cm){foldFunc(cm, cm.getCursor().line);}},
            onCursorActivity: function() {
                editor.matchHighlight("CodeMirror-matchhighlight");
            },
            lineNumberFormatter: function(ln) {
                return '<a name="L'+ ln +'"></a><a href="#L'+ ln +'">'+ ln +'</a>';
            }
        });
    }

    if ($('#readme-content').length) {
        var converter = new Showdown.converter();
        $('#readme-content').html(converter.makeHtml($('#readme-content').text()));
    }

    function paginate() {
        var $pager = $('.pager');
        $pager.find('.next a').one('click', function (e) {
            e.preventDefault();
            $(this).css('pointer-events', 'none');
            $.get(this.href, function (html) {
                $pager.after(html);
                $pager.remove();
                paginate();
            });
        });
        $pager.find('.previous').remove();
    }
    paginate();
});
