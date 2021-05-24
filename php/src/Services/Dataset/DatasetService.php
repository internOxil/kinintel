<?php

namespace Kinintel\Services\Dataset;

use Kiniauth\Objects\Account\Account;
use Kiniauth\Objects\MetaData\TagSummary;
use Kiniauth\Services\MetaData\MetaDataService;
use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\ObjectArrayUtils;
use Kinintel\Exception\UnsupportedDatasourceTransformationException;
use Kinintel\Objects\Dataset\Dataset;
use Kinintel\Objects\Dataset\DatasetInstance;
use Kinintel\Objects\Dataset\DatasetInstanceSearchResult;
use Kinintel\Objects\Dataset\DatasetInstanceSummary;
use Kinintel\Objects\Datasource\BaseDatasource;
use Kinintel\Objects\Datasource\Datasource;
use Kinintel\Objects\Datasource\DefaultDatasource;
use Kinintel\Services\Datasource\DatasourceService;
use Kinintel\ValueObjects\Transformation\TransformationInstance;

class DatasetService {

    /**
     * @var DatasourceService
     */
    private $datasourceService;

    /**
     * @var MetaDataService
     */
    private $metaDataService;


    /**
     * DatasetService constructor.
     *
     * @param DatasourceService $datasourceService
     * @param MetaDataService $metaDataService
     */
    public function __construct($datasourceService, $metaDataService) {
        $this->datasourceService = $datasourceService;
        $this->metaDataService = $metaDataService;
    }


    /**
     * Get a data set instance by id
     *
     * @param $id
     * @return DatasetInstanceSummary
     */
    public function getDataSetInstance($id) {
        return DatasetInstance::fetch($id)->returnSummary();
    }


    /**
     * Filter data set instances optionally limiting by the passed filter string,
     * array of tags and project id.
     *
     * @param string $filterString
     * @param array $tags
     * @param string $projectKey
     * @param int $offset
     * @param int $limit
     * @param int $accountId
     */
    public function filterDataSetInstances($filterString = "", $tags = [], $projectKey = null, $offset = 0, $limit = 10, $accountId = Account::LOGGED_IN_ACCOUNT) {

        $query = "WHERE accountId = ?";
        $params = [$accountId];

        if ($filterString) {
            $query .= " AND title LIKE ?";
            $params[] = "%$filterString%";
        }

        if ($projectKey) {
            $query .= " AND project_key = ?";
            $params[] = $projectKey;
        }

        if ($tags && sizeof($tags) > 0) {
            $query .= " AND tags.tag_key IN (" . str_repeat("?", sizeof($tags)) . ")";
            $params = array_merge($params, $tags);
        }


        $query .= " ORDER BY title LIMIT $limit OFFSET $offset";

        // Return a summary array
        return array_map(function ($instance) {
            return new DatasetInstanceSearchResult($instance->getId(), $instance->getTitle());
        },
            DatasetInstance::filter($query, $params));

    }


    /**
     * Save a data set instance
     *
     * @param DatasetInstanceSummary $dataSetInstanceSummary
     */
    public function saveDataSetInstance($dataSetInstanceSummary, $projectKey = null, $accountId = Account::LOGGED_IN_ACCOUNT) {
        $dataSetInstance = new DatasetInstance($dataSetInstanceSummary, $accountId, $projectKey);

        // Process tags
        if (sizeof($dataSetInstanceSummary->getTags())) {
            $tags = $this->metaDataService->getObjectTagsFromSummaries($dataSetInstanceSummary->getTags(), $accountId, $projectKey);
            $dataSetInstance->setTags($tags);
        }


        $dataSetInstance->save();
        return $dataSetInstance->getId();
    }


    /**
     * Remove the data set instance by id
     *
     * @param $id
     */
    public function removeDataSetInstance($id) {
        $dataSetInstance = DatasetInstance::fetch($id);
        $dataSetInstance->remove();
    }


    /**
     * Wrapper to below function for standard read only use where a data set is being
     * queried
     *
     * @param $dataSetInstanceId
     * @param TransformationInstance[] $additionalTransformations
     */
    public function getEvaluatedDataSetForDataSetInstanceById($dataSetInstanceId, $additionalTransformations = []) {
        $dataSetInstance = $this->getDataSetInstance($dataSetInstanceId);
        return $this->getEvaluatedDataSetForDataSetInstance($dataSetInstance, $additionalTransformations);
    }


    /**
     * Wrapper to below function which also calls the materialise function to just return
     * the dataset.  This is the normal function called to produce charts / tables etc for end
     * use.
     *
     * @param DatasetInstanceSummary $dataSetInstance
     * @param TransformationInstance[] $additionalTransformations
     *
     */
    public function getEvaluatedDataSetForDataSetInstance($dataSetInstance, $additionalTransformations = []) {
        $evaluatedDataSource = $this->getEvaluatedDataSourceForDataSetInstance($dataSetInstance, $additionalTransformations);
        return $evaluatedDataSource->materialise();
    }


    /**
     * Get the evaluated data source for a data set instance.  This is the resultant
     * data source returned after all transformations have been applied
     *
     * @param DatasetInstanceSummary $dataSetInstance
     * @param TransformationInstance[] $additionalTransformations
     *
     * @return BaseDatasource
     */
    public function getEvaluatedDataSourceForDataSetInstance($dataSetInstance, $additionalTransformations = []) {


        // Grab the datasource for this data set instance by key
        $datasourceInstance = $this->datasourceService->getDataSourceInstanceByKey($dataSetInstance->getDatasourceInstanceKey());

        // Grab the data source for this instance
        $datasource = $datasourceInstance->returnDataSource();

        // If we have transformation instances, apply these in sequence
        if ($dataSetInstance->getTransformationInstances()) {
            $datasource = $this->applyTransformationsToDatasource($datasource, $dataSetInstance->getTransformationInstances());
        }

        // If we have additional transformations, apply these in sequence
        if ($additionalTransformations ?? []) {
            $datasource = $this->applyTransformationsToDatasource($datasource, $additionalTransformations);
        }

        // Return the evaluated data source
        return $datasource;

    }


    /**
     * Apply the transformation instances to the supplied data source and return
     * a new datasource.
     *
     * @param Datasource $datasource
     * @param TransformationInstance[] $transformationInstances
     */
    private function applyTransformationsToDatasource($datasource, $transformationInstances) {
        foreach ($transformationInstances as $transformationInstance) {
            $transformation = $transformationInstance->returnTransformation();

            if ($this->isTransformationSupported($datasource, $transformation)) {
                $datasource = $datasource->applyTransformation($transformation);
            } else if ($datasource instanceof DefaultDatasource) {
                throw new UnsupportedDatasourceTransformationException($datasource, $transformation);
            } else {
                $defaultDatasource = new DefaultDatasource($datasource);
                if ($this->isTransformationSupported($defaultDatasource, $transformation))
                    $datasource = $defaultDatasource->applyTransformation($transformation);
                else
                    throw new UnsupportedDatasourceTransformationException($datasource, $transformation);
            }

        }
        return $datasource;
    }

    // Check whether a transformation is supported by a datasource
    private function isTransformationSupported($datasource, $transformation) {
        foreach ($datasource->getSupportedTransformationClasses() ?? [] as $supportedTransformationClass) {
            if (is_a($transformation, $supportedTransformationClass) || is_subclass_of($transformation, $supportedTransformationClass)) {
                return true;
            }
        }
        return false;
    }


}