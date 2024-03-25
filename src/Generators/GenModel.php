<?php

namespace PKeidel\DBtoLaravel\Generators;

use Illuminate\Support\Str;
use PKeidel\DBtoLaravel\DBtoLaravelHelper;
use PKeidel\DBtoLaravel\PhpFileBuilder;

class GenModel {

    public static function generate($table, string $name, $infos) {
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
            if(!in_array($colname, ['id', 'created_at', 'updated_at', 'deleted_at']))
                $fillable[] = $colname;
            $nullable = !empty($col->null) ? '|null' : '';
            switch($col->type) {
                case 'integer':
                case 'bigint':
                    $phpfile->doc[] = "@property int$nullable $colname";
                    $casts[$colname] = 'int';
                    break;
                case 'decimal':
                    $phpfile->doc[] = "@property float$nullable $colname";
                    $casts[$colname] = 'float';
                    break;
                case 'datetime':
                    $phpfile->doc[] = "@property Carbon$nullable $colname";
                    $casts[$colname] = 'datetime';
                    break;
                case 'text':
                    $phpfile->doc[] = "@property string$nullable $colname";
                    break;
                case 'boolean':
                    $casts[$colname] = 'boolean';
                    $phpfile->doc[] = "@property $col->type$nullable $colname";
                    break;
                case 'json':
                    $casts[$colname] = 'json';
                    $phpfile->doc[] = "@property json$nullable $colname";
                    break;
                default:
                    $phpfile->doc[] = "@property $col->type$nullable $colname"; // unknown:
                    break;
            }
        }

        //	    // hasMany
//	    foreach($infos['meta']['hasMany'] as $cls) {
//		    $tbl = $this->genClassName($cls['tbl']);
//		    $phpfile->doc[] = "@property-read Collection ".camel_case($cls['fnc'])." // from hasMany";
//	    }

        // belongsTo
        foreach($infos['meta']['belongsTo'] as $info) {
            $cls = $info['cls'];
            $phpfile->doc[] = "@property-read \App\Models\\$cls ".Str::singular($info['tbl'])." // from belongsTo";

            $phpfile->functions[] = [
                'visibility' => 'public',
                'name' => Str::singular($info['tbl']),
                'body' => "return \$this->belongsTo('App\Models\\$cls', '{$info['col']}');"
            ];
        }

        // belongsToMany
        foreach($infos['meta']['belongsToMany'] as $cls) {
            $tbl = DBtoLaravelHelper::genClassName($cls['tbl']);
            $phpfile->doc[] = "@property-read \App\Models\\$tbl ".strtolower($tbl)." // from belongsToMany";

            $phpfile->functions[] = [
                'visibility' => 'public',
                'name' => strtolower($tbl),
                'body' => "return \$this->belongsToMany('App\Models\\$tbl');"
            ];
        }

        // hasOne or hasMany
        foreach($infos['meta']['hasOneOrMany'] as $cls) {
            $clsName = $cls['cls'];
            $phpfile->doc[] = "@property-read \App\Models\\$clsName {$cls['tbl']} // from hasOneOrMany";

            $phpfile->functions[] = PhpFileBuilder::mkfun($cls['sgl'], '', ["", "@return \Illuminate\Http\Response"], "return \$this->hasOne('App\Models\\$clsName');", "TODO use {$cls['sgl']}() OR {$cls['tbl']}(), NOT both!");
            $phpfile->functions[] = PhpFileBuilder::mkfun($cls['tbl'], '', ["", "@return \Illuminate\Http\Response"], "return \$this->hasMany('App\Models\\$clsName');");
        }
        $phpfile->doc[] = "@package App\Models";

        // Variables
        if(count($fillable))
            $phpfile->vars[] = "protected \$fillable = ['".implode("','", $fillable)."']";
        if(count($casts)) {
            $casts = collect($casts)->mapWithKeys(function($v, $k) {
                return [$k => "'$k' => '$v'"];
            })->join(", ");
            $phpfile->vars[] = "protected \$casts    = [$casts]";
        }

        return $phpfile->__toString();
    }
}
