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

class ConvertMzMLCommand extends Command
{

    const INTENSITY_THRESHOLD = 1000;

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('ConvertMzML')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Converts an mzML file to a format feature finder can use.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command converts a seamass generated mzML file to the decontools _iso format.')
            ->addArgument('MS1', InputArgument::REQUIRED, 'Input file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ms1File = $input->getArgument('MS1');

        if (! file_exists($ms1File)) {
            throw new \InvalidArgumentException('MS1 file "' . $ms1File . '" not found.');
        }

        $this->indexMs1($ms1File);
    }

    private function indexMs1($ms1File)
    {
        echo 'scan_num,mz,monoisotopic_mw,mono_abundance,scan_time' . PHP_EOL;

        $xmlReader = new \SimpleXMLElement($ms1File, LIBXML_PARSEHUGE, true);

        foreach ($xmlReader->mzML->run->spectrumList->spectrum as $spectrum) {
            $level = $this->getMsLevel($spectrum);

            if ($level != 1) {
                continue;
            }

            $precursor = array();
            $precursor['title'] = (string) $spectrum->attributes()->id;
            $precursor['scan_num'] = (int) explode('=', $precursor['title'])[3];
            $precursor['start_time'] = $this->getStartTime($spectrum);
            $precursor['ions'] = $this->getIons($spectrum);

            foreach ($precursor['ions'] as $ion) {
                echo $precursor['scan_num'] . ',';
                echo $ion[0] . ',';
                echo $ion[0] . ',';
                echo $ion[1] . ',';
                echo $precursor['start_time'] . PHP_EOL;
            }
        }
    }

    private function getMsLevel(\SimpleXMLElement $xml)
    {
        $level = - 1;

        foreach ($xml->cvParam as $cvParam) {
            $accession = (string) $cvParam->attributes()->accession;
            $value = $cvParam->attributes()->value;

            if ($accession == 'MS:1000511') {
                $level = (int) $value;
                break;
            }
        }

        return $level;
    }

    private function getStartTime(\SimpleXMLElement $xml)
    {
        $startTime = - 1;

        foreach ($xml->scanList->scan->cvParam as $cvParam) {
            $accession = (string) $cvParam->attributes()->accession;
            $value = $cvParam->attributes()->value;

            switch ($accession) {
                case 'MS:1000016':
                    $startTime = (float) $value;
                    break;
            }
        }

        return $startTime;
    }

    private function getIons(\SimpleXMLElement $xml)
    {
        $ions = array();

        $mzHandle = null;
        $intensityHandle = null;

        $this->getMzIntensity($xml->binaryDataArrayList->binaryDataArray, $mzHandle, $intensityHandle);

        while (! feof($mzHandle)) {
            $mz = (float) trim(fgets($mzHandle));
            $intensity = (float) trim(fgets($intensityHandle));

            if ($intensity <= static::INTENSITY_THRESHOLD) {
                continue;
            }

            $ions[] = array(
                $mz,
                $intensity
            );
        }

        return $ions;
    }

    private function getMzIntensity(\SimpleXMLElement $xml, &$mzHandle, &$intensityHandle)
    {
        foreach ($xml as $binaryDataArray) {
            $isIntensity = false;
            $isMz = false;
            $unpackWith = 'g*';

            foreach ($binaryDataArray->cvParam as $cvParam) {
                $accession = (string) $cvParam->attributes()->accession;
                $value = $cvParam->attributes()->value;

                if ($accession == 'MS:1000514') {
                    $isMz = true;
                }

                if ($accession == 'MS:1000515') {
                    $isIntensity = true;
                }

                if ($accession == 'MS:1000521') {
                    // 32bit
                    $unpackWith = 'f*';
                }

                if ($accession == 'MS:1000523') {
                    // 64bit
                    $unpackWith = 'd*';
                }
            }

            $binary = (string) $binaryDataArray->binary;
            $base64 = base64_decode($binary);
            unset($binary);

            $zlib = zlib_decode($base64);
            $unpacked = unpack($unpackWith, $zlib);
            unset($zlib);

            $tmp = tmpfile();
            // Write to cache
            foreach ($unpacked as $value) {
                fwrite($tmp, $value . PHP_EOL);
            }

            rewind($tmp);

            if ($isMz) {
                $mzHandle = $tmp;
            } elseif ($isIntensity) {
                $intensityHandle = $tmp;
            }
        }
    }
}