<?php

namespace Kinintel\Services\DataProcessor\DatasourceImport;

use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinintel\Exception\NoKeyFieldsException;
use Kinintel\Objects\DataProcessor\DataProcessorInstance;
use Kinintel\Objects\Dataset\DatasetInstance;
use Kinintel\Objects\Dataset\Tabular\ArrayTabularDataset;
use Kinintel\Services\Dataset\DatasetService;
use Kinintel\Services\Datasource\DatasourceService;
use Kinintel\TestBase;
use Kinintel\ValueObjects\DataProcessor\Configuration\DatasourceImport\SourceDatasource;
use Kinintel\ValueObjects\DataProcessor\Configuration\DatasourceImport\TabularDatasourceChangeTrackingProcessorConfiguration;
use Kinintel\ValueObjects\Dataset\Field;
use Kinintel\ValueObjects\Datasource\Update\DatasourceUpdate;
use Kinintel\ValueObjects\Transformation\Filter\Filter;
use Kinintel\ValueObjects\Transformation\Filter\FilterTransformation;
use Kinintel\ValueObjects\Transformation\Formula\Expression;
use Kinintel\ValueObjects\Transformation\Formula\FormulaTransformation;
use Kinintel\ValueObjects\Transformation\Summarise\SummariseExpression;
use Kinintel\ValueObjects\Transformation\Summarise\SummariseTransformation;
use Kinintel\ValueObjects\Transformation\TransformationInstance;

include_once "autoloader.php";

class TabularDatasourceChangeTrackingProcessorTest extends TestBase {

    /**
     * @var MockObject
     */
    private $datasourceService;

    /**
     * @var MockObject
     */
    private $datasetService;

    private TabularDatasourceChangeTrackingProcessor $processor;


    public function setUp(): void {
        $this->datasourceService = MockObjectProvider::instance()->getMockInstance(DatasourceService::class);
        $this->datasetService = MockObjectProvider::instance()->getMockInstance(DatasetService::class);
        $this->processor = new TabularDatasourceChangeTrackingProcessor($this->datasourceService, $this->datasetService);
        passthru("rm -rf Files/change_tracking_processors");
    }

    public function testNewFileCreatedInDataDirectoryOnProcessOfSourceDatasources() {

        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            sourceDatasourceKeys: ["test1", "test2"],
            sourceReadChunkSize: PHP_INT_MAX,
            customKeyFieldNames: ["name","age"]
        );
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");

        $test1Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ]
        ]);

        $test2Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Peter Storm",
                "age" => 15
            ],
            [
                "name" => "Iron Man",
                "age" => 40
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, PHP_INT_MAX
        ]);

        // Actually process
        $this->processor->process($processorInstance);

        // Expect the new and previous files to exist
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/new.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test2/previous.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/adds.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test2/deletes.txt"));

        $this->assertStringContainsString("Joe Bloggs#|!22\nJames Bond#|!56\nAndrew Smith#|!30", file_get_contents("Files/change_tracking_processors/test/test1/new.txt"));
        $this->assertStringContainsString("Peter Storm#|!15\nIron Man#|!40", file_get_contents("Files/change_tracking_processors/test/test2/previous.txt"));

    }


    public function testNestedObjectsAndArraysAreSerialisedToBase64ForChangeTracking() {

        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            ["test3"],
            sourceReadChunkSize: PHP_INT_MAX,
            customKeyFieldNames: ["name", "object", "array"]
        );
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");

        $object1 = ["name" => "Bingo", "age" => 5];
        $array1 = [
            [
                "note" => "Testing 1,2,3"
            ],
            [
                "note" => "Findings 1,2,3"
            ]
        ];
        $object2 = ["name" => "Bongo", "age" => 3];
        $array2 = [
            [
                "note" => "Testing 1,2,3,4"
            ],
            [
                "note" => "Findings 1,2,3,4"
            ]
        ];
        $object3 = ["name" => "Bango", "age" => 5];
        $array3 = [
            [
                "note" => "Testing 1,2,3"
            ],
            [
                "note" => "Findings 1,2,3"
            ]
        ];


        $test1Dataset = new ArrayTabularDataset([new Field("name"), new Field("object"), new Field("array")], [
            [
                "name" => "Joe Bloggs",
                "object" => $object1,
                "array" => $array1
            ],
            [
                "name" => "James Bond",
                "object" => $object2,
                "array" => $array2
            ],
            [
                "name" => "Andrew Smith",
                "object" => $object3,
                "array" => $array3
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test3", [], [], 0, 1
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test3", [], [], 0, PHP_INT_MAX
        ]);


        // Actually process
        $this->processor->process($processorInstance);

        // Expect the new and previous files to exist
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test3/new.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test3/previous.txt"));

        $this->assertStringContainsString("Joe Bloggs#|!base64:" . base64_encode(json_encode($object1)) . "#|!base64:" . base64_encode(json_encode($array1)), file_get_contents("Files/change_tracking_processors/test/test3/new.txt"));
        $this->assertStringContainsString("James Bond#|!base64:" . base64_encode(json_encode($object2)) . "#|!base64:" . base64_encode(json_encode($array2)), file_get_contents("Files/change_tracking_processors/test/test3/new.txt"));
        $this->assertStringContainsString("Andrew Smith#|!base64:" . base64_encode(json_encode($object3)) . "#|!base64:" . base64_encode(json_encode($array3)), file_get_contents("Files/change_tracking_processors/test/test3/new.txt"));


    }


    public function testCanCopyToPreviousOnProcessOfSourceDatasources() {

        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            sourceDatasourceKeys: ["test1", "test2"],
            customKeyFieldNames: ["name", "age"]
            );
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $test1Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ]
        ]);

        $test2Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Peter Storm",
                "age" => 15
            ],
            [
                "name" => "Iron Man",
                "age" => 40
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, PHP_INT_MAX
        ]);

        mkdir("Files/change_tracking_processors/test/test1", 0777, true);
        mkdir("Files/change_tracking_processors/test/test2", 0777, true);
        file_put_contents("Files/change_tracking_processors/test/test1/new.txt", "Joe Bloggs#|!22\nJames Bond#|!56\nPeter Storm#|!15");
        file_put_contents("Files/change_tracking_processors/test/test2/previous.txt", "Joe Bloggs#|!22\nPeter Storm#|!15");


        // Actually process
        $this->processor->process($processorInstance);

        // Expect the new and previous files to exist
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/new.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test2/previous.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/adds.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test2/deletes.txt"));

        $this->assertStringContainsString("Joe Bloggs#|!22\nJames Bond#|!56\nAndrew Smith#|!30", file_get_contents("Files/change_tracking_processors/test/test1/new.txt"));
    }

    public function testCanDetectAdds() {
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            sourceDatasourceKeys: ["test1", "test2"],
            customKeyFieldNames: ["name", "age"]
            );
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $test1Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ]
        ]);

        $test2Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Peter Storm",
                "age" => 15
            ],
            [
                "name" => "Iron Man",
                "age" => 40
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, PHP_INT_MAX
        ]);

        mkdir("Files/change_tracking_processors/test/test1", 0777, true);
        mkdir("Files/change_tracking_processors/test/test2", 0777, true);


        file_put_contents("Files/change_tracking_processors/test/test1/previous.txt", "Joe Bloggs#|!22\nJames Bond#|!56\nPeter Storm#|!15");
        file_put_contents("Files/change_tracking_processors/test/test2/adds.txt", "James Bond#|!56");


        // Actually process
        $this->processor->process($processorInstance);

        // Expect the new and previous files to exist
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/adds.txt"));

        $this->assertStringContainsString("Andrew Smith#|!30", file_get_contents("Files/change_tracking_processors/test/test1/adds.txt"));
        $this->assertStringContainsString("Iron Man#|!40", file_get_contents("Files/change_tracking_processors/test/test2/adds.txt"));
        $this->assertStringNotContainsString("James Bond#|!56", file_get_contents("Files/change_tracking_processors/test/test1/adds.txt"));

    }

    public function testCanDetectAddsIfOutOfOrder(){
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            sourceDatasourceKeys: ["test1"],
            targetChangeDatasourceKey: "changes-key",
            customKeyFieldNames: ["name", "age"]
        );
        $processorInstance = new DataProcessorInstance(
            "test",
            "Changetracker",
            TabularDatasourceChangeTrackingProcessor::class,
            $processorConfig
        );

        $test1Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ]
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        mkdir("Files/change_tracking_processors/test/test1", 0777, true);

        file_put_contents("Files/change_tracking_processors/test/test1/previous.txt", "James Bond#|!56\nJoe Bloggs#|!22");

        // Actually process
        $this->processor->process($processorInstance);

        // Expect the new and previous files to exist
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/adds.txt"));

        $updates = $this->datasourceService->getMethodCallHistory("updateDatasourceInstanceByKey");
        $this->assertEquals(0, count($updates));
    }

    public function testDontAllowNoKeyFields(){
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            sourceDatasourceKeys: ["test1"],
            targetChangeDatasourceKey: "changes-key",
        );
        $processorInstance = new DataProcessorInstance(
            "test",
            "Changetracker",
            TabularDatasourceChangeTrackingProcessor::class,
            $processorConfig
        );

        $test1Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ]
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        mkdir("Files/change_tracking_processors/test/test1", 0777, true);

        file_put_contents("Files/change_tracking_processors/test/test1/previous.txt", "James Bond#|!56\nJoe Bloggs#|!22");

        // Actually process
        try {
            $this->processor->process($processorInstance);
        } catch (NoKeyFieldsException $exception){
            //Success
        }

        $updates = $this->datasourceService->getMethodCallHistory("updateDatasourceInstanceByKey");
        $this->assertEquals(0, count($updates));
    }

    public function testCanDetectDeletes() {
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1", "test2"], [], null, null, null, null);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $test1Dataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ]
        ]);

        $test2Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Peter Storm",
                "age" => 15
            ],
            [
                "name" => "Iron Man",
                "age" => 40
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, PHP_INT_MAX
        ]);

        mkdir("Files/change_tracking_processors/test/test1", 0777, true);
        mkdir("Files/change_tracking_processors/test/test2", 0777, true);
        file_put_contents("Files/change_tracking_processors/test/test1/previous.txt", "Joe Bloggs#|!22\nJames Bond#|!56\nPeter Storm#|!15\nJohn Smith#|!76\nAlexander Hamilton#|!32");
        file_put_contents("Files/change_tracking_processors/test/test2/deletes.txt", "William Williamson#|!91");


        // Actually process
        $this->processor->process($processorInstance);

        // Expect the new and previous files to exist
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/deletes.txt"));

        $this->assertStringContainsString("John Smith#|!76\nAlexander Hamilton#|!32", file_get_contents("Files/change_tracking_processors/test/test1/deletes.txt"));
        $this->assertStringNotContainsString("William Williamson#|!91", file_get_contents("Files/change_tracking_processors/test/test2/deletes.txt"));
    }


    public function testCanWriteToTargetDataSources() {
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test", null, null);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age"), new Field("array")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22,
                "array" => [
                    1, 2, 3, 4
                ]
            ],
            [
                "name" => "James Bond",
                "age" => 56,
                "array" => [
                    2, 3, 4, 5
                ]
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30,
                "array" => [
                    3, 4, 5, 6
                ]
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $expectedUpdate = new DatasourceUpdate([], [], [], [[
            "name" => "Joe Bloggs",
            "age" => 22,
            "array" => [
                1, 2, 3, 4
            ]
        ], [
            "name" => "James Bond",
            "age" => 56,
            "array" => [
                2, 3, 4, 5
            ]
        ], [
            "name" => "Andrew Smith",
            "age" => 30,
            "array" => [
                3, 4, 5, 6
            ]
        ]]);

        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate, true]));
    }


    public function testCanWriteToTargetDataSourcesWithDeletes() {
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test", null, null);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $expectedUpdateAdds = new DatasourceUpdate([], [], [], [[
            "name" => "James Bond",
            "age" => 56
        ], [
            "name" => "Andrew Smith",
            "age" => 30
        ]]);

        $expectedUpdateUpdates = new DatasourceUpdate([], [], [[
            "name" => "Peter Storm",
            "age" => 15
        ]]);

        mkdir("Files/change_tracking_processors/test/test1", 0777, true);
        file_put_contents("Files/change_tracking_processors/test/test1/previous.txt", "Joe Bloggs#|!22\nPeter Storm#|!15");


        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdateAdds, true]));
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdateUpdates, true]));

    }

    public function testCanWriteToTargetDataSourcesWithUpdates() {
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test", null, null);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age", "Age", null, Field::TYPE_STRING, false)], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $expectedUpdateAdds = new DatasourceUpdate([], [], [], [[
            "name" => "James Bond",
            "age" => 56
        ]]);

        $expectedUpdateDeletes = new DatasourceUpdate([], [], [], [[
            "name" => "James Bond",
            "age" => 56
        ]]);

        mkdir("Files/change_tracking_processors/test/test1", 0777, true);
        file_put_contents("Files/change_tracking_processors/test/test1/previous.txt", "Joe Bloggs#|!21\nAndrew Smith#|!30");


        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdateAdds, true]));
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdateDeletes, true]));

    }

    public function testCanWriteToTargetDataSourcesWithAddsUpdatesAndDeletes() {
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test", null, null);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age", "Age", null, Field::TYPE_STRING, false)], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ],
            [
                "name" => "Peter Storm",
                "age" => 15
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $expectedUpdateAdds = new DatasourceUpdate([], [], [], [[
            "name" => "James Bond",
            "age" => 56
        ], [
            "name" => "Peter Storm",
            "age" => 15
        ]]);

        $expectedUpdateUpdates = new DatasourceUpdate([], [[
            "name" => "Andrew Smith",
            "age" => 30
        ]]);

        $expectedUpdateDeletes = new DatasourceUpdate([], [], [[
            "name" => "Iron Man",
            "age" => 40
        ]]);

        mkdir("Files/change_tracking_processors/test/test1", 0777, true);
        file_put_contents("Files/change_tracking_processors/test/test1/previous.txt", "Joe Bloggs#|!22\nAndrew Smith#|!29\nIron Man#|!40");


        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdateAdds, true]));
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdateUpdates, true]));
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdateDeletes, true]));

    }

    public function testCanWriteToTargetDataSourcesWithEmptyDeletes() {
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test", null, null);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age", "Age", null, Field::TYPE_STRING, false)], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ],
            [
                "name" => "Peter Storm",
                "age" => 15
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $expectedUpdate = new DatasourceUpdate([], [], [], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => 30
            ],
            [
                "name" => "Peter Storm",
                "age" => 15
            ]]);


        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate, true]));
    }


    public function testCanWriteWithNullForEmptyField() {

        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test", null, null);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age", "Age", null, Field::TYPE_STRING, false)], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
            ],
            [
                "name" => "Peter Storm",
                "age" => 15
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $expectedUpdate = new DatasourceUpdate([], [], [], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Andrew Smith",
                "age" => null
            ],
            [
                "name" => "Peter Storm",
                "age" => 15
            ]]);


        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate, true]));

    }

    public function testCanUpdateWithChunks() {

        $targetWriteSize = 500;

        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test", null, null, $targetWriteSize);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $data = [];

        for ($i = 0; $i < 1250; $i++) {
            $data[$i]["name"] = $i;
            $data[$i]["age"] = 10;
        }


        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age", "Age")], $data);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $expectedUpdate1 = new DatasourceUpdate([], [], [], array_slice($data, 0, $targetWriteSize));
        $expectedUpdate2 = new DatasourceUpdate([], [], [], array_slice($data, $targetWriteSize, $targetWriteSize));


        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate1, true]));
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate2, true]));
    }

    public function testCanReadGivenChunkSize() {

        $sourceReadChunkSize = 500;
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test", null, null, [], $sourceReadChunkSize, 750);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $data = [];

        for ($i = 0; $i < 750; $i++) {
            $data[$i]["name"] = $i;
            $data[$i]["age"] = 10;
        }


        $testDatasetFull = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age", "Age")], $data);
        $testDatasetFirst = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age", "Age")], array_slice($data, 0, $sourceReadChunkSize));
        $testDatasetSecond = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age", "Age")], array_slice($data, $sourceReadChunkSize, $sourceReadChunkSize));

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDatasetFull, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDatasetFirst, [
            "test1", [], [], 0, $sourceReadChunkSize
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDatasetSecond, [
            "test1", [], [], $sourceReadChunkSize, $sourceReadChunkSize
        ]);

        $expectedUpdate1 = new DatasourceUpdate([], [], [], $data);

        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate1, true]));
    }


    public function testCanWriteToTargetSummaryDataSourceGivenSummaryField() {
        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(["test1"], [], null, "test1", null, "test", ["name"]);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");

        $now = new \DateTime();

        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ],
            [
                "name" => "James Bond",
                "age" => 56
            ],
            [
                "name" => "Joe Bloggs",
                "age" => 30
            ]
        ]);

        $summarisedData = new ArrayTabularDataset([new Field("summary_date"), new Field("month"), new Field("month_name"), new Field("year"), new Field("name"), new Field("total")], [
            [
                "summary_date" => $now->format("Y-m-d H:i:s"),
                "month" => $now->format("m"),
                "month_name" => $now->format("F"),
                "year" => $now->format("Y"),
                "name" => "Joe Bloggs",
                "total" => 2
            ], [
                "summary_date" => $now->format("Y-m-d H:i:s"),
                "month" => $now->format("m"),
                "month_name" => $now->format("F"),
                "year" => $now->format("Y"),
                "name" => "James Bond",
                "total" => 1
            ]
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, 1
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);
        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $summarisedData, [
            "test1", [], [new TransformationInstance("filter", new FilterTransformation([new Filter("[[name]]", null, Filter::FILTER_TYPE_NOT_NULL)])),
                new TransformationInstance("formula", new FormulaTransformation([
                    new Expression("Summary Date", "NOW()"),
                    new Expression("Month", "MONTH(NOW())"),
                    new Expression("Month Name", "MONTHNAME(NOW())"),
                    new Expression("Year", "YEAR(NOW())")
                ])),
                new TransformationInstance("summarise", new SummariseTransformation(array_merge(["summaryDate", "month", "monthName", "year"], $processorConfig->getSummaryFields()), [
                    new SummariseExpression(SummariseExpression::EXPRESSION_TYPE_COUNT, null, null, "Total")
                ]))
            ]
        ]);


        $expectedUpdate = new DatasourceUpdate([[
            "summary_date" => $now->format("Y-m-d H:i:s"),
            "month" => $now->format("m"),
            "month_name" => $now->format("F"),
            "year" => $now->format("Y"),
            "name" => "Joe Bloggs",
            "total" => 2
        ], [
            "summary_date" => $now->format("Y-m-d H:i:s"),
            "month" => $now->format("m"),
            "month_name" => $now->format("F"),
            "year" => $now->format("Y"),
            "name" => "James Bond",
            "total" => 1
        ]]);

        $this->processor->process($processorInstance);
        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate, true]));
    }

    public function testCanProcessDataset() {
        $testMetaDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age"), new Field("array")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22,
                "array" => [
                    1, 2, 3, 4
                ]
            ]
        ]);

        $testDataset = new ArrayTabularDataset([new Field("name", "Name", null, Field::TYPE_STRING, true), new Field("age"), new Field("array")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22,
                "array" => [
                    1, 2, 3, 4
                ]
            ],
            [
                "name" => "James Bond",
                "age" => 56,
                "array" => [
                    2, 3, 4, 5
                ]
            ],
            [
                "name" => "John Smith",
                "age" => 30,
                "array" => [
                    3, 4, 5, 6
                ]
            ]
        ]);


        $dataSetInstance = MockObjectProvider::instance()->getMockInstance(DatasetInstance::class);

        $this->datasetService->returnValue("getEvaluatedDataSetForDataSetInstance", $testMetaDataset, [$dataSetInstance, [], [], 0, 1]);
        $this->datasetService->returnValue("getEvaluatedDataSetForDataSetInstance", $testDataset, [$dataSetInstance, [], [], 0, PHP_INT_MAX]);


        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration([], [], $dataSetInstance, "test", null, null, []);
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $expectedUpdate = new DatasourceUpdate([], [], [], [[
            "name" => "Joe Bloggs",
            "age" => 22,
            "array" => [
                1, 2, 3, 4
            ]
        ], [
            "name" => "James Bond",
            "age" => 56,
            "array" => [
                2, 3, 4, 5
            ]
        ], [
            "name" => "John Smith",
            "age" => 30,
            "array" => [
                3, 4, 5, 6
            ]
        ]]);

        $this->processor->process($processorInstance);


        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate, true]));
    }

    public function testDoesSkipDatasourceIfNewFileEmpty() {

        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            sourceDatasourceKeys: ["test1", "test2"],
            sourceReadChunkSize: PHP_INT_MAX,
            customKeyFieldNames: ["name", "age"]
        );
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $test1Dataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Peter Storm",
                "age" => 15
            ],
            [
                "name" => "Iron Man",
                "age" => 40
            ]
        ]);

        $test2Dataset = new ArrayTabularDataset([], []);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test1Dataset, [
            "test1", [], [], 0, PHP_INT_MAX
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $test2Dataset, [
            "test2", [], [], 0, PHP_INT_MAX
        ]);

        // Actually process
        $this->processor->process($processorInstance);

        // Expect the new and previous files to exist
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/new.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/previous.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/adds.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test1/deletes.txt"));

        $this->assertFalse(file_exists("Files/change_tracking_processors/test/test2/new.txt"));
        $this->assertTrue(file_exists("Files/change_tracking_processors/test/test2/previous.txt"));
        $this->assertFalse(file_exists("Files/change_tracking_processors/test/test2/adds.txt"));
        $this->assertFalse(file_exists("Files/change_tracking_processors/test/test2/deletes.txt"));


        $this->assertStringContainsString("Peter Storm#|!15\nIron Man#|!40", file_get_contents("Files/change_tracking_processors/test/test1/previous.txt"));

    }

    public function testRowsSkippedIfNextRowIsNull() {

        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            sourceDatasourceKeys: ["test"],
            targetLatestDatasourceKey: "test",
            sourceReadChunkSize: PHP_INT_MAX,
            customKeyFieldNames: ["name", "age"]
        );
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);
        $processorInstance->returnValue("getKey", "test");


        $testDataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            null,
            [
                "name" => "Peter Storm",
                "age" => 15
            ],
            null,
            [
                "name" => "Iron Man",
                "age" => 40
            ]
        ]);

        $expectedUpdate = new DatasourceUpdate([], [], [], [
            [
                "name" => "Peter Storm",
                "age" => 15
            ], [
                "name" => "Iron Man",
                "age" => 40
            ]
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test", [], [], 0, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $testDataset, [
            "test", [], [], 0, PHP_INT_MAX
        ]);

        $this->processor->process($processorInstance);

        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["test", $expectedUpdate, true]));

    }

    public function testIfOffsetFieldSuppliedThisIsUsedInConjunctionWithDefaultOffsetToMakeRepeatedCalls() {

        $processorConfig = new TabularDatasourceChangeTrackingProcessorConfiguration(
            sourceDatasourceKeys:["test"],
            targetLatestDatasourceKey: "testLatest",
            sourceReadChunkSize: 1,
            offsetField: "age",
            initialOffset: 55,
            customKeyFieldNames: ["name", "age"]
        );
        $processorInstance = MockObjectProvider::instance()->getMockInstance(DataProcessorInstance::class);
        $processorInstance->returnValue("returnConfig", $processorConfig);

        $firstDataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Peter Storm",
                "age" => 15
            ]
        ]);

        $secondDataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Iron Man",
                "age" => 40
            ]
        ]);

        $thirdDataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
            [
                "name" => "Joe Bloggs",
                "age" => 22
            ]
        ]);

        $fourthDataset = new ArrayTabularDataset([new Field("name"), new Field("age")], [
        ]);


        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $firstDataset, [
            "test", [], [], 55, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $secondDataset, [
            "test", [], [], 15, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $thirdDataset, [
            "test", [], [], 40, 1
        ]);

        $this->datasourceService->returnValue("getEvaluatedDataSourceByInstanceKey", $fourthDataset, [
            "test", [], [], 22, 1
        ]);

        $this->processor->process($processorInstance);


        $expectedUpdate = new DatasourceUpdate([], [], [], [
            [
                "name" => "Peter Storm",
                "age" => 15
            ], [
                "name" => "Iron Man",
                "age" => 40
            ], [
                "name" => "Joe Bloggs",
                "age" => 22
            ]
        ]);

        $this->assertTrue($this->datasourceService->methodWasCalled("updateDatasourceInstanceByKey", ["testLatest", $expectedUpdate, true]));


    }


}