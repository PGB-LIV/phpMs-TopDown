<?php
error_reporting(E_ALL);
ini_set('display_errors', true);
set_time_limit(600);

$seamass = '10';

if (isset($_GET['seamass'])) {
    if ($_GET['seamass'] < 10 || $_GET['seamass'] > 16) {
        die('Seamass value should be between 10 and 16');
    }

    $seamass = $_GET['seamass'];
}

$tolerance = '1Da';

if (isset($_GET['tolerance'])) {
    $tolerance = $_GET['tolerance'];
}

define('MGF_FILE', '/m' . $seamass . '/' . $tolerance . '/features_10ppm.mgf');
define('IDENT_FILE', '/m' . $seamass . '/' . $tolerance . '/results_10ppm.csv');
define('FASTA_FILE', '/aurora.peff.fasta');

define('MGF_LOGS', '/m' . $seamass . '/' . $tolerance . '/MgfLog');

define('IDENT_LOGS', '/m' . $seamass . '/' . $tolerance . '/SearchLog');

include __DIR__ . '/autoload.php';
require __DIR__ . '/vendor/autoload.php';

$page = 'main';

if (isset($_GET['page'])) {
    $page = $_GET['page'];
}

include 'Web/' . $page . '.php';
