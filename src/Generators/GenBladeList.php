<?php

namespace PKeidel\DBtoLaravel\Generators;

class GenBladeList {

    public static function generate($table, $infos, $letter) {
        ob_start();
        echo "<table class=\"table\">\n";
        echo "<tr>\n";
        foreach($infos['cols'] as $col => $info) {
            echo "  <td>{{ __('$col') }}</td>\n";
        }
        echo "  <td></td>\n"; // Buttons
        echo "</tr>\n";
        echo "@foreach(\$$table as \$$letter)\n";
        echo "  <tr>\n";
        foreach($infos['cols'] as $col => $info) {
            if($info['type'] == 'boolean') {
                echo "  <td><input type=\"checkbox\" name=\"$col\" value=\"1\" @if(!empty(\$$letter->$col)) checked @endif readonly></td>\n";
            } else {
                echo "  <td>{{ \${$letter}->$col }}</td>\n";
            }
        }
        echo "  <td>";
        echo "@can('{$table}_show')";
        echo "<a role=\"button\" href=\"{{ route('{$table}.show', \${$letter}->id) }}\" class=\"btn btn-default btn-sm\"><i class=\"fa fa-eye\"></i></a>";
        echo "@endcan";
        echo "@can('{$table}_edit')";
        echo "<a role=\"button\" href=\"{{ route('{$table}.edit', \${$letter}->id) }}\" class=\"btn btn-default btn-sm\"><i class=\"fa fa-pencil\"></i></a>";
        echo "@endcan";
        echo "</td>\n";
        echo "  </tr>\n";
        echo "@endforeach\n";
        echo "</table>\n";
        return ob_get_clean();
    }
}
