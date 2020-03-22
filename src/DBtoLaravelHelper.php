<?php

namespace PKeidel\DBtoLaravel;

use cogpowered\FineDiff\Granularity\GranularityInterface;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\IntegerType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use PKeidel\DBtoLaravel\Generators\GenBladeEdit;
use PKeidel\DBtoLaravel\Generators\GenBladeList;
use PKeidel\DBtoLaravel\Generators\GenBladeView;
use PKeidel\DBtoLaravel\Generators\GenController;
use PKeidel\DBtoLaravel\Generators\GenMigration;
use PKeidel\DBtoLaravel\Generators\GenModel;
use PKeidel\DBtoLaravel\Generators\GenSeeder;

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

	public static function genClassName($table) {
		return ucfirst(Str::camel($table));
    }

	private function genMigrationClassName($table) {
		return "Create".self::genClassName($table)."Table";
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
                    $path = app_path("Http/Controllers/".self::genClassName($tbl)."Controller.php");
                    break;
                case 'model':
                    $path = app_path("Models/".self::genClassName($tbl).".php");
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
                    $path = database_path("seeds/".self::genClassName($tbl)."Seeder.php");
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

    public function genMigration($table) {
        $name  = $this->genMigrationClassName($table);
        $infos = $this->infos[$table];

        return GenMigration::generate($table, $name, $infos);
    }

    public function genModel($table) {
        $infos = $this->infos[$table];
        $name  = self::genClassName($infos['meta']['name']);

        return GenModel::generate($table, $name, $infos);
    }

    public function genView($table) {
        $infos = $this->infos[$table];

        return GenBladeView::generate($table, $infos);
    }

    public function genEdit($table) {
        $infos = $this->infos[$table];

        return GenBladeEdit::generate($table, $infos);
    }

    public function genList($table) {
        $infos = $this->infos[$table];
        $letter = $table[0];

        return GenBladeList::generate($table, $infos, $letter);
    }

    public function genController($table) {
        $infos = $this->infos[$table];
        $name  = self::genClassName($table);
        return GenController::generate($table, $name, $infos);
    }

    public function genRoutes($table) {
        $infos = $this->infos[$table];
        $name  = self::genClassName($infos['meta']['name']).'Controller';
        ob_start();

        echo "Route::resource('{$infos['meta']['name']}', '$name');";

        return ob_get_clean();
    }

    public function genSeeder($table) {
        $name        = self::genClassName($table);
        $seederClass = "{$name}Seeder";

        return GenSeeder::generate($table, $name, $seederClass, DB::connection($this->connection)->table($table)->get());
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
