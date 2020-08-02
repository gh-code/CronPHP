<?php
/**
 * Author: Gary Huang <gh.nctu+code@gmail.com>
 */
namespace Cron;

class ParseException extends \Exception {
}

abstract class Match {
    abstract public function match($value);
    abstract public function rule();
    abstract public function check($field);
    protected function throw_($desc, $min, $max) {
        throw new ParseException("syntax error: out of bound: $desc ($min ~ $max)");
    }
}

class Any extends Match {

    public function match($value) {
        return true;
    }

    public function rule() {
        return '*';
    }

    public function check($field) {
        return true;
    }
}

class Range extends Match {

    public function __construct($token) {
        list($begin, $end) = explode('-', $token);
        $this->begin = $begin;
        $this->end = $end;
    }

    public function match($value) {
        return ($value >= $this->begin &&
                $value <= $this->end);
    }

    public function rule() {
        return $this->begin.'-'.$this->end;
    }

    public function check($field) {
        extract($field);
        if ($this->begin < $min || $this->begin > $max ||
            $this->end < $min || $this->end > $max) {
            $this->throw_($desc, $min, $max);
            return false;
        }
        return true;
    }
}

class Value extends Match {

    public function __construct($token) {
        $value = intval($token);
        $this->value = $value;
    }

    public function match($value) {
        return ($value == $this->value);
    }

    public function rule() {
        return $this->value;
    }

    public function check($field) {
        extract($field);
        if ($this->value < $min || $this->value > $max) {
            $this->throw_($desc, $min, $max);
            return false;
        }
        return true;
    }
}

class ValueList extends Match {

    private $items = array();

    public function __construct($token) {
        $values = explode(',', $token);
        foreach ($values as $value) {
            $this->items[] = new Value($value);
        }
    }

    public function match($value) {
        foreach ($this->items as $item) {
            if ($item->match($value)) {
                return true;
            }
        }
    }

    public function rule() {
        $result = [];
        foreach ($this->items as $item) {
            $result[] = $item->rule();
        }
        return implode(',', $result);
    }

    public function check($field) {
        extract($field);
        foreach ($this->items as $item) {
            $result = $item->check($desc, $min, $max);
            if ($result == false) {
                return false;
            }
        }
        return true;
    }
}

class Period extends Match {

    private $period;
    private $minus;

    public function __construct($token) {
        $period = intval(substr($token, 2));
        $this->period = $period;
        $this->minus = 0;
    }

    public function minus($value) {
        $this->minus = $value;
    }

    public function match($value) {
        return (($value - $this->minus) % $this->period === 0);
    }

    public function rule() {
        return '*/'.$this->period;
    }

    public function check($field) {
        extract($field);
        if ($this->period < $min || $this->period > $max) {
            $this->throw_($desc, $min, $max);
            return false;
        }
        if ($min > 0) {
            $this->minus($min);
        }
        return true;
    }
}

class Expr {

    private static $fields = array(
        array('desc' => 'minutes',       'min' => 0, 'max' => 59),
        array('desc' => 'hours',         'min' => 0, 'max' => 23),
        array('desc' => 'day of month',  'min' => 1, 'max' => 31),
        array('desc' => 'month',         'min' => 1, 'max' => 12),
        array('desc' => 'day of week',   'min' => 0, 'max' =>  6),
        array('desc' => 'year',          'min' => 1970, 'max' => 2099)
    );
    private static $formats = array('i', 'H', 'd', 'm', 'w', 'Y');

    private $values;
    private $commands = array();
    private $optional;

    public function __construct($values) {
        $this->check($values);
        $this->values = $values;
        $fields_size = count(self::$fields);
        $values_size = count($this->values);
        if ($values_size == $fields_size) {
            $this->optional = true;
        } else if ($values_size == $fields_size - 1) {
            $this->optional = false;
            $this->values[] = new Any;
        } else {
            throw new ParseException('syntax error: incorrect field number');
        }
    }

    public function getMinutes()        { return $this->values[0]; }
    public function getHours()          { return $this->values[1]; }
    public function getDayOfMonth()     { return $this->values[2]; }
    public function getMonth()          { return $this->values[3]; }
    public function getDayOfWeek()      { return $this->values[4]; }
    public function getYear()           { return $this->values[5]; }
    public function getCommand($i)      { return $this->commands[$i]; }
    public function getCommandList()    { return $this->commands; }
    public function getCommandSize()    { return count($this->commands); }

    public function setMinutes($mins)   { $this->values[0] = $mins;   return $this; }
    public function setHours($hours)    { $this->values[1] = $hours;  return $this; }
    public function setDayOfMonth($day) { $this->values[2] = $day;    return $this; }
    public function setMonth($month)    { $this->values[3] = $month;  return $this; }
    public function setDayOfWeek($day)  { $this->values[4] = $day;    return $this; }
    public function setYear($year)      { $this->values[5] = $year; $this->optional = true; return $this; }
    public function addCommand($command) { $this->commands[] = $command; return $this; }

    public function matchDetail($datetime) {
        if (is_string($datetime)) {
            $datetime = strtotime($datetime);
        }
        $match = 0;
        $len = count($this->values);
        for ($i = 0; $i < $len; ++$i) {
            $f = self::$formats[$i];
            $a = $this->values[$i];
            $b = intval(date($f, $datetime));
            if ($a->match($b)) {
                $match += 1;
            }
        }
        return $match;
    }

    public function match($datetime) {
        return ($this->matchDetail($datetime) == count(self::$fields));
    }

    public function matchRun($datetime, $command=null, $also=false) {
        if (!$this->match($datetime)) {
            return false;
        }
        if ($command !== null) {
            $command();
            if (!$also) {
                return true;
            }
        } else {
            $also = false;
        }
        if (!$also && count($this->commands) == 0) {
            throw new \Exception('no command');
            return false;
        }
        foreach ($this->commands as $command) {
            $command();
        }
        return true;
    }

    public function rule() {
        $result = [];
        $len = count(self::$fields) - 1;
        for ($i = 0; $i < $len; ++$i) {
            $r = $this->values[$i];
            $result[] = $r->rule();
        }
        if ($this->optional) {
            $r = $this->values[$len];
            $result[] = $r->rule();
        }
        return implode(' ', $result);
    }

    public function check($values) {
        $len = count($values);
        for ($i = 0; $i < $len; ++$i) {
            $values[$i]->check(self::$fields[$i]);
        }
    }
}

function makeExpr($tokens) {
    $values = [];
    foreach ($tokens as $token) {
        if ($token == '' || $token == '*') {
            $values[] = new Any;
        } else if (preg_match('/^\*\/[0-9]+$/', $token)) {
            $values[] = new Period($token);
        } else if (preg_match('/^[0-9]+-[0-9]+$/', $token)) {
            $values[] = new Range($token);
        } else if (preg_match('/^[0-9]+$/', $token)) {
            $values[] = new Value($token);
        } else if (preg_match('/^(?:[0-9]+,)+[0-9]+$/', $token)) {
            $values[] = new ValueList($token);
        } else {
            throw new ParseException('syntax error: unknown token: '.$token);
        }
    }
    return new Expr($values);
}

class Parser {
    public static function parse($input) {
        if (is_string($input)) {
            $fp = fopen('php://memory', 'r+');
            fwrite($fp, $input);
            rewind($fp);
            $result = StreamParser::parse($fp);
            fclose($fp);
            return $result;
        } else if (@get_resource_type($input)) {
            return StreamParser::parse($fp);
        } else {
            throw new ParseException('invalid input');
        }
    }
}

class StreamParser extends Parser {
    public static function parse($handle) {
        $string = stream_get_contents($handle);
        $tokens = preg_split('/\s+/', $string);
        return makeExpr($tokens);
    }
}

class Item {
    private static $lookup = array();
    public static function expr($input) {
        $input = preg_replace('/ +/', ' ', $input);
        if (!array_key_exists($input, self::$lookup)) {
            self::$lookup[$input] = Parser::parse($input);
        }
        return self::$lookup[$input];
    }
}

?>
