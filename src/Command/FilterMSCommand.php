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

class FilterMSCommand extends Command
{

    private $scan2Rt = array();

    private $scan2Type = array();

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('FilterMS')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Returns the scans which meet the specified MS level from the input data and injects RT data.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command iterates through complete MS2 data to identify possible MS2 feature sites.')
            ->addArgument('IsoFile', InputArgument::REQUIRED, 'Isotope file path')
            ->addArgument('ScanFile', InputArgument::REQUIRED, 'Scan file path')
            ->addArgument('Level', InputArgument::REQUIRED, 'Level to filter');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $scanFile = $input->getArgument('ScanFile');
        $this->indexScans($scanFile);

        $isoFile = $input->getArgument('IsoFile');
        $level = (int) $input->getArgument('Level');

        $handle = fopen($isoFile, 'r');

        // Header
        $output->writeln(trim(fgets($handle)) . ',scan_time');

        while ($line = fgets($handle)) {
            $csv = str_getcsv($line);

            $scan = (int) $csv[0];
            $type = $this->scan2Type[$scan];

            if ($type != $level) {
                continue;
            }

            $output->writeln(trim($line) . ',' . $this->scan2Rt[$scan]);
        }

        fclose($handle);
    }

    private function indexScans($scanFile)
    {
        $handle = fopen($scanFile, 'r');

        // Header
        fgetcsv($handle);
        while ($csv = fgetcsv($handle)) {
            $scan = (int) $csv[0];
            $rt = (float) $csv[1];
            $type = (int) $csv[2];

            $this->scan2Rt[$scan] = $rt;
            $this->scan2Type[$scan] = $type;
        }

        fclose($handle);
    }
}