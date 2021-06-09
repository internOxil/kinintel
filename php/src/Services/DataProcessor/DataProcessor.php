<?php


namespace Kinintel\Services\DataProcessor;

/**
 * @implementation datasourceimport \Kinintel\Services\DataProcessor\Generic\DatasourceImportProcessor
 */
interface DataProcessor {


    /**
     * Get the config class expected by the process method.  Can be null if
     * no config required
     *
     * @return string
     */
    public function getConfigClass();

    /**
     * Main process method.  Receives config which should be typed according
     * to the config class declared above.
     */
    public function process($config = null);


}