<?php


namespace Kinintel\Test\Objects\Datasource\SQLDatabase\Util;

use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3DatabaseConnection;
use Kinintel\Objects\Datasource\SQLDatabase\SQLDatabaseDatasource;
use Kinintel\Objects\Datasource\SQLDatabase\Util\SQLFilterJunctionEvaluator;
use Kinintel\ValueObjects\Transformation\Filter\Filter;
use Kinintel\ValueObjects\Transformation\Filter\FilterJunction;
use PHPUnit\Framework\MockObject\MockObject;

include_once "autoloader.php";


class SQLFilterJunctionEvaluatorTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    public function setUp(){
        $this->databaseConnection = new SQLite3DatabaseConnection();
    }

    public function testCanEvaluateSimpleFilterJunctionToSQLForAllSupportedFilterTypes() {
        $filterJunctionEvaluator = new SQLFilterJunctionEvaluator(null,null, $this->databaseConnection);

        // EQUALS
        $this->assertEquals([
            "sql" => "\"name\" = ?",
            "parameters" => [
                "Joe Bloggs"
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[name]]", "Joe Bloggs")
        ])));

        // NULL
        $this->assertEquals([
            "sql" => "\"name\" IS NULL",
            "parameters" => []
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[name]]", "", Filter::FILTER_TYPE_NULL)
        ])));

        // NOT NULL
        $this->assertEquals([
            "sql" => "\"name\" IS NOT NULL",
            "parameters" => []
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[name]]", "", Filter::FILTER_TYPE_NOT_NULL)
        ])));

        // GREATER THAN
        $this->assertEquals([
            "sql" => "\"age\" > ?",
            "parameters" => [44]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[age]]", 44, Filter::FILTER_TYPE_GREATER_THAN)
        ])));

        // GREATER THAN OR EQUAL TO
        $this->assertEquals([
            "sql" => "\"age\" >= ?",
            "parameters" => [44]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[age]]", 44, Filter::FILTER_TYPE_GREATER_THAN_OR_EQUAL_TO)
        ])));

        // LESS THAN
        $this->assertEquals([
            "sql" => "\"age\" < ?",
            "parameters" => [44]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[age]]", 44, Filter::FILTER_TYPE_LESS_THAN)
        ])));

        // LESS THAN OR EQUAL TO
        $this->assertEquals([
            "sql" => "\"age\" <= ?",
            "parameters" => [44]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[age]]", 44, Filter::FILTER_TYPE_LESS_THAN_OR_EQUAL_TO)
        ])));

        // PREFIX LIKE
        $this->assertEquals([
            "sql" => "\"name\" LIKE ?",
            "parameters" => [
                "%ee"
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[name]]", "*ee", Filter::FILTER_TYPE_LIKE)
        ])));

        // SUFFIX LIKE
        $this->assertEquals([
            "sql" => "\"name\" LIKE ?",
            "parameters" => [
                "ee%"
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[name]]", "ee*", Filter::FILTER_TYPE_LIKE)
        ])));

        // OPEN LIKE
        $this->assertEquals([
            "sql" => "\"name\" LIKE ?",
            "parameters" => [
                "%ee%"
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[name]]", "*ee*", Filter::FILTER_TYPE_LIKE)
        ])));

        // BETWEEN
        $this->assertEquals([
            "sql" => "\"age\" BETWEEN ? AND ?",
            "parameters" => [
                12,
                50
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[age]]", [12, 50], Filter::FILTER_TYPE_BETWEEN)
        ])));


        // IN
        $this->assertEquals([
            "sql" => "\"age\" IN (?,?,?)",
            "parameters" => [
                12,
                50,
                75
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[age]]", [12, 50, 75], Filter::FILTER_TYPE_IN)
        ])));

        // NOT IN
        $this->assertEquals([
            "sql" => "\"age\" NOT IN (?,?,?)",
            "parameters" => [
                12,
                50,
                75
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[age]]", [12, 50, 75], Filter::FILTER_TYPE_NOT_IN)
        ])));
    }

    public function testAndAndOrFiltersAreMappedCorrectlyForMultipleFiltersInAJunction() {

        $filterJunctionEvaluator = new SQLFilterJunctionEvaluator(null,null, $this->databaseConnection);

        // Default AND junction
        $this->assertEquals([
            "sql" => "\"name\" = ? AND \"age\" IN (?,?,?,?)",
            "parameters" => [
                "Joe Bloggs",
                5,
                7,
                9,
                11
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[name]]", "Joe Bloggs"),
            new Filter("[[age]]", [5, 7, 9, 11], Filter::FILTER_TYPE_IN)
        ])));


        // Or junction
        $this->assertEquals([
            "sql" => "\"name\" = ? OR \"age\" IN (?,?,?,?)",
            "parameters" => [
                "Joe Bloggs",
                5,
                7,
                9,
                11
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction([
            new Filter("[[name]]", "Joe Bloggs"),
            new Filter("[[age]]", [5, 7, 9, 11], Filter::FILTER_TYPE_IN)
        ], [], FilterJunction::LOGIC_OR)));

    }


    public function testNestedJunctionsAreEvaluatedCorrectly() {

        $filterJunctionEvaluator = new SQLFilterJunctionEvaluator(null,null, $this->databaseConnection);

        // Default AND junction
        $this->assertEquals([
            "sql" => "\"dob\" > ? OR (\"name\" = ? AND \"age\" IN (?,?,?,?))",
            "parameters" => [
                '2000-01-01',
                "Joe Bloggs",
                5,
                7,
                9,
                11
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction(
            [
                new Filter("[[dob]]", "2000-01-01", Filter::FILTER_TYPE_GREATER_THAN)
            ],
            [
                new FilterJunction([
                    new Filter("[[name]]", "Joe Bloggs"),
                    new Filter("[[age]]", [5, 7, 9, 11], Filter::FILTER_TYPE_IN)
                ])
            ],
            FilterJunction::LOGIC_OR
        )));

    }


    public function testIfLHSTableAliasSuppliedItIsAddedToAllFieldNamePrefixes() {

        $filterJunctionEvaluator = new SQLFilterJunctionEvaluator("T", null, $this->databaseConnection);

        // Default AND junction
        $this->assertEquals([
            "sql" => "T.\"dob\" > ? OR (T.\"name\" = ? AND T.\"age\" IN (?,?,?,?))",
            "parameters" => [
                '2000-01-01',
                "Joe Bloggs",
                5,
                7,
                9,
                11
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction(
            [
                new Filter("[[dob]]", "2000-01-01", Filter::FILTER_TYPE_GREATER_THAN)
            ],
            [
                new FilterJunction([
                    new Filter("[[name]]", "Joe Bloggs"),
                    new Filter("[[age]]", [5, 7, 9, 11], Filter::FILTER_TYPE_IN)
                ])
            ],
            FilterJunction::LOGIC_OR
        )));

    }


    public function testIfExpressionWithSquareBracketsSuppliedToLHSTheSquareBracketsAreRemovedAndPrefixedAsRequired() {

        $filterJunctionEvaluator = new SQLFilterJunctionEvaluator("T", null, $this->databaseConnection);

        // Default AND junction
        $this->assertEquals([
            "sql" => "SUBSTR(T.\"dob\", ?, ?) > ? OR (? || T.\"name\" = ? AND T.\"age\" * ? IN (?,?,?,?))",
            "parameters" => [
                0,
                10,
                '2000-01-01',
                'Me:',
                "Joe Bloggs",
                10,
                5,
                7,
                9,
                11
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction(
            [
                new Filter("SUBSTR([[dob]], 0, 10)", "2000-01-01", Filter::FILTER_TYPE_GREATER_THAN)
            ],
            [
                new FilterJunction([
                    new Filter("'Me:' || [[name]]", "Joe Bloggs"),
                    new Filter("[[age]] * 10", [5, 7, 9, 11], Filter::FILTER_TYPE_IN)
                ])
            ],
            FilterJunction::LOGIC_OR
        )));


    }


    public function testIfColumnsSuppliedUsingSquareBracketsTheseAreIncludedLiterallyInLieuOfPlaceholders() {

        $filterJunctionEvaluator = new SQLFilterJunctionEvaluator(null,null, $this->databaseConnection);

        // Default AND junction
        $this->assertEquals([
            "sql" => "\"dob\" > \"new_dob\" OR (\"name\" = \"new_name\" AND \"age\" IN (?,?,?,?))",
            "parameters" => [
                5,
                7,
                9,
                11
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction(
            [
                new Filter("[[dob]]", "[[new_dob]]", Filter::FILTER_TYPE_GREATER_THAN)
            ],
            [
                new FilterJunction([
                    new Filter("[[name]]", "[[new_name]]"),
                    new Filter("[[age]]", [5, 7, 9, 11], Filter::FILTER_TYPE_IN)
                ])
            ],
            FilterJunction::LOGIC_OR
        )));
    }


    public function testIfRHSColumnAliasSuppliedWIthColumnsSuppliedUsingSquareBracketsTheseAreIncludedLiterallyInLieuOfPlaceholders() {

        $filterJunctionEvaluator = new SQLFilterJunctionEvaluator(null, "J", $this->databaseConnection);

        // Default AND junction
        $this->assertEquals([
            "sql" => "\"dob\" > J.\"new_dob\" OR (\"name\" = J.\"new_name\" AND \"age\" IN (?,?,?,?))",
            "parameters" => [
                5,
                7,
                9,
                11
            ]
        ], $filterJunctionEvaluator->evaluateFilterJunctionSQL(new FilterJunction(
            [
                new Filter("[[dob]]", "[[new_dob]]", Filter::FILTER_TYPE_GREATER_THAN)
            ],
            [
                new FilterJunction([
                    new Filter("[[name]]", "[[new_name]]"),
                    new Filter("[[age]]", [5, 7, 9, 11], Filter::FILTER_TYPE_IN)
                ])
            ],
            FilterJunction::LOGIC_OR
        )));
    }


}