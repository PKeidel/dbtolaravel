<?php

namespace PKeidel\DBtoLaravel\Generators;

use Illuminate\Support\Str;
use PKeidel\DBtoLaravel\PhpFileBuilder;

class GenController {

    public static function generate($table, $classname, $infos) {
        $tableSing = Str::singular($table);

        $phpfile = new PhpFileBuilder("{$classname}Controller", "App\Http\Controllers");

        $phpfile->imports[] = "Illuminate\\Http\\Request";
        $phpfile->imports[] = "App\\Models\\{$classname}";

        $phpfile->extends = 'Controller';

        ob_start();
        echo "// TODO complete validation
        \$data = \$this->validate(\$request, [\n";
        $nl = "";
        foreach($infos['cols'] as $col => $info) {
            if(in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))
                continue;
            // if null is not allowed this field is required
            echo "$nl            '$col' => '". ($info['null'] ? '' : 'required') ."',";
            $nl = "\n";
        }
        echo "
        ]);

        {$classname}::create(\$data);

        return redirect(route(\"$table.index\"));";
        $contentStore = ob_get_clean();

        $phpfile->functions[] = PhpFileBuilder::mkfun('index', '', ["GET|HEAD  /$table", "Display a listing of the resource.", "", "@return \Illuminate\Http\Response"], "return view(\"$table.list\", ['$table' => {$classname}::all()]);");
        $phpfile->functions[] = PhpFileBuilder::mkfun('create', '', ["GET|HEAD  /$table/create", "Show the form for creating a new resource.", "", "@return \Illuminate\Http\Response"], "return view(\"$table.edit\");");
        $phpfile->functions[] = PhpFileBuilder::mkfun('store', "Request \$request", ["POST  /$table", "Store a newly created resource in storage.", "", "@param  \Illuminate\Http\Request \$request", "", "@return \Illuminate\Http\Response"], $contentStore);
        $phpfile->functions[] = PhpFileBuilder::mkfun('show', "$classname \$$tableSing", ["GET|HEAD /$table/{{$tableSing}}", "Display the specified resource.", "", "@param  $classname \$$tableSing", "", "@return \Illuminate\Http\Response"], "return view(\"$table.view\", [\"$table\" => \$$tableSing]);");
        $phpfile->functions[] = PhpFileBuilder::mkfun('edit', "$classname \$$tableSing", ["GET|HEAD /$table/{{$tableSing}}/edit", "Show the form for editing the specified resource.", "", "@param  $classname \$$tableSing","", "@return \Illuminate\Http\Response"], "return view(\"$table.edit\", [\"$table\" => \$$tableSing]);");
        $phpfile->functions[] = PhpFileBuilder::mkfun('update', "Request \$request, $classname \$$tableSing", ["PUT|PATCH /$table/{{$tableSing}}", "Update the specified resource in storage.", "", "@param  \Illuminate\Http\Request \$request", "@param  $classname \$$tableSing", "", "@return \Illuminate\Http\Response"], <<<HERE
\$newData = \$request->except(['_method', '_token', 'id']);
        \${$tableSing}->fill(\$newData);
        \${$tableSing}->save();
        return redirect(route("$table.show", [\${$tableSing}->id]));
HERE
        );
        $phpfile->functions[] = PhpFileBuilder::mkfun('destroy', '', ["DELETE /$table/{{$tableSing}}", "Remove the specified resource from storage.", "", "@param  $classname \$$tableSing", "", "@return \Illuminate\Http\Response", "", "@throws \Exception"], <<<HERE
\${$tableSing}->delete();
        return redirect(route("$table.index"));
HERE
        );
        return $phpfile->__toString();
    }
}
