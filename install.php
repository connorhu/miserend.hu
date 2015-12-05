<?php

require 'vendor/autoload.php';
include_once('functions.php');
include_once('config.php');

use Illuminate\Database\Capsule\Manager as DB;

dbconnect();
$result = DB::table('information_schema.tables')
        ->where('table_type', "=", 'BASE TABLE')
        ->where('table_schema', '=', $config['connection']['database'])
        ->count();
if (!$result) {
    $command = 'mysql -h "' . $config['connection']['host'] . '" -u "' . $config['connection']['user'] . '" --password="' . $config['connection']['password'] . '" "' . $config['connection']['database'] . '" < mysql_sample.sql';
    echo $command . "\n";
    exec($command, $output, $return_var);
    printr($output);
    printr($return_var);
} else {
    throw new Exception("Database '" . $config['connection']['database'] . "' is not empty.");
}
if (!is_dir('fajlok')) {
    //exec('ln -s ../fajlok fajlok',$em);
    if (!is_dir('fajlok')) {
        exec('rm fajlok');
        mkdir('fajlok');
        mkdir('fajlok/templomok');
    }
}
if (!is_dir('kepek')) {
    //exec('ln -s ../kepek kepek',$em);
    if (!is_dir('kepek')) {
        mkdir('kepek');
        mkdir('kepek/templomok');
        mkdir('fajlok/staticmaps');
    }
}

if (!is_dir('fajlok/igenaptar')) {
    mkdir('fajlok/igenaptar');
}
?>