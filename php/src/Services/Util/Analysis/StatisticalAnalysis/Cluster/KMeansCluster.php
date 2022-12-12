<?php

namespace Kinintel\Services\Util\Analysis\StatisticalAnalysis\Cluster;

use Kinintel\Objects\Dataset\Tabular\ArrayTabularDataset;
use Kinintel\Objects\Dataset\Tabular\TabularDataset;
use Kinintel\Services\Util\Analysis\StatisticalAnalysis\Distance\MetricCalculator;
use Kinintel\ValueObjects\DataProcessor\Configuration\Analysis\StatisticalAnalysis\KMeansClusterConfiguration;
use Kinintel\ValueObjects\Dataset\Field;

class KMeansCluster {

    /**
     * @param KMeansClusterConfiguration $config
     * @param TabularDataset $dataSet
     * @param MetricCalculator $calculator
     * @return ArrayTabularDataset
     */
    public function process($config, $sourceDataset, $calculator) {
        $sourceData = $sourceDataset->getAllData();

        $keyToId = []; //Goes from keyname to index
        $IdToKey = []; //Goes from index to keyname
        $maxDist = []; //Key -> MaxDistanceToThisElement
        $centroids = []; //Array of the k cluster centroids containing their distance to all the elements
        $distVecs = []; //Array of the distance from each individual element to each other element
        $kfname = $sourceDataset->getColumns()[0]->getName();
        $kfname2 = $sourceDataset->getColumns()[1]->getName();
        $vfname = $sourceDataset->getColumns()[2]->getName();
        $totalIDs = 0;

        for ($i = 0; $i < count($sourceData); $i++) { //For each row
            $key = $sourceData[$i][$kfname];
            $key2 = $sourceData[$i][$kfname2];
            $val = $sourceData[$i][$vfname];

            //Add the key to the keyToId array if it's not in there
            if (!array_key_exists($key, $keyToId)) {
                $keyToId[$key] = $totalIDs;
                $IdToKey[$totalIDs] = $key;
                $totalIDs++;
            }
            if (!array_key_exists($key2, $keyToId)) {
                $keyToId[$key2] = $totalIDs;
                $IdToKey[$totalIDs] = $key2;
                $totalIDs++;
            } else {

            }

            $distVecs[$keyToId[$key]][$keyToId[$key2]] = $val; // Input the distance vector from this row of source data

            // Update the max distance from this element to another element
            $maxDist[$keyToId[$key]] = max($maxDist[$keyToId[$key]] ?? 0, $val);
            $maxDist[$keyToId[$key2]] = max($maxDist[$keyToId[$key2]] ?? 0, $val);
        }

        // Generate k centroids
        for ($i = 0; $i < $config->getNumberOfClusters(); $i++) {
            for ($j = 0; $j < count($keyToId); $j++) {
                $centroids[$i][$j] = $maxDist[$j] * rand(1, 999) / 1000; //Randomise the "position" (distance to each element) of the centroid within the bounding box of data
            }
        }

        /**
         * @var integer[] $lastMatches id -> centroid
         */
        $lastMatches = []; // Element -> Centroid The array of the closest centroid to each element in the last timestep

        /**
         * @var integer[] $bestMatches id => centroid
         */
        $bestMatches = []; // Element -> Centroid The array of the closest centroid to each element in the current timestep

        /**
         * @var integer[][] $centroidsChildren centroid => [id of children]
         */
        $centroidsChildren = [];

        for ($t = 0; $t < $config->getTimestepLimit(); $t++) {

            for ($c = 0; $c < count($centroids); $c++) {
                $centroidsChildren[$c] = []; //The array of centroids and their matching elements
            }

            //Find the closest centroid to each element.
            for ($j = 0; $j < $totalIDs; $j++) {
                $bestMatches[$j] = $this->bestCentroid($distVecs[$j], $centroids, $calculator);

                $centroidsChildren[$bestMatches[$j]][] = $j; //Best matching centroid gets j as child
            }
            if ($bestMatches === $lastMatches) { //If there was no change since the last iteration
                break;
            } else {
                $lastMatches = $bestMatches;
            }

            for ($c = 0; $c < count($centroids); $c++) { //Foreach centroid
                if (count($centroidsChildren[$c]) > 0) { //If the centroid has any children
                    //Reset the centroid's distance vectors to zero
                    for ($n = 0; $n < count($keyToId); $n++) {
                        $centroids[$c][$n] = 0;
                    }
                    //Set the centroid's distance vectors to the average distance vector of their children
                    for ($m = 0; $m < count($centroidsChildren[$c]); $m++) { //Foreach child in of centroid c
                        for ($n = 0; $n < count($keyToId); $n++) { //For each element, add
                            $id = $centroidsChildren[$c][$m];
                            $centroids[$c][$n] += $distVecs[$id][$n];
                        }
                    }
                    for ($n = 0; $n < count($keyToId); $n++) { //For each element, divide the corresponding distvec component by the number of children
                        $centroids[$c][$n] /= count($centroidsChildren[$c]);
                    } //Centroid's distance vectors are set to average distance vector of its children

                } else { //Send this centroid to the nearest element
                    $nearest = 0;
                    for ($n = 0; $n < count($keyToId); $n++) {
                        $nearest = $centroids[$c][$n] < $centroids[$c][$nearest] ? $n : $nearest;
                    }
                    $centroids[$c] = $distVecs[$nearest];
                }
            }
        }

        $outputData = []; //The array to format the data and write to snapshot
        for ($c = 0; $c < count($centroids); $c++) {
            $outputData[$c]["id"] = $c;
            $outputData[$c]["elements"] = [];
            foreach ($centroidsChildren[$c] as $child) {
                $outputData[$c]["elements"][] = $IdToKey[$child];
            }
            $outputData[$c]["size"] = count($centroidsChildren[$c]);
        }

        return new ArrayTabularDataset([
            new Field("id"),
            new Field("elements"),
            new Field("size")
        ], $outputData);
    }

    /**
     * Takes in the distVec of an element, and the current centroids, and finds the closest centroid
     * @param float[] $distVec
     * @param MetricCalculator $calculator
     * @return int
     */
    private function bestCentroid($distVec, $centroids, $calculator) {
        $bd = null; //Best distance
        $bestMatch = 0; //Closest Centroid

        for ($c = 0; $c < count($centroids); $c++) {
            $d = $calculator->calculateDistance($distVec, $centroids[$c]);
            if (is_null($bd) || $d < $bd) {
                $bd = $d;
                $bestMatch = $c;
            }
        }
        return $bestMatch;


//        for ($c = 0; $c < count($centroids); $c++){
//            //Calculate distance to centroid c
//            $d = $calculator->calculateDistance($distVec, $centroids[$c]);
//            //If best distance is unset, calculate it;
//            $bd = $bd == -1 ? $d : $bd;
//            //If distance is smaller than the distance to the current best centroid, set a new best centroid
//            $bestMatches[$j] = $centroids[$c][$j] < $d ? $c : $bestMatches[$j];
//        }
    }
}