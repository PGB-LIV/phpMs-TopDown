<?php
/**
 * Copyright 2017 University of Liverpool
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace pgb_liv\top_down\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use pgb_liv\php_ms\Core\Spectra\PrecursorIon;
use pgb_liv\php_ms\Core\Spectra\FragmentIon;
use pgb_liv\php_ms\Writer\MgfWriter;
use pgb_liv\php_ms\Utility\Sort\IonSort;

class MergeMSMSCommand extends Command
{

    const RT_TOLERANCE = 60;

    private $logDir;

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('MergeMSMS')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Searches through MS2 data to identify MS2 Features.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command generates an MGF from the MS1 and MS2 features file.')
            ->addArgument('MS1Features', InputArgument::REQUIRED, 'The file path to the MS1 features file.')
            ->addArgument('MS2Features', InputArgument::REQUIRED, 'The file path to the MS2 features file.')
            ->addArgument('LogDir', InputArgument::OPTIONAL, 'The log directory for extended feature output.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ms1Path = $input->getArgument('MS1Features');
        $ms2Path = $input->getArgument('MS2Features');

        if (! is_null($input->getArgument('LogDir'))) {
            $this->logDir = $input->getArgument('LogDir');

            if (! file_exists($this->logDir)) {
                mkdir($this->logDir);
            }
        }

        $ms1Index = $this->indexMs($ms1Path);
        $ms2Index = $this->indexMs($ms2Path);

        $mgf = new MgfWriter('php://stdout');
        $this->matchMSMS($ms1Index, $ms2Index, $mgf);
        $mgf->close();
    }

    private function matchMSMS(array $ms1Index, array $ms2Index, MgfWriter $mgf)
    {
        $specCount = 0;
        foreach ($ms1Index as $ms1) {
            $precursor = new PrecursorIon();
            $precursor->setIntensity($ms1['abundance']);
            $precursor->setMonoisotopicMass($ms1['maxMass']);
            $precursor->setRetentionTimeWindow($ms1['minRt'], $ms1['maxRt']);
            $precursor->setCharge(1);
            $precursor->setTitle('Spectrum ' . $specCount);

            $this->findFragments($precursor, $ms2Index);

            if (count($precursor->getFragmentIons()) == 0) {
                continue;
            }

            $mgf->write($precursor);
            $specCount ++;

            if (! is_null($this->logDir)) {
                $this->log($precursor);
            }
        }
    }

    private function findFragments(PrecursorIon $precursor, array $ms2Index)
    {
        $matches = array();
        $fragmentSorter = new IonSort(IonSort::SORT_MASS, SORT_ASC);

        foreach ($ms2Index as $ms2) {
            if ($ms2['maxMass'] > $precursor->getMass()) {
                continue;
            }

            // MS2 Rt should start or end within the precursor window
            $rtWindow = $precursor->getRetentionTimeWindow();
            if ($ms2['maxRt'] < $rtWindow[PrecursorIon::RETENTION_TIME_START] - static::RT_TOLERANCE ||
                $ms2['minRt'] > $rtWindow[PrecursorIon::RETENTION_TIME_END] + static::RT_TOLERANCE) {
                continue;
            }

            $fragment = new FragmentIon();
            $fragment->setMonoisotopicMass(($ms2['minMass'] + $ms2['maxMass']) / 2);
            $fragment->setCharge(1);
            $fragment->setIntensity($ms2['abundance']);
            $fragment->setRetentionTimeWindow($ms2['minRt'], $ms2['maxRt']);

            $matches[] = $fragment;
        }

        // Sort fragments
        $fragmentSorter->sort($matches, false);

        foreach ($matches as $fragment) {
            $precursor->addFragmentIon($fragment);
        }
    }

    public static function cmp_minMass($a, $b)
    {
        if ($a['minMass'] == $b['minMass']) {
            return 0;
        }

        return ($a['minMass'] < $b['minMass']) ? - 1 : 1;
    }

    private function indexMs($featureFile)
    {
        $handle = fopen($featureFile, 'r');

        // Header
        $header = fgetcsv($handle);

        $index = array();
        while ($csv = fgetcsv($handle)) {
            $entry = array_combine($header, $csv);

            $element = array();
            $element['minRt'] = (float) $entry['min_scan_time'] * 60;
            $element['maxRt'] = (float) $entry['max_scan_time'] * 60;

            $element['minMass'] = (float) $entry['min_monoisotopic_mw'];
            $element['maxMass'] = (float) $entry['max_monoisotopic_mw'];

            $element['abundance'] = (float) $entry['abundance'];

            $index[] = $element;
        }

        fclose($handle);

        // Sort fragments
        usort($index, array(
            'pgb_liv\rt_profile\Command\MergeMSMSCommand',
            'cmp_minMass'
        ));

        return $index;
    }

    private function log(PrecursorIon $precursor)
    {
        $handle = fopen($this->logDir . '/' . $precursor->getTitle() . '.csv', 'w');

        $fields = array();
        $fields[] = 'Mass';
        $fields[] = 'Intensity';
        $fields[] = 'RtStart';
        $fields[] = 'RtEnd';

        fputcsv($handle, $fields);

        $fields = array();
        $fields[] = $precursor->getMonoisotopicMass();
        $fields[] = $precursor->getIntensity();
        $fields[] = $precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_START];
        $fields[] = $precursor->getRetentionTimeWindow()[PrecursorIon::RETENTION_TIME_END];

        fputcsv($handle, $fields);

        fputs($handle, PHP_EOL . PHP_EOL);

        $fields = array();
        $fields[] = 'Mass';
        $fields[] = 'Intensity';
        $fields[] = 'RtStart';
        $fields[] = 'RtEnd';

        fputcsv($handle, $fields);

        foreach ($precursor->getFragmentIons() as $fragmentIon) {
            $fields = array();
            $fields[] = $fragmentIon->getMonoisotopicMass();
            $fields[] = $fragmentIon->getIntensity();
            $fields[] = $fragmentIon->getRetentionTimeWindow()[FragmentIon::RETENTION_TIME_START];
            $fields[] = $fragmentIon->getRetentionTimeWindow()[FragmentIon::RETENTION_TIME_END];

            fputcsv($handle, $fields);
        }
    }
}