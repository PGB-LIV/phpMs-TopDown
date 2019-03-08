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

class Ident2HtmlCommand extends Command
{

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('Ident2Html')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Returns the scans which meet the specified MS level from the input data and injects RT data.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command iterates through complete MS2 data to identify possible MS2 feature sites.')
            ->addArgument('IdentFile', InputArgument::REQUIRED, 'Isotope file path')
            ->addArgument('FastaFile', InputArgument::REQUIRED, 'Scan file path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fasta = $this->indexFasta($input->getArgument('FastaFile'));

        $this->writeHeader();

        $handle = fopen($input->getArgument('IdentFile'), 'r');

        $header = fgetcsv($handle);
        while ($csv = fgetcsv($handle)) {
            $ions = array();
            $entry = array_combine($header, $csv);

            echo '<h1>' . $entry['m/z'] . ' = ' . $entry['identifier'] . '</h1>' . PHP_EOL;

            $sequence = $fasta[$entry['identifier']];
            $ions['b'] = explode(':', substr($entry['b_matches'], 1, - 1));
            $ions['y'] = explode(':', substr($entry['y_matches'], 1, - 1));
            $ions['c'] = explode(':', substr($entry['c_matches'], 1, - 1));
            $ions['z'] = explode(':', substr($entry['z_matches'], 1, - 1));

            $newIons = array();
            foreach ($ions as $ionType => $ions) {
                $newIons[$ionType] = array();
                foreach ($ions as $ion) {

                    if (strlen($ion) == 0) {
                        continue;
                    }

                    $newIons[$ionType][] = $ion - 1;
                }
            }

            $ions = $newIons;

            echo 'RT: ' . $entry['rt'] . 's, ';
            echo 'Delta: ' . $entry['delta(da)'] . 'Da, ';
            echo 'Ions: ' . $entry['ionMatches'];
            $this->formatSequence($sequence, $ions, $entry['phospho_location']);
        }

        fclose($handle);
    }

    private function writeHeader()
    {
        echo '<html><head><meta charset="UTF-8"> 
<style>
.ionTypeb, .ionTypec, .ionTypey, .ionTypez {
    color: #f00;
    font-size: .8em;
}
.highlight0 {
    background-color: #ff0;
}
.highlight1 {
    background-color: #dd0;
}
.ionTypeb, .ionTypey {
    vertical-align: sub;
}
.ionTypeb+.ionTypez {
    margin-left:-.5em;
}
.ionTypec, .ionTypez {
    vertical-align: super;
}
.ionTypeb::before {
    content: "b";
}
.ionTypec::before {
    content: "c";
}
.ionTypey::before {
    content: "y";
}
.ionTypez::before {
    content: "z";
}
.ionTypePhos {
	color: #f00;
	font-size: 2em;
}
.sequence {
    margin-left: auto;
    margin-right: auto;
    width: 80%;
    word-wrap: break-word;
	font-family:monospace;
}
</style> 
</head>
<body>';
    }

    private function formatSequence($sequence, $ionSet, $phosLocation)
    {
        echo '<p class="sequence">';
        for ($index = 0; $index < strlen($sequence); $index ++) {

            $ionsFound = array();

            foreach ($ionSet as $ionType => $ions) {
                if (in_array($index, $ions, true)) {
                    $ionsFound[$ionType] = $ionType;
                }
            }

            if (strlen($phosLocation) > 0 && $phosLocation == $index) {
                $ionsFound['Phos'] = 'Phos';
            }

            if (count($ionsFound) > 0) {
                echo '<span class="highlight';

                echo $index % 2;

                if (isset($ionsFound['Phos'])) {
                    echo ' ionTypePhos';
                }

                echo '">';
            }

            if (isset($ionsFound['b'])) {
                echo '<span class="ionTypeb"></span>';
            }
            if (isset($ionsFound['z'])) {
                echo '<span class="ionTypez"></span>';
            }

            echo $sequence[$index];

            if (isset($ionsFound['c'])) {
                echo '<span class="ionTypec"></span>';
            }
            if (isset($ionsFound['y'])) {
                echo '<span class="ionTypey"></span>';
            }
            if (count($ionsFound) > 0) {
                echo '</span>';
            }
        }

        echo '</p>' . PHP_EOL;
    }

    private function indexFasta($fastaFile)
    {
        $fasta = new FastaReader($fastaFile);

        $entries = array();
        foreach ($fasta as $fastaEntry) {
            $entries[$fastaEntry->getUniqueIdentifier()] = $fastaEntry->getSequence();
        }

        return $entries;
    }
}