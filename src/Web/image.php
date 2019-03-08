<?php
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

error_reporting(E_ALL);
ini_set('display_errors', true);

$featureX = $_GET['mw'];
$featureY = $_GET['scan'];

$featureXMin = $featureX - 100;
$featureXMax = $featureX + 100;

$featureYMin = $featureY - 1;
$featureYMax = $featureY + 1;

$seamass = $_GET['seamass'];
$tolerance = $_GET['tolerance'];

$msLevel = 1;
if (isset($_GET['ms_level'])) {
    $msLevel = $_GET['ms_level'];
}

// TODO: Fix paths
if ($msLevel == 1) {
    $peaks = '/m' . $seamass . '/ms1_peaks.csv';
    $features = '/m' . $seamass . '/' . $tolerance . '/ms1_features.csv';
} else {
    $peaks = '/m' . $seamass . '/ms2_peaks.csv';
    $features = '/m' . $seamass . '/ms2_features_10ppm.csv';
}

$process = new Process(
    'php PlotMS2.php "' . $peaks . '" "' . $features . '" "monoisotopic_mw" "scan_time" ' . $featureXMin . ' ' .
    $featureXMax . ' ' . $featureYMin . ' ' . $featureYMax);
$process->run();

// executes after the command finishes
if (! $process->isSuccessful()) {
    echo '<pre>';
    throw new ProcessFailedException($process);
}

header('Content-type: image/png');
echo $process->getOutput();
?>