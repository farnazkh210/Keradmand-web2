<?php

/**
 * ZohoRecruit Integration
 *
 */

namespace BitCode\BitFormPro\Integration\ZohoRecruit;

use WP_Error;
use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitFormPro\Integration\ZohoRecruit\RecordApiHelper;

/**
 * Provide functionality for ZohoCrm integration
 */
class ZohoRecruitHandler
{
    private $_formID;
    private $_integrationID;

    public function __construct($integrationID, $fromID)
    {
        $this->_formID = $fromID;
        $this->_integrationID = $integrationID;
    }

    /**
     * Helps to register ajax function's with wp
     *
     * @return null
     */
    public static function registerAjax()
    {
        add_action('wp_ajax_bitforms_zrecruit_generate_token', array(__CLASS__, 'generateTokens'));
        add_action('wp_ajax_bitforms_zrecruit_refresh_modules', array(__CLASS__, 'refreshModulesAjaxHelper'));
        add_action('wp_ajax_bitforms_zrecruit_refresh_notetypes', array(__CLASS__, 'refreshNoteTypesAjaxHelper'));
        add_action('wp_ajax_bitforms_zrecruit_refresh_related_lists', array(__CLASS__, 'refreshRelatedModulesAjaxHelper'));
        add_action('wp_ajax_bitforms_zrecruit_get_fields', array(__CLASS__, 'getFields'));
    }

    /**
     * Process ajax request for generate_token
     *
     * @return JSON zoho recruit api response and status
     */
    public static function generateTokens()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $requestsParams = json_decode($inputJSON);
            if (
                empty($requestsParams->{'accounts-server'})
                || empty($requestsParams->dataCenter)
                || empty($requestsParams->clientId)
                || empty($requestsParams->clientSecret)
                || empty($requestsParams->redirectURI)
                || empty($requestsParams->code)
            ) {
                wp_send_json_error(
                    __(
                        'Requested parameter is empty',
                        'bitform'
                    ),
                    400
                );
            }

            $apiEndpoint = \urldecode($requestsParams->{'accounts-server'}) . '/oauth/v2/token';
            $requestParams = array(
                "grant_type" => "authorization_code",
                "client_id" => $requestsParams->clientId,
                "client_secret" => $requestsParams->clientSecret,
                "redirect_uri" => \urldecode($requestsParams->redirectURI),
                "code" => $requestsParams->code
            );
            $apiResponse = HttpHelper::post($apiEndpoint, $requestParams);

            if (is_wp_error($apiResponse) || !empty($apiResponse->error)) {
                wp_send_json_error(
                    empty($apiResponse->error) ? 'Unknown' : $apiResponse->error,
                    400
                );
            }
            $apiResponse->generates_on = \time();
            wp_send_json_success($apiResponse, 200);
        } else {
            wp_send_json_error(
                __(
                    'Token expired',
                    'bitform'
                ),
                401
            );
        }
    }
    /**
     * Process ajax request for refresh recruit modules
     *
     * @return JSON recruit module data
     */
    public static function refreshModulesAjaxHelper()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $queryParams = json_decode($inputJSON);
            if (
                empty($queryParams->tokenDetails)
                || empty($queryParams->dataCenter)
                || empty($queryParams->clientId)
                || empty($queryParams->clientSecret)
            ) {
                wp_send_json_error(
                    __(
                        'Requested parameter is empty',
                        'bitform'
                    ),
                    400
                );
            }
            $response = [];
            if ((intval($queryParams->tokenDetails->generates_on) + (55 * 60)) < time()) {
                $response['tokenDetails'] = ZohoRecruitHandler::_refreshAccessToken($queryParams);
            }
            $zohosIntegratedModules = array(
                'zohosign__ZohoSign_Document_Events',
                'zohoshowtime__ShowTime_Sessions',
                'zohoshowtime__Zoho_ShowTime',
                'zohosign__ZohoSign_Documents',
                'zohosign__ZohoSign_Recipients'
            );
            $modulesMetaApiEndpoint = "https://recruit.zoho.{$queryParams->dataCenter}/recruit/private/json/Info/getModules?authtoken={$queryParams->tokenDetails->access_token}&scope=ZohoRecruit.modules.all&version=2";
            $authorizationHeader["Authorization"] = "Zoho-oauthtoken {$queryParams->tokenDetails->access_token}";
            $modulesMetaResponse = HttpHelper::get($modulesMetaApiEndpoint, null, $authorizationHeader);
            if (!is_wp_error($modulesMetaResponse) && (empty($modulesMetaResponse->status) || (!empty($modulesMetaResponse->status) && $modulesMetaResponse->status !== 'error'))) {
                $retriveModuleData = $modulesMetaResponse->response->result->row;
                $allModules = array(
                    'Tasks' => (object) array(
                        'aMod' => 'Tasks',
                        'pl' => 'Tasks'
                    ),
                    'Events' => (object) array(
                        'aMod' => 'Events',
                        'pl' => 'Events'
                    ),
                    'Calls' => (object) array(
                        'aMod' => 'Calls',
                        'pl' => 'Calls'
                    ),
                );
                foreach ($retriveModuleData as $value) {
                    if (preg_match("/CustomModule/", $value->aMod) || preg_match("/Candidates/", $value->aMod)) {
                        $allModules[$value->aMod] = (object) array(
                            "aMod" => $value->aMod,
                            "pl" => $value->pl
                        );
                    }
                }
                uksort($allModules, 'strnatcasecmp');
                $response["modules"] = $allModules;
            } else {
                wp_send_json_error(
                    empty($modulesMetaResponse->error) ? 'Unknown' : $modulesMetaResponse->error,
                    400
                );
            }
            if (!empty($response['tokenDetails']) && !empty($queryParams->id)) {
                ZohoRecruitHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response['modules']);
            }
            wp_send_json_success($response, 200);
        } else {
            wp_send_json_error(
                __(
                    'Token expired',
                    'bitform'
                ),
                401
            );
        }
    }

    public static function refreshNoteTypesAjaxHelper()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $queryParams = json_decode($inputJSON);
            if (
                empty($queryParams->tokenDetails)
                || empty($queryParams->dataCenter)
                || empty($queryParams->clientId)
                || empty($queryParams->clientSecret)
            ) {
                wp_send_json_error(
                    __(
                        'Requested parameter is empty',
                        'bitform'
                    ),
                    400
                );
            }
            $response = [];
            if ((intval($queryParams->tokenDetails->generates_on) + (55 * 60)) < time()) {
                $response['tokenDetails'] = ZohoRecruitHandler::_refreshAccessToken($queryParams);
            }

            $notesMetaApiEndpoint = "https: //recruit.zoho.com/recruit/private/json/Notes/getNoteTypes?authtoken={$queryParams->tokenDetails->access_token}&scope=ZohoRecruit.modules.call.all&version=2";

            $authorizationHeader["Authorization"] = "Zoho-oauthtoken {$queryParams->tokenDetails->access_token}";
            $notesMetaResponse = HttpHelper::get($notesMetaApiEndpoint, null, $authorizationHeader);

            // wp_send_json_success($notesMetaResponse, 200);

            if (!is_wp_error($notesMetaResponse) && (empty($notesMetaResponse->status) || (!empty($notesMetaResponse->status) && $notesMetaResponse->status !== 'error'))) {
                $retriveModuleData = $notesMetaResponse->response->result->Notes->row;
                $allNoteTypes = [];
                foreach ($retriveModuleData as $value) {
                    $allNoteTypes[$value->FL[0]->dv] = (object) array(
                        'noteTypeId' => $value->FL[1]->content,
                        'noteTypeName' => $value->FL[0]->dv
                    );
                }
                uksort($allNoteTypes, 'strnatcasecmp');
                $response["noteTypes"] = $allNoteTypes;
            } else {
                wp_send_json_error(
                    empty($notesMetaResponse->error) ? 'Unknown' : $notesMetaResponse->error,
                    400
                );
            }
            if (!empty($response['tokenDetails']) && !empty($queryParams->id)) {
                ZohoRecruitHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response['notetypes']);
            }
            wp_send_json_success($response, 200);
        } else {
            wp_send_json_error(
                __(
                    'Token expired',
                    'bitform'
                ),
                401
            );
        }
    }

    /**
     * Process ajax request for refresh recruit modules
     *
     * @return JSON recruit module data
     */
    public static function refreshRelatedModulesAjaxHelper()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $queryParams = json_decode($inputJSON);
            if (
                empty($queryParams->tokenDetails)
                || empty($queryParams->dataCenter)
                || empty($queryParams->clientId)
                || empty($queryParams->clientSecret)
                || empty($queryParams->module)
            ) {
                wp_send_json_error(
                    __(
                        'Requested parameter is empty',
                        'bitform'
                    ),
                    400
                );
            }
            $response = [];
            $relatedModules = [];
            $allModules = array(
                'Tasks' => (object) array(
                    'aMod' => 'Tasks',
                    'pl' => 'Tasks'
                ),
                'Events' => (object) array(
                    'aMod' => 'Events',
                    'pl' => 'Events'
                ),
                'Calls' => (object) array(
                    'aMod' => 'Calls',
                    'pl' => 'Calls'
                ),
                // 'Notes' => (object) array(
                //     'aMod' => 'Notes',
                //     'pl' => 'Notes'
                // ),
            );
            foreach ($allModules as $module) {
                if ($module->aMod !== $queryParams->module) {
                    $relatedModules[$module->aMod] = (object) array(
                        "aMod" => $module->aMod,
                        "pl" => $module->pl
                    );
                }
            }
            uksort($relatedModules, 'strnatcasecmp');
            $response["related_modules"] = $relatedModules;

            if (!empty($response['tokenDetails']) && !empty($queryParams->id)) {
                ZohoRecruitHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response['related_modules']);
            }
            wp_send_json_success($response, 200);
        } else {
            wp_send_json_error(
                __(
                    'Token expired',
                    'bitform'
                ),
                401
            );
        }
    }
    /**
     * Process ajax request for refesh recruit layouts
     *
     * @return JSON recruit layout data
     */
    public static function getFields()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $queryParams = json_decode($inputJSON);
            if (
                empty($queryParams->module)
                || empty($queryParams->tokenDetails)
                || empty($queryParams->dataCenter)
                || empty($queryParams->clientId)
                || empty($queryParams->clientSecret)
            ) {
                wp_send_json_error(
                    __(
                        'Requested parameter is empty',
                        'bitform'
                    ),
                    400
                );
            }
            $response = [];
            if ((intval($queryParams->tokenDetails->generates_on) + (55 * 60)) < time()) {
                $response['tokenDetails'] = ZohoRecruitHandler::_refreshAccessToken($queryParams);
            }
            $fieldsMetaApiEndpoint = "https://recruit.zoho.{$queryParams->dataCenter}/recruit/private/json/{$queryParams->module}/getFields?authtoken={$queryParams->tokenDetails->access_token}&scope=recruitapi&version=2";
            $authorizationHeader["Authorization"] = "Zoho-oauthtoken {$queryParams->tokenDetails->access_token}";
            $requiredParams['module'] = $queryParams->module;
            $fieldsMetaResponse = HttpHelper::get($fieldsMetaApiEndpoint, $requiredParams, $authorizationHeader);

            if (!is_wp_error($fieldsMetaResponse) && (empty($fieldsMetaResponse->status) || (!empty($fieldsMetaResponse->status) && $fieldsMetaResponse->status !== 'error'))) {
                $retriveFieldsData = $fieldsMetaResponse->{$queryParams->module}->section;
                $fields = [];
                $fileUploadFields = [];
                $requiredFields = [];
                $requiredFileUploadFiles = [];
                if ($queryParams->module === 'Candidates') {
                    $fileUploadFields['Candidate Photo'] = (object) array(
                        'display_label' => 'Candidate Profile Photo',
                        'length' => 1000,
                        'data_type' => 'UploadText',
                        'required' => 'false'
                    );
                }
                if (count($retriveFieldsData) > 1) {
                    foreach ($retriveFieldsData as $fieldValue) {
                        foreach ($fieldValue->FL as $sectionValue) {
                            if ($sectionValue->dv === null) {
                                continue;
                            }
                            if ($sectionValue->type === 'UploadText') {
                                $fileUploadFields[$sectionValue->dv] = (object) array(
                                    'display_label' => $sectionValue->dv,
                                    'length' => $sectionValue->maxlength,
                                    'data_type' => $sectionValue->type,
                                    'required' => $sectionValue->req
                                );
                                if ($sectionValue->req === 'true') {
                                    $requiredFileUploadFiles[] = $sectionValue->dv;
                                }
                            } else {
                                $fields[$sectionValue->dv] = (object) array(
                                    'display_label' => $sectionValue->dv,
                                    'length' => $sectionValue->maxlength,
                                    'data_type' => $sectionValue->type,
                                    'required' => $sectionValue->req
                                );
                                if ($sectionValue->req === 'true') {
                                    $requiredFields[] = $sectionValue->dv;
                                }
                            }
                        }
                    }
                } else {
                    foreach ($retriveFieldsData->FL as $sectionValue) {
                        if ($sectionValue->dv === null) {
                            continue;
                        }
                        if ($sectionValue->aMod === 'Candidates') {
                            $fileUploadFields[$sectionValue->dv] = (object) array(
                                'display_label' => 'Candidate Profile Photo',
                                'length' => 1000,
                                'data_type' => 'UploadText',
                                'required' => 'false'
                            );
                        }
                        if ($sectionValue->type === 'UploadText') {
                            $fileUploadFields[$sectionValue->dv] = (object) array(
                                'display_label' => $sectionValue->dv,
                                'length' => $sectionValue->maxlength,
                                'data_type' => $sectionValue->type,
                                'required' => $sectionValue->req
                            );
                            if ($sectionValue->req === 'true') {
                                $requiredFileUploadFiles[] = $sectionValue->dv;
                            }
                        } else {
                            $fields[$sectionValue->dv] = (object) array(
                                'display_label' => $sectionValue->dv,
                                'length' => $sectionValue->maxlength,
                                'data_type' => $sectionValue->type,
                                'required' => $sectionValue->req
                            );
                            if ($sectionValue->req === 'true') {
                                $requiredFields[] = $sectionValue->dv;
                            }
                        }
                    }
                }

                if ($queryParams->module === 'Candidates' && isset($fields['Zip/Postal Code'])) {
                    $fields['Zip Code'] = $fields['Zip/Postal Code'];
                    unset($fields['Zip/Postal Code']);
                }

                uksort($fields, 'strnatcasecmp');
                uksort($fileUploadFields, 'strnatcasecmp');
                usort($requiredFields, 'strnatcasecmp');
                usort($requiredFileUploadFiles, 'strnatcasecmp');

                $fieldDetails = (object) array(
                    'fields' => $fields,
                    'fileUploadFields' => $fileUploadFields,
                    'required' => $requiredFields,
                    'requiredFileUploadFields' => $requiredFileUploadFiles
                );
                $response["fieldDetails"] = $fieldDetails;
            } else {
                wp_send_json_error(
                    $fieldsMetaResponse->status === 'error' ? $fieldsMetaResponse->message : 'Unknown',
                    400
                );
            }
            if (!empty($response['tokenDetails']) && $response['tokenDetails'] && !empty($queryParams->id)) {
                $response["queryModule"] = $queryParams->module;
                ZohoRecruitHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response);
            }
            wp_send_json_success($response, 200);
        } else {
            wp_send_json_error(
                __(
                    'Token expired',
                    'bitform'
                ),
                401
            );
        }
    }

    /**
     * Helps to refresh zoho recruit access_token
     *
     * @param  Array $apiData Contains required data for refresh access token
     * @return JSON  $tokenDetails API token details
     */
    protected static function _refreshAccessToken($apiData)
    {
        if (
            empty($apiData->dataCenter)
            || empty($apiData->clientId)
            || empty($apiData->clientSecret)
            || empty($apiData->tokenDetails)
        ) {
            return false;
        }
        $tokenDetails = $apiData->tokenDetails;

        $dataCenter = $apiData->dataCenter;
        $apiEndpoint = "https://accounts.zoho.{$dataCenter}/oauth/v2/token";
        $requestParams = array(
            "grant_type" => "refresh_token",
            "client_id" => $apiData->clientId,
            "client_secret" => $apiData->clientSecret,
            "refresh_token" => $tokenDetails->refresh_token,
        );

        $apiResponse = HttpHelper::post($apiEndpoint, $requestParams);
        if (is_wp_error($apiResponse) || !empty($apiResponse->error)) {
            return false;
        }
        $tokenDetails->generates_on = \time();
        $tokenDetails->access_token = $apiResponse->access_token;
        return $tokenDetails;
    }

    /**
     * Save updated access_token to avoid unnecessary token generation
     *
     * @param Integer $fromID        ID of Integration related form
     * @param Integer $integrationID ID of Zoho recruit Integration
     * @param Obeject $tokenDetails  refreshed token info
     *
     * @return null
     */
    protected static function _saveRefreshedToken($formID, $integrationID, $tokenDetails, $others = null)
    {
        if (empty($formID) || empty($integrationID)) {
            return;
        }

        $integrationHandler = new IntegrationHandler($formID, IpTool::getUserDetail());
        $zrecruitDetails = $integrationHandler->getAIntegration($integrationID);

        if (is_wp_error($zrecruitDetails)) {
            return;
        }
        $newDetails = json_decode($zrecruitDetails[0]->integration_details);

        $newDetails->tokenDetails = $tokenDetails;
        if (!empty($others['modules'])) {
            $newDetails->default->modules = $others['modules'];
        }
        if (!empty($others['related_modules'])) {
            $newDetails->default->relatedlist['modules'] = $others['related_modules'];
        }

        $integrationHandler->updateIntegration($integrationID, $zrecruitDetails[0]->integration_name, 'Zoho Recruit', \json_encode($newDetails), 'form');
    }

    public function execute(IntegrationHandler $integrationHandler, $integrationData, $fieldValues, $entryID, $logID)
    {
        $integrationDetails = is_string($integrationData->integration_details) ? json_decode($integrationData->integration_details) : $integrationData->integration_details;
        $tokenDetails = $integrationDetails->tokenDetails;
        $module = $integrationDetails->module;
        $fieldMap = $integrationDetails->field_map;
        $actions = $integrationDetails->actions;
        $defaultDataConf = $integrationDetails->default;
        if (
            empty($tokenDetails)
            || empty($module)
            || empty($fieldMap)
        ) {
            return new WP_Error('REQ_FIELD_EMPTY', __('module, fields are required for zoho recruit api', 'bitform'));
        }
        if (empty($defaultDataConf->moduleData->{$module}->fields) || empty($defaultDataConf->modules->{$module})) {
            return new WP_Error('REQ_FIELD_EMPTY', __('module, fields are required for zoho recruit api', 'bitform'));
        }
        if ((intval($tokenDetails->generates_on) + (55 * 60)) < time()) {
            $requiredParams['clientId'] = $integrationDetails->clientId;
            $requiredParams['clientSecret'] = $integrationDetails->clientSecret;
            $requiredParams['dataCenter'] = $integrationDetails->dataCenter;
            $requiredParams['tokenDetails'] = $tokenDetails;
            $newTokenDetails = ZohoRecruitHandler::_refreshAccessToken((object)$requiredParams);
            if ($newTokenDetails) {
                ZohoRecruitHandler::_saveRefreshedToken($this->_formID, $this->_integrationID, $newTokenDetails);
                $tokenDetails = $newTokenDetails;
            }
        }

        $required = !empty($defaultDataConf->moduleData->{$module}->required) ?
            $defaultDataConf->moduleData->{$module}->required : [];

        $actions = $integrationDetails->actions;
        $fileMap = $integrationDetails->upload_field_map;
        $dataCenter = $integrationDetails->dataCenter;
        $recordApiHelper = new RecordApiHelper($dataCenter, $tokenDetails, $this->_integrationID, $logID);
        $zRecruitApiResponse = $recordApiHelper->executeRecordApi(
            $this->_formID,
            $entryID,
            $defaultDataConf,
            $module,
            $fieldValues,
            $fieldMap,
            $actions,
            $required,
            $fileMap
        );
        if (is_wp_error($zRecruitApiResponse)) {
            return $zRecruitApiResponse;
        }

        if (
            count($integrationDetails->relatedlists)
            && !empty($zRecruitApiResponse->response->result->row->success->details->FL[0])
            && $zRecruitApiResponse->response->result->row->success->details->FL[0]->val === 'Id'
        ) {
            foreach ($integrationDetails->relatedlists as $relatedlist) {
                if (!empty($relatedlist->module)) {
                    $recordID = $zRecruitApiResponse->response->result->row->success->details->FL[0]->content;
                    $relatedListModule =  $relatedlist->module;
                    $defaultDataConf->moduleData->{$relatedListModule}->fields->{'SEMODULE'} = (object) array(
                        'length' => \strlen($relatedListModule),
                        'required' => true,
                        'data_type' => 'string',
                    );
                    $fieldValues['SEMODULE'] = $relatedListModule;
                    $relatedlist->field_map[] = (object)
                    array(
                        'formField' => 'SEMODULE',
                        'zohoFormField' => 'SEMODULE'
                    );

                    $defaultDataConf->moduleData->{$relatedListModule}->fields->{'SEID'} = (object) array(
                        'length' => \strlen($recordID),
                        'required' => true,
                        'data_type' => 'string',
                    );
                    $fieldValues['SEID'] = $recordID;
                    $relatedlist->field_map[] = (object)
                    array(
                        'formField' => 'SEID',
                        'zohoFormField' => 'SEID'
                    );

                    $zRecruitRelatedRecResp = $recordApiHelper->executeRecordApi(
                        $this->_formID,
                        $entryID,
                        $defaultDataConf,
                        $relatedListModule,
                        $fieldValues,
                        $relatedlist->field_map,
                        $relatedlist->actions,
                        !empty($defaultDataConf->moduleData->{$relatedListModule}->required) ?
                            $defaultDataConf->moduleData->{$relatedListModule}->required : [],
                        $relatedlist->upload_field_map
                    );
                }
            }
        }
        return $zRecruitApiResponse;
    }
}
