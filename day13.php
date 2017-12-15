<?php

ini_set('memory_limit', '512M');
define('DEBUG', false);

if (isset($argv[1])) {
    $input = $argv[1];
} else {
    $input = '';
}

class Firewall
{
    public $firewall = array();
    public $max_depth = 0;
    public $current_packet_column = -1;
    public $severity = 0;
    public $debug = false;

    public function __construct($input, $debug = false)
    {
        $this->debug = $debug;

        $firewall_definition_strings = explode("\n", $input);

        $firewall_definitions = array();
        foreach ($firewall_definition_strings as $firewall_definition_string) {
            $parts = explode(': ', $firewall_definition_string);
            $firewall_definitions[$parts[0]] = $parts[1];
        }

        $max_firewall_column_index = max(array_keys($firewall_definitions));

        for ($i = 0; $i <= $max_firewall_column_index; $i++) {
            $firewall_column = array();

            // if we have a scanner, add it's depth of elements
            if (isset($firewall_definitions[$i])) {
                $firewall_column['depth'] = $firewall_definitions[$i];

                if ($firewall_column['depth'] > $this->max_depth) {
                    $this->max_depth = $firewall_column['depth'];
                }
                for ($j = 0; $j < $firewall_column['depth']; $j++) {
                    $firewall_column['scanner_positions'][$j] = false;
                }

                // set he initial scanner position as the first element
                $firewall_column['scanner_positions'][0] = true;

                if ($firewall_column['depth'] > 1) {
                    $firewall_column['scanner_position_change'] = 1;
                } else {
                    $firewall_column['scanner_position_change'] = 0;
                }
            } else {
                // add a single depth, with no active scanner position
                //$this->firewall[$i][0] = false;
            }

            $this->firewall[$i] = $firewall_column;
        }

        if ($this->debug) {
            echo ('Initial State: ' . "\n");
            $this->printFirewall();
        }
    }

    function printFirewall()
    {
        $output = '';
        foreach ($this->firewall as $column => $scanner) {
            $output .= str_pad($column, 4, ' ', STR_PAD_BOTH);
        }

        $output .= "\n";
        for ($i = 0; $i < $this->max_depth; $i++) {
            foreach ($this->firewall as $column => $scanner) {
                if (isset($scanner['scanner_positions'][$i])) {
                    if ($scanner['scanner_positions'][$i]) {
                        $column_output = 'S';
                    } else {
                        $column_output = ' ';
                    }

                    if ($column == $this->current_packet_column && $i == 0) {
                        $output .= '(' . $column_output . ') ';
                    } else {
                        $output .= '[' . $column_output . '] ';
                    }
                } elseif ($column == $this->current_packet_column && $i == 0) {
                    $output .= '(.) ';
                } else {
                    $output .= ' .  ';
                }
            }
            $output .= "\n";
        }

        echo $output . "\n";
    }

    function isCaught()
    {
        if (!isset($this->firewall[$this->current_packet_column]['scanner_positions'])) {
            return false;
        }

        if ($this->firewall[$this->current_packet_column]['scanner_positions'][0] == true) {
            return true;
        } else {
            return false;
        }
    }

    function walk()
    {
        $this->current_packet_column++;

        if ($this->debug) {
            echo ('Picosecond ' . $this->current_packet_column . ': ' . "\n");
            $this->printFirewall();
        }

        if ($this->isCaught()) {
            $severity = $this->current_packet_column * $this->firewall[$this->current_packet_column]['depth'];

            if ($this->debug) {
                echo ('Caught in column: ' . $this->current_packet_column . ', severity: ' . $severity . "\n");
            }

            $this->severity += $severity;
        }

        foreach ($this->firewall as $firewall_column_index => &$firewall_column) {
            //echo ('update firewall_column: ' . $firewall_column_index . "\n");

            // if this firewall column has a scanner, move it
            if (isset($firewall_column['scanner_positions'])) {
                //echo ('have scanner, update it' . "\n");
                $scanner_index = array_search(true, $firewall_column['scanner_positions']);

                if ($scanner_index !== false) {
                    //echo ('scanner index: ' . $scanner_index . "\n");

                    if ($scanner_index == 0) {
                        //$scanner_index = 0;
                        $firewall_column['scanner_position_change'] = 1;
                    }

                    if ($scanner_index == ($firewall_column['depth'] - 1)) {
                        //$scanner_index = $firewall_column['depth'] - 1;
                        $firewall_column['scanner_position_change'] = -1;
                    }

                    $scanner_index += $firewall_column['scanner_position_change'];

                    foreach ($firewall_column['scanner_positions'] as $scanner_position_index => &$scanner_position) {
                        if ($scanner_position_index == $scanner_index) {
                            $scanner_position = true;
                        } else {
                            $scanner_position = false;
                        }
                    }
                } else {
                    //echo ('no scanner index' . "\n");
                }
            }
        }

        if ($this->debug) {
            $this->printFirewall();
        }
    }

    function doWalk()
    {
        for ($i = 0; $i < count($this->firewall); $i++) {
            $this->walk();
        }
    }
}

$firewall = new Firewall($input, DEBUG);
$firewall->doWalk();

$result = $firewall->severity;
echo ('result: ' . $result . "\n");