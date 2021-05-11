<?php


namespace Kinintel\ValueObjects\Datasource\Configuration\SQLDatabase;


use Kinintel\ValueObjects\Datasource\DatasourceConfig;

class SQLDatabaseDatasourceConfig implements DatasourceConfig {

    /**
     * @var string
     */
    private $source;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string
     */
    private $query;

    // Currently supported sources for the data using the authenticated connection.
    const SOURCE_TABLE = "table";
    const SOURCE_QUERY = "query";

    /**
     * SQLDatabaseDatasourceConfig constructor.
     *
     * @param string $source
     * @param string $tableName
     * @param string $query
     */
    public function __construct($source, $tableName = "", $query = "") {
        $this->source = $source;
        $this->tableName = $tableName;
        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getSource() {
        return $this->source;
    }

    /**
     * @param string $source
     */
    public function setSource($source) {
        $this->source = $source;
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @param string $tableName
     */
    public function setTableName($tableName) {
        $this->tableName = $tableName;
    }

    /**
     * @return string
     */
    public function getQuery() {
        return $this->query;
    }

    /**
     * @param string $query
     */
    public function setQuery($query) {
        $this->query = $query;
    }


}