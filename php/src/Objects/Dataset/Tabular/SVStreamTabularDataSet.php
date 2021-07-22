<?php


namespace Kinintel\Objects\Dataset\Tabular;

use Kinikit\Core\Stream\ReadableStream;
use Kinikit\Core\Stream\StreamException;
use Kinikit\Core\Util\StringUtils;
use Kinintel\ValueObjects\Dataset\Field;

/**
 * General purpose tabular data set which receives a Separated Value stream
 * as input
 *
 * Class SVStreamTabularDataSet
 * @package Kinintel\Objects\Dataset\Tabular
 */
class SVStreamTabularDataSet extends TabularDataset {

    /**
     * @var ReadableStream
     */
    private $stream;

    /**
     * @var string
     */
    private $separator;

    /**
     * @var string
     */
    private $enclosure;


    /**
     * @var integer
     */
    private $limit;

    /**
     * @var int
     */
    private $readItems = 0;


    /**
     * CSV Columns as read from the stream
     *
     * @var Field[]
     */
    private $csvColumns = [];


    /**
     * SVStreamTabularDataSet constructor.
     *
     * @param Field[] $columns
     * @param ReadableStream $stream
     * @param int $firstRowOffset
     * @param false $firstRowHeader
     * @param string $separator
     * @param string $enclosure
     * @param integer $limit
     * @param integer $offset
     */
    public function __construct($columns, $stream, $firstRowOffset = 0, $firstRowHeader = false, $separator = ",", $enclosure = '"', $limit = PHP_INT_MAX, $offset = 0) {
        parent::__construct($columns);
        $this->stream = $stream;
        $this->separator = $separator;
        $this->enclosure = $enclosure;

        // Total limit including offset
        $this->limit = $limit + $offset + $firstRowOffset;


        // If first row offset supplied, move there now
        if ($firstRowOffset) {
            for ($i = 0; $i < $firstRowOffset; $i++)
                $this->nextDataItem();
        }

        // Read header row
        if ($firstRowHeader) {
            $this->readHeaderRow();
        } else {
            $this->csvColumns = $columns;
        }


        // if offset, forward wind to that offset
        if ($offset) {
            for ($i = 0; $i < $offset; $i++)
                $this->nextDataItem();
        }

    }


    /**
     * Fall back to the csv columns if no explicit columns
     *
     * @return Field[]
     */
    public function getColumns() {
        return sizeof($this->columns) ? $this->columns : $this->csvColumns;
    }


    /**
     * Read the next data item from the stream using the SV format.
     */
    public function nextRawDataItem() {

        // Shortcut if we have reached the limit
        if ($this->readItems >= $this->limit)
            return null;

        try {
            $csvLine = $this->stream->readCSVLine($this->separator, $this->enclosure);

            $this->readItems++;

            // Only continue if we have some content
            if (trim($csvLine[0])) {

                while (sizeof($this->csvColumns) < sizeof($csvLine)) {
                    $this->csvColumns[] = new Field("column" . (sizeof($this->csvColumns) + 1));
                }

                $dataItem = [];
                foreach ($csvLine as $index => $value) {
                    $dataItem[$this->csvColumns[$index]->getName()] = trim($value);
                }

                return $dataItem;
            } else {
                return null;
            }


        } catch (StreamException $e) {
            return null;
        }

    }

    // Read the header row
    private function readHeaderRow() {

        // Grab the line as items
        $csvLine = $this->stream->readCSVLine($this->separator, $this->enclosure);

        // Create fields from these
        $columns = [];
        foreach ($csvLine as $column) {
            // Expand out the title and the name
            $name = StringUtils::convertToCamelCase(trim($column));
            $columns[] = new Field($name);
        }

        $this->csvColumns = $columns;
    }


}