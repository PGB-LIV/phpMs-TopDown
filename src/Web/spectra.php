<?php
use pgb_liv\php_ms\Reader\MgfReader;
use pgb_liv\php_ms\Core\Spectra\FragmentIon;
use pgb_liv\php_ms\Core\Spectra\PrecursorIon;

function ion_sort($a, $b)
{
    if ($a[7] == $b[7]) {
        return 0;
    }

    return ($a[7] > $b[7]) ? - 1 : 1;
}

$mgf = new MgfReader(MGF_FILE);
$precursor = null;
foreach ($mgf as $precursor) {
    if ($precursor->getTitle() == $_GET['id']) {
        break;
    }
}

$logFile = MGF_LOGS . '/' . $_GET['id'] . '.csv';
?>
<h1><?php echo $precursor->getTitle(); ?></h1>

<img
    src="?page=image&amp;seamass=<?php echo $seamass; ?>&amp;tolerance=<?php echo $tolerance; ?>&amp;mw=<?php echo $precursor->getMassCharge(); ?>&amp;scan=<?php echo $precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START] / 60; ?>"
    style="float: right; border: 1px solid #000" />

<h2>Precursor</h2>
<table
    style="margin-left: auto; margin-right: auto; border: solid 1px #000; width: 800px;">
    <thead>
        <tr>
            <th>Mass</th>
            <th>RT Start</th>
            <th>RT End</th>
            <th>Intensity</th>
        </tr>
    </thead>
    <tbody>
    <?php
    echo '<tr>';
    echo '<td style="text-align: right;">' . number_format($precursor->getMassCharge(), 2) . 'Da</td>';
    echo '<td style="text-align: right;">' .
        number_format($precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START], 2) . 's (' .
        number_format($precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START] / 60, 2) . 'm)</td>';
    echo '<td style="text-align: right;">' .
        number_format($precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_END], 2) . 's (' .
        number_format($precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_END] / 60, 2) . 'm)</td>';
    echo '<td style="text-align: right;">' . number_format($precursor->getIntensity(), 2) . '</td>';
    echo '</tr>';
    ?>
    </tbody>
</table>

<hr />
<h2>Identifications</h2>

<table
    style="margin-left: auto; margin-right: auto; border: solid 1px #000; width: 800px;">
    <thead>
        <tr>
            <th>Protein</th>
            <th>Modifications</th>
            <th>Mass Delta</th>
            <th>Ion Matches</th>
        </tr>
    </thead>
    <tbody>
<?php
$identHandle = fopen(IDENT_FILE, 'r');
fgets($identHandle);

$idents = array();
while ($record = fgetcsv($identHandle)) {
    if ($record[0] != $_GET['id']) {
        continue;
    }

    $idents[] = $record;
}

usort($idents, 'ion_sort');

foreach ($idents as $record) {
    echo '<tr>';
    echo '<td><a href="?page=ident&amp;id=' . $record[1] . '&amp;seamass=' . $seamass . '&amp;tolerance=' . $tolerance .
        '">' . $record[2] . '</a></td>';
    echo '<td>' . (strlen($record[4]) > 0 ? $record[5] : ' &nbsp;') . '</td>';
    echo '<td style="text-align: right;">' . number_format(- $record[6], 4) . '</td>';
    echo '<td style="text-align: right;">' . $record[7] . '</td>';
    echo '</tr>';
}
?>
    </tbody>
</table>

<hr />

<h2>Fragments</h2>
<table
    style="margin-left: auto; margin-right: auto; border: solid 1px #000; width: 800px;">
    <thead>
        <tr>
            <th>Mass</th>
            <th>RT Start</th>
            <th>RT End</th>
            <th>RT Delta</th>
            <th>Intensity</th>
        </tr>
    </thead>
    <tbody>
<?php
$logHandle = fopen($logFile, 'r');
fgets($logHandle); // Header
fgets($logHandle); // Precursor
fgets($logHandle); // Empty
fgets($logHandle); // Empty
fgets($logHandle); // Header

$precursor->clearFragmentIons();
while ($csv = fgetcsv($logHandle)) {
    $fragmentIon = new FragmentIon();
    $fragmentIon->setMass((float) $csv[0]);
    $fragmentIon->setIntensity((float) $csv[1]);
    $fragmentIon->setRetentionTimeWindow((float) $csv[2], (float) $csv[3]);
    $precursor->addFragmentIon($fragmentIon);
}

foreach ($precursor->getFragmentIons() as $fragmentIon) {
    $rtDeltaStart = 0;
    if ($fragmentIon->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START] <
        $precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START]) {

        $rtDeltaStart = $fragmentIon->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START] -
            $precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START];
    }

    $rtDelta = min($rtDeltaStart, 0);
    echo '<tr>';
    echo '<td style="text-align: right;">' . number_format($fragmentIon->getMass(), 4) . '</td>';
    echo '<td style="text-align: right;">' .
        number_format($fragmentIon->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START], 2) . '</td>';
    echo '<td style="text-align: right;">' .
        number_format($fragmentIon->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_END], 2) . '</td>';

    echo '<td style="text-align: right;">' . number_format($rtDelta, 2) . '</td>';
    echo '<td style="text-align: right;">' . number_format($fragmentIon->getIntensity(), 2) . '</td>';
    echo '</tr>';
}
?>
</tbody>
</table>