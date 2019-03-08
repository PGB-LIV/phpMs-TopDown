<?php
use pgb_liv\php_ms\Reader\MgfReader;

$handle = fopen(IDENT_FILE, 'r');

fgets($handle);

$idents = array();
$identsIons = array();
while ($entry = fgetcsv($handle)) {
    $spectrum = $entry[0];

    if (! isset($idents[$spectrum])) {
        $idents[$spectrum] = 0;
        $identsIons[$spectrum] = 0;
    }

    $idents[$spectrum] ++;
    $identsIons[$spectrum] = max($identsIons[$spectrum], $entry[7]);
}

fclose($handle);

$mgf = new MgfReader(MGF_FILE);
?>

<div style="float: right;">
    <strong>Seamass Resolution</strong><br />
    <ul>
<?php

for ($i = 10; $i <= 16; $i ++) {
    echo '<li><a href="?seamass=' . $i . '&amp;tolerance=' . $tolerance . '">' . $i . '</a></li>';
}
?>
 </ul>
</div>

<div style="float: right; clear: both;">
    <strong>Precursor Tolerance</strong><br />
    <ul>
<?php
echo '<li><a href="?seamass=' . $seamass . '&amp;tolerance=0.6Da">0.6Da</a></li>';
echo '<li><a href="?seamass=' . $seamass . '&amp;tolerance=1Da">1Da</a></li>';
echo '<li><a href="?seamass=' . $seamass . '&amp;tolerance=2Da">2Da</a></li>';
?>
 </ul>
</div>
<table
    style="margin-left: auto; margin-right: auto; border: solid 1px #000; width: 800px;">
    <thead>
        <tr>
            <th>Title</th>
            <th>Mass</th>
            <th>RT</th>
            <th>Intensity</th>
            <th>Fragments</th>
            <th>Identifications</th>
            <th>Highest Ion Match</th>

        </tr>
    </thead>
    <tbody>
<?php
foreach ($mgf as $precursor) {
    $identCount = 0;
    $ionCount = 0;
    if (isset($idents[$precursor->getTitle()])) {
        $identCount = $idents[$precursor->getTitle()];
        $ionCount = $identsIons[$precursor->getTitle()];
        echo '<tr style="background-color: #0f0;">';
    } else {
        if (! isset($_GET['show_all'])) {
            continue;
        }
        echo '<tr>';
    }

    echo '<td><a href="?page=spectra&amp;id=' . $precursor->getTitle() . '&amp;seamass=' . $seamass . '&amp;tolerance=' .
        $tolerance . '">' . $precursor->getTitle() . '</a></td>';
    echo '<td style="text-align: right;">' . number_format($precursor->getMassCharge(), 2) . '</td>';
    echo '<td style="text-align: right;">' . number_format($precursor->getRetentionTime(), 2) . '</td>';
    echo '<td style="text-align: right;">' . number_format($precursor->getIntensity(), 2) . '</td>';
    echo '<td style="text-align: right;">' . number_format(count($precursor->getFragmentIons())) . '</td>';
    echo '<td style="text-align: right;">' . number_format($identCount) . '</td>';
    echo '<td style="text-align: right;">' . $ionCount . '</td>';
    echo '</tr>';
}
?>
</tbody>
</table>
