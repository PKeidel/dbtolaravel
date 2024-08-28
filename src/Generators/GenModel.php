<?php

namespace PKeidel\DBtoLaravel\Generators;

use Illuminate\Support\Str;
use PKeidel\DBtoLaravel\DBtoLaravelHelper;
use PKeidel\DBtoLaravel\PhpFileBuilder;

class GenModel {

    public static function generate($table, string $name, $infos) {
        $name = Str::plural($name);
        $phpfile = new PhpFileBuilder($name, 'App\Models');

        $phpfile->doc[] = "Model $name";
        $phpfile->doc[] = "";
        $phpfile->extends = 'Model';
        $phpfile->vars[] = "protected \$table    = '{$infos['meta']['name']}'";

        if($infos['meta']['useSoftDelete']) {
            $phpfile->imports[] = 'Illuminate\Database\Eloquent\SoftDeletes';
            $phpfile->use[]     = 'SoftDeletes';
        }

        $phpfile->imports[] = 'Carbon\Carbon';
        $phpfile->imports[] = 'Illuminate\Database\Eloquent\Model';
        $phpfile->imports[] = 'Illuminate\Database\Eloquent\Collection';

        $fillable = [];
        $casts    = [];

        // PHPdoc
        foreach ($infos['cols'] as $colname => $col) {
            $col = (object) $col;
            $col->type = strtolower($col->type);
            if(!in_array($colname, ['id', 'created_at', 'updated_at', 'deleted_at']))
                $fillable[] = $colname;
            $nullable = !empty($col->null) ? '?' : '';
            switch($col->type) {
                case 'integer':
                case 'bigint':
                    $phpfile->doc[] = "@property {$nullable}int \$$colname";
                    break;
                case 'decimal':
                    $phpfile->doc[] = "@property {$nullable}float \$$colname";
                    $casts[$colname] = 'float';
                    break;
                case 'datetime':
                    $phpfile->doc[] = "@property {$nullable}Carbon \$$colname";
                    $casts[$colname] = 'datetime';
                    break;
                case 'text':
                    $phpfile->doc[] = "@property {$nullable}string \$$colname";
                    break;
                case 'boolean':
                    $phpfile->doc[] = "@property {$nullable}$col->type \$$colname";
                    break;
                case 'json':
                    $phpfile->doc[] = "@property {$nullable}json \$$colname";
                    $casts[$colname] = 'json';
                    break;
                default:
                    $phpfile->doc[] = "@property {$nullable}$col->type \$$colname"; // unknown:
                    break;
            }
        }

        // belongsTo
        foreach($infos['meta']['belongsTo'] as $info) {
            $singlular = Str::camel(Str::singular($info['tbl']));
            $plural = Str::camel(Str::plural($info['tbl']));
            $className = ucfirst(Str::camel($plural));
            $phpfile->doc[] = "@property-read \App\Models\\$className \${$singlular} // from belongsTo";

            $phpfile->functions[] = [
                'visibility' => 'public',
                'name' => $singlular,
                'body' => "return \$this->belongsTo(\App\Models\\$className::class, '{$info['col']}');",
                'returnType' => '\Illuminate\Database\Eloquent\Relations\BelongsTo',
            ];
        }

        // belongsToMany
        foreach($infos['meta']['belongsToMany'] as $cls) {
            $tbl = DBtoLaravelHelper::genClassName($cls['tbl']);
            $plural = Str::plural($tbl);
            $propName = Str::camel(strtolower($plural));
            $phpfile->doc[] = "@property-read \App\Models\\$plural \${$propName} // from belongsToMany";

            $phpfile->functions[] = [
                'visibility' => 'public',
                'name' => $propName,
                'body' => "return \$this->belongsToMany(\App\Models\\$tbl::class);"
            ];
        }

        // hasOne or hasMany
        foreach($infos['meta']['hasOneOrMany'] as $cls) {
            $clsName = $cls['cls'];
            $sglPropName = Str::camel($cls['sgl']);
            $plPropName = Str::camel(Str::plural($cls['tbl']));
            $phpfile->doc[] = "@property-read \App\Models\\$clsName \${$sglPropName} // from hasOneOrMany hasOne";
            $phpfile->doc[] = "@property-read \App\Models\\$clsName \${$plPropName} // from hasOneOrMany hasMany";

            $phpfile->functions[] = PhpFileBuilder::mkfun($sglPropName, '', ["TODO use {$sglPropName}() OR {$plPropName}(), NOT both!"], "return \$this->hasOne(\App\Models\\$clsName::class);", '\Illuminate\Database\Eloquent\Relations\HasOne');
            $phpfile->functions[] = PhpFileBuilder::mkfun($plPropName, '', [], "return \$this->hasMany(\App\Models\\$clsName::class);", '\Illuminate\Database\Eloquent\Relations\HasMany');
        }

        // Variables
        if(count($fillable) > 0)
            $phpfile->vars[] = "protected \$fillable = ['".implode("', '", $fillable)."']";
        if(count($casts) > 0) {
            $casts = collect($casts)->mapWithKeys(function($v, $k) {
                return [$k => "'$k' => '$v'"];
            })->join(", ");
            $phpfile->vars[] = "protected \$casts    = [$casts]";
        }

        return $phpfile->__toString();
    }
}
