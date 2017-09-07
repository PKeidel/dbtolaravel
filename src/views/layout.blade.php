<html>
<head>
    <title>DB to Laravel</title>
    <link rel="stylesheet"
          href="https://gitcdn.xyz/repo/abodelot/jquery.json-viewer/master/json-viewer/jquery.json-viewer.css"></link>
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/styles/default.min.css">
    <style>
        .hljs{display:block;overflow-x:auto;padding:0.5em;color:#abb2bf;background:#282c34}.hljs-comment,.hljs-quote{color:#5c6370;font-style:italic}.hljs-doctag,.hljs-keyword,.hljs-formula{color:#c678dd}.hljs-section,.hljs-name,.hljs-selector-tag,.hljs-deletion,.hljs-subst{color:#e06c75}.hljs-literal{color:#56b6c2}.hljs-string,.hljs-regexp,.hljs-addition,.hljs-attribute,.hljs-meta-string{color:#98c379}.hljs-built_in,.hljs-class .hljs-title{color:#e6c07b}.hljs-attr,.hljs-variable,.hljs-template-variable,.hljs-type,.hljs-selector-class,.hljs-selector-attr,.hljs-selector-pseudo,.hljs-number{color:#d19a66}.hljs-symbol,.hljs-bullet,.hljs-link,.hljs-meta,.hljs-selector-id,.hljs-title{color:#61aeee}.hljs-emphasis{font-style:italic}.hljs-strong{font-weight:bold}.hljs-link{text-decoration:underline}
    </style>
    @yield('head')
</head>
<body>
@yield('content')
<script src="https://code.jquery.com/jquery-1.12.4.min.js"
        integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ=" crossorigin="anonymous"></script>
<script src="https://gitcdn.xyz/repo/abodelot/jquery.json-viewer/master/json-viewer/jquery.json-viewer.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js"></script>
@yield('script')
</body>
</html>
