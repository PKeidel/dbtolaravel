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
}