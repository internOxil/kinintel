<?php


namespace Kinintel\Test\Objects\Datasource;


use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinintel\Exception\ItemInUseException;
use Kinintel\Objects\Dataset\DatasetInstance;
use Kinintel\Objects\Dataset\DatasetInstanceSummary;
use Kinintel\Objects\Datasource\DatasourceInstance;
use Kinintel\Objects\Datasource\DatasourceInstanceInterceptor;
use Kinintel\ValueObjects\Transformation\Join\JoinTransformation;
use Kinintel\ValueObjects\Transformation\TransformationInstance;

include_once "autoloader.php";

class DatasourceInstanceInterceptorTest extends \PHPUnit\Framework\TestCase {


    /**
     * @var DatasourceInstanceInterceptor
     */
    private $interceptor;


    public function setUp(): void {
        $this->interceptor = Container::instance()->get(DatasourceInstanceInterceptor::class);

        Container::instance()->get(DatabaseConnection::class)->execute("DELETE FROM ki_dataset_instance WHERE datasource_instance_key = ?", "test-dep-ds");
    }


    public function testIfDatasourceInstanceReferencedByDatasetItCannotBeDeleted() {

        // Check no references initially
        $datasourceInstance = new DatasourceInstance("test-dep-ds", "Test Instance", "test");
        $datasourceInstance->save();

        $this->interceptor->preDelete($datasourceInstance);

        // Save a dataset with dependent datasource
        $dataset = new DatasetInstance(new DatasetInstanceSummary("Test Dependent", "test-dep-ds"));
        $dataset->save();

        try {
            $this->interceptor->preDelete($datasourceInstance);
            $this->fail("Should have thrown here");
        } catch (ItemInUseException $e) {
            $this->assertTrue(true);
        }
    }

    public function testIfDatasourceInstanceReferencedInJoinTransformationByDatasetItCannotBeDeleted() {


        // Check no references initially
        $referencedDatasource = new DatasourceInstance("test-referenced", "Test Instance", "test");
        $referencedDatasource->save();


        // Save a dataset with dependent datasource
        $dataset = new DatasetInstance(new DatasetInstanceSummary("Test Dependent", "test-json", null, [
            new TransformationInstance("join", new JoinTransformation("test-referenced"))
        ]));
        $dataset->save();

        try {
            $this->interceptor->preDelete($referencedDatasource);
            $this->fail("Should have thrown here");
        } catch (ItemInUseException $e) {
            $this->assertTrue(true);
        }


    }

}