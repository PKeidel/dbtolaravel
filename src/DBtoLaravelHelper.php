<?php

namespace PKeidel\DBtoLaravel;

use cogpowered\FineDiff\Granularity\GranularityInterface;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\IntegerType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/*
TODO Support morphs
*/

class DBtoLaravelHelper {

    private $infos  = [];

    private $connection;
    private $driver;
    /** @var  AbstractSchemaManager $manager */
    private $manager;
    private $arrayCache = [];

    public static $FILTER = NULL;
    public static $MAPPINGS = ['enum' => 'string', 'bytea' => 'binary', 'macaddr' => 'string'];

    public function __construct($connection = NULL) {
        $this->connection = !empty($connection) ? $connection : config('database.default');
        $this->driver     = config("database.connections.$connection")['driver'];

        if(request()->has('resetcache'))
            Cache::forget("dbtolaravel:tables:1:$connection");

        $infos = Cache::remember("dbtolaravel:tables:1:$connection", 300, function() {
	        /** @var Connection $con */
	        $con = DB::connection($this->connection);

	        // Set up some mappings
	        $platform = $con->getDoctrineConnection()->getDatabasePlatform();
	        foreach(self::$MAPPINGS as $dbtype => $doctrinetype)
    	        $platform->registerDoctrineTypeMapping($dbtype, $doctrinetype);

	        $this->manager    = $con->getDoctrineSchemaManager();

	        $infos  = [];
	        $tables = $this->manager->listTableNames();
	        foreach($tables as $tbl) {
		        $colsTmp       = $this->manager->listTableColumns($tbl);
		        $cols          = [];
		        $dependson     = [];
		        $foreigns      = [];
		        $belongsTo     = [];
		        $belongsToMany = [];
		        $morph         = [];
		        $colNames      = array_map(function($c) {
		            return $c->getName();
                }, $colsTmp);

		        // $cols verarbeiten und $meta generieren
		        /** @var Column $col */
		        foreach($colsTmp as $col) {
			        // morph || belongsTo
			        // Wenn der Name %_id ist, dann ist es ein belongsTo zu der anderen Tabelle
			        // außer es existiert auch eine %_type Spalte, dann ist es ein morph
			        if(substr($col->getName(), -3) === '_id') {
                        $tblName = substr($col->getName(), 0, -3);
			        	if(in_array("{$tblName}_type", $colNames)) {
                            $morph[] = [
                                'col'  => $tblName,
                                'null' => !$col->getNotnull(),
                            ];
                            continue;
			        	}
			        	elseif(in_array($tblName, $tables)) {
			        	    $belongsTo[] = [
                                'tbl' => $tblName,
                                'cls' => ucfirst($tblName),
                                'sgl' => Str::singular($tblName),
                                'col' => $col->getName(),
                            ];
			        	}
			        }

                    $cols[$col->getName()] = [
                        'type'          => $col->getType()->getName(),
                        'len'           => $col->getLength(),
                        'precision'     => $col->getPrecision(),
                        'scale'         => $col->getScale(),
                        'unsigned'      => $col->getUnsigned(),
                        'null'          => !$col->getNotnull(),
                        'autoincrement' => $col->getAutoincrement(),
                        'default'       => $col->getDefault(),
                        'comment'       => $col->getComment(),
                    ];
		        }

		        $tmp       = $this->manager->listTableForeignKeys($tbl);
		        foreach($tmp as $foreign) {
			        /** ForeignKeyConstraint $foreign */
			        if(!in_array($foreign->getForeignTableName(), $dependson))
				        $dependson[] = $foreign->getForeignTableName();

			        $key = implode('_', $foreign->getLocalColumns())."-".implode('_', $foreign->getForeignColumns())."-".$foreign->getForeignTableName();
			        $foreigns[$key] = [
			        	'col' => $foreign->getLocalColumns()[0],
				        'refCol' => $foreign->getForeignColumns()[0],
				        'refTbl' => $foreign->getForeignTableName()
			        ];
		        }

		        $infos[$tbl] = ['meta' => [
			        'name' => $tbl,
			        'islinktable' => false,
			        'index' => $this->manager->listTableIndexes($tbl),
			        'foreign' => $foreigns,
			        'dependson' => $dependson,
			        'hasOneOrMany' => [],
			        'belongsTo' => $belongsTo,
			        'belongsToMany' => $belongsToMany,
			        'morph' => $morph,
			        'useSoftDelete' => isset($cols['deleted_at']),
			        'useTimestamps' => isset($cols['created_at']) && isset($cols['updated_at'])
		        ], 'cols' => $cols];
	        }

	        // Diesen Stand auslagern in eigene function
            // um ggf. mehr infos per DDL sammeln zu können
//	        dd($infos);

	        // Und noch ne Runde, für die Verknüpfungen
	        foreach($tables as $tbl) {
		        $islinktable = false;
		        $inf = explode('_', $tbl);
		        if(count($inf) === 2 && in_array($inf[0], $tables) && in_array($inf[1], $tables)) {
			        $infos[$tbl]['meta']['islinktable'] = true;
			        $infos[$inf[0]]['meta']['belongsToMany'][] = [
			        	'tbl' => ucfirst($inf[1]),
				        'fnc' => Str::singular($inf[1])
			        ];
			        $infos[$inf[1]]['meta']['belongsToMany'][] = [
			        	'tbl' => ucfirst($inf[0]),
				        'fnc' => Str::singular($inf[0])
			        ];
		        }
	        }

	        // hasMany hinzufügen:
	        // - die in ['foreign'] genannten Tabellen haben ein hasMany auf die akuelle Tabelle
	        foreach ($infos as $tbl => $info) {
		        foreach($info['meta']['foreign'] as $foreign) {
			        if(!($infos[$tbl]['meta']['islinktable']))
				        $infos[$foreign['refTbl']]['meta']['hasOneOrMany'][] = [
				        	'tbl' => $tbl,
				        	'cls' => ucfirst($tbl),
					        'sgl' => Str::singular($tbl)
				        ];
		        }
	        }

		    uasort($infos, function($a, $b) {
			    if($a['meta']['islinktable'] !== $b['meta']['islinktable'])
				    return $a['meta']['islinktable'] - $b['meta']['islinktable'];
			    return strcmp($a['meta']['name'], $b['meta']['name']);
		    });

	        return $infos;
        });
	    $this->infos = self::$FILTER === NULL ? $infos : array_filter($infos, self::$FILTER, ARRAY_FILTER_USE_KEY);
    }

	public function genClassName($table) {
		return ucfirst(Str::camel($table));
    }

	private function genMigrationClassName($table) {
		return "Create".$this->genClassName($table)."Table";
    }

    public function getInfos($table = NULL) {
        return isset($table) ? $this->infos[$table] : $this->infos;
    }

    public function getArrayAll($withContent = false) {
        $ret = [];
        foreach($this->infos as $tbl => $obj)
            $ret[$tbl] = $this->getArrayForTable($tbl, $withContent);
        return $ret;
    }

    public function getArrayForTable($table, $withContent = false, $type = NULL) {
        if(!empty($this->arrayCache[$table."-".($withContent ? 'yes' : 'no')]))
            return $this->arrayCache[$table."-".($withContent ? 'yes' : 'no')];

        $diff = new \cogpowered\FineDiff\Diff(new \cogpowered\FineDiff\Granularity\Character());

        $test = function($key, $tbl) use($diff, $withContent) {
            $fn = "gen".ucfirst($key);
            $path = 'na';
            switch($key) {
                case 'migration':
                    // else create a new file
                    $path = database_path("migrations/".date('Y_m_d_His')."_create_{$tbl}_table.php");

                    // If a file with "Schema::create('$tbl'" exists, then use that existing file
                    foreach(scandir($migrationsPath = database_path("migrations")) as $file) {
                        if($file === '.' || $file === '..') continue;
                        $fullFilePath = "$migrationsPath/$file";
                        if(!is_file($fullFilePath)) continue;
                        $content = file_get_contents($fullFilePath);
                        preg_match("/Schema::create\(['\"]{$tbl}['\"]/", $content, $matches);
                        if($matches)
                            // dd($fullFilePath, $content, $matches);
                            $path = $fullFilePath;
                    }
                    break;
                case 'routes':
                    $path = "web.php";
                    break;
                case 'controller':
                    $path = app_path("Http/Controllers/".$this->genClassName($tbl)."Controller.php");
                    break;
                case 'model':
                    $path = app_path("Models/".$this->genClassName($tbl).".php");
                    break;
                case 'view':
                    $path = resource_path("views/{$tbl}/generated_view.blade.php");
                    break;
                case 'edit':
                    $path = resource_path("views/{$tbl}/generated_edit.blade.php");
                    break;
                case 'list':
                    $path = resource_path("views/{$tbl}/generated_list.blade.php");
                    break;
                case 'seeder':
                    $path = database_path("seeds/".$this->genClassName($tbl)."Seeder.php");
                    break;
            }
            $content = $withContent ? $this->$fn($tbl) : '';
            return [
                $key => [
                    'path' => $path,
                    'exists' => $exists = file_exists($path),
                    'content' => $withContent ? $content : false,
                    'diff' => $withContent && file_exists($path) ? $diff->render(file_get_contents($path), $content) : false,
                    'different' => file_exists($path) ? (file_get_contents($path) !== $content) : false,
                ]
            ];
        };

        // if type is given, don't generate all possible things
        if($type !== NULL) {
            return $test($type, $table)[$type];
        }

        return $this->arrayCache[$table."-".($withContent ? 'yes' : 'no')] = [
             'schema' => $this->getInfos($table),
         ]
         + $test('migration', $table)
         + $test('routes', $table)
         + $test('controller', $table)
         + $test('model', $table)
         + $test('view', $table)
         + $test('edit', $table)
         + $test('list', $table)
         + $test('seeder', $table);
    }

    public function genSeeder($table) {
        $name        = $this->genClassName($table);
        $seederClass = "{$name}Seeder";

        $phpfile = new PhpFileBuilder($seederClass);

        $phpfile->imports[] = 'Illuminate\Database\Seeder';

        $phpfile->imports[] = "App\Models\\$name";

	    $phpfile->doc[] = "Seeder for table $table";
	    $phpfile->doc[] = "TODO: Don't forget to include `\$this->call($seederClass::class);` in DatabaseSeeder.php::run() method";

        $phpfile->extends = 'Seeder';

        $content = "try {\n";
        $content .= "            DB::beginTransaction();\n";
        $content .= "\n";

        foreach(DB::connection($this->connection)->table($table)->get() as $row) {
//            $content .= "            // ".json_encode($row)."\n";
            $arr = [];
            foreach($row as $key => $value)
                if(!in_array($key, ['id', 'created_at', 'updated_at', 'password']) && $value !== NULL) $arr[] = "'$key' => \"".str_replace(['"', "\r", "\n"], ['\"', '', '\n'], $value)."\"";
            $content .= "            $name::create([".implode(', ', $arr)."]);\n";
        }

        $content .= "\n";
        $content .= "            DB::commit();\n";
        $content .= "        } catch(Exception \$e) {\n";
        $content .= "            DB::rollback();\n";
        $content .= "            echo \$e;\n";
        $content .= "            exit;\n";
        $content .= "        }";

        $phpfile->functions[] = [
            'visibility' => 'public',
            'name' => 'run',
            'body' => $content
        ];

	    return $phpfile->__toString();
    }

    public function genMigration($table) {
        $phpfile = new PhpFileBuilder($this->genMigrationClassName($table));

        $phpfile->imports[] = 'Illuminate\Support\Facades\Schema';
        $phpfile->imports[] = 'Illuminate\Database\Schema\Blueprint';
        $phpfile->imports[] = 'Illuminate\Database\Migrations\Migration';

        $phpfile->extends = 'Migration';

        ob_start();

        $infos = $this->infos[$table];

        echo "Schema::create('{$infos['meta']['name']}', function (Blueprint \$table) {\n";

        $morphs = $this->getMorphs($infos);
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

        $allForeigns = [];
        if(isset($infos['meta']['foreign']) && count($infos['meta']['foreign']) > 0) {
            foreach($infos['meta']['foreign'] as $f) {
                // TODO detect max length and cut beginning of
                $alias = '';
//	            $indexname = $table.'_'.$f['col'].'_foreign';
//        	    $maxlen = 40;
//	            if(strlen($indexname) > $maxlen)
//		            $alias = ", 's".rand(10, 99).'_'.substr($indexname, 0 - $maxlen)."'";
                echo "            \$table->foreign('{$f['col']}'$alias)->references('{$f['refCol']}')->on('{$f['refTbl']}');\n";
                $allForeigns[] = $f['col'];
                $ret = array_search($f['col'], $infos['meta']['index'])."\n";
                if($ret !== FALSE) {
                    unset($infos['meta']['index'][intval($ret)]);
                }
            }
        }

        if(isset($infos['meta']['index']) && count($infos['meta']['index']) > 0) {
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

        echo "        });";

        $content = ob_get_clean();

        $phpfile->functions[] = [
            'visibility' => 'public',
            'name' => 'up',
            'doc' => ['Run the migrations.', '@return void'],
            'body' => $content
        ];

        return $phpfile->__toString();
    }

    public function genEdit($table) {
        $infos = $this->infos[$table];
        $tbl = $infos['meta']['name'];
        ob_start();

        echo "@if(isset(\$$tbl))
    <form action=\"{{ route('$tbl.update', \$${tbl}->id) }}\" method=\"POST\">
        <input type=\"hidden\" name=\"_method\" value=\"PUT\" />
@else
    <form action=\"{{ route('$tbl.store') }}\" method=\"POST\">
@endif
{!! csrf_field() !!}\n";
        echo "<table class=\"table\">\n";
        foreach($infos['cols'] as $col => $info) {

        	if(in_array($col, ['created_at', 'updated_at', 'deleted_at'])) {
		        continue;
	        } elseif($col == "id") {
		        echo "<input type=\"hidden\" name=\"$col\" value=\"{{ isset(\$$tbl) ? \$$tbl->$col : '' }}\">\n";
	        } else {
		        echo "<tr>\n";
		        echo "  <td>{{ __('$col') }}</td>\n";

		        if($info['type'] == 'boolean') {
			        echo "  <td><input type=\"checkbox\" name=\"$col\" value=\"1\"></td>\n";
		        } elseif($info['type'] == 'integer') {
                    echo "  <td><input type=\"number\" name=\"$col\" value=\"{{ isset(\$$tbl) ? \$$tbl->$col : '' }}\"></td>\n";
                } else {
                    echo "  <td><input name=\"$col\" value=\"{{ isset(\$$tbl) ? \$$tbl->$col : '' }}\"></td>\n";
                }

		        echo "</tr>\n";
	        }
        }
        echo "</table>\n";
        echo "<input type=\"submit\" value=\"{{ __('Save') }}\" />\n</form>\n";
        return ob_get_clean();
    }

    public function genView($table) {
        $infos = $this->infos[$table];
        $tbl = $infos['meta']['name'];
        ob_start();

        echo "<table class=\"table table-bordered table-hover\">\n";
        foreach($infos['cols'] as $col => $info) {
            echo "<tr>\n";
            echo "  <td>{{ __('$col') }}</td>\n";
            echo "  <td>{{ \$$tbl->$col }}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        return ob_get_clean();
    }

    public function genList($table) {
        $infos = $this->infos[$table];
        $tbl = $infos['meta']['name'];
        $tblSing = Str::singular($tbl);
        $letter = substr($tbl, 0, 1);
        ob_start();

        echo "<table class=\"table\">\n";
        echo "<tr>\n";
        foreach($infos['cols'] as $col => $info) {
            echo "  <td>{{ __('$col') }}</td>\n";
        }
	    echo "  <td></td>\n"; // Buttons
        echo "</tr>\n";
        echo "@foreach(\$$tbl as \$$letter)\n";
        echo "  <tr>\n";
        foreach($infos['cols'] as $col => $info) {
            if($info['type'] == 'boolean') {
                echo "  <td><input type=\"checkbox\" name=\"$col\" value=\"1\" @if(!empty(\$$letter->$col)) checked @endif readonly></td>\n";
            } else {
                echo "  <td>{{ \${$letter}->$col }}</td>\n";
            }
        }
	    echo "  <td>";
        echo "@can('{$tbl}_show')";
        echo "<a role=\"button\" href=\"{{ route('{$tbl}.show', \${$letter}->id) }}\" class=\"btn btn-default btn-sm\"><i class=\"fa fa-eye\"></i></a>";
    echo "@endcan";
    echo "@can('{$tbl}_edit')";
      echo "<a role=\"button\" href=\"{{ route('{$tbl}.edit', \${$letter}->id) }}\" class=\"btn btn-default btn-sm\"><i class=\"fa fa-pencil\"></i></a>";
    echo "@endcan";
  echo "</td>\n";
        echo "  </tr>\n";
        echo "@endforeach\n";
        echo "</table>\n";
        return ob_get_clean();
    }

    public function genController($table) {
        $infos = $this->infos[$table];
        $tableSing = Str::singular($table);
        $name  = $this->genClassName($table);

        $phpfile = new PhpFileBuilder("{$name}Controller", "App\Http\Controllers");

        $phpfile->imports[] = "Illuminate\\Http\\Request";
        $phpfile->imports[] = "App\\Models\\{$name}";

        $phpfile->extends = 'Controller';

        function mkfn($fnname, $doc, $body) {
            if(in_array("", $doc, true))
                $doc = array_merge($doc, ["@return \Illuminate\Http\Response"]);
            else
                $doc = array_merge($doc, ["", "@return \Illuminate\Http\Response"]);
            return [
                'name' => $fnname,
                'doc' => $doc,
                'body' => $body
            ];
        }

        ob_start();
        echo "// TODO complete validation
        \$data = \$this->validate(\$request, [\n";
        foreach($infos['cols'] as $col => $info) {
            if(in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))
                continue;
            echo "            '$col' => '',\n";
        }
        echo "
        ]);

        {$name}::create(\$data);

        return redirect(route(\"$table.index\"));";
        $contentStore = ob_get_clean();

        $phpfile->functions[] = mkfn('index', ["GET|HEAD  /$table", "Display a listing of the resource."], "return view(\"$table.list\", ['$table' => {$name}::all()]);");
        $phpfile->functions[] = mkfn('create', ["GET|HEAD  /$table/create", "Show the form for creating a new resource."], "return view(\"$table.edit\");");
        $phpfile->functions[] = mkfn('store', ["POST  /$table", "Store a newly created resource in storage.", "", "@param  \Illuminate\Http\Request  \$request"], $contentStore);
        $phpfile->functions[] = mkfn('show', ["GET|HEAD /$table/{{$tableSing}}", "Display the specified resource.", "", "@param  $name \$$tableSing"], "return view(\"$table.view\", [\"$table\" => \$$tableSing]);");
        $phpfile->functions[] = mkfn('edit', ["GET|HEAD /$table/{{$tableSing}}/edit", "Show the form for editing the specified resource.", "", "@param  $name \$$tableSing"], "return view(\"$table.edit\", [\"$table\" => \$$tableSing]);");
        $phpfile->functions[] = mkfn('update', ["PUT|PATCH /$table/{{$tableSing}}", "Update the specified resource in storage.", "", "@param  \Illuminate\Http\Request  \$request", "@param  $name \$$tableSing"], <<<HERE
\$newData = \$request->except(['_method', '_token', 'id']);
        \${$tableSing}->fill(\$newData);
        \${$tableSing}->save();
        return redirect(route("$table.show", [\${$tableSing}->id]));
HERE
);
        $phpfile->functions[] = mkfn('destroy', ["DELETE /$table/{{$tableSing}}", "Remove the specified resource from storage.", "", "@param  $name \$$tableSing", "@throws \Exception"], <<<HERE
\${$tableSing}->delete();
        return redirect(route("$table.index"));
HERE
);
        return $phpfile->__toString();
    }

    public function genRoutes($table) {
        $infos = $this->infos[$table];
        $name  = $this->genClassName($infos['meta']['name']).'Controller';
        ob_start();

        echo "Route::resource('{$infos['meta']['name']}', '$name');";
        // echo "Route::get('/', '$name@index');";

        return ob_get_clean();
    }

    public function genModel($table) {
	    $infos = $this->infos[$table];
	    $name  = $this->genClassName($infos['meta']['name']);

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
	    $dates    = [];
	    $casts    = '';
        $uses     = '';

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
				    break;
			    case 'decimal':
				    $phpfile->doc[] = "@property float$nullable $colname";
				    break;
			    case 'datetime':
				    $phpfile->doc[] = "@property Carbon$nullable $colname";
				    $dates[] = $colname;
				    break;
			    case 'text':
				    $phpfile->doc[] = "@property string$nullable $colname";
				    break;
			    case 'boolean':
				    $casts .= ", '$colname' => 'boolean'";
			    // NO break! So that the @property is generated
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
		    $tbl = $this->genClassName($cls['tbl']);
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

		    $phpfile->functions[] = [
		    	'visibility' => 'public',
		    	'name' => $cls['sgl'],
		    	'body' => "return \$this->hasOne('App\Models\\$clsName');",
			    'comment' => "TODO use {$cls['sgl']}() OR {$cls['tbl']}(), NOT both!"
		    ];
		    $phpfile->functions[] = [
		    	'visibility' => 'public',
		    	'name' => $cls['tbl'],
		    	'body' => "return \$this->hasMany('App\Models\\$clsName');"
		    ];
	    }
	    $phpfile->doc[] = "@package App\Models";

	    // Variables
	    if(count($fillable))
	    	$phpfile->vars[] = "protected \$fillable = ['".implode("','", $fillable)."']";
	    if(count($dates))
	    	$phpfile->vars[] = "protected \$dates    = ['".implode("','", $dates)."']";
	    if(strlen($casts))
		    $phpfile->vars[] = "protected \$casts    = [".(strlen($casts) ? substr($casts, 2) : '')."]";

	    return $phpfile->__toString();
    }

    /**
     * Gets morphs's for a table or NULL
     * @param $infos
     * @return string|null
     */
    private function getMorphs($infos) {
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

    private function hasTimestamps($infos) {
        $hasCreated = false;
        $hasUpdated = false;
        foreach($infos['cols'] as $col => $info) {
            if($col === 'created_at')
                $hasCreated = true;
            if($col === 'updated_at')
                $hasUpdated = true;
        }
        return $hasCreated && $hasUpdated;
    }
}
