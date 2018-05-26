<?php

namespace PKeidel\DBtoLaravel;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\IntegerType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Log;

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

    public function __construct($connection = NULL) {
        $this->connection = !empty($connection) ? $connection : config('database.default');
        $this->driver     = config("database.connections.$connection")['driver'];

        $infos = Cache::remember("dbtolaravel:tables:1:$connection", 10, function() {
	        /** @var MySqlConnection $con */
	        $con = DB::connection($this->connection);

	        // Set up some mappings
	        // Allow user to type some own mappings into some textfield "enum=string"
	        $platform = $con->getDoctrineConnection()->getDatabasePlatform();
	        $platform->registerDoctrineTypeMapping('enum', 'string');

	        $this->manager    = $con->getDoctrineSchemaManager();

	        $tables = $this->manager->listTableNames();
	        $infos  = [];
	        foreach($tables as $tbl) {



		        $colsTmp       = $this->manager->listTableColumns($tbl);
		        $cols          = [];
		        $dependson     = [];
		        $foreigns      = [];
		        $belongsTo     = [];
		        $belongsToMany = [];

		        // $cols verarbeiten und $meta generieren
		        /** @var Column $col */
		        foreach($colsTmp as $col) {
			        $cols[$col->getName()] = [
				        'type'          => $col->getType()->getName(),
				        'len'           => $col->getLength(),
				        'unsigned'      => $col->getUnsigned(),
				        'null'          => !$col->getNotnull(),
				        'autoincrement' => $col->getAutoincrement(),
				        'default'       => $col->getDefault(),
				        'comment'       => $col->getComment(),
			        ];

			        // TODO belongsTo
			        // Wenn der Name %_id ist, dann ist es ein belongsTo zu der anderen Tabelle
			        // TODO außer es existiert auch eine %_type Spalte, dann ist es ein morph
			        if(substr($col->getName(), -3) === '_id') {
			        	$tblName = substr($col->getName(), 0, -3);
			        	if(in_array($tblName, $tables))
			        	    $belongsTo[] = [
			        	    	'tbl' => $tblName,
					            'cls' => ucfirst($tblName),
					            'sgl' => str_singular($tblName)
				            ];
			        }
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
			        'useSoftDelete' => isset($cols['deleted_at']),
			        'useTimestamps' => isset($cols['created_at']) && isset($cols['updated_at'])
		        ], 'cols' => $cols];
	        }

	        // Und noch ne Runde, für die Verknüpfungen
	        foreach($tables as $tbl) {
		        $islinktable = false;
		        $inf = explode('_', $tbl);
		        if(count($inf) === 2 && in_array($inf[0], $tables) && in_array($inf[1], $tables)) {
			        $infos[$tbl]['meta']['islinktable'] = true;
			        $infos[$inf[0]]['meta']['belongsToMany'][] = [
			        	'tbl' => ucfirst($inf[1]),
				        'fnc' => str_singular($inf[1])
			        ];
			        $infos[$inf[1]]['meta']['belongsToMany'][] = [
			        	'tbl' => ucfirst($inf[0]),
				        'fnc' => str_singular($inf[0])
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
					        'sgl' => str_singular($tbl)
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
		return ucfirst(camel_case($table));
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

    public function getArrayForTable($table, $withContent = false) {

        if(!empty($this->arrayCache[$table."-".($withContent ? 'yes' : 'no')]))
            return $this->arrayCache[$table."-".($withContent ? 'yes' : 'no')];

        $diff = new \cogpowered\FineDiff\Diff();

        $test = function($key, $tbl) use($diff, $withContent) {
            $fn = "gen".ucfirst($key);
            $path = 'na';
            switch($key) {
                case 'migration':
                    $path = database_path("migrations/".date('Y_m_d_His')."_create_{$tbl}_table.php");
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
                    $path = resource_path("views/{$tbl}/view.blade.php");
                    break;
                case 'edit':
                    $path = resource_path("views/{$tbl}/edit.blade.php");
                    break;
                case 'list':
                    $path = resource_path("views/{$tbl}/list.blade.php");
                    break;
                case 'seeder':
                    $path = database_path("seeds/".$this->genClassName($tbl)."Seeder.php");
                    break;
            }
            $content = $withContent ? $this->$fn($tbl) : '';
//            $content = $this->$fn($tbl);
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
        $infos = $this->infos[$table];
        $name  = $this->genClassName($infos['meta']['name']);
        $phpfile = new PhpFileBuilder("{$name}Seeder");

        $phpfile->imports[] = 'Illuminate\Database\Seeder';

	    $phpfile->doc[] = "Seeder for table $table";

        $phpfile->extends = 'Seeder';

        $content = "try {\n";
        $content .= "            DB::beginTransaction();\n";
        $content .= "\n";

        foreach(DB::connection($this->connection)->table($table)->get() as $row) {
//            $content .= "            // ".json_encode($row)."\n";
            $arr = [];
            foreach($row as $key => $value)
                if(!in_array($key, ['id', 'created_at', 'updated_at', 'password']) && $value !== NULL) $arr[] = "'$key' => '".str_replace("'", "\'", $value)."'";
            $content .= "            DB::table('$table')->insert([".implode(', ', $arr)."]);\n";
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
        $infos = $this->infos[$table];
        ob_start();

        echo "<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

";

        echo "class ".$this->genMigrationClassName($table)." extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('{$infos['meta']['name']}', function (Blueprint \$table) {\n";

        $morphs = $this->getMorphs($infos);
        if($morphs !== NULL) {
            unset($infos['meta']['index']);
            unset($infos['cols']["{$morphs}_type"]);
        }

        foreach($infos['cols'] as $col => $info) {
            /** @var Column $info */
            $info = (object) $info;

//            if($col == 'used')
//                dd($info);

            $laravelType = strtolower($info->type);
            $colName     = "'$col'";
            $extra       = '';

            if($info->null) {
                $extra .= '->nullable()';
            }

            // Ignore some columns, they are handled elsewhere
            if(in_array($col, ['updated_at', 'deleted_at', 'created_at']))
                continue;

            if("{$morphs}_id" === $col) {
                $laravelType = 'morphs';
                $colName     = "'$morphs'";
            } elseif($info->type === 'integer') {

                $laravelType = 'integer';
                if(isset($info->len) && $info->len != 10) {
                    $colName = "'$col', $info->len";
                }

                if($info->autoincrement)
                    $laravelType = 'increments';
                elseif($info->unsigned)
                    $laravelType = 'unsignedInteger';
                if($info->len == 1) {
                    $laravelType = 'boolean';
                    $colName     = "'$col'";
                }

            } elseif($info->type === 'bigint') {

                $laravelType = 'bigInteger';
                if($info->unsigned)
                    $extra .= '->unsigned()';
                if(!empty($info->len) && $info->len != 20)
                    $colName = "'$col', $info->len";

                if($info->autoincrement)
                    $laravelType = 'bigIncrements';

            } elseif($info->type === 'string') {
                if(!empty($info->len) && $info->len != 255)
                    $colName = "'$col', $info->len";
            } elseif($info->type === 'longtext') {
                $laravelType = 'longText';
            } elseif($info->type === 'tinyint') {
                $laravelType = 'tinyInteger';
                if($info->unsigned)
                    $extra .= '->unsigned()';
                if($info->len == 1)
                    $laravelType = 'boolean';
            }

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

            echo "            \$table->$laravelType($colName)$extra;\n";
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

        echo "        });
    }
}";

        return ob_get_clean();
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
        echo "{!! button(__('Save'), 'submit', 'primary') !!}\n</form>\n";
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
        $tblSing = str_singular($tbl);
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
	    echo "  <td>
    @can('{$tbl}_show')
      <a role=\"button\" href=\"{{ route('{$tbl}.show', \${$letter}->id) }}\" class=\"btn btn-default btn-sm\"><i class=\"fa fa-eye\"></i></a>
    @endcan
    @can('{$tbl}_edit')
      <a role=\"button\" href=\"{{ route('{$tbl}.edit', \${$letter}->id) }}\" class=\"btn btn-default btn-sm\"><i class=\"fa fa-pencil\"></i></a>
    @endcan
  </td>\n";
        echo "  </tr>\n";
        echo "@endforeach\n";
        echo "</table>\n";
        return ob_get_clean();
    }

    public function genController($table) {
        $infos = $this->infos[$table];
        $tableSing = str_singular($table);
        $name  = $this->genClassName($table);

        ob_start();

        echo <<<HERE
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\\{$name};

class {$name}Controller extends Controller {
    /**
     * GET|HEAD  /$table
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        return view("$table.list", ['$table' => {$name}::all()]);
    }

    /**
     * GET|HEAD  /$table/create
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        return view("$table.edit");
    }

    /**
     * POST  /$table
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  \$request
     * @return \Illuminate\Http\Response
     */
    public function store(Request \$request) {
        // TODO complete validation
        \$data = \$this->validate(\$request, [\n
HERE;
        foreach($infos['cols'] as $col => $info) {
            if(in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))
                continue;
            echo "            '$col' => '',\n";
        }
        echo <<< HERE
        ]);

        {$name}::create(\$data);

        return redirect(route("$table.index"));
    }

    /**
     * GET|HEAD /$table/{$tableSing}
     * Display the specified resource.
     *
     * @param  $name \$$tableSing
     * @return \Illuminate\Http\Response
     */
    public function show($name \$$tableSing) {
        return view("$table.view", ["$table" => \$$tableSing]);
    }

    /**
     * GET|HEAD /$table/\{$tableSing}/edit
     * Show the form for editing the specified resource.
     *
     * @param  $name \$$tableSing
     * @return \Illuminate\Http\Response
     */
    public function edit($name \$$tableSing) {
        return view("$table.edit", ["$table" => \$$tableSing]);
    }

    /**
     * PUT|PATCH /$table/{$tableSing}
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  \$request
     * @param  $name \$$tableSing
     * @return \Illuminate\Http\Response
     */
    public function update(Request \$request, $name \$$tableSing) {
        \$newData = \$request->except(['_method', '_token', 'id']);
        \${$tableSing}->fill(\$newData);
        \${$tableSing}->save();
        return redirect(route("$table.show", [\${$tableSing}->id]));
    }

    /**
     * DELETE /$table/\{$tableSing}
     * Remove the specified resource from storage.
     *
     * @param  $name \$$tableSing
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy($name \$$tableSing) {
        \${$tableSing}->delete();
        return redirect(route("$table.index"));
    }
}
HERE;

        return ob_get_clean();
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
		    $phpfile->doc[] = "@property-read \App\Models\\$cls ".str_singular($info['tbl'])." // from belongsTo";

		    $phpfile->functions[] = [
		    	'visibility' => 'public',
		    	'name' => str_singular($info['tbl']),
		    	'body' => "return \$this->belongsTo('App\Models\\$cls');"
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
