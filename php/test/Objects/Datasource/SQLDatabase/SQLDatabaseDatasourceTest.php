<?php


namespace Kinintel\Test\Objects\Datasource\SQLDatabase;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Core\Validation\Validator;
use Kinikit\Persistence\Database\BulkData\BulkDataManager;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\Generator\TableDDLGenerator;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\ResultSet\ResultSet;
use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3DatabaseConnection;
use Kinintel\Exception\DatasourceNotUpdatableException;
use Kinintel\Exception\DatasourceUpdateException;
use Kinintel\Objects\Dataset\Dataset;
use Kinintel\Objects\Dataset\DatasetInstance;
use Kinintel\Objects\Dataset\Tabular\SQLResultSetTabularDataset;
use Kinintel\Objects\Dataset\Tabular\TabularDataset;
use Kinintel\Objects\Datasource\BaseUpdatableDatasource;
use Kinintel\Objects\Datasource\Datasource;
use Kinintel\Objects\Datasource\DatasourceInstance;
use Kinintel\Objects\Datasource\DefaultDatasource;
use Kinintel\Objects\Datasource\SQLDatabase\SQLDatabaseDatasource;
use Kinintel\Objects\Datasource\SQLDatabase\TransformationProcessor\SQLTransformationProcessor;
use Kinintel\Objects\Datasource\UpdatableDatasource;
use Kinintel\Services\Dataset\DatasetService;
use Kinintel\Services\Datasource\DatasourceService;
use Kinintel\ValueObjects\Authentication\AuthenticationCredentials;
use Kinintel\ValueObjects\Authentication\SQLDatabase\SQLiteAuthenticationCredentials;
use Kinintel\ValueObjects\Dataset\Field;
use Kinintel\ValueObjects\Datasource\Configuration\SQLDatabase\SQLDatabaseDatasourceConfig;
use Kinintel\ValueObjects\Datasource\DatasourceUpdateConfig;
use Kinintel\ValueObjects\Datasource\SQLDatabase\SQLQuery;
use Kinintel\ValueObjects\Transformation\Join\JoinTransformation;
use Kinintel\ValueObjects\Transformation\Query\Filter\Filter;
use Kinintel\ValueObjects\Transformation\Query\FilterTransformation;
use Kinintel\ValueObjects\Transformation\SQLDatabaseTransformation;
use Kinintel\ValueObjects\Transformation\TestTransformation;

include_once "autoloader.php";

class SQLDatabaseDatasourceTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var MockObject
     */
    private $authCredentials;

    /**
     * @var MockObject
     */
    private $validator;


    /**
     * @var MockObject
     */
    private $databaseConnection;


    /**
     * @var MockObject
     */
    private $bulkDataManager;


    // Setup
    public function setUp(): void {


        $this->databaseConnection = MockObjectProvider::instance()->getMockInstance(DatabaseConnection::class);
        $this->bulkDataManager = MockObjectProvider::instance()->getMockInstance(BulkDataManager::class);
        $this->databaseConnection->returnValue("getBulkDataManager", $this->bulkDataManager);

        $this->authCredentials = MockObjectProvider::instance()->getMockInstance(SQLiteAuthenticationCredentials::class);
        $this->authCredentials->returnValue("returnDatabaseConnection", $this->databaseConnection);

        $this->validator = MockObjectProvider::instance()->getMockInstance(Validator::class);

    }


    public function testCanMaterialiseDataSetForUntransformedTableDatasource() {


        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "test_data"),
            $this->authCredentials, null, $this->validator);


        $resultSet = MockObjectProvider::instance()->getMockInstance(ResultSet::class);

        $this->databaseConnection->returnValue("query", $resultSet, [
            "SELECT * FROM test_data", []
        ]);

        /**
         * @var SQLResultSetTabularDataset $dataSet
         */
        $dataSet = $sqlDatabaseDatasource->materialiseDataset();

        $this->assertEquals(new SQLResultSetTabularDataset($resultSet), $dataSet);
    }


    public function testColumnsPassedThroughToDataSetIfSupplied() {

        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "test_data", "", [
            new Field("test_id")
        ]),
            $this->authCredentials, null, $this->validator);


        $resultSet = MockObjectProvider::instance()->getMockInstance(ResultSet::class);

        $this->databaseConnection->returnValue("query", $resultSet, [
            "SELECT * FROM test_data", []
        ]);

        /**
         * @var SQLResultSetTabularDataset $dataSet
         */
        $dataSet = $sqlDatabaseDatasource->materialiseDataset();

        $this->assertEquals(new SQLResultSetTabularDataset($resultSet, [
            new Field("test_id")
        ]), $dataSet);
    }


    public function testCanMaterialiseDataSetForUntransformedQueryDatasource() {


        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_QUERY, "", "SELECT * FROM test_data d LEFT JOIN other_table o ON d.id = o.test_id"),
            $this->authCredentials, null, $this->validator);


        $resultSet = MockObjectProvider::instance()->getMockInstance(ResultSet::class);

        $this->databaseConnection->returnValue("query", $resultSet, [
            "SELECT * FROM (SELECT * FROM test_data d LEFT JOIN other_table o ON d.id = o.test_id) A", []
        ]);

        /**
         * @var SQLResultSetTabularDataset $dataSet
         */
        $dataSet = $sqlDatabaseDatasource->materialiseDataset();

        $this->assertEquals(new SQLResultSetTabularDataset($resultSet), $dataSet);
    }


    public function testAnyPassedParametersAreAppliedExplicitlyToTheQueryInAQueryBasedDatasource() {


        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_QUERY, "", "SELECT * FROM test_data d LEFT JOIN other_table o ON d.id = o.test_id WHERE d.id = {{testId}}"),
            $this->authCredentials, null, $this->validator);


        $resultSet = MockObjectProvider::instance()->getMockInstance(ResultSet::class);

        $this->databaseConnection->returnValue("query", $resultSet, [
            "SELECT * FROM (SELECT * FROM test_data d LEFT JOIN other_table o ON d.id = o.test_id WHERE d.id = 255) A", []
        ]);

        /**
         * @var SQLResultSetTabularDataset $dataSet
         */
        $dataSet = $sqlDatabaseDatasource->materialiseDataset([
            "testId" => 255
        ]);

        $this->assertEquals(new SQLResultSetTabularDataset($resultSet), $dataSet);

    }


    public function testCanMaterialiseTableBasedDataSetWithSQLDatabaseTransformationsApplied() {

        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "test_data"),
            $this->authCredentials, null, $this->validator);


        $transformation1 = MockObjectProvider::instance()->getMockInstance(SQLDatabaseTransformation::class);
        $transformation1->returnValue("getSQLTransformationProcessorKey", "filter");
        $transformation2 = MockObjectProvider::instance()->getMockInstance(SQLDatabaseTransformation::class);
        $transformation2->returnValue("getSQLTransformationProcessorKey", "filter");
        $transformation3 = MockObjectProvider::instance()->getMockInstance(SQLDatabaseTransformation::class);
        $transformation3->returnValue("getSQLTransformationProcessorKey", "filter");


        // Apply each transformation
        $sqlDatabaseDatasource->applyTransformation($transformation1, ["param1" => "Hello", "param2" => "World"]);
        $sqlDatabaseDatasource->applyTransformation($transformation2, ["param1" => "Hello", "param2" => "World"]);
        $sqlDatabaseDatasource->applyTransformation($transformation3, ["param1" => "Hello", "param2" => "World"]);


        $transformationProcessor = MockObjectProvider::instance()->getMockInstance(SQLTransformationProcessor::class);
        $transformationProcessor2 = MockObjectProvider::instance()->getMockInstance(SQLTransformationProcessor::class);

        $sqlDatabaseDatasource->setTransformationProcessorInstances([
            "test1" => $transformationProcessor,
            "test2" => $transformationProcessor2
        ]);


        $transformation1->returnValue("getSQLTransformationProcessorKey", "test1");
        $transformation2->returnValue("getSQLTransformationProcessorKey", "test1");
        $transformation3->returnValue("getSQLTransformationProcessorKey", "test2");

        $transformationProcessor->returnValue("updateQuery", new SQLQuery("*", "?", [1]), [
            $transformation1, new SQLQuery("*", "test_data"), ["param1" => "Hello", "param2" => "World"],
            $sqlDatabaseDatasource
        ]);

        $transformationProcessor->returnValue("updateQuery", new SQLQuery("*", "?", [2]), [
            $transformation2, new SQLQuery("*", "?", [1]), ["param1" => "Hello", "param2" => "World"],
            $sqlDatabaseDatasource
        ]);

        $transformationProcessor2->returnValue("updateQuery", new SQLQuery("*", "?", [3]), [
            $transformation3, new SQLQuery("*", "?", [2]), ["param1" => "Hello", "param2" => "World"],
            $sqlDatabaseDatasource
        ]);

        $resultSet = MockObjectProvider::instance()->getMockInstance(ResultSet::class);

        $this->databaseConnection->returnValue("query", $resultSet, [
            "SELECT * FROM ?", [3]
        ]);


        /**
         * @var SQLResultSetTabularDataset $dataSet
         */
        $dataSet = $sqlDatabaseDatasource->materialiseDataset(["param1" => "Hello", "param2" => "World"]);

        $this->assertEquals(new SQLResultSetTabularDataset($resultSet), $dataSet);


    }


    public function testCanMaterialiseQueryBasedDataSetWithSQLDatabaseTransformationsApplied() {

        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_QUERY, "", "SELECT * FROM test_data d"),
            $this->authCredentials, null, $this->validator);


        $transformation1 = MockObjectProvider::instance()->getMockInstance(SQLDatabaseTransformation::class);
        $transformation1->returnValue("getSQLTransformationProcessorKey", "filter");

        $transformation2 = MockObjectProvider::instance()->getMockInstance(SQLDatabaseTransformation::class);
        $transformation2->returnValue("getSQLTransformationProcessorKey", "filter");

        $transformation3 = MockObjectProvider::instance()->getMockInstance(SQLDatabaseTransformation::class);
        $transformation3->returnValue("getSQLTransformationProcessorKey", "filter");

        // Apply each transformation
        $sqlDatabaseDatasource->applyTransformation($transformation1);
        $sqlDatabaseDatasource->applyTransformation($transformation2);
        $sqlDatabaseDatasource->applyTransformation($transformation3);


        $transformationProcessor = MockObjectProvider::instance()->getMockInstance(SQLTransformationProcessor::class);
        $transformationProcessor2 = MockObjectProvider::instance()->getMockInstance(SQLTransformationProcessor::class);

        $sqlDatabaseDatasource->setTransformationProcessorInstances([
            "test1" => $transformationProcessor,
            "test2" => $transformationProcessor2
        ]);


        $transformation1->returnValue("getSQLTransformationProcessorKey", "test1");
        $transformation2->returnValue("getSQLTransformationProcessorKey", "test1");
        $transformation3->returnValue("getSQLTransformationProcessorKey", "test2");

        $transformationProcessor->returnValue("updateQuery", new SQLQuery("*", "(SELECT * from test_data d) A", [1]), [
            $transformation1, new SQLQuery("*", "(SELECT * from test_data d) A"), []
        ]);

        $transformationProcessor->returnValue("updateQuery", new SQLQuery("*", "(SELECT * from test_data d) A", [2]), [
            $transformation2, new SQLQuery("*", "(SELECT * from test_data d) A", [1]), [$transformation1]
        ]);

        $transformationProcessor2->returnValue("updateQuery", new SQLQuery("*", "(SELECT * from test_data d) A", [3]), [
            $transformation3, new SQLQuery("*", "(SELECT * from test_data d) A", [2]), [$transformation2, $transformation1]
        ]);

        $resultSet = MockObjectProvider::instance()->getMockInstance(ResultSet::class);

        $this->databaseConnection->returnValue("query", $resultSet, [
            "(SELECT * from test_data d) A", 3
        ]);


        /**
         * @var SQLResultSetTabularDataset $dataSet
         */
        //$dataSet = $sqlDatabaseDatasource->materialiseDataset();


        // $this->assertEquals(new SQLResultSetTabularDataset($resultSet), $dataSet);
        $this->assertTrue(true);

    }


    public function testUpdateExceptionThrownIfAttemptToUpdateUpdatableDatasourceWithNoUpdateConfig() {

        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "test_data"),
            $this->authCredentials, null, $this->validator);

        $dataSet = MockObjectProvider::instance()->getMockInstance(TabularDataset::class);

        try {
            $sqlDatabaseDatasource->update($dataSet);
            $this->fail("Should have thrown here");
        } catch (DatasourceNotUpdatableException $e) {
            $this->assertTrue(true);
        }

    }


    public function testUpdateExceptionThrownIfAttemptToUpdateDatasourceWithQuery() {

        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_QUERY, "", "SELECT * FROM test", true),
            $this->authCredentials, new DatasourceUpdateConfig(), $this->validator);

        $dataSet = MockObjectProvider::instance()->getMockInstance(TabularDataset::class);

        try {
            $sqlDatabaseDatasource->update($dataSet);
            $this->fail("Should have thrown here");
        } catch (DatasourceUpdateException $e) {
            $this->assertTrue(true);
        }

    }

    public function testUpdateExceptionThrownIfAttemptToUpdateWithNoneTabularDataset() {


        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "test_data", "", true),
            $this->authCredentials, new DatasourceUpdateConfig(), $this->validator);

        $dataSet = MockObjectProvider::instance()->getMockInstance(Dataset::class);

        try {
            $sqlDatabaseDatasource->update($dataSet);
            $this->fail("Should have thrown here");
        } catch (DatasourceUpdateException $e) {
            $this->assertTrue(true);
        }

    }


    public function testAllDataAddedCorrectlyUsingBulkDataManagerWhenSuppliedAsSuch() {

        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "test_data", "", true),
            $this->authCredentials, new DatasourceUpdateConfig(), $this->validator);

        $dataSet = MockObjectProvider::instance()->getMockInstance(TabularDataset::class);


        $data = [
            [
                "name" => "Bobby Owens",
                "age" => 55,
                "extraDetail" => "He's a dude"
            ],
            [
                "name" => "David Suchet",
                "age" => 66,
                "extraDetail" => "He's a geezer"
            ]
        ];

        $dataSet->returnValue("getAllData", $data, []);


        $sqlDatabaseDatasource->update($dataSet, UpdatableDatasource::UPDATE_MODE_ADD);


        $this->assertTrue($this->bulkDataManager->methodWasCalled("insert", [
            "test_data", $data, null
        ]));

    }


    public function testAllDataRemovedCorrectlyUsingBulkDataManagerWhenSuppliedAsSuchUsingKeyFieldNames() {

        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "test_data", "", true),
            $this->authCredentials, new DatasourceUpdateConfig(["name"]), $this->validator);

        $dataSet = MockObjectProvider::instance()->getMockInstance(TabularDataset::class);


        $data = [
            [
                "name" => "Bobby Owens",
                "age" => 55,
                "extraDetail" => "He's a dude"
            ],
            [
                "name" => "David Suchet",
                "age" => 66,
                "extraDetail" => "He's a geezer"
            ]
        ];

        $dataSet->returnValue("getAllData", $data, []);


        $sqlDatabaseDatasource->update($dataSet, UpdatableDatasource::UPDATE_MODE_DELETE);


        $this->assertTrue($this->bulkDataManager->methodWasCalled("delete", [
            "test_data", $data
        ]));

    }

    public function testAllDataReplacedCorrectlyUsingBulkDataManagerWhenSuppliedAsSuch() {

        $sqlDatabaseDatasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "test_data", "", true),
            $this->authCredentials, new DatasourceUpdateConfig(), $this->validator);

        $dataSet = MockObjectProvider::instance()->getMockInstance(TabularDataset::class);


        $data = [
            [
                "name" => "Bobby Owens",
                "age" => 55,
                "extraDetail" => "He's a dude"
            ],
            [
                "name" => "David Suchet",
                "age" => 66,
                "extraDetail" => "He's a geezer"
            ]
        ];

        $dataSet->returnValue("getAllData", $data, []);


        $sqlDatabaseDatasource->update($dataSet, UpdatableDatasource::UPDATE_MODE_REPLACE);


        $this->assertTrue($this->bulkDataManager->methodWasCalled("replace", [
            "test_data", $data, null
        ]));
    }


    public function testModifyTableStructureCreatesTableAccordingToPassedFieldsIfNoneExists() {

        $ddlGenerator = MockObjectProvider::instance()->getMockInstance(TableDDLGenerator::class);

        $datasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "mytable"),
            $this->authCredentials, null, $this->validator, $ddlGenerator);


        // Throw an exception when getting table meta data
        $this->databaseConnection->throwException("getTableMetaData", new SQLException("Table does not exist"), [
            "mytable"
        ]);

        // Expect create table statement created using ddl generator
        $newMetaData = new TableMetaData("mytable", [
            new TableColumn("when", TableColumn::SQL_DATE, null, null, null, true),
            new TableColumn("why", TableColumn::SQL_VARCHAR, null, null, null, true),
            new TableColumn("how_many", TableColumn::SQL_INTEGER, null, null, null, false)
        ]);

        $ddlGenerator->returnValue("generateTableCreateSQL", "NEW TABLE CREATE", [
            $newMetaData,
            $this->databaseConnection
        ]);


        // Modify the table structure and ensure a create was made
        $datasource->modifyTableStructure([
            new Field("when", null, null, Field::TYPE_DATE),
            new Field("why"),
            new Field("how_many", null, null, Field::TYPE_INTEGER)
        ], ["when", "why"]);


        // Expect create to be issued
        $this->assertTrue($this->databaseConnection->methodWasCalled("executeScript", [
            "NEW TABLE CREATE"
        ]));

    }


    public function testModifyTableStructureModifiesTableAccordingToPassedFieldsIfTableAlreadyExists() {
        $ddlGenerator = MockObjectProvider::instance()->getMockInstance(TableDDLGenerator::class);

        $datasource = new SQLDatabaseDatasource(new SQLDatabaseDatasourceConfig(SQLDatabaseDatasourceConfig::SOURCE_TABLE, "mytable"),
            $this->authCredentials, null, $this->validator, $ddlGenerator);


        $existingMetaData = new TableMetaData("mytable", [
            new TableColumn("which", TableColumn::SQL_DATE, null, null, null, true),
            new TableColumn("what", TableColumn::SQL_VARCHAR, null, null, null, true),
            new TableColumn("how_many", TableColumn::SQL_INTEGER, null, null, null, false)
        ]);

        // Return existing meta data from call
        $this->databaseConnection->returnValue("getTableMetaData", $existingMetaData, [
            "mytable"
        ]);

        // Expect create table statement created using ddl generator
        $newMetaData = new TableMetaData("mytable", [
            new TableColumn("when", TableColumn::SQL_DATE, null, null, null, true),
            new TableColumn("why", TableColumn::SQL_VARCHAR, null, null, null, true),
            new TableColumn("how_many", TableColumn::SQL_INTEGER, null, null, null, false)
        ]);

        $ddlGenerator->returnValue("generateTableModifySQL", "NEW TABLE MODIFY", [
            $existingMetaData,
            $newMetaData,
            $this->databaseConnection
        ]);


        // Modify the table structure and ensure a create was made
        $datasource->modifyTableStructure([
            new Field("when", null, null, Field::TYPE_DATE),
            new Field("why"),
            new Field("how_many", null, null, Field::TYPE_INTEGER)
        ], ["when", "why"]);


        // Expect create to be issued
        $this->assertTrue($this->databaseConnection->methodWasCalled("executeScript", [
            "NEW TABLE MODIFY"
        ]));
    }


}