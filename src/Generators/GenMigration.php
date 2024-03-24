<?php

namespace PKeidel\DBtoLaravel\Generators;

use Doctrine\DBAL\Schema\Column;
use PKeidel\DBtoLaravel\PhpFileBuilder;

class GenMigration {

    /**
     * Gets morphs's for a table or NULL
     * @param $infos
     * @return string|null
     */
    private static function getMorphs($infos) {
//        if(count($infos['meta']['index']) === 2) {
//            dd($infos['meta']['index']);
//            $m1 = explode('_', $infos['meta']['index'][0]);
//            $m2 = explode('_', $infos['meta']['index'][1]);
//
//            if(count($m1) == 2 && count($m2) == 2 && $m1[0] === $m2[0]) {
//                if(($m1[1] === 'id' && $m2[1] === 'type') || ($m2[1] === 'id' && $m1[1] === 'type')) {
//                    if(isset($infos['cols'][$infos['meta']['index'][0]]) && isset($infos['cols'][$infos['meta']['index'][0]])) {
//                        return $m1[0];
//                    }
//                }
//            }
//        }
        return NULL;
    }

    public static function generate($table, $name, $infos) {
        $phpfile = new PhpFileBuilder($name);

        $phpfile->imports[] = 'Illuminate\Support\Facades\Schema';
        $phpfile->imports[] = 'Illuminate\Database\Schema\Blueprint';
        $phpfile->imports[] = 'Illuminate\Database\Migrations\Migration';

        $phpfile->extends = 'Migration';

        ob_start();

        echo "Schema::create('{$table}', function (Blueprint \$table) {\n";

        $morphs = self::getMorphs($infos);
        if($morphs !== NULL) {
            unset($infos['meta']['index']);
            unset($infos['cols']["{$morphs}_type"]);
        }

        foreach($infos['cols'] as $col => $info) {
            /** @var Column $info */
            $info = (object) $info;

            $laravelType = strtolower($info->type);
            $colName     = "'$col'";
            $extra       = '';
            $args        = '';

            if($info->null)
                $extra .= '->nullable()';

            // Ignore some columns, they are handled elsewhere
            if(in_array($col, ['updated_at', 'deleted_at', 'created_at']))
                continue;

            if("{$morphs}_id" === $col) {
                $laravelType = 'morphs';
                $colName     = "'$morphs'";
            } elseif($info->type === 'integer') {

                $laravelType = 'integer';
                if(isset($info->len) && $info->len != 10)
                    $args = ", $info->len";

                if($info->autoincrement)
                    $laravelType = 'increments';
                elseif($info->unsigned)
                    $laravelType = 'unsignedInteger';
                if($info->len == 1) {
                    $laravelType = 'boolean';
                }

            } elseif($info->type === 'bigint') {

                $laravelType = 'bigInteger';
                if($info->unsigned)
                    $extra .= '->unsigned()';
                if(!empty($info->len) && $info->len != 20)
                    $args = ", $info->len";

                if($info->autoincrement)
                    $laravelType = 'bigIncrements';

            } elseif($info->type === 'string') {
                if(!empty($info->len) && $info->len != 255)
                    $args = ", $info->len";
            } elseif($info->type === 'longtext') {
                $laravelType = 'longText';
            } elseif($info->type === 'tinyint') {
                $laravelType = 'tinyInteger';
                if($info->unsigned)
                    $extra .= '->unsigned()';
                if($info->len == 1)
                    $laravelType = 'boolean';
            }

            // some columns may have a precision and scale value
            if(in_array($laravelType, ['decimal', 'float', 'double']))
                if(!empty($info->precision))
                    $args = ", $info->precision, $info->scale";

            if($info->default !== NULL) {
                if(is_numeric($info->default))
                    $extra .= "->default($info->default)";
                elseif(is_bool($info->default))
                    $extra .= "->default(".($info->default ? 'true' : 'false').")";
                else
                    $extra .= "->default('$info->default')";
            }

            if(!empty($info->comment))
                $extra .= "->comment('".addslashes($info->comment)."')";

            echo "            \$table->$laravelType($colName$args)$extra;\n"; // ".json_encode($info)."
        }

        if($infos['meta']['useTimestamps'])
            echo "            \$table->timestamps();\n";

        if($infos['meta']['useSoftDelete'])
            echo "            \$table->softDeletes();\n";

        if(isset($infos['meta']['index']) && count($infos['meta']['index']) > 0) {
            echo "\n";
            foreach($infos['meta']['index'] as $index) {
                $cols = $index->getColumns();

                if($index->isPrimary() && count($cols) == 1 && !$infos['cols'][$cols[0]]['autoincrement']) {
                    echo "            \$table->primary(".json_encode($cols)."); // isPrimary => ".$index->getName()."\n";
                } elseif($index->isSimpleIndex()) {
                    echo "            \$table->index(".json_encode($cols)."); // isSimpleIndex => ".$index->getName()."\n";
                } elseif($index->isUnique()) {
                    if(count($cols) != 1 || $cols[0] != 'id')
                        echo "            \$table->unique(".json_encode($cols)."); // isUnique => ".$index->getName()."\n";
                } else {
                    dd($index);
                }
            }
        }

        $allForeigns = [];
        if(isset($infos['meta']['foreign']) && count($infos['meta']['foreign']) > 0) {
            echo "\n";
            foreach($infos['meta']['foreign'] as $f) {
                // TODO detect max length and cut beginning of
                $alias = '';
//	            $indexname = $table.'_'.$f['col'].'_foreign';
//        	    $maxlen = 40;
//	            if(strlen($indexname) > $maxlen)
//              $alias = ", 's".rand(10, 99).'_'.substr($indexname, 0 - $maxlen)."'";
                echo "            \$table->foreign('{$f['col']}'$alias)->references('{$f['refCol']}')->on('{$f['refTbl']}');\n";
                $allForeigns[] = $f['col'];
                $ret = array_search($f['col'], $infos['meta']['index'])."\n";
                if($ret !== FALSE) {
                    unset($infos['meta']['index'][intval($ret)]);
                }
            }
        }

        echo "        });";

        $content = ob_get_clean();

        $phpfile->functions[] = [
            'returnType' => 'void'
        ] + PhpFileBuilder::mkfun('up', '', ['Run the migrations.', 'no-doc-return-type'], $content);

        return $phpfile->__toString();
    }
}
