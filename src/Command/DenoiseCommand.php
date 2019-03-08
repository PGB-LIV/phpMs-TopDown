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

class DenoiseCommand extends Command
{

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('Denoise')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Denoises a dataset.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command removes any noise for a dataset.')
            ->addArgument('Input', InputArgument::REQUIRED, 'Input file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Output stats
        $inputFile = $input->getArgument('Input');

        $handle = fopen($inputFile, 'r');

        // Header
        fgetcsv($handle);

        $freq = array();
        $scans = array();
        while ($csv = fgetcsv($handle)) {
            $mz = '' . round($csv[3], 1);

            if (! isset($freq[$mz])) {
                $freq[$mz] = 0;
            }

            $freq[$mz] ++;
            $scans[$csv[0]] = 1;
        }

        fclose($handle);
        $scans = count($scans);

        foreach (array_keys($freq) as $mz) {
            $freq[$mz] /= $scans;
            $freq[$mz] *= 100;
        }

        $handle = fopen($inputFile, 'r');

        // Header
        echo fgets($handle);

        while ($line = fgets($handle)) {
            $csv = str_getcsv($line);
            $mz = '' . round($csv[3], 1);

            if ($freq[$mz] > 5) {
                continue;
            }

            echo $line;
        }

        fclose($handle);
    }
}