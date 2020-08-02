<?php
/**
 * Author: Gary Huang <gh.nctu+code@gmail.com>
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('cron.php');

function job1() { echo 'job1 '; }
function job2() { echo 'job2 '; }

var_dump(date('Y/m/d H:i:s'));
echo "<br />\n";
echo "<br />\n";

$tests[] = '2020/08/01 12:01:00';
$tests[] = '2020/08/02 12:01:00';
$tests[] = '2020/08/03 12:01:09';
$tests[] = '2020/08/04 11:01:03';
$tests[] = '2020/08/05 11:01:00';
$tests[] = '2020/08/23 13:01:01';
$tests[] = '2020/08/23 13:11:00';
$tests[] = '2020/08/23 23:02:07';
$tests[] = '2020/08/23 12:58:00';
$tests[] = '2020/09/01 11:01:20';

try {
    $crontab[] = Cron\Item::expr('1 11-12 */2 * *')
                        ->addCommand('job1')
                        ->addCommand('job2');
    $crontab[] = Cron\Item::expr('*/2 * * * *')
                        ->addCommand('job2');

    foreach ($crontab as $item) {
        echo $item->rule() . "<br />\n";
        foreach ($tests as $test) {
            echo "$test ";
            $item->matchRun($test);
            echo "<br />\n";
        }
        echo "<br />\n";
    }
} catch (Cron\ParseException $e) {
    echo $e->getMessage() . "\n";
}

?>
