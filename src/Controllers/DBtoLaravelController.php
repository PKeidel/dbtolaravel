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
        return view('dbtolaravel::welcome', [
            'tables' => $helper->getTables(),
            'connections' => array_keys(config('database.connections')),
            'connection' => $connection
        ]);
    }

    public function getInfos($connection, $table) {
        /** @var DBtoLaravelHelper $helper */
        $helper = app()->makeWith(\PKeidel\DBtoLaravel\DBtoLaravelHelper::class, ['connection' => $connection]);
        return [
            'infos' => $helper->getInfos($table),
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

	public function writeToFile($connection, $table, $key, $overwrite = FALSE) {
		$infos   = $this->getInfos($connection, $table);

		if(!isset($infos[$key]))
			return ['error' => "Key $key not found"];

		$content = $infos[$key];

		$file = '/tmp';

		switch($key) {
			case 'migration':
				$file  = date('Y_m_d_His')."_create_{$table}_table.php";
				$file = database_path("migrations/$file");
				break;
			case 'controller':
				$file  = ucfirst($table).'Controller';
				$file = app_path("Http/Controllers/$file.php");
				break;
			case 'model':
				$file  = ucfirst($table);
				$file = app_path("Models/$file.php");
				break;
		}

		if($overwrite === FALSE && file_exists($file))
			return ['error' => "File $file already exists", 'key' => 'file-exists'];

		return ['error' => file_put_contents($file, $content) === FALSE];
    }
}