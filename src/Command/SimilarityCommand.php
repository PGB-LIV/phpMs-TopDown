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
use pgb_liv\php_ms\Core\Tolerance;

class SimilarityCommand extends Command
{

    const SimilarityTolerance = 10;

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('Similarity')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Creates a similarity matrix.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command creates a similarity matrix from a list of scans.')
            ->addArgument('Input', InputArgument::REQUIRED, 'Input file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputFile = $input->getArgument('Input');
        $handle = fopen($inputFile, 'r');

        // Header
        fgetcsv($handle);

        $scans = array();
        while ($csv = fgetcsv($handle)) {
            $scan = (int) $csv[0];
            $mz = (float) $csv[3];

            if (! isset($scans[$scan])) {
                $scans[$scan] = array();
            }

            $scans[$scan][] = $mz;
        }

        fclose($handle);

        echo '-,';
        foreach (array_keys($scans) as $scanId) {
            echo $scanId . ',';
        }

        echo PHP_EOL;

        $scores = array();

        foreach ($scans as $scanA => $fragmentsA) {
            echo $scanA . ',';

            foreach ($scans as $scanB => $fragmentsB) {
                if (! isset($scores[$scanB][$scanA])) {
                    $score = $this->getScore($fragmentsA, $fragmentsB);
                    $scores[$scanA][$scanB] = $score;
                }

                echo $scores[$scanA][$scanB] . ',';
            }

            echo PHP_EOL;
        }
    }

    private function getScore($fragmentsA, $fragmentsB)
    {
        $score = 0;

        foreach ($fragmentsA as $observed) {
            foreach ($fragmentsB as $expected) {
                $tol = Tolerance::getDifferencePpm($observed, $expected);

                if (abs($tol) > static::SimilarityTolerance) {
                    continue;
                }

                $score ++;
                break;
            }
        }

        $div = max(count($fragmentsA), count($fragmentsB));

        if ($div > 0) {
            $score /= $div;
        }

        return $score;
    }
}