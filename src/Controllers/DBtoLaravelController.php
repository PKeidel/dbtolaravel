<?php

namespace PKeidel\DBtoLaravel\Controllers;

use App\Http\Controllers\Controller;
use PKeidel\DBtoLaravel\DBtoLaravelHelper;

class DBtoLaravelController extends Controller {

    // http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-manager.html

    public function redirect() {
        $connection = config('database.default');
        return redirect("/dbtolaravel/$connection");
    }

    public function welcome($connection) {

        if (request()->has('connection')) {
            return redirect("/dbtolaravel/".request()->get('connection'));
        }

        if($connection == NULL)
            $connection = request()->has('connection') ? request()->get('connection') : config('database.default');

        if(config('database.connections.'.$connection) == null) {
            return view('dbtolaravel::error-wrongconnection', [
                'message' => "Connection '$connection' not configured",
                'connections' => array_keys(config('database.connections'))
            ]);
        }

        /** @var DBtoLaravelHelper $helper */
        $helper = app()->makeWith(\PKeidel\DBtoLaravel\DBtoLaravelHelper::class, ['connection' => $connection]);
//        $infos  = array_map(function($i) {
//        	return $i['meta']['islinktable'];
//        }, $helper->getInfos());
        $infos = [];
        foreach($helper->getInfos() as $info)
        	$infos[$info['meta']['name']] = $info['meta']['islinktable'];

        return view('dbtolaravel::welcome', [
            'tables' => $infos,
            'connections' => array_keys(config('database.connections')),
            'connection' => $connection
        ]);
    }

    public function getInfos($connection, $table) {
        /** @var DBtoLaravelHelper $helper */
        $helper = app()->makeWith(\PKeidel\DBtoLaravel\DBtoLaravelHelper::class, ['connection' => $connection]);
        return [
            'infos' => [
	            'schema' => $helper->getInfos($table),
	            'files' => [
		            'migration' => database_path("migrations/".date('Y_m_d_His')."_create_{$table}_table.php"),
		            'controller' => app_path("Http/Controllers/".$helper->genClassName($table)."Controller.php"),
		            'model' => app_path("Models/".$helper->genClassName($table).".php"),
	                'blades:list' => resource_path("views/$table/list.blade.php"),
	                'blades:view' => resource_path("views/$table/view.blade.php"),
	                'blades:edit' => resource_path("views/$table/edit.blade.php")
	            ]
            ],
            'migration' => $helper->genMigration($table),
            'routes' => $helper->genRoutes($table),
            'controller' => $helper->genController($table),
            'model' => $helper->genModel($table),
            'blades' => [
                'view' => $helper->genBladeView($table),
                'edit' => $helper->genBladeEdit($table),
                'list' => $helper->getBladeList($table)
            ]
        ];
    }

    public function getAllInfos($connection) {
        /** @var DBtoLaravelHelper $helper */
        $helper = app()->makeWith(\PKeidel\DBtoLaravel\DBtoLaravelHelper::class, ['connection' => $connection]);
	    return $helper->getInfos();
    }

    // PUT
	public function writeToFile($connection, $table, $key, $overwrite = FALSE) {
		$infos   = $this->getInfos($connection, $table);

		$cat = $key;
		if(strpos($key, ':') !== FALSE) {
			$cat    = explode(':', $key)[0];
			$subkey = explode(':', $key)[1];
		}

		if(!isset($infos[$cat]))
			return ['error' => "Subkey $cat not found"];

		$content = !empty($subkey) ? $infos[$cat][$subkey] : $infos[$key];

		$file = request()->get('file') ?? $infos['files'][$key];

		if($file == '-')
			return ['error' => "No valid filename could be generated", 'key' => 'file-invalid'];

		if($overwrite === FALSE && file_exists($file))
			return ['error' => "File $file already exists", 'key' => 'file-exists'];

		$dir = dirname($file);
		if(!file_exists($dir))
			mkdir($dir);

		return ['error' => file_put_contents($file, $content) === FALSE];
    }
}