<?php

namespace PKeidel\DBtoLaravel;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\IntegerType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/*
TODO Support morphs
*/

class DBtoLaravelHelper {

    private $tables = [];
    private $infos  = [];

    private $connection;
    private $driver;
    /** @var  AbstractSchemaManager $manager */
    private $manager;

    public function __construct($connection = NULL) {
        $this->connection = isset($connection) ? $connection : config('database.default');
        $this->driver     = config("database.connections.$connection")['driver'];

        $this->manager    = DB::connection($this->connection)->getDoctrineSchemaManager();

        $tables = $this->manager->listTableNames();
        $infos  = [];
        foreach($tables as $tbl) {
            $colsTmp = $this->manager->listTableColumns($tbl);
            $cols    = [];

            // $cols verarbeiten und $meta generieren
            /** @var Column $col */
            foreach($colsTmp as $col) {
                $cols[$col->getName()] = [
                    'line'          => 'na',
                    'matches'       => '',
                    'type'          => $col->getType()->getName(),
                    'len'           => $col->getLength(),
                    'unsigned'      => $col->getUnsigned(),
                    'null'          => !$col->getNotnull(),
                    'autoincrement' => $col->getAutoincrement(),
                    'default'       => $col->getDefault(),
                    'comment'       => $col->getComment(),
                ];
            }

            $dependson = [];
            $foreigns  = [];
            $tmp       = $this->manager->listTableForeignKeys($tbl);
            foreach($tmp as $foreign) {
                /** ForeignKeyConstraint $foreign */
                if(!in_array($foreign->getForeignTableName(), $dependson))
                    $dependson[] = $foreign->getForeignTableName();

                $foreigns[] = ['col' => $foreign->getLocalColumns()[0], 'refCol' => $foreign->getForeignColumns()[0], 'refTbl' => $foreign->getForeignTableName()];
            }


            $infos[$tbl] = ['meta' => [
                'name' => $tbl,
                'index' => $this->manager->listTableIndexes($tbl),
                'foreign' => $foreigns,
                'dependson' => $dependson,
                'hasMany' => [],
                'belongsTo' => [],
                'useSoftDelete' => isset($cols['deleted_at']),
                'useTimestamps' => isset($cols['created_at']) && isset($cols['updated_at'])
            ], 'cols' => $cols];
        }

        // hasMany hinzufügen:
        // - die in ['foreign'] genannten Tabellen haben ein hasMany auf die akuelle Tabelle
        foreach ($infos as $tbl => $info) {
            foreach($info['meta']['foreign'] as $foreign) {
                $infos[$foreign['refTbl']]['meta']['hasMany'][] = ['tbl' => ucfirst($tbl), 'fnc' => $tbl];
                $infos[$tbl]['meta']['belongsTo'][] = ['tbl' => ucfirst($foreign['refTbl']), 'fnc' => str_singular($foreign['refTbl'])];
            }
        }

        $this->infos = $infos;

	    $this->tables = array_keys($this->infos);
    }

    public function getInfos($table = NULL) {
        return isset($table) ? $this->infos[$table] : $this->infos;
    }

    public function getTables() {
        return $this->tables;
    }

    public function genMigration($table) {

        $infos = $this->infos[$table];

        ob_start();

        echo "// Table: {$infos['meta']['name']}\nSchema::create('{$infos['meta']['name']}', function (Blueprint \$table) {\n";

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
                if($info->len != 20)
                    $colName = "'$col', $info->len";

                if($info->autoincrement)
                    $laravelType = 'bigIncrements';

            } elseif($info->type === 'string') {
                if($info->len != 255)
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
                $extra .= "->comment('$info->comment')";

            echo "    \$table->$laravelType($colName)$extra;\n";
        }

        if($infos['meta']['useTimestamps'])
            echo "    \$table->timestamps();\n";

        if($infos['meta']['useSoftDelete'])
            echo "    \$table->softDeletes();\n";

        $allForeigns = [];
        if(isset($infos['meta']['foreign']) && count($infos['meta']['foreign']) > 0) {
            foreach($infos['meta']['foreign'] as $f) {
                echo "    \$table->foreign('{$f['col']}')->references('{$f['refCol']}')->on('{$f['refTbl']}');\n";
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
                if($index->isSimpleIndex()) {
                    echo "    \$table->index(".json_encode($cols)."); // isSimpleIndex => ".$index->getName()."\n";
                } elseif($index->isUnique()) {
                    if(count($cols) != 1 || $cols[0] != 'id')
                        echo "    \$table->unique(".json_encode($cols)."); // isUnique => ".$index->getName()."\n";
                } elseif($index->isPrimary()) {
                    echo "    \$table->primary(".json_encode($cols)."); // isPrimary => ".$index->getName()."\n";
                } else {
                    dd($index);
                }
            }
        }

        echo "});\n\n";

        return ob_get_clean();
    }

    public function genBladeEdit($table) {

        $infos = $this->infos[$table];

        $tbl = $infos['meta']['name'];
        ob_start();
        echo "@if(isset(\$$tbl))
    <form action=\"{{ route('$tbl.update', \$${tbl}->id) }}\" method=\"POST\">
@else
    <form action=\"{{ route('$tbl.store') }}\" method=\"PUT\">
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

    public function genBladeView($table) {

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

    public function getBladeList($table) {

        $infos = $this->infos[$table];

        $tbl = $infos['meta']['name'];

        $letter = substr($tbl, 0, 1);

        ob_start();
        echo "<table class=\"table\">\n";
        echo "<tr>\n";
        foreach($infos['cols'] as $col => $info) {
            echo "  <td>{{ __('$col') }}</td>\n";
        }
        echo "</tr>\n";
        echo "@foreach(\$$tbl as \$$letter)\n";
        echo "  <tr>\n";
        foreach($infos['cols'] as $col => $info) {
            if($info['type'] == 'boolean') {
                echo "  <td><input type=\"checkbox\" name=\"$col\" value=\"1\" @if(!empty(\$$letter->$col)) checked @endif readonly></td>\n";
            } else {
                echo "  <td>{{ \$$letter->$col }}</td>\n";
            }
        }
        echo "  </tr>\n";
        echo "@endforeach\n";
        echo "</table>\n";
        return ob_get_clean();
    }

    public function genController($table) {

        $infos = $this->infos[$table];

        $name  = ucfirst($infos['meta']['name']);

        ob_start();

        echo <<<HERE
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class {$name}Controller extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        return view("$table.list");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        return view("$table.edit");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  \$request
     * @return \Illuminate\Http\Response
     */
    public function store(Request \$request) {
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
     * Display the specified resource.
     *
     * @param  $name \$$table
     * @return \Illuminate\Http\Response
     */
    public function show($name \$$table) {
        return view("$table.view", ["$table" => \$$table]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  $name \$$table
     * @return \Illuminate\Http\Response
     */
    public function edit($name \$$table) {
        return view("$table.edit", ["$table" => \$$table]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  \$request
     * @param  int  \$id
     * @return \Illuminate\Http\Response
     */
    public function update(Request \$request, \$id) {}

    /**
     * Remove the specified resource from storage.
     *
     * @param  $name \$$table
     * @return \Illuminate\Http\Response
     */
    public function destroy($name \$$table) {
        \${$table}->delete();
        return view("$table.list");
    }
}
HERE;

        return ob_get_clean();
    }

    public function genRoutes($table) {

        $infos = $this->infos[$table];

        $name  = ucfirst($infos['meta']['name']).'Controller';

        ob_start();

        echo "Route::resource('{$infos['meta']['name']}', '$name');\n\n";
        echo "Route::get('/', '$name@index');";

        return ob_get_clean();
    }

    public function genModel($table) {

	    $infos = $this->infos[$table];

	    $name  = ucfirst($infos['meta']['name']);

	    $fillable = [];
	    $dates    = [];
	    $casts    = '';
        $uses     = '';


        if($infos['meta']['useSoftDelete'])
            $uses = "\n    use SoftDeletes;\n";

	    ob_start();

    	echo "<?php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
".($infos['meta']['useSoftDelete'] ? "use Illuminate\Database\Eloquent\SoftDeletes;\n" : '')."
/**
 * Model $name
 *\n";

        foreach ($infos['cols'] as $colname => $col) {
            $col = (object) $col;
            if(!in_array($colname, ['id', 'created_at', 'updated_at', 'deleted_at']))
                $fillable[] = $colname;
            switch($col->type) {
                case 'integer':
                case 'bigint':
                    echo " * @property int $colname\n";
                    break;
                case 'decimal':
                    echo " * @property float $colname\n";
                    break;
                case 'datetime':
                    echo " * @property Carbon $colname\n";
                    $dates[] = $colname;
                    break;
                case 'text':
                    echo " * @property string $colname\n";
                    break;
                case 'boolean':
                    $casts .= ", '$colname' => 'boolean'";
                default:
                    echo " * @property $col->type $colname\n"; // unknown:
                    break;
            }
        }

        $fillable = count($fillable) ? "\n    protected \$fillable = ['".implode("','", $fillable)."'];" : '';
        $dates    = count($dates) ? "\n    protected \$dates = ['".implode("','", $dates)."'];" : '';
        $casts    = strlen($casts) ? "\n    protected \$casts = [".(strlen($casts) ? substr($casts, 2) : '')."];" : '';

        echo "*/
class $name extends Model {{$uses}
    protected \$table    = '{$infos['meta']['name']}';{$fillable}{$dates}{$casts}";

        // hasMany
        foreach($infos['meta']['hasMany'] as $cls) {
            $tbl = $cls['tbl'];
            echo "\n\n    public function {$cls['fnc']}() {
       return \$this->hasMany('App\Models\\$tbl');
    }\n";
        }

        // belongsTo
        foreach($infos['meta']['belongsTo'] as $cls) {
            $tbl = $cls['tbl'];
            echo "\n\n    public function {$cls['fnc']}() {
       return \$this->belongsTo('App\Models\\$tbl');
    }\n";
        }


        echo "\n}\n";

    	return ob_get_clean();
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
