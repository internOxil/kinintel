<?php


namespace Kinintel\Objects\Datasource\SQLDatabase\TransformationProcessor;


use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\ObjectArrayUtils;
use Kinintel\Exception\DatasourceTransformationException;
use Kinintel\Objects\Datasource\Datasource;
use Kinintel\Objects\Datasource\DefaultDatasource;
use Kinintel\Objects\Datasource\SQLDatabase\SQLDatabaseDatasource;
use Kinintel\Objects\Datasource\SQLDatabase\Util\SQLFilterJunctionEvaluator;
use Kinintel\Services\Dataset\DatasetService;
use Kinintel\Services\Datasource\DatasourceService;
use Kinintel\ValueObjects\Dataset\Field;
use Kinintel\ValueObjects\Datasource\SQLDatabase\SQLQuery;
use Kinintel\ValueObjects\Transformation\Join\JoinTransformation;
use Kinintel\ValueObjects\Transformation\Paging\PagingTransformation;
use Kinintel\ValueObjects\Transformation\Transformation;

class JoinTransformationProcessor extends SQLTransformationProcessor {

    /**
     * @var DatasourceService
     */
    private $datasourceService;

    /**
     * @var DatasetService
     */
    private $datasetService;


    /**
     * Table index
     *
     * @var int
     */
    private $tableIndex = 0;


    /**
     * Subquery index
     *
     * @var int
     */
    private $subQueryIndex = 0;


    /**
     * Alias index
     *
     * @var int
     */
    private $aliasIndex = 0;


    /**
     * JoinTransformationProcessor constructor.
     *
     * @param DatasourceService $datasourceService
     * @param DatasetService $datasetService
     */
    public function __construct($datasourceService, $datasetService) {
        $this->datasourceService = $datasourceService;
        $this->datasetService = $datasetService;
    }

    /**
     * @param JoinTransformation $transformation
     * @param Datasource $datasource
     * @param mixed[] $parameterValues
     *
     * @return \Kinintel\Objects\Datasource\Datasource|DefaultDatasource|mixed
     * @throws \Kinikit\Core\Validation\ValidationException
     * @throws \Kinikit\Persistence\ORM\Exception\ObjectNotFoundException
     * @throws \Kinintel\Exception\InvalidParametersException
     * @throws \Kinintel\Exception\MissingDatasourceAuthenticationCredentialsException
     * @throws \Kinintel\Exception\UnsupportedDatasourceTransformationException
     */
    public function applyTransformation($transformation, $datasource, $parameterValues = []) {

        // Get the parameter mappings as an indexed array
        $joinParameterMappings = ObjectArrayUtils::indexArrayOfObjectsByMember("parameterName", $transformation->getJoinParameterMappings() ?? []);

        // Triage to see whether we can read the evaluated data source
        if ($transformation->returnEvaluatedDataSource()) {
            $joinDatasource = $transformation->returnEvaluatedDataSource();
        } else if ($transformation->getJoinedDataSourceInstanceKey()) {
            $this->datasourceService = $this->datasourceService ?? Container::instance()->get(DatasourceService::class);
            $joinDatasourceInstance = $this->datasourceService->getDataSourceInstanceByKey($transformation->getJoinedDataSourceInstanceKey());

            // If parameters required for a data source, ensure that we have received mappings for them.
            if ($joinDatasourceParams = $joinDatasourceInstance->getParameters()) {
                foreach ($joinDatasourceParams as $datasourceParam) {
                    if (!isset($joinParameterMappings[$datasourceParam->getName()])) {
                        throw new DatasourceTransformationException("Parameter mapping required for parameter {$datasourceParam->getName()} when adding the datasource with key {$transformation->getJoinedDataSourceInstanceKey()} using the join operation.");
                    }
                }
            }

            $joinDatasource = $joinDatasourceInstance->returnDataSource();
        } else if ($transformation->getJoinedDataSetInstanceId()) {
            $this->datasetService = $this->datasetService ?? Container::instance()->get(DatasetService::class);
            $joinDataSet = $this->datasetService->getDataSetInstance($transformation->getJoinedDataSetInstanceId());
            $joinDatasource = $this->datasourceService->getTransformedDataSource($joinDataSet->getDatasourceInstanceKey(),
                $joinDataSet->getTransformationInstances(), $parameterValues);

            // If parameters required for a data set, ensure we have mappings for them.
            if ($joinDatasetParams = $this->datasetService->getEvaluatedParameters($transformation->getJoinedDataSetInstanceId())) {
                foreach ($joinDatasetParams as $datasetParam) {
                    if (!isset($joinParameterMappings[$datasetParam->getName()])) {
                        throw new DatasourceTransformationException("Parameter mapping required for parameter {$datasetParam->getName()} when adding the dataset with id {$transformation->getJoinedDataSetInstanceId()} using the join operation.");
                    }
                }
            }


        }


        // Update the transformation with the evaluated data source.
        $transformation->setEvaluatedDataSource($joinDatasource);

        // If mismatch of authentication credentials, harmonise as required
        if ($joinDatasource && ($joinDatasource->getAuthenticationCredentials() != $datasource->getAuthenticationCredentials())) {

            if (!($joinDatasource instanceof DefaultDatasource)) {
                $joinDatasource = new DefaultDatasource($joinDatasource);
                $joinDatasource->populate($parameterValues);
                $transformation->setEvaluatedDataSource($joinDatasource);

            }

            // If we are not a default datasource already, return a new instance
            if (!($datasource instanceof DefaultDatasource)) {
                $newDataSource = new DefaultDatasource($datasource);
                $newDataSource->applyTransformation($transformation);
                $datasource = $newDataSource;
            }

        }


        // For a join transformation, if join columns are supplied we must have master datasource columns as well
        if ($transformation->getJoinColumns() && !$datasource->getConfig()->getColumns()) {

            // Add a paging transformation to make the query efficient
            $datasource->applyTransformation(new PagingTransformation(1));

            // Materialise the set
            $dataSet = $datasource->materialise($parameterValues);

            // Remove the redundant Paging Transformation
            $datasource->unapplyLastTransformation();

            $datasource->getConfig()->setColumns($dataSet->getColumns());
        }


        return $datasource;

    }


    /**
     * Update a SQL query object for a join transformation.
     *
     * @param JoinTransformation $transformation
     * @param SQLQuery $query
     * @param mixed[] $parameterValues
     * @param $dataSource
     *
     * @return SQLQuery|void
     */
    public function updateQuery($transformation, $query, $parameterValues, $dataSource) {

        // Ensure we have an evaluated datasource before continuing
        $joinDatasource = $transformation->returnEvaluatedDataSource();

        // If we have a child query, use this to generate a new query using the various criteria.
        if ($joinDatasource instanceof SQLDatabaseDatasource &&
            ($joinDatasource->getAuthenticationCredentials() == $dataSource->getAuthenticationCredentials())) {
            $childQuery = $joinDatasource->buildQuery($parameterValues);

            // Calculate the new aliases
            $mainTableAlias = "T" . ++$this->tableIndex;
            $childTableAlias = "T" . ++$this->tableIndex;

            // Evaluate join criteria if supplied
            $joinCriteria = "1 = 1";
            $joinParameters = [];
            if ($transformation->getJoinFilters()) {
                $evaluator = new SQLFilterJunctionEvaluator($childTableAlias, $mainTableAlias);
                $evaluated = $evaluator->evaluateFilterJunctionSQL($transformation->getJoinFilters(), $parameterValues);
                $joinCriteria = $evaluated["sql"];
                $joinParameters = $evaluated["parameters"];
            }

            // Aggregate all parameters for join query
            $allParameters = array_merge($query->getParameters(), $childQuery->getParameters(), $joinParameters);


            // If join columns supplied, change the select query for selection
            $childSelectColumns = $childTableAlias . ".*";
            if ($transformation->getJoinColumns()) {

                $newColumns = $dataSource->getConfig()->getColumns() ?? [];

                // Create the SQL fragments and new column mappings.
                $joinColumnStrings = [];
                foreach ($transformation->getJoinColumns() as $joinColumn) {
                    $joinColumnStrings[] = $childTableAlias . "." . $joinColumn->getName() . " alias_" . ++$this->aliasIndex;
                    $newColumns[] = new Field("alias_" . $this->aliasIndex, $joinColumn->getTitle());
                }

                $childSelectColumns = join(",", $joinColumnStrings);
                $dataSource->getConfig()->setColumns($newColumns);
            }

            $subQueryIndex = ++$this->subQueryIndex;

            // Create the join query
            $joinQuery = new SQLQuery("*", "(SELECT $mainTableAlias.*,$childSelectColumns FROM ({$query->getSQL()}) $mainTableAlias INNER JOIN ({$childQuery->getSQL()}) $childTableAlias ON {$joinCriteria}) S$subQueryIndex", $allParameters);

            return $joinQuery;
        } else {
            return $query;
        }


    }


}