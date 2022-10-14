<?php

namespace Kinintel\ValueObjects\DataProcessor\Configuration\DatasourceImport;

use Kinintel\Objects\Dataset\DatasetInstance;

class TabularDatasourceChangeTrackingProcessorConfiguration {

    /**
     * @var string[]
     */
    private $sourceDatasourceKeys;
    /**
     * @var string
     */
    private $targetLatestDatasourceKey;

    /**
     * @var string
     */
    private $targetChangeDatasourceKey;

    /**
     * @var int
     */
    private $sourceReadChunkSize = null;

    /**
     * @var int
     */
    private $targetWriteChunkSize = 500;

    /**
     * @param string[] $sourceDatasourceKeys
     * @param string $targetLatestDatasourceKey
     * @param string $targetChangeDatasourceKey
     * @param int $sourceReadChunkSize
     * @param int $targetWriteChunkSize
     */
    public function __construct($sourceDatasourceKeys, $targetLatestDatasourceKey = null, $targetChangeDatasourceKey = null, $sourceReadChunkSize = 200, $targetWriteChunkSize = 200) {
        $this->sourceDatasourceKeys = $sourceDatasourceKeys;
        $this->targetLatestDatasourceKey = $targetLatestDatasourceKey;
        $this->targetChangeDatasourceKey = $targetChangeDatasourceKey;
        $this->sourceReadChunkSize = $sourceReadChunkSize;
        $this->targetWriteChunkSize = $targetWriteChunkSize;
    }

    /**
     * @return string[]
     */
    public function getSourceDatasourceKeys() {
        return $this->sourceDatasourceKeys;
    }

    /**
     * @param string[] $sourceDatasourceKeys
     */
    public function setSourceDatasourceKeys($sourceDatasourceKeys) {
        $this->sourceDatasourceKeys = $sourceDatasourceKeys;
    }

    /**
     * @return string
     */
    public function getTargetLatestDatasourceKey() {
        return $this->targetLatestDatasourceKey;
    }

    /**
     * @param string $targetLatestDatasourceKey
     */
    public function setTargetLatestDatasourceKey($targetLatestDatasourceKey) {
        $this->targetLatestDatasourceKey = $targetLatestDatasourceKey;
    }

    /**
     * @return string
     */
    public function getTargetChangeDatasourceKey() {
        return $this->targetChangeDatasourceKey;
    }

    /**
     * @param string $targetChangeDatasourceKey
     */
    public function setTargetChangeDatasourceKey($targetChangeDatasourceKey) {
        $this->targetChangeDatasourceKey = $targetChangeDatasourceKey;
    }

    /**
     * @return int
     */
    public function getSourceReadChunkSize() {
        return $this->sourceReadChunkSize;
    }

    /**
     * @param int $sourceReadChunkSize
     */
    public function setSourceReadChunkSize($sourceReadChunkSize) {
        $this->sourceReadChunkSize = $sourceReadChunkSize;
    }

    /**
     * @return int
     */
    public function getTargetWriteChunkSize() {
        return $this->targetWriteChunkSize;
    }

    /**
     * @param int $targetWriteChunkSize
     */
    public function setTargetWriteChunkSize($targetWriteChunkSize) {
        $this->targetWriteChunkSize = $targetWriteChunkSize;
    }

}