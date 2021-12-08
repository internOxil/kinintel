<?php


namespace Kinintel\Traits\Controller\Admin;


use Kinintel\Objects\Dataset\DatasetInstanceSnapshotProfileSummary;
use Kinintel\Objects\Dataset\DatasetInstanceSummary;
use Kinintel\Services\Dataset\DatasetService;
use Kinintel\ValueObjects\Dataset\EvaluatedDataset;

trait Dataset {

    /**
     * @var DatasetService
     */
    private $datasetService;

    /**
     * Dataset constructor.
     *
     * @param DatasetService $datasetService
     */
    public function __construct($datasetService) {
        $this->datasetService = $datasetService;
    }


    /**
     * Get a dataset instance summary by id
     *
     * @http GET /$id
     *
     * @param $id
     * @return DatasetInstanceSummary
     */
    public function getDatasetInstance($id) {
        return $this->datasetService->getDataSetInstance($id);
    }


    /**
     * Get an extended dataset instance summary by id
     *
     * @http GET /extended/$id
     *
     * @param $id
     * @return DatasetInstanceSummary
     */
    public function getExtendedDatasetInstance($id) {
        return $this->datasetService->getExtendedDatasetInstance($id);
    }


    /**
     * Filter dataset instances, optionally by title, project key, tags and limited by offset and limit.
     *
     * @http GET /
     *
     * @param string $filterString
     * @param string $projectKey
     * @param string $tags
     * @param int $offset
     * @param int $limit
     *
     * @return \Kinintel\Objects\Dataset\DatasetInstanceSearchResult[]
     */
    public function filterDatasetInstances($filterString = "", $accountId = 0, $offset = 0, $limit = 10) {
        return $this->datasetService->filterDataSetInstances($filterString, [], null, $offset, $limit, is_numeric($accountId) ? $accountId : null);
    }


    /**
     * Save a data set instance object
     *
     * @http POST
     *
     * @param DatasetInstanceSummary $dataSetInstanceSummary
     * @param string $projectKey
     */
    public function saveDatasetInstance($dataSetInstanceSummary, $projectKey = null, $accountId = 0) {
        $this->datasetService->saveDataSetInstance($dataSetInstanceSummary, $projectKey, is_numeric($accountId) ? $accountId : null);
    }


    /**
     * Remove a dataset instance by id
     *
     * @http DELETE /$id
     *
     * @param $id
     */
    public function removeDatasetInstance($id) {
        $this->datasetService->removeDataSetInstance($id);
    }


    /**
     * Get the evaluated parameters for the supplied dataset instance by id.
     * The array of transformation instances can be supplied as payload.
     *
     * @http POST /parameters
     *
     * @param DatasetInstanceSummary $datasetInstanceSummary
     */
    public function getEvaluatedParameters($datasetInstanceSummary) {
        return $this->datasetService->getEvaluatedParameters($datasetInstanceSummary);
    }


    /**
     * Evaluate a dataset and return a dataset
     *
     * @http POST /evaluate
     *
     * @param DatasetInstanceSummary $datasetInstanceSummary
     * @return \Kinintel\Objects\Dataset\Dataset
     */
    public function evaluateDataset($datasetInstanceSummary, $offset = 0, $limit = 25) {
        return $this->datasetService->getEvaluatedDataSetForDataSetInstance($datasetInstanceSummary, [], [],
            $offset, $limit);
    }


    /**
     * Filter snapshot profiles optionally by a string, project and tags
     *
     * @http GET /snapshotprofile
     *
     * @param string $filterString
     * @param string $projectKey
     * @param string $tags
     * @param int $offset
     * @param int $limit
     * @return \Kinintel\Objects\Dataset\DatasetInstanceSnapshotProfileSearchResult[]
     */
    public function filterSnapshotProfiles($filterString = "", $projectKey = null, $tags = "", $offset = 0, $limit = 10) {
        $tags = $tags ? explode(",", $tags) : [];
        return $this->datasetService->filterSnapshotProfiles($filterString, $tags, $projectKey, $offset, $limit);
    }

    /**
     * List snapshot profiles for dataset instance by instance id
     *
     * @http GET /snapshotprofile/$datasetInstanceId
     *
     * @param $datasetInstanceId
     */
    public function listSnapshotProfilesForDataSetInstance($datasetInstanceId) {
        return $this->datasetService->listSnapshotProfilesForDataSetInstance($datasetInstanceId);
    }


    /**
     * Save a snapshot profile for an instance
     *
     * @http POST /snapshotprofile/$datasetInstanceId
     *
     * @param DatasetInstanceSnapshotProfileSummary $snapshotProfileSummary
     * @param $datasetInstanceId
     */
    public function saveSnapshotProfile($datasetInstanceId, $snapshotProfileSummary) {
        $this->datasetService->saveSnapshotProfile($snapshotProfileSummary, $datasetInstanceId);
    }


    /**
     * Remove a snapshot profile for an instance
     *
     * @http DELETE /snapshotprofile/$datasetInstanceId
     *
     * @param $datasetInstanceId
     * @param $snapshotProfileId
     */
    public function removeSnapshotProfile($snapshotProfileId, $datasetInstanceId) {
        $this->datasetService->removeSnapshotProfile($datasetInstanceId, $snapshotProfileId);
    }


}
