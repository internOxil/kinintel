<?php


namespace Kinintel\Traits\Controller\Account;


use Kinintel\Objects\Alert\AlertGroupSummary;
use Kinintel\Services\Alert\AlertService;

trait Alert {

    /**
     * @var AlertService
     */
    private $alertService;


    /**
     * Alert constructor.
     *
     * @param AlertService $alertService
     */
    public function __construct($alertService) {
        $this->alertService = $alertService;
    }


    /**
     * Get a single alert group by id
     *
     * @http GET /group/$id
     *
     * @param $id
     * @return AlertGroupSummary
     */
    public function getAlertGroup($id) {
        return $this->alertService->getAlertGroup($id);
    }


    /**
     * List alert groups filtering on title and project key if required
     *
     * @http GET /group
     *
     * @param string $filterString
     * @param string $projectKey
     * @param int $offset
     * @param int $limit
     *
     * @return AlertGroupSummary[]
     */
    public function listAlertGroups($filterString = "", $projectKey = null, $offset = 0, $limit = 10) {
        return $this->alertService->listAlertGroups($filterString, $limit, $offset, $projectKey);
    }


    /**
     * Save an alert group summary
     *
     * @http POST /group
     *
     * @param AlertGroupSummary $alertGroup
     * @param string $projectKey
     */
    public function saveAlertGroup($alertGroup, $projectKey = null) {
        $this->alertService->saveAlertGroup($alertGroup, $projectKey);
    }


    /**
     * Remove an alert group by id
     *
     * @http DELETE /group/$id
     *
     * @param integer$id
     */
    public function removeAlertGroup($id) {
        $this->alertService->deleteAlertGroup($id);
    }

}