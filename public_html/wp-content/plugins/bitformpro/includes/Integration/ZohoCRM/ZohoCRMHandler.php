<?php

/**
 * ZohoCrm Integration
 *
 */

namespace BitCode\BitFormPro\Integration\ZohoCRM;

use WP_Error;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitFormPro\Integration\ZohoCRM\TagApiHelper;
use BitCode\BitFormPro\Integration\ZohoCRM\MetaDataApiHelper;
use BitCode\BitFormPro\Integration\ZohoCRM\RecordApiHelper;
use BitCode\BitForm\Core\Integration\ZohoCRM\ZohoCRMHandler as CrmHandler;

/**
 * Provide functionality for ZohoCrm integration
 */
final class ZohoCRMHandler extends CrmHandler
{
    public static function registerAjax()
    {
        add_action('wp_ajax_bitforms_zcrm_get_users', array(__CLASS__, 'refreshUsersAjaxHelper'));
        add_action('wp_ajax_bitforms_zcrm_get_tags', array(__CLASS__, 'refreshTagListAjaxHelper'));
        add_action('wp_ajax_bitforms_zcrm_get_assignment_rules', array(__CLASS__, 'getAssignmentRulesAjaxHelper'));
        add_action('wp_ajax_nopriv_bitforms_zcrm_get_assignment_rules', array(__CLASS__, 'getAssignmentRulesAjaxHelper'));
        add_action('wp_ajax_bitforms_zcrm_get_related_lists', array(__CLASS__, 'getRelatedListsAjaxHelper'));
    }

    public static function registerHooks()
    {
        add_filter('bitform_zcrm_addRelatedList', array(__CLASS__, 'addRelatedList'), 10, 8);
    }

    /**
     * Process ajax request to get assignment rules of a Zoho CRM module
     *
     * @return JSON crm assignment rules data
     */
    public static function getAssignmentRulesAjaxHelper()
    {
        if (true || isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
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
                $response['tokenDetails'] = CrmHandler::_refreshAccessToken($queryParams);
            }
            $metaDataApiHelper = new MetaDataApiHelper($queryParams->tokenDetails, true);
            $assignmentRulesResponse = $metaDataApiHelper->getAssignmentRules($queryParams->module);
            if (
                !is_wp_error($assignmentRulesResponse)
                && !empty($assignmentRulesResponse)
                && empty($assignmentRulesResponse->status)
            ) {
                uksort($assignmentRulesResponse, 'strnatcasecmp');
                $response["assignmentRules"] = $assignmentRulesResponse;
            } else {
                wp_send_json_error(
                    !empty($assignmentRulesResponse->status)
                        && $assignmentRulesResponse->status === 'error' ?
                        $assignmentRulesResponse->message : (empty($assignmentRulesResponse) ? __('Assignment rules is empty', 'bitform') : 'Unknown'),
                    empty($assignmentRulesResponse) ? 204 : 400
                );
            }
            if (!empty($response['tokenDetails']) && $response['tokenDetails'] && !empty($queryParams->id)) {
                // $response["queryModule"] = $queryParams->module;
                CrmHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response);
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
     * Process ajax request to get realted lists of a Zoho CRM module
     *
     * @return JSON crm layout data
     */
    public static function getRelatedListsAjaxHelper()
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
                $response['tokenDetails'] = CrmHandler::_refreshAccessToken($queryParams);
            }
            $metaDataApiHelper = new MetaDataApiHelper($queryParams->tokenDetails);
            $relatedListResponse = $metaDataApiHelper->getRelatedLists($queryParams->module);
            if (
                !is_wp_error($relatedListResponse)
                && !empty($relatedListResponse)
                && empty($relatedListResponse->status)
            ) {
                uksort($relatedListResponse, 'strnatcasecmp');
                $response["relatedLists"] = $relatedListResponse;
            } else {
                wp_send_json_error(
                    !empty($relatedListResponse->status)
                        && $relatedListResponse->status === 'error' ?
                        $relatedListResponse->message : (empty($relatedListResponse) ? __('RelatedList is empty', 'bitform') : 'Unknown'),
                    empty($relatedListResponse) ? 204 : 400
                );
            }
            if (!empty($response['tokenDetails']) && $response['tokenDetails'] && !empty($queryParams->id)) {
                // $response["queryModule"] = $queryParams->module;
                CrmHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response);
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
     * Process ajax request for refresh crm users
     *
     * @return JSON crm users data
     */
    public static function refreshUsersAjaxHelper()
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
                $response['tokenDetails'] = CrmHandler::_refreshAccessToken($queryParams);
            }
            $usersApiEndpoint = "{$queryParams->tokenDetails->api_domain}/crm/v2/users?type=ActiveConfirmedUsers";
            $authorizationHeader["Authorization"] = "Zoho-oauthtoken {$queryParams->tokenDetails->access_token}";
            $retrivedUsersData = [];
            $usersResponse = null;
            do {
                $requiredParams = [];
                if (!empty($usersResponse->users)) {
                    if (!empty($retrivedUsersData)) {
                        $retrivedUsersData = array_merge($retrivedUsersData, $usersResponse->users);
                    } else {
                        $retrivedUsersData = $usersResponse->users;
                    }
                }
                if (!empty($usersResponse->info->more_records) && $usersResponse->info->more_records) {
                    $requiredParams["page"] = intval($usersResponse->info->page) + 1;
                }
                $usersResponse = HttpHelper::get($usersApiEndpoint, $requiredParams, $authorizationHeader);
            } while ($usersResponse == null || (!empty($usersResponse->info->more_records) && $usersResponse->info->more_records));
            if (empty($requiredParams) && !is_wp_error($usersResponse)) {
                $retrivedUsersData = $usersResponse->users;
            }
            if (!is_wp_error($usersResponse) && !empty($retrivedUsersData)) {
                $users = [];
                foreach ($retrivedUsersData as $userKey => $userValue) {
                    $users[$userValue->full_name] = (object) array(
                        'full_name' => $userValue->full_name,
                        'id' => $userValue->id,
                    );
                }
                uksort($users, 'strnatcasecmp');
                $response["users"] = $users;
            } else {
                wp_send_json_error(
                    $usersResponse->status === 'error' ? $usersResponse->message : 'Unknown',
                    400
                );
            }
            if (!empty($response['tokenDetails']) && $response['tokenDetails'] && !empty($queryParams->id)) {
                CrmHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response);
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
     * Process ajax request for refresh tags of a module
     *
     * @return JSON crm Tags  for a module
     */
    public static function refreshTagListAjaxHelper()
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
                $response['tokenDetails'] = CrmHandler::_refreshAccessToken($queryParams);
            }
            $tokenDetails = empty($response['tokenDetails']) ? $queryParams->tokenDetails : $response['tokenDetails'];
            $tagApiHelper = new TagApiHelper($tokenDetails, $queryParams->module);
            $tagListApiResponse = $tagApiHelper->getTagList();
            if (!is_wp_error($tagListApiResponse)) {
                usort($tagListApiResponse, 'strnatcasecmp');
                $response["tags"] = $tagListApiResponse;
            } else {
                wp_send_json_error(
                    is_wp_error($tagListApiResponse) ? $tagListApiResponse->get_error_message() : (empty($tagListApiResponse) ? __('Tag is empty', 'bitform') : 'Unknown'),
                    empty($tagListApiResponse) ?  204 : 400
                );
            }
            if (!empty($response['tokenDetails']) && $response['tokenDetails'] && !empty($queryParams->id)) {
                CrmHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response);
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

    public static function addRelatedList($zcrmApiResponse, $formID, $entryID,$integID, $logID, $fieldValues, $integrationDetails, RecordApiHelper $recordApiHelper)
    {
        foreach ($integrationDetails->relatedlists as $relatedlist) {
            // Related List apis..
            $relatedListModule =  !empty($relatedlist->module) ? $relatedlist->module : '';
            $relatedListLayout =  !empty($relatedlist->layout) ? $relatedlist->layout : '';
            $defaultDataConf = $integrationDetails->default;
            if (empty($relatedListModule) || empty($relatedListLayout)) {
                return new WP_Error('REQ_FIELD_EMPTY', __('module, layout are required for zoho crm relatedlist', 'bitform'));
            }
            $module = $integrationDetails->module;
            $moduleSingular = \substr($module, 0, \strlen($module) - 1);
            if (isset($defaultDataConf->layouts->{$relatedListModule}->{$relatedListLayout}->fields->{$module})) {
                $moduleSingular = $module;
            } elseif (!isset($defaultDataConf->layouts->{$relatedListModule}->{$relatedListLayout}->fields->{$moduleSingular})) {
                $moduleSingular = '';
            }
            $relatedListRequired = !empty($defaultDataConf->layouts->{$relatedListModule}->{$relatedListLayout}->required) ?
                $defaultDataConf->layouts->{$relatedListModule}->{$relatedListLayout}->required : [];
            $recordID = $zcrmApiResponse->data[0]->details->id;
            $defaultDataConf->layouts->{$relatedListModule}->{$relatedListLayout}->fields->{'$se_module'} = (object) array(
                'length' => 200,
                'visible' => true,
                'json_type' => 'string',
                'data_type' => 'string',
            );
            $fieldValues['$se_module'] = $module;
            $relatedlist->field_map[] = (object)
            array(
                'formField' => '$se_module',
                'zohoFormField' => '$se_module'
            );
            if (isset($defaultDataConf->layouts->{$relatedListModule}->{$relatedListLayout}->fields->Parent_Id)) {
                $fieldValues['Parent_Id'] = (object) ['id' => $recordID];
                $relatedlist->field_map[] = (object)
                array(
                    'formField' => "Parent_Id",
                    'zohoFormField' => "Parent_Id"
                );
            } elseif (!empty($moduleSingular)) {
                $fieldValues[$moduleSingular] = ['id' => $recordID];
                $relatedlist->field_map[] = (object)
                array(
                    'formField' => $moduleSingular,
                    'zohoFormField' => $moduleSingular
                );
            } elseif ($module === 'Contacts') {
                $fieldValues['Who_Id'] = (object) ['id' => $recordID];
                $relatedlist->field_map[] = (object)
                array(
                    'formField' => 'Who_Id',
                    'zohoFormField' => 'Who_Id'
                );
            } else {
                $fieldValues['What_Id'] = (object) ['id' => $recordID];
                $relatedlist->field_map[] = (object)
                array(
                    'formField' => 'What_Id',
                    'zohoFormField' => 'What_Id'
                );
            }

            $zcrmRelatedlistApiResponse = $recordApiHelper->executeRecordApi(
                $formID,
                $entryID,
                $integID,
                $logID,
                $defaultDataConf,
                $relatedListModule,
                $relatedListLayout,
                $fieldValues,
                $relatedlist->field_map,
                $relatedlist->actions,
                $relatedListRequired,
                $relatedlist->upload_field_map,
                true
            );
        }
    }
}
