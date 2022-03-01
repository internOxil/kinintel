<?php


namespace Kinintel\Objects\Datasource\SQLDatabase\Util;


use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Template\TemplateParser;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinintel\Services\Util\SQLClauseSanitiser;

class SQLFilterValueEvaluator {

    /**
     * @var SQLClauseSanitiser
     */
    private $sqlClauseSanitiser;


    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    /**
     * SQLFilterValueEvaluator constructor.
     *
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($databaseConnection) {
        $this->sqlClauseSanitiser = Container::instance()->get(SQLClauseSanitiser::class);
        $this->databaseConnection = $databaseConnection;
    }


    /**
     * Evaluate a filter value using all required rules
     *
     * @param $value
     * @param array $templateParameters
     */
    public function evaluateFilterValue($value, $templateParameters = [], $tableAlias = null, &$outputParameters = []) {

        $valueArray = is_array($value) ? $value : [$value];

        $valueStrings = [];
        foreach ($valueArray as $valueEntry) {


            // Replace any template parameters
            $value = preg_replace_callback("/([\*%]*){{(.*?)}}([\*%]*)/", function ($matches) use (&$outputParameters, $templateParameters) {
                $matchingParamValue = $templateParameters[$matches[2]] ?? null;
                $valueArray = is_array($matchingParamValue) ? $matchingParamValue : [$matchingParamValue];
                foreach ($valueArray as $matchingParamValueElement) {
                    $outputParameters[] = ($matches[1] ? "%" : "") . $matchingParamValueElement . ($matches[3] ? "%" : "");
                }
                return str_repeat("?,", sizeof($valueArray) - 1) . "?";
            }, $valueEntry);

            // Evaluate time offset parameters for days ago and hours ago
            $value = preg_replace_callback("/([0-9]+)_DAYS_AGO/", function ($matches) use (&$outputParameters) {
                $outputParameters[] = (new \DateTime())->sub(new \DateInterval("P" . $matches[1] . "D"))->format("Y-m-d H:i:s");
                return "?";
            }, $value);

            $value = preg_replace_callback("/([0-9]+)_HOURS_AGO/", function ($matches) use (&$outputParameters) {
                $outputParameters[] = (new \DateTime())->sub(new \DateInterval("PT" . $matches[1] . "H"))->format("Y-m-d H:i:s");
                return "?";
            }, $value);


            // If no [[ expressions assume this is a single string
            if (preg_replace("/\[\[(.*?)\]\]/", "", $value) == $valueEntry) {
                $outputParameters[] = $value;
                $value = "?";
            } else {

                $value = $this->sqlClauseSanitiser->sanitiseSQL($value, $outputParameters);

                // Remove any [[ from column names and prefix with table alias if supplied
                $value = preg_replace("/\[\[(.*?)\]\]/", ($tableAlias ? $tableAlias . "." : "") . $this->databaseConnection->escapeColumn("$1"), $value);
            }


            $valueStrings[] = $value;

        }

        return join(",", $valueStrings);

    }


}