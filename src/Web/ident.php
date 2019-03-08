<?php
use pgb_liv\php_ms\Reader\FastaReader;
use pgb_liv\php_ms\Core\Protein;
use pgb_liv\php_ms\Utility\Fragment\BFragment;
use pgb_liv\php_ms\Utility\Fragment\YFragment;
use pgb_liv\php_ms\Utility\Fragment\CFragment;
use pgb_liv\php_ms\Utility\Fragment\ZFragment;
use pgb_liv\php_ms\Core\Modification;
use pgb_liv\php_ms\Core\Spectra\FragmentIon;
use pgb_liv\php_ms\Core\Spectra\PrecursorIon;

set_time_limit(36000);

$fastaHandle = new FastaReader(FASTA_FILE);

$fasta = array();
foreach ($fastaHandle as $fastaEntry) {
    $fasta[$fastaEntry->getUniqueIdentifier()] = $fastaEntry->getSequence();
}

$handle = fopen(IDENT_FILE, 'r');

fgetcsv($handle);
while ($csv = fgetcsv($handle)) {
    if ($csv[1] != $_GET['id']) {
        continue;
    }

    $identRecord = $csv;
    break;
}

fclose($handle);

$handle = fopen(MGF_LOGS . '/' . $identRecord[0] . '.csv', 'r');

fgetcsv($handle);
$csv = fgetcsv($handle);
$precursor = new PrecursorIon();
$precursor->setMass((float) $csv[0]);
$precursor->setIntensity((float) $csv[1]);
$precursor->setRetentionTimeWindow((float) $csv[2], (float) $csv[3]);

fgetcsv($handle);
fgetcsv($handle);
fgetcsv($handle);

$rawIons = array();
while ($csv = fgetcsv($handle)) {
    $ion = new FragmentIon();
    $ion->setMass((float) $csv[0]);
    $ion->setMassCharge((float) $csv[0]);
    $ion->setIntensity((float) $csv[1]);
    $ion->setRetentionTimeWindow((float) $csv[2], (float) $csv[3]);

    $rawIons['i' . $csv[0]] = $ion;
}

fclose($handle);

$handle = fopen(IDENT_LOGS . '/' . urlencode($_GET['id']) . '.csv', 'r');

fgetcsv($handle);
$hits = array();
while ($csv = fgetcsv($handle)) {
    $hits[$csv[0]] = $csv;
}

fclose($handle);

$protein = new Protein();
$protein->setSequence($fasta[$identRecord[2]]);

if (strlen($identRecord[5]) > 0) {
    $matches = array();
    preg_match_all('/\\[([0-9]+)\\]([0-9.]+)/', $identRecord[5], $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $modification = new Modification();
        $modification->setLocation((int) $match[1]);
        $modification->setMonoisotopicMass((float) $match[2]);
        $modification->setName($match[2]);

        $protein->addModification($modification);
    }
}

$ions = array();
$ions['b'] = (new BFragment($protein))->getIons();
$ions['y'] = (new YFragment($protein))->getIons();
$ions['c'] = (new CFragment($protein))->getIons();
$ions['z'] = (new ZFragment($protein))->getIons();
?>
<h1>Identification Result</h1>

<table
    style="margin-left: auto; margin-right: auto; border: solid 1px #000; width: 300px; float: right;">
    <thead>
        <tr>
            <th colspan="3">Retention Time</th>
        </tr>
        <tr>
            <th>Ion</th>
            <th>Start (s)</th>
            <th>Stop (s)</th>
        </tr>
    </thead>
    <tbody>
        <?php
        echo '<tr>';
        echo '<th>Precursor</th>';

        echo '<td style="text-align: right;">' .
            number_format($precursor->getRetentionTimeWindow()[FragmentIon::RETENTION_TIME_START], 2) . 's' . '</td>';
        echo '<td style="text-align: right;">' .
            number_format($precursor->getRetentionTimeWindow()[FragmentIon::RETENTION_TIME_END], 2) . 's' . '</td>';
        echo '</tr>';
        foreach ($hits as $hitId => $hit) {
            $rawIon = $rawIons['i' . $hits[$hitId][2]];
            echo '<tr>';
            echo '<th>' . $hitId . '</th>';
            echo '<td style="text-align: right;">' .
                number_format($rawIon->getRetentionTimeWindow()[FragmentIon::RETENTION_TIME_START], 2) . 's' . '</td>';
            echo '<td style="text-align: right;">' .
                number_format($rawIon->getRetentionTimeWindow()[FragmentIon::RETENTION_TIME_END], 2) . 's' . '</td>';
            echo '</tr>';
        }
        ?>
    </tbody>
</table>

<table
    style="margin-left: auto; margin-right: auto; border: solid 1px #000; width: 300px; clear: both; float: right;">
    <thead>
        <tr>
            <th colspan="3">Mass Delta</th>
        </tr>
        <tr>
            <th>Ion</th>
            <th>Expected (Da)</th>
            <th>Observed (Da)</th>
            <th>Delta (Da)</th>
            <th>Delta (ppm)</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($hits as $hitId => $hit) {
            echo '<tr>';
            echo '<th>' . $hitId . '</th>';
            echo '<td style="text-align: right;">' . number_format($hit[1], 2) . '</td>';
            echo '<td style="text-align: right;">' . number_format($hit[2], 2) . '</td>';
            echo '<td style="text-align: right;">' . number_format($hit[1] - $hit[2], 2) . '</td>';
            echo '<td style="text-align: right;">' . number_format($hit[3], 2) . '</td>';
            echo '</tr>';
        }
        ?>
    </tbody>
</table>

<table
    style="margin-left: auto; margin-right: auto; border: solid 1px #000; width: 300px; clear: both; float: right;">
    <thead>
        <tr>
            <th colspan="3">Intensity</th>
        </tr>
        <tr>
            <th>Ion</th>
            <th>Intensity</th>
        </tr>
    </thead>
    <tbody>
        <?php
        echo '<tr>';
        echo '<th>Precursor</th>';

        echo '<td style="text-align: right;">' . number_format($precursor->getIntensity(), 2) . '</td>';
        echo '</tr>';
        foreach ($hits as $hitId => $hit) {
            echo '<tr>';
            echo '<th>' . $hitId . '</th>';
            echo '<td style="text-align: right;">' . number_format($hit[4], 2) . '</td>';
            echo '</tr>';
        }
        ?>
    
    
    
    
    </tbody>
</table>
<h2>Sequence</h2>

<?php echo wordwrap($protein->getSequence(), 80, '<br />', true);?>

<h2>Fragments</h2>
<table
    style="margin-left: auto; margin-right: auto; border: solid 1px #000; width: 800px;">
    <thead>
        <tr>
            <th>AA</th>
        <?php
        foreach (array_keys($ions) as $ionType) {
            echo '<th colspan="4">' . $ionType . '</th>';
        }
        ?>
            <th>AA</th>
        </tr>
    </thead>
    <tbody>
        <?php
        for ($ionIndex = 1; $ionIndex <= count($ions['b']); $ionIndex ++) {
            echo '<tr style="text-align: right;">';
            echo '<td>' . $protein->getSequence()[$ionIndex - 1] . '</td>';
            foreach (array_keys($ions) as $ionType) {
                $hitId = $ionType . $ionIndex;
                $hasHit = false;
                if (isset($hits[$hitId])) {
                    $hasHit = true;
                }

                $ion = 'N/A';

                if (isset($ions[$ionType][$ionIndex])) {
                    $ion = $ions[$ionType][$ionIndex];
                }

                echo '<td' . ($hasHit ? ' style="background-color: #0a0;"' : '') . '>';
                echo $ion == 'N/A' ? 'N/A' : number_format($ion, 2);
                echo '</td>';

                if ($hasHit) {
                    $rawIon = $rawIons['i' . $hits[$hitId][2]];
                    echo '<td style="background-color: #0a0;">';
                    echo number_format($hits[$hitId][3], 2) . 'ppm';
                    echo '</td>';
                    echo '<td style="background-color: #0a0;">';
                    echo number_format($rawIon->getRetentionTime(), 2) . 's';
                    echo '</td>';
                    echo '<td style="background-color: #0a0; text-align: right;">';
                    echo number_format($hits[$hitId][4]);
                    echo '</td>';
                } else {
                    echo '<td colspan="3">';
                    echo '&nbsp;';
                    echo '</td>';
                }
            }

            echo '<td>' . $protein->getSequence()[strlen($protein->getSequence()) - $ionIndex] . '</td>';
            echo '</tr>';
        }
        ?>
    </tbody>
</table>