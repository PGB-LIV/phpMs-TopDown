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
error_reporting(E_ALL);
ini_set('display_errors', true);

class MS2FeatureFinderCommand extends Command
{

    const RtTolerance = 0.3;

    private $tolerance;

    private $boxed = array();

    private $scan2Rt = array();

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('MS2FeatureFinder')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Searches through MS2 data to identify MS2 Features.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command iterates through complete MS2 data to identify possible MS2 feature sites.')
            ->addArgument('IsoFile', InputArgument::REQUIRED, 'Isotope file path')
            ->addArgument('Tolerance', InputArgument::OPTIONAL, 'Tolerance', '0.6Da');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->tolerance = $this->getTolerance($input);

        $scans = $this->indexScans($input);

        // Create boxes of single fragments following down a series of scans.
        // Fragment -> scanStart, scanEnd

        $featuresFound = 0;
        $output->writeln(
            'feature_id,min_scan_num,max_scan_num,min_monoisotopic_mw,max_monoisotopic_mw,min_scan_time,max_scan_time,min_mz,max_mz,ion_count,abundance,mass_window_ppm');
        foreach ($scans as $scanNum => $fragments) {
            foreach ($fragments as $fragmentIndex => $fragmentIon) {
                // If is boxed, skip
                if (isset($this->boxed[$scanNum][$fragmentIndex])) {
                    continue;
                }

                $bounds = $this->getBounds($fragmentIon, $scanNum, $scans);

                if ($bounds['ionCount'] <= 1) {
                    continue;
                }

                // Feature Found!
                $featuresFound ++;

                $output->write($featuresFound . ',');
                $output->write($bounds['scanMin'] . ',');
                $output->write($bounds['scanMax'] . ',');
                $output->write($bounds['massMin'] . ',');
                $output->write($bounds['massMax'] . ',');
                $output->write($this->scan2Rt[$bounds['scanMin']] . ',');
                $output->write($this->scan2Rt[$bounds['scanMax']] . ',');
                $output->write($bounds['mzMin'] . ',');
                $output->write($bounds['mzMax'] . ',');
                $output->write($bounds['ionCount'] . ',');
                $output->write($bounds['abundance'] . ',');
                $output->writeln(Tolerance::getDifferencePpm($bounds['massMin'], $bounds['massMax']));
            }
        }
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

    private function indexScans(InputInterface $input)
    {
        $inputFile = $input->getArgument('IsoFile');

        $handle = fopen($inputFile, 'r');

        // Header
        $header = fgetcsv($handle);

        $scans = array();
        while ($csv = fgetcsv($handle)) {
            $entry = array_combine($header, $csv);

            $scan = (int) $entry['scan_num'];
            $mz = (float) $entry['mz'];
            $mass = (float) $entry['monoisotopic_mw'];
            $rt = (float) $entry['scan_time'];
            $abundance = (float) $entry['mono_abundance'];

            $this->scan2Rt[$scan] = $rt;

            if (! isset($scans[$scan])) {
                $scans[$scan] = array();
            }

            $scans[$scan][] = array(
                'mass' => $mass,
                'mz' => $mz,
                'abundance' => $abundance
            );
        }

        fclose($handle);

        return $scans;
    }

    private function getBounds($fragmentIon, $scanNum, array $scans)
    {
        $endIndex = $scanNum;
        $maxRT = $this->scan2Rt[$scanNum] + static::RtTolerance;

        $ionMass = array();
        $ionMz = array();
        $abundance = 0;

        for ($scanIndex = $scanNum; $scanIndex <= max(array_keys($scans)); $scanIndex ++) {
            if (! isset($scans[$scanIndex])) {
                continue;
            }

            $scan = $scans[$scanIndex];
            $rt = $this->scan2Rt[$scanIndex];

            if ($rt > $maxRT) {
                break;
            }

            $fragmentIndices = $this->isFragmentScanned($fragmentIon, $scan, $scanIndex);

            if (count($fragmentIndices) == 0) {
                continue;
            }

            $endIndex = $scanIndex;
            $maxRT = $rt + static::RtTolerance;

            foreach ($fragmentIndices as $fragmentIndex) {
                $this->boxed[$scanIndex][$fragmentIndex] = 1;
                $ionMass[] = $scan[$fragmentIndex]['mass'];
                $ionMz[] = $scan[$fragmentIndex]['mz'];
                $abundance += $scan[$fragmentIndex]['abundance'];
            }
        }

        // TODO:
        // Perform 2nd pass. Take average of mass window and generate new origin
        // Re-run scan and ions again.
        // If original origin not included, skip.

        // Return a bounds box
        return array(
            'scanMin' => $scanNum,
            'scanMax' => $endIndex,
            'massMin' => min($ionMass),
            'massMax' => max($ionMass),
            'mzMin' => min($ionMz),
            'mzMax' => max($ionMz),
            'ionCount' => count($ionMz),
            'abundance' => $abundance
        );
    }

    private function isFragmentScanned(array $searchIon, array $fragments, $scanNum)
    {
        $matches = array();
        foreach ($fragments as $fragmentIndex => $ion) {
            if (isset($this->boxed[$scanNum][$fragmentIndex])) {
                continue;
            }

            if ($this->tolerance->isTolerable($searchIon['mass'], $ion['mass'])) {
                $matches[] = $fragmentIndex;
            }
        }

        return $matches;
    }
}