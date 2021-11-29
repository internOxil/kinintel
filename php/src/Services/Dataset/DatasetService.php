<?php

namespace Kinintel\Services\Dataset;

use Kiniauth\Objects\Account\Account;
use Kiniauth\Objects\MetaData\TagSummary;
use Kiniauth\Objects\Workflow\Task\Scheduled\ScheduledTask;
use Kiniauth\Objects\Workflow\Task\Scheduled\ScheduledTaskSummary;
use Kiniauth\Services\MetaData\MetaDataService;
use Kiniauth\Test\Services\Security\AuthenticationHelper;
use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\ObjectArrayUtils;
use Kinikit\Persistence\ORM\Exception\ObjectNotFoundException;
use Kinintel\Exception\UnsupportedDatasourceTransformationException;
use Kinintel\Objects\DataProcessor\DataProcessorInstance;
use Kinintel\Objects\Dataset\Dataset;
use Kinintel\Objects\Dataset\DatasetInstance;
use Kinintel\Objects\Dataset\DatasetInstanceSearchResult;
use Kinintel\Objects\Dataset\DatasetInstanceSnapshotProfile;
use Kinintel\Objects\Dataset\DatasetInstanceSnapshotProfileSearchResult;
use Kinintel\Objects\Dataset\DatasetInstanceSnapshotProfileSummary;
use Kinintel\Objects\Dataset\DatasetInstanceSummary;
use Kinintel\Objects\Datasource\BaseDatasource;
use Kinintel\Objects\Datasource\Datasource;
use Kinintel\Objects\Datasource\DefaultDatasource;
use Kinintel\Services\Datasource\DatasourceService;
use Kinintel\ValueObjects\Parameter\Parameter;
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
    public function getDataSetInstance($id, $enforceReadOnly = true) {
        return DatasetInstance::fetch($id)->returnSummary($enforceReadOnly);
    }


    /**
     * Get a full data set instance
     *
     * @param $id
     * @return mixed
     */
    public function getFullDataSetInstance($id) {
        return DatasetInstance::fetch($id);
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

        $params = [];
        if ($accountId === null) {
            $query = "WHERE accountId IS NULL";
        } else {
            $query = "WHERE accountId = ?";
            $params[] = $accountId;
        }

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
     * Filter snapshot profiles for accounts, optionally by project key and tags.
     *
     * @param string $filterString
     * @param array $tags
     * @param string $projectKey
     * @param int $offset
     * @param int $limit
     * @param string $accountId
     */
    public function filterSnapshotProfiles($filterString = "", $tags = [], $projectKey = null, $offset = 0, $limit = 10, $accountId = Account::LOGGED_IN_ACCOUNT) {

        $clauses = [];
        $params = [];
        if ($accountId) {
            $clauses[] = "datasetInstanceLabel.account_id = ?";
            $params[] = $accountId;
        }
        if ($projectKey) {
            $clauses[] = "datasetInstanceLabel.project_key = ?";
            $params[] = $projectKey;
        }

        if ($tags && sizeof($tags) > 0) {
            $clauses[] = "datasetInstanceLabel.tags.tag_key IN (" . str_repeat("?", sizeof($tags)) . ")";
            $params = array_merge($params, $tags);
        }

        if ($filterString) {
            $clauses[] = "(title LIKE ? OR datasetInstanceLabel.title LIKE ?)";
            $params[] = "%$filterString%";
            $params[] = "%$filterString%";
        }

        $query = sizeof($clauses) ? "WHERE " . join(" AND ", $clauses) : "";
        $query .= " ORDER BY datasetInstanceLabel.title, title";

        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
        }
        if ($offset) {
            $query .= " OFFSET ?";
            $params[] = $offset;
        }


        $snapshotProfiles = DatasetInstanceSnapshotProfile::filter($query, $params);


        return array_map(function ($snapshotProfile) {
            return new DatasetInstanceSnapshotProfileSearchResult($snapshotProfile);
        }, $snapshotProfiles);


    }


    /**
     * List all snapshots for a dataset instance
     *
     * @param $datasetInstanceId
     * @return DatasetInstanceSnapshotProfileSummary[]
     */
    public function listSnapshotProfilesForDataSetInstance($datasetInstanceId) {

        // Check we have access to the instance first
        DatasetInstance::fetch($datasetInstanceId);

        $profiles = DatasetInstanceSnapshotProfile::filter("WHERE datasetInstanceId = ? ORDER BY title", $datasetInstanceId);
        return array_map(function ($profile) {
            return $profile->returnSummary();
        }, $profiles);
    }


    /**
     * Save a snapshot profile for an instance
     *
     * @param DatasetInstanceSnapshotProfileSummary $snapshotProfileSummary
     * @param integer $datasetInstanceId
     */
    public function saveSnapshotProfile($snapshotProfileSummary, $datasetInstanceId) {

        // Security check to ensure we can access the parent instance
        $datasetInstance = DatasetInstance::fetch($datasetInstanceId);

        // Update the processor configuration object to include the datasetInstanceId
        $processorConfig = $snapshotProfileSummary->getProcessorConfig() ?? [];
        $processorConfig["datasetInstanceId"] = $datasetInstanceId;


        // If an existing profile we want to update the master one
        if ($snapshotProfileSummary->getId()) {

            $snapshotProfile = DatasetInstanceSnapshotProfile::fetch($snapshotProfileSummary->getId());
            if ($snapshotProfile->getDatasetInstanceId() != $datasetInstanceId)
                throw new ObjectNotFoundException(DatasetInstanceSnapshotProfile::class, $snapshotProfileSummary->getId());

            $snapshotProfile->setTitle($snapshotProfileSummary->getTitle());
            $snapshotProfile->getScheduledTask()->setTimePeriods($snapshotProfileSummary->getTaskTimePeriods());


            $processorConfig["snapshotIdentifier"] = $snapshotProfile->getDataProcessorInstance()->getKey();
            $snapshotProfile->getDataProcessorInstance()->setConfig($processorConfig);


        } // Otherwise create new
        else {

            $dataProcessorKey = "dataset_snapshot_" . (new \DateTime())->format("Uv");

            $processorConfig["snapshotIdentifier"] = $dataProcessorKey;


            // Create a processor instance
            $dataProcessorInstance = new DataProcessorInstance($dataProcessorKey,
                "Dataset Instance Snapshot: " . $datasetInstance->getId() . " - " . $snapshotProfileSummary->getTitle(),
                $snapshotProfileSummary->getProcessorType(), $processorConfig,
                $datasetInstance->getProjectKey(), $datasetInstance->getAccountId());


            $snapshotProfile = new DatasetInstanceSnapshotProfile($datasetInstanceId, $snapshotProfileSummary->getTitle(),
                new ScheduledTask(new ScheduledTaskSummary("dataprocessor", "Dataset Instance Snapshot:$datasetInstanceId - " . $snapshotProfileSummary->getTitle(),
                    [
                        "dataProcessorKey" => $dataProcessorKey
                    ], $snapshotProfileSummary->getTaskTimePeriods()), $datasetInstance->getProjectKey(), $datasetInstance->getAccountId()),
                $dataProcessorInstance);


        }


        // Save the profile
        $snapshotProfile->save();


        return $snapshotProfile->getId();


    }


    /**
     * Remove a snapshot profile for an instance
     *
     * @param $datasetInstanceId
     * @param $snapshotProfileId
     */
    public function removeSnapshotProfile($datasetInstanceId, $snapshotProfileId) {

        // Security check to ensure we can access the parent instance
        DatasetInstance::fetch($datasetInstanceId);

        // Grab the profile
        $profile = DatasetInstanceSnapshotProfile::fetch($snapshotProfileId);

        if ($profile->getDatasetInstanceId() == $datasetInstanceId) {
            $profile->remove();
        }


    }


    /**
     * Get evaluated parameters for the passed datasource by id - this includes parameters from both
     * the dataset and datasource concatenated.
     *
     * @param DatasetInstanceSummary $datasourceInstanceSummary
     *
     * @return Parameter[]
     */
    public function getEvaluatedParameters($datasetInstanceSummary) {


        if ($datasetInstanceSummary->getDatasourceInstanceKey()) {
            $params = $this->datasourceService->getEvaluatedParameters($datasetInstanceSummary->getDatasourceInstanceKey());
        } else if ($datasetInstanceSummary->getDatasetInstanceId()) {
            $parentDatasetInstanceSummary = $this->getDataSetInstance($datasetInstanceSummary->getDatasetInstanceId(), false);
            $params = $this->getEvaluatedParameters($parentDatasetInstanceSummary);
        }

        $params = array_merge($params, $datasetInstanceSummary->getParameters() ?? []);
        return $params;
    }


    /**
     * Wrapper to below function for standard read only use where a data set is being
     * queried
     *
     * @param $dataSetInstanceId
     * @param TransformationInstance[] $additionalTransformations
     */
    public function getEvaluatedDataSetForDataSetInstanceById($dataSetInstanceId, $parameterValues = [], $additionalTransformations = [], $offset = 0, $limit = 25) {
        $dataSetInstance = $this->getDataSetInstance($dataSetInstanceId, false);
        return $this->getEvaluatedDataSetForDataSetInstance($dataSetInstance, $parameterValues, $additionalTransformations, $offset, $limit);
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
    public function getEvaluatedDataSetForDataSetInstance($dataSetInstance, $parameterValues = [], $additionalTransformations = [], $offset = 0, $limit = 25) {

        // Aggregate transformations and parameter values.
        $transformations = array_merge($dataSetInstance->getTransformationInstances() ?? [], $additionalTransformations ?? []);
        $parameterValues = array_merge($dataSetInstance->getParameterValues() ?? [], $parameterValues ?? []);

        // Call the appropriate function depending whether a datasource / dataset was being targeted.
        if ($dataSetInstance->getDatasourceInstanceKey()) {
            return $this->datasourceService->getEvaluatedDataSource($dataSetInstance->getDatasourceInstanceKey(), $parameterValues,
                $transformations, $offset, $limit);
        } else if ($dataSetInstance->getDatasetInstanceId()) {
            return $this->getEvaluatedDataSetForDataSetInstanceById($dataSetInstance->getDatasetInstanceId(), $parameterValues, $transformations, $offset, $limit);
        }
    }


}
