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
use pgb_liv\php_ms\Reader\FastaReader;
use pgb_liv\php_ms\Reader\MgfReader;
use pgb_liv\php_ms\Core\Spectra\PrecursorIon;
use pgb_liv\php_ms\Core\Protein;
use pgb_liv\php_ms\Core\Tolerance;
use pgb_liv\php_ms\Core\Modification;
use pgb_liv\php_ms\Utility\ActivationMethod\CidActivationMethod;
use pgb_liv\php_ms\Core\NeutralLoss;
error_reporting(E_ALL);
ini_set('display_errors', true);

class IdentifySearchCommand extends Command
{

    private $fragmentTolerance;

    private $fragmentOutputFolder;

    /**
     *
     * @var Protein[]
     */
    private $proteins;

    /**
     *
     * @var PrecursorIon[]
     */
    private $spectra;

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('IdentifySearch')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Returns the scans which meet the specified MS level from the input data and injects RT data.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command iterates through complete MS2 data to identify possible MS2 feature sites.')
            ->addArgument('MgfFile', InputArgument::REQUIRED, 'Isotope file path')
            ->addArgument('FastaFile', InputArgument::REQUIRED, 'Scan file path')
            ->addArgument('CandidateFile', InputArgument::REQUIRED, 'Output folder for fragment information')
            ->addArgument('FragmentFolder', InputArgument::OPTIONAL, 'Output folder for fragment information');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->fragmentTolerance = new Tolerance(10, Tolerance::PPM);

        $mgfFile = $input->getArgument('MgfFile');
        $fastaFile = $input->getArgument('FastaFile');
        $candidateFile = $input->getArgument('CandidateFile');

        if ($input->hasArgument('FragmentFolder')) {
            $this->fragmentOutputFolder = $input->getArgument('FragmentFolder');

            if (! file_exists($this->fragmentOutputFolder)) {
                mkdir($this->fragmentOutputFolder);
            }
        }

        $this->proteins = $this->indexFasta($fastaFile);
        $this->spectra = $this->indexSearchableSpectra($candidateFile, $mgfFile);

        $this->search($candidateFile);
    }

    private function indexSearchableSpectra($candidateFile, $mgfFile)
    {
        $handle = fopen($candidateFile, 'r');

        $records = array();
        fgets($handle); // header

        while ($csv = fgetcsv($handle)) {
            $records[$csv[1]] = 1;
        }

        fclose($handle);

        $handle = new MgfReader($mgfFile);

        foreach ($handle as $precursor) {
            $title = $precursor->getTitle();

            if (! isset($records[$title])) {
                continue;
            }

            $records[$title] = $precursor;
        }

        return $records;
    }

    /**
     *
     * @param PrecursorIon[] $precursors
     * @param Protein[] $proteins
     */
    private function search($candidateFile)
    {
        echo 'spectrum_id,ident_id,protein_id,obs_mz,rt,modifications,delta_da,ion_matches,b_matches,y_matches,c_matches,z_matches' .
            PHP_EOL;

        $handle = fopen($candidateFile, 'r');

        fgets($handle); // header

        while ($csv = fgetcsv($handle)) {
            $precursor = $this->spectra[$csv[1]];
            $protein = $this->proteins[$csv[2]];
            $protein->clearModifications();

            if (strlen($csv[3]) > 0) {
                // Add Mods
                $matches = array();
                preg_match_all('/\\[([0-9]+)\\]([0-9.]+)/', $csv[3], $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $modification = new Modification();
                    $modification->setLocation((int) $match[1]);
                    $modification->setMonoisotopicMass((float) $match[2]);

                    if ($modification->getMonoisotopicMass() == 79.966331) {
                        $neutralLoss = new NeutralLoss();
                        $neutralLoss->setMonoisotopicMass(97.976896);
                        $neutralLoss->setName('H3O4P');
                        $modification->addNeutralLoss($neutralLoss);
                    }

                    $protein->addModification($modification);
                }
            }

            $this->performSearch($csv[0], $precursor, $protein);
            $protein->clearModifications();
        }
    }

    private function performSearch($candidateId, PrecursorIon $precursor, Protein $protein)
    {
        echo $precursor->getTitle() . ',';
        echo $candidateId . ',';
        echo $protein->getUniqueIdentifier() . ',';
        echo round($precursor->getMonoisotopicMass(), 5) . ',';
        echo round($precursor->getRetentionTime(), 3) . ',';

        foreach ($protein->getModifications() as $modification) {
            echo '[' . $modification->getLocation() . ']' . $modification->getMonoisotopicMass();
        }

        echo ',';

        echo round($precursor->getMonoisotopicMass() - $protein->getMonoisotopicMass(), 5) . ',';

        $ionMatches = $this->searchIons($precursor, $protein, $candidateId);

        $ionSum = 0;
        foreach ($ionMatches as $type => $matches) {
            $ionSum += count(array_unique($matches));
        }

        echo $ionSum . ',';
        foreach ($ionMatches as $type => $matches) {
            echo '[' . $type . '=>' . implode($matches) . ']';
        }

        echo PHP_EOL;
    }

    private function searchIons(PrecursorIon $precursor, Protein $protein, $identifier)
    {
        $logHandle = null;
        if (! is_null($this->fragmentOutputFolder)) {
            $logHandle = fopen($this->fragmentOutputFolder . '/' . $identifier . '.csv', 'w');

            $fields = array();
            $fields[] = 'Fragment';
            $fields[] = 'Expected';
            $fields[] = 'Observed';
            $fields[] = 'DeltaPpm';
            $fields[] = 'Intensity';

            fputcsv($logHandle, $fields);
        }

        // TODO: This returns 'groups' must return best from group
        $activation = (new CidActivationMethod($protein))->getIons();
        $fraggers = $activation[1];
        $ionMatches = array();

        $fragmentIons = $precursor->getFragmentIons();

        foreach ($fraggers as $fragmentType => $expFragmentIons) {
            $ionMatches[$fragmentType] = array();
            $lastMatch = 1;

            foreach ($fragmentIons as $observed) {
                $index = $this->hasFragmentMatch($expFragmentIons, $observed->getMonoisotopicMassCharge(), $lastMatch);

                if ($index === false) {
                    continue;
                }

                $lastMatch = $index;
                $expected = $expFragmentIons[$index];

                if (! is_null($logHandle)) {
                    $fields = array();
                    $fields[] = $fragmentType . $index;
                    $fields[] = $expected;
                    $fields[] = $observed->getMonoisotopicMassCharge();
                    $fields[] = Tolerance::getDifferencePpm($observed->getMonoisotopicMassCharge(), $expected);
                    $fields[] = $observed->getIntensity();

                    fputcsv($logHandle, $fields);
                }

                $ionMatches[$fragmentType][] = $index;
            }
        }

        if (! is_null($logHandle)) {
            fclose($logHandle);
        }

        return $ionMatches;
    }

    private function hasFragmentMatch(array $expFragmentIons, $observed, &$startIndex)
    {
        for ($index = $startIndex; $index <= count($expFragmentIons); $index ++) {

            $expected = $expFragmentIons[$index];

            if (! $this->fragmentTolerance->isTolerable($observed, $expected)) {
                if ($observed < $expected) {
                    break;
                }

                $startIndex = $index;
                continue;
            }

            return $index;
        }

        return false;
    }

    private function indexFasta($fastaFile)
    {
        $reader = new FastaReader($fastaFile);

        $records = array();
        foreach ($reader as $protein) {
            $protein->clearModifications();
            $records[$protein->getUniqueIdentifier()] = $protein;
        }

        return $records;
    }
}