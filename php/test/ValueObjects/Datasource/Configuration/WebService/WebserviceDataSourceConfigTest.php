<?php


namespace Kinintel\Test\ValueObjects\Datasource\Configuration\WebService;


use Kinikit\Core\Validation\FieldValidationError;
use Kinikit\Core\Validation\ValidationException;
use Kinintel\ValueObjects\Datasource\Configuration\WebService\WebserviceDataSourceConfig;

/**
 * Test cases for the web service data source config
 *
 * Class WebserviceDataSourceConfigTest
 * @package Kinintel\Test\ValueObjects\Datasource\Configuration\WebService
 */
class WebserviceDataSourceConfigTest extends \PHPUnit\Framework\TestCase {


    public function testValidateMethodCorrectlyValidatesInvalidCompressionTypes() {

        $webserviceConfig = new WebserviceDataSourceConfig("https://test.com");
        $webserviceConfig->setCompressionType("badtype");

        $validationErrors = $webserviceConfig->validate();

        $this->assertEquals(1, sizeof($validationErrors));
        $this->assertEquals(new FieldValidationError("compressionType", "invalidtype", "The compression type 'badtype' does not exists"),
            $validationErrors["compressionType"]["invalidtype"]);

    }


    public function testValidateMethodCorrectlyValidatesInvalidCompressionConfig() {

        $webserviceConfig = new WebserviceDataSourceConfig("https://test.com");
        $webserviceConfig->setCompressionType("zip");
        $webserviceConfig->setCompressionConfig([]);

        $validationErrors = $webserviceConfig->validate();

        $this->assertEquals(1, sizeof($validationErrors));
        $this->assertEquals(new FieldValidationError("entryFilename", "requiredEither", "One of the fields entryFilename, entryFilenames is required"),
            $validationErrors["compressionConfig"]["entryFilename"]["requiredEither"]);


    }


}