<?php

namespace PKeidel\DBtoLaravel\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PKeidel\DBtoLaravel\DBtoLaravelHelper;

class DBtoLaravelController extends Controller {

    // http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-manager.html

    public function redirect() {
        $connection = config('database.default');
        return redirect("/dbtolaravel/$connection");
    }

    public function table($connection) {

        if (request()->has('connection'))
            return redirect("/dbtolaravel/".request()->get('connection'));

        if($connection == NULL)
            $connection = request()->has('connection') ? request()->get('connection') : config('database.default');

        if(config('database.connections.'.$connection) == null) {
            return view('dbtolaravel::error-wrongconnection', [
                'message' => "Connection '$connection' not configured",
                'connections' => array_keys(config('database.connections'))
            ]);
        }

        /** @var DBtoLaravelHelper $helper */
        $helper = app()->makeWith(DBtoLaravelHelper::class, ['connection' => $connection]);

        return view('dbtolaravel::table', [
            'helper' => $helper,
            'connection' => $connection,
            'connections' => array_keys(config('database.connections')),
        ]);
    }

    // GET {connection}/render/{table}/{type}
    public function render($connection, $table, $type) {
        /** @var DBtoLaravelHelper $helper */
        $helper = app()->makeWith(DBtoLaravelHelper::class, ['connection' => $connection]);
        return $helper->getArrayForTable($table, true, $type);
    }

    // GET {connection}/render/{table}/{type}/diff
    public function renderDiff($connection, $table, $type) {
        /** @var DBtoLaravelHelper $helper */
        $helper = app()->makeWith(DBtoLaravelHelper::class, ['connection' => $connection]);
        return $helper->getArrayForTable($table, true)[$type];
    }

    // PUT write
	public function writeToFile(Request $request) {
		$file      = $request->input('file');
		$content   = $request->input('content');
		$overwrite = $request->input('overwrite');

		if($overwrite === FALSE && file_exists($file))
			return ['error' => "File $file already exists", 'key' => 'file-exists'];

		$dir = dirname($file);

		try {
            if(!file_exists($dir))
                mkdir($dir, 0777, true);
            $erg = file_put_contents($file, $content);
            if($erg === FALSE)
                return ['error' => true];
        } catch (\Exception $e) {

		    return ['error' => get_class($e).': '.$e->getMessage()];
        }

		return ['error' => false];
    }
}
