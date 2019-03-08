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

class PlotMS2Command extends Command
{

    const offsetX = 80;

    const offsetY = 80;

    const colourMinorGridline = 'rgb(245, 245, 245)';

    const colourGridline = 'rgb(200, 200, 200)';

    const colourBorder = 'rgb(0, 0, 0)';

    const colourPoint = 'rgba(0, 0, 255, 0.25)';

    const colourBoundedPoint = 'rgba(255, 0, 0, 0.75)';

    const colourBoundBox = 'rgba(255, 0, 0, 0.25)';

    const scale = 2;

    private $bounds = array();

    private $yAxis = 'scan_num';

    private $xAxis = 'monoisotopic_mw';

    private $axisOffset = array(
        'scan_time' => 1.0,
        'scan_num' => 100.0,
        'monoisotopic_mw' => 100.0,
        'mz' => 100.0
    );

    private $axisTransform = array(
        'scan_time' => 100.0,
        'scan_num' => 1.0,
        'monoisotopic_mw' => 1.0,
        'mz' => 1.0
    );

    private $minX = 0;

    private $minY = 0;

    private $minZ = 0;

    private $maxX = 0;

    private $maxY = 0;

    private $maxZ = 0;

    private $axisMinY = 0;

    private $axisMinX = 0;

    private $pointIndex = array();

    private $rangeMinX = 0;

    private $rangeMaxX = PHP_INT_MAX;

    private $rangeMinY = 0;

    private $rangeMaxY = PHP_INT_MAX;

    protected function configure()
    {
        $this->
        // the name of the command (the part after "app/console")
        setName('PlotMS2')
            ->
        // the short description shown while running "php app/console list"
        setDescription('Generates a 2D plot for MS2 data points.')
            ->
        // the full command description shown when running the command with
        // the "--help" option
        setHelp('This command generates a 2-dimensional plot of the MS2 data.')
            ->addArgument('Input', InputArgument::REQUIRED, 'Input file')
            ->addArgument('Bounds', InputArgument::OPTIONAL, 'Bounds file')
            ->addArgument('xAxis', InputArgument::OPTIONAL, 'X axis data point', 'monoisotopic_mw')
            ->addArgument('yAxis', InputArgument::OPTIONAL, 'Y axis data point', 'scan_num')
            ->addArgument('RangeMinX', InputArgument::OPTIONAL, 'Min X axis to cover', $this->rangeMinX)
            ->addArgument('RangeMaxX', InputArgument::OPTIONAL, 'Max X axis to cover', $this->rangeMaxX)
            ->addArgument('RangeMinY', InputArgument::OPTIONAL, 'Min Y axis to cover', $this->rangeMinY)
            ->addArgument('RangeMaxY', InputArgument::OPTIONAL, 'Max Y axis to cover', $this->rangeMaxY);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->xAxis = $input->getArgument('xAxis');
        $this->yAxis = $input->getArgument('yAxis');

        $this->rangeMinX = $input->getArgument('RangeMinX');
        $this->rangeMaxX = $input->getArgument('RangeMaxX');

        $this->rangeMinY = $input->getArgument('RangeMinY');
        $this->rangeMaxY = $input->getArgument('RangeMaxY');

        $boundsFile = $input->getArgument('Bounds');
        if ($boundsFile) {
            $this->indexBounds($boundsFile);
        }

        // Output stats
        $inputFile = $input->getArgument('Input');

        $points = $this->indexPoints($inputFile);

        $this->axisMinY = floor($this->minY / $this->axisOffset[$this->yAxis]) * $this->axisOffset[$this->yAxis];
        $this->axisMinX = floor($this->minX / $this->axisOffset[$this->xAxis]) * $this->axisOffset[$this->xAxis];

        $width = (($this->maxX * $this->axisTransform[$this->xAxis]) + static::offsetX + (static::offsetX / 2)) -
            ($this->axisMinX * $this->axisTransform[$this->xAxis]);
        $height = (($this->maxY * $this->axisTransform[$this->yAxis]) + static::offsetY + (static::offsetY / 2)) -
            ($this->axisMinY * $this->axisTransform[$this->yAxis]);

        $draw = new \ImagickDraw();
        $draw->scale(static::scale, static::scale);

        // Draw Axis
        $this->drawAxis($draw, $this->maxX, $this->maxY);

        // Draw Points
        $this->drawPoints($draw, $points);

        // Draw Bounds
        $this->drawIsotopeBounds($draw);

        // output jpeg (or any other chosen) format & quality
        $magic = new \Imagick();
        $magic->newimage($width * static::scale, $height * static::scale, new \ImagickPixel('white'));
        $magic->setimageformat('png');
        $magic->drawimage($draw);
        echo $magic->getImageBlob();
    }

    private function indexPoints($inputFile)
    {
        $handle = fopen($inputFile, 'r');

        // Header
        $header = fgetcsv($handle);

        $points = array();
        $this->minX = PHP_INT_MAX;
        $this->minY = PHP_INT_MAX;
        $this->minZ = PHP_INT_MAX;
        $this->maxX = 0;
        $this->maxY = 0;
        $this->maxZ = 0;
        $pointId = 0;
        while ($csv = fgetcsv($handle)) {
            $entry = array_combine($header, $csv);

            $x = (float) $entry[$this->xAxis];
            $y = (float) $entry[$this->yAxis];
            $z = (float) $entry['mono_abundance'];

            // Range filter
            if ($x < $this->rangeMinX || $x > $this->rangeMaxX) {
                continue;
            }

            if ($y < $this->rangeMinY || $y > $this->rangeMaxY) {
                continue;
            }

            $points[$pointId] = array(
                $x,
                $y,
                $z
            );

            $this->minX = min($this->minX, $x);
            $this->minY = min($this->minY, $y);
            $this->minZ = min($this->minZ, $z);

            $this->maxX = max($this->maxX, $x);
            $this->maxY = max($this->maxY, $y);
            $this->maxZ = max($this->maxZ, $z);

            $this->pointIndex[floor($y)][] = $pointId;

            $pointId ++;
        }

        fclose($handle);

        return $points;
    }

    private function indexBounds($boundsFile)
    {
        $handle = fopen($boundsFile, 'r');

        // Skip header
        $header = fgetcsv($handle);

        while ($csv = fgetcsv($handle)) {
            $entry = array_combine($header, $csv);

            $x1 = (float) $entry['min_' . $this->xAxis];
            $x2 = (float) $entry['max_' . $this->xAxis];
            $y1 = (float) $entry['min_' . $this->yAxis];
            $y2 = (float) $entry['max_' . $this->yAxis];

            if ($x2 < $this->rangeMinX || $x1 > $this->rangeMaxX) {
                continue;
            }

            if ($y2 < $this->rangeMinY || $y1 > $this->rangeMaxY) {
                continue;
            }

            $this->bounds[] = array(
                $x1,
                $y1,
                $x2,
                $y2
            );
        }

        fclose($handle);
    }

    private function isPointInBounds($x, $y)
    {
        foreach ($this->bounds as $bound) {
            if ($x < $bound[0]) {
                continue;
            }

            if ($x > $bound[2]) {
                continue;
            }

            if ($y < $bound[1]) {
                continue;
            }

            if ($y > $bound[3]) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function drawIsotopeBounds(\ImagickDraw $draw)
    {
        $draw->setfillcolor(new \ImagickPixel('rgba(0, 0, 0, 0)'));
        $draw->setstrokecolor(new \ImagickPixel(static::colourBoundBox));
        $draw->setstrokewidth(.1);

        foreach ($this->bounds as $bound) {
            $x1 = static::offsetX + ($bound[0] * $this->axisTransform[$this->xAxis]);
            $x1 -= ($this->axisMinX * $this->axisTransform[$this->xAxis]);

            $y1 = static::offsetX + ($bound[1] * $this->axisTransform[$this->yAxis]);
            $y1 -= ($this->axisMinY * $this->axisTransform[$this->yAxis]);

            $x2 = static::offsetY + ($bound[2] * $this->axisTransform[$this->xAxis]);
            $x2 -= ($this->axisMinX * $this->axisTransform[$this->xAxis]);

            $y2 = static::offsetY + ($bound[3] * $this->axisTransform[$this->yAxis]);
            $y2 -= ($this->axisMinY * $this->axisTransform[$this->yAxis]);

            $draw->rectangle($x1 - 1, $y1 - 1, $x2 + 1, $y2 + 1);
        }
    }

    private function drawAxis(\ImagickDraw $draw, $maxX, $maxY)
    {
        $transformedMaxX = ($maxX * $this->axisTransform[$this->xAxis]) -
            ($this->axisMinX * $this->axisTransform[$this->xAxis]);
        $transformedMaxY = ($maxY * $this->axisTransform[$this->yAxis]) -
            ($this->axisMinY * $this->axisTransform[$this->yAxis]);

        $draw->setfillcolor(new \ImagickPixel(static::colourBorder));

        // Border
        $draw->line(static::offsetX, static::offsetY, static::offsetX + $transformedMaxX, static::offsetY);
        $draw->line(static::offsetX, static::offsetY, static::offsetX, static::offsetY + $transformedMaxY);

        // Titles
        $draw->annotation(static::offsetX + ($transformedMaxX / 2), static::offsetY - 50, $this->xAxis);
        $draw->annotation(static::offsetX - 75, static::offsetY + ($transformedMaxY / 2), $this->yAxis);

        // Minor Gridlines

        $draw->setfillcolor(new \ImagickPixel(static::colourMinorGridline));
        for ($x = $this->axisMinX; $x < $maxX; $x = $x + ($this->axisOffset[$this->xAxis] / 5)) {
            $transformedX = (static::offsetX + ($x * $this->axisTransform[$this->xAxis])) -
                ($this->axisMinX * $this->axisTransform[$this->xAxis]);

            if ($x > $this->axisMinX) {
                $draw->line($transformedX, static::offsetY, $transformedX, static::offsetY + $transformedMaxY);
            }
        }

        for ($y = $this->axisMinY; $y < $maxY; $y = $y + ($this->axisOffset[$this->yAxis] / 5)) {
            $transformedY = (static::offsetY + ($y * $this->axisTransform[$this->yAxis])) -
                ($this->axisMinY * $this->axisTransform[$this->yAxis]);

            if ($y > $this->axisMinY) {
                $draw->line(static::offsetX, $transformedY, static::offsetX + $transformedMaxX, $transformedY);
            }
        }

        // Major Gridlines
        for ($x = $this->axisMinX; $x < $maxX; $x = $x + $this->axisOffset[$this->xAxis]) {
            $transformedX = (static::offsetX + ($x * $this->axisTransform[$this->xAxis])) -
                ($this->axisMinX * $this->axisTransform[$this->xAxis]);

            $draw->setfillcolor(new \ImagickPixel(static::colourBorder));
            $draw->annotation($transformedX, static::offsetY - 25, $x);

            if ($x > $this->axisMinX) {
                $draw->setfillcolor(new \ImagickPixel(static::colourGridline));
                $draw->line($transformedX, static::offsetY, $transformedX, static::offsetY + $transformedMaxY);
            }
        }

        for ($y = $this->axisMinY; $y < $maxY; $y = $y + $this->axisOffset[$this->yAxis]) {
            $transformedY = (static::offsetY + ($y * $this->axisTransform[$this->yAxis])) -
                ($this->axisMinY * $this->axisTransform[$this->yAxis]);

            $draw->setfillcolor(new \ImagickPixel(static::colourBorder));
            $draw->annotation(static::offsetX - 50, $transformedY, $y);

            if ($y > $this->axisMinY) {
                $draw->setfillcolor(new \ImagickPixel(static::colourGridline));
                $draw->line(static::offsetX, $transformedY, static::offsetX + $transformedMaxX, $transformedY);
            }
        }
    }

    private function drawPoints(\ImagickDraw $draw, $points)
    {
        foreach ($points as $point) {
            $x = $point[0];
            $y = $point[1];
            $z = $point[2];

            if ($this->isPointInBounds($x, $y)) {
                $draw->setfillcolor(new \ImagickPixel(static::colourBoundedPoint));
            } else {
                $draw->setfillcolor(new \ImagickPixel(static::colourPoint));
            }

            $offsetX = ($x * $this->axisTransform[$this->xAxis]) + static::offsetX;
            $offsetX -= $this->axisMinX * $this->axisTransform[$this->xAxis];

            $offsetY = ($y * $this->axisTransform[$this->yAxis]) + static::offsetY;
            $offsetY -= $this->axisMinY * $this->axisTransform[$this->yAxis];

            $draw->line($offsetX - 1, $offsetY - 1, $offsetX + 1, $offsetY + 1);
            $draw->line($offsetX - 1, $offsetY + 1, $offsetX + 1, $offsetY - 1);
        }
    }

    private function getPointColour($z)
    {
        if ($z < $this->maxZ * 0.1) {
            return new \ImagickPixel('rgba(229, 229, 229, .5)');
        } elseif ($z < $this->maxZ * 0.2) {
            return new \ImagickPixel('rgba(204, 204, 204, .5)');
        } elseif ($z < $this->maxZ * 0.3) {
            return new \ImagickPixel('rgba(178, 178, 178, .5)');
        } elseif ($z < $this->maxZ * 0.4) {
            return new \ImagickPixel('rgba(153, 153, 153, .5)');
        } elseif ($z < $this->maxZ * 0.5) {
            return new \ImagickPixel('rgba(127, 127, 127, .5)');
        } elseif ($z < $this->maxZ * 0.6) {
            return new \ImagickPixel('rgba(102, 102, 102, .5)');
        } elseif ($z < $this->maxZ * 0.7) {
            return new \ImagickPixel('rgba(76, 76, 76, .5)');
        } elseif ($z < $this->maxZ * 0.8) {
            return new \ImagickPixel('rgba(51, 51, 51, .5)');
        } elseif ($z < $this->maxZ * 0.9) {
            return new \ImagickPixel('rgba(25, 25, 25, .5)');
        }

        return new \ImagickPixel('rgba(0, 0, 0, .5)');
    }

    private function getPointColourMaxima($points, $x, $y, $z)
    {
        // Find local minima
        $minX = floor($x - 50);
        $maxX = ceil($x + 50);
        $minY = $y - 50;
        $maxY = $y + 50;

        $minima = $z;
        $maxima = $z;

        for ($xWindow = $minX; $xWindow <= $maxX; $xWindow ++) {
            if (! isset($this->pointIndex[$xWindow])) {
                continue;
            }
            $pointIds = $this->pointIndex[$xWindow];

            foreach ($pointIds as $pointId) {
                $point = $points[$pointId];

                if ($point[1] < $minY || $point[1] > $maxY) {
                    continue;
                }

                $minima = min($point[2], $minima);
                $maxima = max($point[2], $maxima);
            }
        }

        if ($z < $maxima * 0.01) {
            return new \ImagickPixel('rgba(0, 0, 255, .75)');
        } elseif ($z < $maxima * 0.05) {
            return new \ImagickPixel('rgba(0, 84, 255, .75)');
        } elseif ($z < $maxima * 0.1) {
            return new \ImagickPixel('rgba(0, 169, 255, .75)');
        } elseif ($z < $maxima * 0.2) {
            return new \ImagickPixel('rgba(0, 255, 255, .75)');
        } elseif ($z < $maxima * 0.3) {
            return new \ImagickPixel('rgba(0, 255, 169, .75)');
        } elseif ($z < $maxima * 0.4) {
            return new \ImagickPixel('rgba(0, 255, 85, .75)');
        } elseif ($z < $maxima * 0.5) {
            return new \ImagickPixel('rgba(0, 255, 0, .75)');
        } elseif ($z < $maxima * 0.6) {
            return new \ImagickPixel('rgba(84, 255, 0, .75)');
        } elseif ($z < $maxima * 0.7) {
            return new \ImagickPixel('rgba(170, 255, 0, .75)');
        } elseif ($z < $maxima * 0.8) {
            return new \ImagickPixel('rgba(255, 255, 0, .75)');
        } elseif ($z < $maxima * 0.9) {
            return new \ImagickPixel('rgba(255, 170, 0, .75)');
        } elseif ($z < $maxima * 0.95) {
            return new \ImagickPixel('rgba(255, 84, 0, .75)');
        }

        return new \ImagickPixel('rgba(255, 0, 0, .75)');
    }
}