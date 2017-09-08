@extends('dbtolaravel::layout')

@section('head')
    <style>
        pre code {
            transition: max-height 1s linear;
            max-height: 220px;
            max-width: 100%;
        }
        pre > span {
            user-select: none;
        }
        pre {
            margin-top: 5px;
        }
        span.btn {
            background-color: silver;
            padding: 3px;
            border-radius: 3px;
            cursor: pointer;
        }
        span.btn:hover {
            background-color: gray;
        }
    </style>
@endsection

@section('script')
    <script>
        var currentTable;
        var req;
        var infos      = $('#infos');
        var migration  = $('#migration');
        var blades     = $('#blades');
        var routes     = $('#routes');
        var controller = $('#controller');
        var model      = $('#model');
        function toggle(id) {
            var ele = $('#' + id);
            const h = ele.css('max-height');
            ele.css('max-height', h === '220px' ? ele.get(0).scrollHeight + 'px' : '220px');
        }
        function table_clicked(tbl) {
            if(req) req.abort();
            currentTable = tbl;
            req = $.get('/dbtolaravel/{{ $connection }}/' + tbl + '/infos').done(function(data) {
                infos.jsonViewer(data.infos);
                migration.text(data.migration);
                routes.text(data.routes);
                controller.text(data.controller);
                model.text(data.model);
                hljs.highlightBlock(migration.get(0));
                hljs.highlightBlock(infos.get(0));
                hljs.highlightBlock(routes.get(0));
                hljs.highlightBlock(controller.get(0));
                hljs.highlightBlock(model.get(0));

                blades.text('<!-- view -->\n' + data.blades.view +
                    '\n\n<!-- edit -->\n' + data.blades.edit +
                    '\n\n<!-- list -->\n' + data.blades.list);
                hljs.highlightBlock(blades.get(0));
                req = null;
            });
        }
        function writefile(key, overwrite) {
            // /{connection}/{table}/{key}/write
            $.get('/dbtolaravel/{{ $connection }}/' + currentTable + '/' + key + '/write' + (overwrite ? '/overwrite' : '')).done(function(data) {
                console.log("data=%o", data);
                if(data.error && data.key && data.key === 'file-exists') {
                    if(confirm('File already exists. Override?')) {
                        writefile(key, true);
                    }
                } else if(data.error)
                    alert('Error: ' + data.error);
            });
        }
        hljs.initHighlightingOnLoad();
    </script>
@endsection

@section('content')
    <div>Connection: {{ $connection }}</div>
    <form>
        <select name="connection">
            @foreach($connections as $c)
                <option @if($c == $connection) selected @endif>{{ $c }}</option>
            @endforeach
        </select>
        <input type="submit" value="Set Connection">
    </form>
    <br>
    <table class="table" border="" width="100%">
        <tr>
            <td valign="top" width="200px">
                <h4>Tables</h4>
                @foreach($tables as $table)
                    <li onclick="table_clicked('{{ $table }}');">{{ $table }}</li>
                @endforeach
            </td>
            <td valign="top">
                <h4>Infos 1</h4>
                <span class="btn" onclick="toggle('migration');">toggle migration</span>
                <span class="btn" onclick="writefile('migration');">write</span>
                <pre>
                    <code id="migration" class="php">migration</code>
                </pre>
                <span class="btn" onclick="toggle('blades');">toggle blades</span>
                <pre>
                    <code id="blades" class="html">blades</code>
                </pre>
                <span class="btn" onclick="toggle('infos');">toggle infos</span>
                <pre>
                    <code id="infos" class="json">infos</code>
                </pre>
            </td>
            <td valign="top">
                <h4>Infos 2</h4>
                <span class="btn" onclick="toggle('routes');">toggle routes</span>
                <pre>
                    <code id="routes" class="php">routes</code>
                </pre>
                <span class="btn" onclick="toggle('controller');">toggle controller</span>
                <span class="btn" onclick="writefile('controller');">write</span>
                <pre>
                    <code id="controller" class="php">controller</code>
                </pre>
                <span class="btn" onclick="toggle('model');">toggle model</span>
                <span class="btn" onclick="writefile('model');">write</span>
                <pre>
                    <code id="model" class="php">model</code>
                </pre>
            </td>
        </tr>
    </table>
@endsection