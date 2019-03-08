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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use pgb_liv\php_ms\Core\Modification;
use pgb_liv\php_ms\Core\Peptide;
use pgb_liv\php_ms\Core\Tolerance;
use pgb_liv\php_ms\Utility\Fragment\BFragment;
use pgb_liv\php_ms\Utility\Fragment\YFragment;
use pgb_liv\php_ms\Utility\Fragment\CFragment;
use pgb_liv\php_ms\Utility\Fragment\ZFragment;

class IonTestCommand extends Command
{

    private $fragmentTolerance;

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('IonTest')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Returns the scans which meet the specified MS level from the input data and injects RT data.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command iterates through complete MS2 data to identify possible MS2 feature sites.')
            ->addArgument('IsoFile', InputArgument::REQUIRED, 'Isotope file path')
            ->addArgument('Sequence', InputArgument::REQUIRED, 'Sequence to fragment and compare');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->fragmentTolerance = new Tolerance(5, Tolerance::PPM);

        $observedMasses = $this->getFragments($input->getArgument('IsoFile'));
        $inputSequence = $input->getArgument('Sequence');

        $sequence = $inputSequence;
        $peptide = new Peptide();
        do {
            $matches = array();
            $matchCount = preg_match('/\\(([0-9.+-]+)\\)/', $sequence, $matches, PREG_OFFSET_CAPTURE);

            if ($matchCount > 0) {
                $newSequence = substr($sequence, 0, $matches[0][1]);
                $newSequence .= substr($sequence, $matches[0][1] + strlen($matches[0][0]));
                $sequence = $newSequence;
                $modification = new Modification();
                $modification->setLocation($matches[0][1]);
                $modification->setMonoisotopicMass((float) $matches[1][0]);
                $peptide->addModification($modification);
            }
        } while ($matchCount > 0);

        $peptide->setSequence($sequence);

        $fraggers = array();
        $fraggers['B Ions'] = new BFragment($peptide);
        $fraggers['Y Ions'] = new YFragment($peptide);
        $fraggers['C Ions'] = new CFragment($peptide);
        $fraggers['Z Ions'] = new ZFragment($peptide);

        echo $inputSequence . PHP_EOL . PHP_EOL;

        $hitSummary = array();
        foreach ($fraggers as $title => $fragger) {
            echo $title . PHP_EOL;
            echo '======' . PHP_EOL;

            $hitSummary[$title] = $this->generateFragmentReport($observedMasses, $sequence, $fragger);
            echo PHP_EOL;
        }

        echo 'Summary' . PHP_EOL;
        echo '=======' . PHP_EOL;

        $sum = 0;
        foreach ($hitSummary as $title => $matches) {
            $sum += array_sum($matches);
            echo $title . ', ' . array_sum($matches) . PHP_EOL;
        }
        echo 'TOTAL, ' . $sum . PHP_EOL;
    }

    private function generateFragmentReport(array $observedMasses, $sequence, $fragger)
    {
        $expectedMasses = $fragger->getIons();

        $hits = array();
        $positions = array();
        foreach ($observedMasses as $observedScan => $observedMass) {
            foreach ($expectedMasses as $ionIndex => $expectedMass) {
                if (! $this->fragmentTolerance->isTolerable($observedMass, $expectedMass)) {
                    continue;
                }

                if (! isset($hits[$ionIndex])) {
                    $hits[$ionIndex] = 0;
                    $positions[$ionIndex] = array();
                }

                $hits[$ionIndex] ++;
                $positions[$ionIndex][] = $observedScan;
            }
        }

        ksort($hits);
        foreach ($hits as $ionIndex => $hitCount) {
            echo $ionIndex . '#, ' . $sequence[$ionIndex - 1] . ', ' . $expectedMasses[$ionIndex] . ', ' . $hitCount .
                ',' . implode(':', $positions[$ionIndex]) . PHP_EOL;
        }

        return $hits;
    }

    private function getFragments($inputFile)
    {
        $handle = fopen($inputFile, 'r');

        // Header
        $header = fgetcsv($handle);

        $masses = array();

        $scanCount = 0;
        while ($csv = fgetcsv($handle)) {
            $entry = array_combine($header, $csv);

            $scanNum = (int) $entry['scan_num'];
            $index = $scanNum . '_' . $scanCount;

            $masses[$index] = (float) $entry['monoisotopic_mw'];

            $scanCount ++;
        }

        fclose($handle);

        return $masses;
    }
}