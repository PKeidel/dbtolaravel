<html>
<head>
    <title>DB to Laravel</title>
    <style>
        /* https://raw.githubusercontent.com/abodelot/jquery.json-viewer/master/json-viewer/jquery.json-viewer.css */
        ul.json-dict,ol.json-array{list-style-type:none;margin:0 0 0 1px;border-left:1px dotted #ccc;padding-left:2em}.json-string{color:#0B7500}.json-literal{color:#1A01CC;font-weight:700}a.json-toggle{position:relative;color:inherit;text-decoration:none}a.json-toggle:focus{outline:none}a.json-toggle:before{color:#aaa;content:"\25BC";position:absolute;display:inline-block;width:1em;left:-1em}a.json-toggle.collapsed:before{transform:rotate(-90deg);-ms-transform:rotate(-90deg);-webkit-transform:rotate(-90deg)}a.json-placeholder{color:#aaa;padding:0 1em;text-decoration:none}a.json-placeholder:hover{text-decoration:underline}
        .hljs{display:block;overflow-x:auto;padding:0.5em;background:#F0F0F0}.hljs,.hljs-subst{color:#444}.hljs-comment{color:#888888}.hljs-keyword,.hljs-attribute,.hljs-selector-tag,.hljs-meta-keyword,.hljs-doctag,.hljs-name{font-weight:bold}.hljs-type,.hljs-string,.hljs-number,.hljs-selector-id,.hljs-selector-class,.hljs-quote,.hljs-template-tag,.hljs-deletion{color:#880000}.hljs-title,.hljs-section{color:#880000;font-weight:bold}.hljs-regexp,.hljs-symbol,.hljs-variable,.hljs-template-variable,.hljs-link,.hljs-selector-attr,.hljs-selector-pseudo{color:#BC6060}.hljs-literal{color:#78A960}.hljs-built_in,.hljs-bullet,.hljs-code,.hljs-addition{color:#397300}.hljs-meta{color:#1f7199}.hljs-meta-string{color:#4d99bf}.hljs-emphasis{font-style:italic}.hljs-strong{font-weight:bold}
    </style>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" integrity="sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB" crossorigin="anonymous">
    @yield('head')
</head>
<body>
    <div class="container-fluid">
        @yield('content')
    </div>
<script src="https://code.jquery.com/jquery-1.12.4.min.js" integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ=" crossorigin="anonymous"></script>
<script>
    /* https://raw.githubusercontent.com/abodelot/jquery.json-viewer/master/json-viewer/jquery.json-viewer.js */
    (function($){function isCollapsable(arg){return arg instanceof Object&&Object.keys(arg).length>0}
        function isUrl(string){var regexp=/^(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;return regexp.test(string)}
        function json2html(json,options){var html='';if(typeof json==='string'){json=json.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');if(isUrl(json))
            html+='<a href="'+json+'" class="json-string">'+json+'</a>';else html+='<span class="json-string">"'+json+'"</span>'}
        else if(typeof json==='number'){html+='<span class="json-literal">'+json+'</span>'}
        else if(typeof json==='boolean'){html+='<span class="json-literal">'+json+'</span>'}
        else if(json===null){html+='<span class="json-literal">null</span>'}
        else if(json instanceof Array){if(json.length>0){html+='[<ol class="json-array">';for(var i=0;i<json.length;++i){html+='<li>';if(isCollapsable(json[i])){html+='<a href class="json-toggle"></a>'}
            html+=json2html(json[i],options);if(i<json.length-1){html+=','}
            html+='</li>'}
            html+='</ol>]'}
        else{html+='[]'}}
        else if(typeof json==='object'){var key_count=Object.keys(json).length;if(key_count>0){html+='{<ul class="json-dict">';for(var key in json){if(json.hasOwnProperty(key)){html+='<li>';var keyRepr=options.withQuotes?'<span class="json-string">"'+key+'"</span>':key;if(isCollapsable(json[key])){html+='<a href class="json-toggle">'+keyRepr+'</a>'}
        else{html+=keyRepr}
            html+=': '+json2html(json[key],options);if(--key_count>0)
                html+=',';html+='</li>'}}
            html+='</ul>}'}
        else{html+='{}'}}
            return html}
        $.fn.jsonViewer=function(json,options){options=options||{};return this.each(function(){var html=json2html(json,options);if(isCollapsable(json))
            html='<a href class="json-toggle"></a>'+html;$(this).html(html);$(this).off('click');$(this).on('click','a.json-toggle',function(){var target=$(this).toggleClass('collapsed').siblings('ul.json-dict, ol.json-array');target.toggle();if(target.is(':visible')){target.siblings('.json-placeholder').remove()}
        else{var count=target.children('li').length;var placeholder=count+(count>1?' items':' item');target.after('<a href class="json-placeholder">'+placeholder+'</a>')}
            return!1});$(this).on('click','a.json-placeholder',function(){$(this).siblings('a.json-toggle').click();return!1});if(options.collapsed==!0){$(this).find('a.json-toggle').click()}})}})(jQuery)
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js"></script>
<script src="https://unpkg.com/mermaid@7.0.5/dist/mermaid.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
@yield('script')
</body>
</html>
