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

class IdentifySearchCandidatesCommand extends Command
{

    private $precursorTolerance;

    private $proteins;

    private $spectra;

    private $candidateId = 0;

    private $mod2Mass = array(
        'UNIMOD:1' => 42.010565,
        'UNIMOD:512' => 324.105647,
        'UNIMOD:34' => 14.015650,
        'UNIMOD:35' => 15.994915,
        'UNIMOD:21' => 79.966331,
        'UNIMOD:1877' => 259.141973
    );

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('IdentifySearchCandidates')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Returns the scans which meet the specified MS level from the input data and injects RT data.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command iterates through complete MS2 data to identify possible MS2 feature sites.')
            ->addArgument('MgfFile', InputArgument::REQUIRED, 'Isotope file path')
            ->addArgument('FastaFile', InputArgument::REQUIRED, 'Fasta file to search against');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->precursorTolerance = new Tolerance(20, Tolerance::DA);

        $mgfFile = $input->getArgument('MgfFile');
        $fastaFile = $input->getArgument('FastaFile');

        $this->proteins = $this->indexFasta($fastaFile);
        $this->spectra = $this->indexMgf($mgfFile);

        $this->getCandidates();
    }

    /**
     *
     * @param PrecursorIon[] $precursors
     * @param Protein[] $proteins
     */
    private function getCandidates()
    {
        echo 'candidate_id,spectrum_id,protein_id,modifications' . PHP_EOL;

        $this->getCandidatesUnmodified();

        $this->getCandidatesModified();
    }

    /**
     *
     * @param Protein $protein
     * @param Modification[] $possibleSites
     * @param int $siteInitiator
     * @param float $proteinMass
     */
    private function getCandidatesModifiedRecursive(Protein $protein, array $possibleSites, $siteInitiator, $proteinMass)
    {
        if (count($protein->getModifications()) >= 6 || $siteInitiator >= count($possibleSites)) {
            return;
        }

        for ($siteIndex = $siteInitiator; $siteIndex < count($possibleSites); $siteIndex ++) {
            $newMod = $possibleSites[$siteIndex];
            $protein->addModification($newMod);
            $proteinMass += $newMod->getMonoisotopicMass();

            // Search!
            foreach ($this->spectra as $spectraId => $mass) {
                if (! $this->precursorTolerance->isTolerable($mass, $proteinMass)) {
                    if ($mass > $proteinMass) {
                        break;
                    }

                    continue;
                }

                $this->writeCandidate($spectraId, $protein);
            }

            // Search recursive
            $this->getCandidatesModifiedRecursive($protein, $possibleSites, $siteIndex + 1, $proteinMass);

            $protein->removeModification($newMod);
            $proteinMass -= $newMod->getMonoisotopicMass();
        }
    }

    private function getCandidatesUnmodified()
    {
        foreach ($this->proteins as $protein) {
            $modifications = $protein->getModifications();
            $protein->clearModifications();
            $proteinMass = $protein->getMonoisotopicMass();
            foreach ($this->spectra as $spectraId => $mass) {
                // Search unmodified
                if (! $this->precursorTolerance->isTolerable($mass, $proteinMass)) {
                    if ($mass > $protein->getMonoisotopicMass()) {
                        break;
                    }

                    continue;
                }

                $this->writeCandidate($spectraId, $protein);
            }

            $protein->addModifications($modifications);
        }
    }

    private function getCandidatesModified()
    {
        foreach ($this->proteins as $protein) {
            // Get Modifiable sites

            $possibleSites = $protein->getModifications();
            $protein->clearModifications();

            $this->getCandidatesModifiedRecursive($protein, $possibleSites, 0, $protein->getMonoisotopicMass());
        }
    }

    private function writeCandidate($spectraId, Protein $protein)
    {
        echo $this->candidateId ++ . ',';
        echo $spectraId . ',';
        echo $protein->getUniqueIdentifier() . ',';

        foreach ($protein->getModifications() as $modification) {
            echo '[' . $modification->getLocation() . ']' . $modification->getMonoisotopicMass();
        }

        echo PHP_EOL;
    }

    private function indexFasta($fastaFile)
    {
        $reader = new FastaReader($fastaFile);

        $records = array();
        foreach ($reader as $protein) {
            foreach ($protein->getModifications() as $modification) {
                $mass = $this->mod2Mass[$modification->getAccession()];

                if (is_null($mass)) {
                    die('Unknown mass for ' . $modification->getAccession());
                }

                $modification->setMonoisotopicMass($mass);
            }

            $records[] = $protein;
        }

        return $records;
    }

    private function indexMgf($fastaFile)
    {
        $reader = new MgfReader($fastaFile);

        $records = array();
        foreach ($reader as $entry) {
            $records[$entry->getTitle()] = $entry->getMonoisotopicMass();
        }

        asort($records);

        return $records;
    }
}