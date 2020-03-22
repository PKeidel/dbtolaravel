<?php

namespace PKeidel\DBtoLaravel\Generators;

class GenBladeEdit {

    public static function generate($table, $infos) {
        ob_start();

        echo "@if(isset(\$$table))
    <form action=\"{{ route('$table.update', [\$${table}->id]) }}\" method=\"POST\">
        <input type=\"hidden\" name=\"_method\" value=\"PUT\" />
@else
    <form action=\"{{ route('$table.store') }}\" method=\"POST\">
@endif
{!! csrf_field() !!}\n";
        echo "<table class=\"table\">\n";
        foreach($infos['cols'] as $col => $info) {

            if(in_array($col, ['created_at', 'updated_at', 'deleted_at'])) {
                continue;
            } elseif($col == "id") {
                echo "<input type=\"hidden\" name=\"$col\" value=\"{{ isset(\$$table) ? \$$table->$col : '' }}\">\n";
            } else {
                echo "<tr>\n";
                echo "  <td>{{ __('$col') }}</td>\n";

                if($info['type'] == 'boolean') {
                    echo "  <td><input type=\"checkbox\" name=\"$col\" value=\"1\"></td>\n";
                } elseif($info['type'] == 'integer') {
                    echo "  <td><input type=\"number\" name=\"$col\" value=\"{{ isset(\$$table) ? \$$table->$col : '' }}\"></td>\n";
                } else {
                    echo "  <td><input name=\"$col\" value=\"{{ isset(\$$table) ? \$$table->$col : '' }}\"></td>\n";
                }

                echo "</tr>\n";
            }
        }
        echo "</table>\n";
        echo "<input type=\"submit\" value=\"{{ __('Save') }}\" />\n</form>\n";
        return ob_get_clean();
    }
}
