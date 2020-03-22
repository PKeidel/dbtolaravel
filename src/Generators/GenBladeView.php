<?php

namespace PKeidel\DBtoLaravel\Generators;

class GenBladeView {

    public static function generate($table, $infos) {
        ob_start();
        echo "<table class=\"table table-bordered table-hover\">\n";
        foreach($infos['cols'] as $col => $info) {
            echo "<tr>\n";
            echo "  <td>{{ __('$col') }}</td>\n";
            echo "  <td>{{ \$$table->$col }}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        return ob_get_clean();
    }
}
