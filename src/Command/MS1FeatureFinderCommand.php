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
use pgb_liv\php_ms\Core\Tolerance;

class MS1FeatureFinderCommand extends Command
{

    const MASS = 0;

    const INTENSITY = 1;

    private $scanToMaxima = array();

    private $assignedIons = array();

    private $tolerance;

    private $scan2Rt = array();

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('MS1FeatureFinder')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Searches through MS1 data to identify MS2 Features.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command iterates through complete MS1 data to identify possible MS1 feature sites.')
            ->addArgument('IsoFile', InputArgument::REQUIRED, 'Isotope file path')
            ->addArgument('Tolerance', InputArgument::OPTIONAL, 'Tolerance', '0.6Da');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->tolerance = $this->getTolerance($input);

        $isoFile = $input->getArgument('IsoFile');

        $this->indexPoints($isoFile);

        $featuresFound = 0;
        $output->writeln(
            'feature_id,min_scan_num,max_scan_num,min_monoisotopic_mw,max_monoisotopic_mw,min_scan_time,max_scan_time,min_mz,max_mz,ion_count,abundance,mass_window_ppm');

        foreach ($this->scanToMaxima as $scanNum => $ions) {
            foreach (array_keys($ions) as $ionId) {
                if (isset($this->assignedIons[$scanNum][$ionId])) {
                    continue;
                }

                $feature = $this->findFeature($scanNum, $ionId);

                if ($feature['maxScan'] <= $feature['minScan']) {
                    continue;
                }

                // Feature Found!
                $featuresFound ++;

                $output->write($featuresFound . ',');
                $output->write($feature['minScan'] . ',');
                $output->write($feature['maxScan'] . ',');
                $output->write($feature['minMass'] . ',');
                $output->write($feature['maxMass'] . ',');
                $output->write($this->scan2Rt[$feature['minScan']] . ',');
                $output->write($this->scan2Rt[$feature['maxScan']] . ',');
                $output->write($feature['minMass'] . ',');
                $output->write($feature['maxMass'] . ',');
                $output->write($feature['ionCount'] . ',');
                $output->write($feature['abundance'] . ',');
                $output->writeln(Tolerance::getDifferencePpm($feature['minMass'], $feature['maxMass']));
            }
        }
    }

    private function findFeature($scanNum, $ionId)
    {
        $sourceIon = $this->scanToMaxima[$scanNum][$ionId];

        $startScan = $scanNum;
        $endScan = $scanNum;
        $minMass = $sourceIon[0];
        $maxMass = $sourceIon[0];
        $abundance = 0;
        $featureIons = array();

        for ($scanIndex = $scanNum; $scanIndex < max(array_keys($this->scanToMaxima)); $scanIndex ++) {
            if (! isset($this->scanToMaxima[$scanIndex])) {
                continue;
            }

            $ions = $this->scanToMaxima[$scanIndex];
            foreach ($ions as $ionIndex => $ion) {
                if (isset($this->assignedIons[$scanIndex][$ionIndex])) {
                    continue;
                }

                if (! $this->tolerance->isTolerable($sourceIon[static::MASS], $ion[static::MASS])) {

                    // We've passed the site of the peak, end scanning
                    if ($ion[static::MASS] > $sourceIon[static::MASS]) {
                        break;
                    }

                    continue;
                }

                $endScan = $scanIndex;
                $featureIons[] = $ion;

                $this->assignedIons[$scanIndex][$ionIndex] = 1;
                $minMass = min($minMass, $ion[static::MASS]);
                $maxMass = max($maxMass, $ion[static::MASS]);
                $abundance += $ion[static::INTENSITY];
            }

            if ($scanIndex > $endScan + 20) {
                break;
            }
        }

        return array(
            'minScan' => $startScan,
            'maxScan' => $endScan,
            'minMass' => $minMass,
            'maxMass' => $maxMass,
            'abundance' => $abundance,
            'ionCount' => count($featureIons)
        );
    }

    private function indexPoints($inputFile)
    {
        $handle = fopen($inputFile, 'r');

        // Header
        $header = fgetcsv($handle);

        $lastScanNum = - 1;
        $ions = array();

        while ($csv = fgetcsv($handle)) {
            $entry = array_combine($header, $csv);

            $mass = (float) $entry['monoisotopic_mw'];
            $scanNum = $entry['scan_num'];
            $intensity = (float) $entry['mono_abundance'];
            $rt = (float) $entry['scan_time'];
            $this->scan2Rt[$scanNum] = $rt;

            if ($lastScanNum == - 1) {
                $lastScanNum = $scanNum;
            }

            if ($scanNum != $lastScanNum) {
                // Peak Pick
                $this->scanToMaxima[$lastScanNum] = $ions;

                // Purge data
                $ions = array();
                $lastScanNum = $scanNum;
            }

            $ions[] = array(
                static::MASS => $mass,
                static::INTENSITY => $intensity
            );
        }

        // Peak Pick
        $this->scanToMaxima[$scanNum] = $ions;

        fclose($handle);
    }

    private function getTolerance(InputInterface $input)
    {
        $arg = $input->getArgument('Tolerance');
        $matches = array();

        preg_match('/([0-9.]+)([a-zA-Z]+)/', $arg, $matches);

        if (count($matches) != 3 ||
            (strtoupper($matches[2]) != strtoupper(Tolerance::DA) &&
            strtoupper($matches[2]) != strtoupper(Tolerance::PPM))) {
            throw new \InvalidArgumentException(
                'Invalid tolerance format specified, use [Value][Unit], e.g. 5ppm or 0.6Da');
        }

        return new Tolerance((float) $matches[1], $matches[2]);
    }
}