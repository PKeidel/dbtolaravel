<?php


namespace PKeidel\DBtoLaravel\Generators;


use Illuminate\Support\Facades\DB;
use PKeidel\DBtoLaravel\PhpFileBuilder;

class GenSeeder {
    public static function generate($table, $classname, $seederClass, $tabledata) {
        $phpfile = new PhpFileBuilder($seederClass);

        $phpfile->imports[] = "App\Models\\$classname";
        $phpfile->imports[] = 'Illuminate\Database\Seeder';
        $phpfile->imports[] = "Illuminate\Support\Facades\DB";

        $phpfile->doc[] = "Seeder for table $table";
        $phpfile->doc[] = "TODO: Don't forget to include `\$this->call(\\$seederClass::class);` in DatabaseSeeder.php::run() method";

        $phpfile->extends = 'Seeder';

        $content = "try {\n";
        $content .= "            DB::beginTransaction();\n";
        $content .= "\n";

        foreach($tabledata as $row) {
//            $content .= "            // ".json_encode($row)."\n";
            $arr = [];
            foreach($row as $key => $value) {
                $cls = "\App\Models\\$classname";

                if(class_exists($cls)) {
                    $model = new $cls;
                    $casts = $model->getCasts(); // ["id" => "int", "fees" => "json"]
                    if(!in_array($key, ['id', 'created_at', 'updated_at', 'password']) && $value !== NULL) {
                        // if json, then output a php array
                        if(array_key_exists($key, $casts) && $casts[$key] === 'json') {
                            $str = [];
                            $value = json_decode($value, true);
                            foreach($value as $k => $v)
                                if(is_numeric($v))
                                    $str[] = "\"$k\" => ".floatval($v);
                                else
                                    $str[] = "\"$k\" => \"$v\"";
                            $str = implode(', ', $str);
                            $arr[] = "'$key' => [$str]";
                        } else {
                            if(is_numeric($value))
                                $arr[] = "'$key' => ".floatval($value);
                            else
                                $arr[] = "'$key' => \"".str_replace(['"', "\r", "\n"], ['\"', '', '\n'], $value)."\"";
                        }
                    }
                } else {
                    foreach($row as $key => $value)
                        if(!in_array($key, ['id', 'created_at', 'updated_at', 'password']) && $value !== NULL)
                            if(is_numeric($value))
                                $arr[] = "'$key' => ".floatval($value);
                            else
                                $arr[] = "'$key' => \"".str_replace(['"', "\r", "\n"], ['\"', '', '\n'], $value)."\"";
                }
            }
            $content .= "            $classname::create([".implode(', ', $arr)."]);\n";
        }

        $content .= "\n";
        $content .= "            DB::commit();\n";
        $content .= "        } catch(Exception \$e) {\n";
        $content .= "            DB::rollback();\n";
        $content .= "            echo \$e;\n";
        $content .= "            exit;\n";
        $content .= "        }";

        $phpfile->functions[] = PhpFileBuilder::mkfun('run', '', [], $content);

        return $phpfile->__toString();
    }
}
