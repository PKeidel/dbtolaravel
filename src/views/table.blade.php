@extends('dbtolaravel::layout')

@section('head')
    <style>
        @media (min-width: 1700px) {
            .modal-lg {
                max-width: 1600px;
                width: fit-content !important;
            }
        }
        del {
            color: red !important;
            border: 1px solid red;
        }
        ins {
            color: green !important;
            border: 1px solid green;
        }
        div.CodeMirror.cm-s-default {
            height: 100%;
        }
        div.modal-body > div.hljs {
            max-height: 100%;
        }
        .table td, .table th {
            padding: .3rem;
        }

        .lds-ring {
            display: inline-block;
            position: relative;
            width: 80px;
            height: 80px;
        }
        .lds-ring div {
            box-sizing: border-box;
            display: block;
            position: absolute;
            width: 64px;
            height: 64px;
            margin: 8px;
            border: 8px solid #fff;
            border-radius: 50%;
            animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
            border-color: #fff transparent transparent transparent;
        }
        .lds-ring div:nth-child(1) {
            animation-delay: -0.45s;
        }
        .lds-ring div:nth-child(2) {
            animation-delay: -0.3s;
        }
        .lds-ring div:nth-child(3) {
            animation-delay: -0.15s;
        }
        @keyframes lds-ring {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
        .loading-animation {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background-color: #0000006e;
            padding-left: 50%;
            padding-top: 20%;
            z-index: 9999;
        }
    </style>
@endsection
@section('script')
    <script>
        let modal = $('#myModal'), modalTitle = $('.modal-title'), modalBody = $('.modal-body'), modalBtn = $('#btnWrite'), infos = {!! json_encode($helper->getArrayAll()) !!};

        function startLoading() {
            $('.loading-animation').css('display', 'block');
        }
        function endLoading() {
            $('.loading-animation').css('display', 'none');
        }

        function showCode(sourceCode, mode, readOnly) {
            let code = $('<textarea></textarea>');
            code.text(sourceCode);
            modalBody.children().remove();
            modalBody.append(code);

            modal.modal({show:true});

            document.editor = CodeMirror.fromTextArea(code.get(0), {
                lineNumbers: true,
                matchBrackets: true,
                mode: mode,
                indentUnit: 4,
                indentWithTabs: true,
                readOnly: readOnly
            });

            setTimeout(() => document.editor.refresh(), 200);
        }

        function showDialog(table, type) {
            // quick write
            let ctrlKeyDown = arguments.callee && arguments.callee.caller && arguments.callee.caller.arguments[0].ctrlKey || false;
            if(ctrlKeyDown) {
                startLoading();
                $.get(`{{ $connection }}/render/${table}/${type}`)
                    .success((data) => {
                        endLoading();
                        writefile(table, type, data.content);
                    })
                    .error(endLoading);
                return;
            }

            modalTitle.text(`View: ${table} ${type}`);
            modalBtn.off().prop('disabled', true);
            modalBody.children().remove();

            startLoading();
            $.get(`{{ $connection }}/render/${table}/${type}`)
                .success((data) => {
                    endLoading();
                    window.lastData = data;
                    modalBtn.on('click', () => {
                        writefile(table, type, document.editor.getValue());
                    }).prop('disabled', false);

                    showCode(data.content, "application/x-httpd-php");
                    modalBody.append(`<span>File: ${data.path}</span>`);
                })
                .error(endLoading);
        }
        function showDiffDialog(table, type) {
            modalTitle.text(`Diff: ${table} ${type}`);
            modalBtn.off().prop('disabled', true);
            modalBody.children().remove();

            startLoading();
            $.get(`{{ $connection }}/render/${table}/${type}/diff`)
                .success((data) => {
                    endLoading();
                    window.lastData = data;
                    modalBtn.on('click', () => {
                        writefile(table, type, window.lastData.content);
                    }).prop('disabled', false);

                    // showCode(data.content, 'diff', true);
                    // modalBody.append(`<span>File: ${data.path}</span>`);

                    let code = $('<div></div>');
                    code.css('border', '1px solid silver').css('border-radius', '5px').css('padding', '5px').css('font-family', 'monospace');
                    code.html(data.diff.replace(/\n/g, '<br>').replace(/ /g, '&nbsp;'));
                    modalBody.children().remove();
                    modalBody.append(code);
                    modalBody.append(`<div>File: ${data.path}</div>`);
                    modalBody.append(`<div><del>red will be removed</del></div>`);
                    modalBody.append(`<div><ins>green will be added</ins></div>`);
                    hljs.highlightBlock(code.get(0));
                    modal.modal({show:true});
                })
                .error(endLoading);
        }
        function writefile(table, type, content, overwrite) {
            if(!content)
                throw new Error('content must be provided!');

            startLoading();
            $.ajax({
                url: 'write',
                data: {
                    file: infos[table][type].path,
                    content: content,
                    overwrite: !!overwrite
                },
                method: 'PUT'
            }).success(function(data) {
                endLoading();
                console.log(data);
                if(data.error && data.key && data.key === 'file-exists') {
                    if(confirm('File already exists. Override?')) {
                        writefile(type, true);
                    }
                } else if(data.error) {
                    alert('Error: ' + data.error);
                } else {
                    $(`#btn_${table}_${type} i`).remove();
                    $(`#btn_${table}_${type}`).removeClass('text-info').addClass('text-success').prop('disabled', true).append('<i class="fas fa-check"></i>');
                    modal.modal('hide');
                }
                window.lastData = null;
            })
            .error(endLoading);
        }
    </script>
@endsection
@section('content')
    <div class="loading-animation"><div class="lds-ring"><div></div><div></div><div></div><div></div></div></div>

    <div class="card">
        <div class="card-header">Connection: {{ $connection }}</div>
        <div class="card-body">
            <form>
                <select name="connection">
                    @foreach($connections as $c)
                        <option @if($c == $connection) selected @endif>{{ $c }}</option>
                    @endforeach
                </select>
                <input type="submit" class="btn btn-sm btn-primary" value="Set Connection">
                <input type="submit" value="Clear Cache" name="resetcache">
            </form>
        </div>
    </div>

    <br><br>

    <div><i class="fas fa-check text-success"></i> exists and is identical</div>
    <div><i class="fas fa-not-equal text-warning"></i> exists but is different <small>(click to view diff)</small></div>
    <div><i class="fas fa-plus text-info"></i> create new file <small>(click => open editor; ctrl+click => quick write)</small></div>

    <table class="table table-bordered table-small">
        <tr>
            <th>table</th>
            <th>migration</th>
            <th>model</th>
            <th colspan="3">view</th>
            <th>controller</th>
            <th>route</th>
            <th>db:seed</th>
        </tr>
        <tr>
            <th></th>
            <th></th>
            <th></th>
            <th>view</th>
            <th>edit</th>
            <th>list</th>
            <th></th>
            <th></th>
            <th></th>
        </tr>
        @foreach($helper->getArrayAll() as $table => $data)
            @php
            $d = \Illuminate\Support\Arr::except($data, ['schema']);
            @endphp
            <tr>
                <td>{{ $table }}</td>
                <td>
                    @if($d['migration']['exists'] && !$d['migration']['different'])
                        <button id="btn_{{ $table }}_migration" class="btn btn-sm btn-light text-success" disabled><i class="fas fa-check"></i></button>
                    @elseif($d['migration']['exists'])
                        <button id="btn_{{ $table }}_migration" class="btn btn-sm btn-light text-warning" onclick="showDiffDialog('{{ $table }}', 'migration')"><i class="fas fa-not-equal"></i></button>
                        <button id="btn_{{ $table }}_migration" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'migration')"><i class="fas fa-plus"></i></button>
                    @else
                        <button id="btn_{{ $table }}_migration" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'migration')"><i class="fas fa-plus"></i></button>
                    @endif
                </td>
                <td>
                    @if($d['model']['exists'] && !$d['model']['different'])
                        <button id="btn_{{ $table }}_model" class="btn btn-sm btn-light text-success" disabled><i class="fas fa-check"></i></button>
                    @elseif($d['model']['exists'])
                        <button id="btn_{{ $table }}_model" class="btn btn-sm btn-light text-warning" onclick="showDiffDialog('{{ $table }}', 'model')"><i class="fas fa-not-equal"></i></button>
                        <button id="btn_{{ $table }}_model" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'model')"><i class="fas fa-plus"></i></button>
                    @else
                        <button id="btn_{{ $table }}_model" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'model')"><i class="fas fa-plus"></i></button>
                    @endif
                </td>
                <td>
                    @if($d['view']['exists'] && !$d['view']['different'])
                        <button id="btn_{{ $table }}_view"  class="btn btn-sm btn-light text-success" disabled><i class="fas fa-check"></i></button>
                    @elseif($d['view']['exists'])
                        <button id="btn_{{ $table }}_view" class="btn btn-sm btn-light text-warning" onclick="showDiffDialog('{{ $table }}', 'view')"><i class="fas fa-not-equal"></i></button>
                    @else
                        <button id="btn_{{ $table }}_view" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'view')"><i class="fas fa-plus"></i></button>
                    @endif
                </td>
                <td>
                    @if($d['edit']['exists'] && !$d['edit']['different'])
                        <button id="btn_{{ $table }}_edit" class="btn btn-sm btn-light text-success" disabled><i class="fas fa-check"></i></button>
                    @elseif($d['edit']['exists'])
                        <button id="btn_{{ $table }}_edit" class="btn btn-sm btn-light text-warning" onclick="showDiffDialog('{{ $table }}', 'edit')"><i class="fas fa-not-equal"></i></button>
                    @else
                        <button id="btn_{{ $table }}_edit" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'edit')"><i class="fas fa-plus"></i></button>
                    @endif
                </td>
                <td>
                    @if($d['list']['exists'] && !$d['list']['different'])
                        <button id="btn_{{ $table }}_list" class="btn btn-sm btn-light text-success" disabled><i class="fas fa-check"></i></button>
                    @elseif($d['list']['exists'])
                        <button id="btn_{{ $table }}_list" class="btn btn-sm btn-light text-warning" onclick="showDiffDialog('{{ $table }}', 'list')"><i class="fas fa-not-equal"></i></button>
                    @else
                        <button id="btn_{{ $table }}_list" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'list')"><i class="fas fa-plus"></i></button>
                    @endif
                </td>
                <td>
                    @if($d['controller']['exists'] && !$d['controller']['different'])
                        <button id="btn_{{ $table }}_controller" class="btn btn-sm btn-light text-success" disabled><i class="fas fa-check"></i></button>
{{--                    @elseif($d['controller']['exists'])--}}
                    @else
                        <button id="btn_{{ $table }}_controller" class="btn btn-sm btn-light text-warning" onclick="showDiffDialog('{{ $table }}', 'controller')"><i class="fas fa-not-equal"></i></button>
                        <button id="btn_{{ $table }}_controller" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'controller')"><i class="fas fa-plus"></i></button>
                    @endif
                </td>
                <td>
                    @if($d['routes']['exists'] && !$d['routes']['different'])
                        <button id="btn_{{ $table }}_routes" class="btn btn-sm btn-light text-success" disabled><i class="fas fa-check"></i></button>
                    @elseif($d['routes']['exists'])
                        <button id="btn_{{ $table }}_routes" class="btn btn-sm btn-light text-warning" onclick="showDiffDialog('{{ $table }}', 'routes')"><i class="fas fa-not-equal"></i></button>
                    @else
                        <button id="btn_{{ $table }}_routes" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'routes')"><i class="fas fa-plus"></i></button>
                    @endif
                </td>
                <td>
                    @if($d['seeder']['exists'] && !$d['seeder']['different'])
                        <button id="btn_{{ $table }}_seeder" class="btn btn-sm btn-light text-success" disabled><i class="fas fa-check"></i></button>
                    @elseif($d['seeder']['exists'])
                        <button id="btn_{{ $table }}_seeder" class="btn btn-sm btn-light text-warning" onclick="showDiffDialog('{{ $table }}', 'seeder')"><i class="fas fa-not-equal"></i></button>
                        <button id="btn_{{ $table }}_seeder" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'seeder')"><i class="fas fa-plus"></i></button>
                    @else
                        <button id="btn_{{ $table }}_seeder" class="btn btn-sm btn-light text-info" onclick="showDialog('{{ $table }}', 'seeder')"><i class="fas fa-plus"></i></button>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <div id="myModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modal title</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="height:75%;height:calc(100% - 240px);">
                    <p>Modal body text goes here.</p>
                </div>
                <div class="modal-footer">
                    <button id="btnWrite" type="button" class="btn btn-primary">Write</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection