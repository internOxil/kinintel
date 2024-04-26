<?php

namespace Kinintel\Test\Services\DataProcessor\Query;

use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinintel\Exception\DatasourceUpdateException;
use Kinintel\Objects\Authentication\AuthenticationCredentialsInstance;
use Kinintel\Objects\DataProcessor\DataProcessorInstance;
use Kinintel\Objects\Dataset\DatasetInstance;
use Kinintel\Objects\Dataset\DatasetInstanceSummary;
use Kinintel\Objects\Dataset\Tabular\ArrayTabularDataset;
use Kinintel\Objects\Datasource\DatasourceInstance;
use Kinintel\Services\DataProcessor\Query\QueryCachingDataProcessor;
use Kinintel\Services\Dataset\DatasetService;
use Kinintel\Services\Datasource\DatasourceService;
use Kinintel\ValueObjects\DataProcessor\Configuration\Query\QueryCachingDataProcessorConfiguration;
use Kinintel\ValueObjects\Dataset\Field;
use Kinintel\ValueObjects\Datasource\Configuration\Caching\CachingDatasourceConfig;
use Kinintel\ValueObjects\Datasource\Configuration\SQLDatabase\Index;
use Kinintel\ValueObjects\Datasource\Configuration\SQLDatabase\ManagedTableSQLDatabaseDatasourceConfig;
use Kinintel\ValueObjects\Datasource\Update\DatasourceUpdateField;
use Kinintel\ValueObjects\Parameter\Parameter;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class QueryCachingDataProcessorTest extends TestCase {

    /**
     * @var MockObject
     */
    private $authCreds;

    /**
     * @var MockObjectProvider
     */
    private $datasourceService;

    /**
     * @var MockObjectProvider
     */
    private $datasetService;

    public function setUp(): void {
        $this->authCreds = MockObjectProvider::instance()->getMockInstance(AuthenticationCredentialsInstance::class);
        $this->datasourceService = MockObjectProvider::instance()->getMockInstance(DatasourceService::class);
        $this->datasetService = MockObjectProvider::instance()->getMockInstance(DatasetService::class);
    }

    public function testDoesCreateCacheTableAndDatasourceAndCachingDatasourceCorrectlyWithoutParameters() {

        $processorConfig = new QueryCachingDataProcessorConfiguration(25, 1, 12, ["col1"]);

        $sourceDatasetSummary = new DatasetInstanceSummary(
            title: "My Query",
            datasourceInstanceKey: "some_source"
        );


        $mockDataset = MockObjectProvider::instance()->getMockInstance(ArrayTabularDataset::class);
        $mockDataset->returnValue("getColumns", [
            new Field("col1"),
            new Field("col2")
        ]);

        $this->datasetService->returnValue("getDataSetInstance", $sourceDatasetSummary, [25]);
        $this->datasetService->returnValue("getEvaluatedDataSetForDataSetInstance", $mockDataset, [$sourceDatasetSummary]);


        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getAccountId", 1);
        $processorInstance->returnValue("getProjectKey", "my_project");


        $processor = new QueryCachingDataProcessor($this->authCreds, $this->datasourceService, $this->datasetService);
        $processor->process($processorInstance);

        $history = $this->datasourceService->getMethodCallHistory("saveDataSourceInstance");

        $expectedCacheConfig = new ManagedTableSQLDatabaseDatasourceConfig(
            source: "table",
            tableName: "query_cache.dataset_25_cache",
            columns: [
                new DatasourceUpdateField(
                    name: "parameters",
                    valueExpression: "1",
                    keyField: true
                ),
                new DatasourceUpdateField(
                    name: "cached_time",
                    type: Field::TYPE_DATE_TIME,
                    keyField: true
                ),
                new DatasourceUpdateField(
                    name: "col1",
                    keyField: true
                ),
                new DatasourceUpdateField(
                    name: "col2"
                )
            ],
            indexes: [
                new Index(["parameters", "cached_time"])
            ]
        );

        $expectedCacheDatasourceInstance = new DatasourceInstance("dataset-25-cache", "My Query Cache",
            "sqldatabase", $expectedCacheConfig, "test");
        $expectedCacheDatasourceInstance->setAccountId(1);
        $expectedCacheDatasourceInstance->setProjectKey("my_project");
        $this->assertEquals($expectedCacheDatasourceInstance, $history[0][0]);


        $expectedCachingConfig = new CachingDatasourceConfig(
            sourceDatasetId: 25,
            cachingDatasourceKey: "dataset-25-cache",
            cacheExpiryDays: 1,
            cacheHours: 12
        );

        $expectedCachingDatasourceInstance = new DatasourceInstance("dataset-25-caching-datasource", "My Query Caching Datasource",
            "caching", $expectedCachingConfig);
        $expectedCachingDatasourceInstance->setAccountId(1);
        $expectedCachingDatasourceInstance->setProjectKey("my_project");
        $this->assertEquals($expectedCachingDatasourceInstance, $history[1][0]);

    }

    public function testDoesPassThroughParametersWhenCreatingCachingDatasource() {

        $processorConfig = new QueryCachingDataProcessorConfiguration(25, 1, 12);
        $params = [new Parameter("param", "Param")];

        $sourceDatasetSummary = new DatasetInstanceSummary(
            title: "My Query",
            datasourceInstanceKey: "some_source",
            parameters: $params
        );


        $mockDataset = MockObjectProvider::instance()->getMockInstance(ArrayTabularDataset::class);
        $mockDataset->returnValue("getColumns", [
            new Field("col1"),
            new Field("col2")
        ]);

        $this->datasetService->returnValue("getDataSetInstance", $sourceDatasetSummary, [25]);
        $this->datasetService->returnValue("getEvaluatedDataSetForDataSetInstance", $mockDataset, [$sourceDatasetSummary]);


        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getAccountId", 1);
        $processorInstance->returnValue("getProjectKey", "my_project");


        $processor = new QueryCachingDataProcessor($this->authCreds, $this->datasourceService, $this->datasetService);
        $processor->process($processorInstance);

        $history = $this->datasourceService->getMethodCallHistory("saveDataSourceInstance");

        $expectedCacheConfig = new ManagedTableSQLDatabaseDatasourceConfig(
            source: "table",
            tableName: "query_cache.dataset_25_cache",
            columns: [
                new DatasourceUpdateField(
                    name: "parameters",
                    keyField: true
                ),
                new DatasourceUpdateField(
                    name: "cached_time",
                    type: Field::TYPE_DATE_TIME,
                    keyField: true
                ),
                new DatasourceUpdateField(
                    name: "col1"
                ),
                new DatasourceUpdateField(
                    name: "col2"
                )
            ],
            indexes: [
                new Index(["parameters", "cached_time"])
            ]
        );


        $expectedCacheDatasourceInstance = new DatasourceInstance(
            key: "dataset-25-cache",
            title: "My Query Cache",
            type: "sqldatabase",
            config: $expectedCacheConfig,
            credentialsKey: "test"
        );

        $expectedCacheDatasourceInstance->setAccountId(1);
        $expectedCacheDatasourceInstance->setProjectKey("my_project");
        $this->assertEquals($expectedCacheDatasourceInstance, $history[0][0]);


        $expectedCachingConfig = new CachingDatasourceConfig(
            sourceDatasetId: 25,
            cachingDatasourceKey: "dataset-25-cache",
            cacheExpiryDays: 1,
            cacheHours: 12
        );

        $expectedCachingDatasourceInstance = new DatasourceInstance(
            key: "dataset-25-caching-datasource",
            title: "My Query Caching Datasource",
            type: "caching",
            config: $expectedCachingConfig,
            parameters: $params
        );
        $expectedCachingDatasourceInstance->setAccountId(1);
        $expectedCachingDatasourceInstance->setProjectKey("my_project");
        $this->assertEquals($expectedCachingDatasourceInstance, $history[1][0]);

    }

    public function testDoesCallCorrectDeleteMethodsWhenOnInstanceDelete() {

        $processorConfig = new QueryCachingDataProcessorConfiguration(25, 1, 12);
        $processor = new QueryCachingDataProcessor($this->authCreds, $this->datasourceService, $this->datasetService);

        $instance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $instance->returnValue("returnConfig", $processorConfig);

        $processor->onInstanceDelete($instance);

        $this->assertTrue($this->datasourceService->methodWasCalled("removeDatasourceInstance", ["dataset-25-cache"]));
        $this->assertTrue($this->datasourceService->methodWasCalled("removeDatasourceInstance", ["dataset-25-caching-datasource"]));

    }

}