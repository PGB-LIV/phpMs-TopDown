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

class MS1PeakPickerCommand extends Command
{

    const MASS = 0;

    const INTENSITY = 1;

    private $scanToRt = array();

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('MS1PeakPicker')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Searches through MS1 data to identify peaks.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command iterates through complete MS1 data to identify possible MS1 feature sites.')
            ->addArgument('IsoFile', InputArgument::REQUIRED, 'Isotope file path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isoFile = $input->getArgument('IsoFile');

        $handle = fopen($isoFile, 'r');

        // Header
        $header = fgets($handle);
        $output->write($header);
        fclose($handle);

        // Find peaks
        $this->findPeaks($output, $isoFile);
    }

    private function outputPeaks(OutputInterface $output, $scanNum, array $peaks)
    {
        foreach ($peaks as $peak) {
            $output->write($scanNum . ',');
            $output->write($peak[static::MASS] . ',');
            $output->write($peak[static::MASS] . ',');
            $output->write($peak[static::INTENSITY] . ',');
            $output->writeln($this->scanToRt[$scanNum]);
        }
    }

    private function peakPick(array $ions)
    {
        usort($ions, array(
            'pgb_liv\rt_profile\Command\MS1PeakPickerCommand',
            'cmp_mass'
        ));

        $maxima = array();

        // TODO: Check terminal corner case
        if (count($ions) == 1 || $ions[0][static::INTENSITY] > $ions[1][static::INTENSITY]) {
            $maxima[0] = $ions[0];
        }

        for ($ionId = 1; $ionId < count($ions) - 1; $ionId ++) {
            $prev = $ions[$ionId - 1][static::INTENSITY];
            $curr = $ions[$ionId][static::INTENSITY];
            $next = $ions[$ionId + 1][static::INTENSITY];

            // Check if larger than neighbours
            if ($curr > $prev && $curr > $next) {
                $maxima[$ionId] = $ions[$ionId];
            }
        }

        return $maxima;
    }

    private function findPeaks(OutputInterface $output, $isoFile)
    {
        $handle = fopen($isoFile, 'r');

        // Header
        $header = fgetcsv($handle);

        $lastScanNum = - 1;
        $ions = array();
        while ($csv = fgetcsv($handle)) {
            $entry = array_combine($header, $csv);

            $scanNum = (int) $entry['scan_num'];
            $this->scanToRt[$scanNum] = (float) $entry['scan_time'];

            if ($lastScanNum == - 1) {
                $lastScanNum = $scanNum;
            }

            if ($scanNum != $lastScanNum) {
                // Peak Pick
                $peaks = $this->peakPick($ions);
                $this->outputPeaks($output, $lastScanNum, $peaks);

                // Purge data
                $ions = array();
                $lastScanNum = $scanNum;
            }

            $ions[] = array(
                (float) $entry['monoisotopic_mw'],
                (float) $entry['mono_abundance']
            );
        }

        // Manually run last scan not completed by loop
        $peaks = $this->peakPick($ions);
        $this->outputPeaks($output, $scanNum, $peaks);

        fclose($handle);
    }

    public static function cmp_mass($a, $b)
    {
        if ($a[static::MASS] == $b[static::MASS]) {
            return 0;
        }

        return ($a[static::MASS] < $b[static::MASS]) ? - 1 : 1;
    }
}