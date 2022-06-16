<?php

/**
 * ZohoSheet Integration
 *
 */

namespace BitCode\BitFormPro\Integration\GoogleSheet;

use WP_Error;
use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitFormPro\Integration\GoogleSheet\RecordApiHelper;



/**
 * Provide functionality for ZohoCrm integration
 */
class GoogleSheetHandler
{
    private $_formID;
    private $_integrationID;

    public function __construct($integrationID, $fromID)
    {
        $this->_formID = $fromID;
        $this->_integrationID = $integrationID;
    }

    /**bitforms_zsheet_refresh_worksheet_headers
     * Helps to register ajax function's with wp
     *
     * @return null
     */
    public static function registerAjax()
    {
        add_action('wp_ajax_bitforms_gsheet_generate_token', array(__CLASS__, 'generateTokens'));
        add_action('wp_ajax_bitforms_gsheet_refresh_spreadsheets', array(__CLASS__, 'refreshSpreadsheetsAjaxHelper'));
        add_action('wp_ajax_bitforms_gsheet_refresh_worksheets', array(__CLASS__, 'refreshWorksheetsAjaxHelper'));
        add_action('wp_ajax_bitforms_gsheet_refresh_worksheet_headers', array(__CLASS__, 'refreshWorksheetHeadersAjaxHelper'));
    }

    /**
     * Process ajax request for generate_token
     *
     * @return JSON zoho crm api response and status
     */
    public static function generateTokens()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $requestsParams = json_decode($inputJSON);
            if (
                empty($requestsParams->clientId)
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

            $apiEndpoint = 'https://oauth2.googleapis.com/token';
            $authorizationHeader["Content-Type"] = 'application/x-www-form-urlencoded';
            $requestParams = array(
                "grant_type" => "authorization_code",
                "client_id" => $requestsParams->clientId,
                "client_secret" => $requestsParams->clientSecret,
                "redirect_uri" => \urldecode($requestsParams->redirectURI),
                "code" => urldecode($requestsParams->code)
            );
            $apiResponse = HttpHelper::post($apiEndpoint, $requestParams, $authorizationHeader);

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
     * Process ajax request for refresh crm modules
     *
     * @return JSON crm module data
     */
    public static function refreshSpreadsheetsAjaxHelper()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $queryParams = json_decode($inputJSON);
            if (
                empty($queryParams->tokenDetails)
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
                $response['tokenDetails'] = GoogleSheetHandler::_refreshAccessToken($queryParams);
            }

            $workSheets = "https://www.googleapis.com/drive/v3/files?q=mimeType%20%3D%20'application%2Fvnd.google-apps.spreadsheet'";

            $authorizationHeader["Authorization"] = "Bearer {$queryParams->tokenDetails->access_token}";
            $workSheetResponse = HttpHelper::get($workSheets, null, $authorizationHeader);

            $allSpreadsheet = [];
            if (!is_wp_error($workSheetResponse) && empty($workSheetResponse->response->error)) {
                $spreadsheets = $workSheetResponse->files;

                // wp_send_json_success($spreadsheets);

                foreach ($spreadsheets as $spreadsheet) {
                    $allSpreadsheet[$spreadsheet->name] = (object) array(
                        'spreadsheetId' => $spreadsheet->id,
                        'spreadsheetName' => $spreadsheet->name
                    );
                }
                uksort($allSpreadsheet, 'strnatcasecmp');
                // wp_send_json_success($allSpreadsheet, 200);
                $response['spreadsheets'] = $allSpreadsheet;
            } else {
                wp_send_json_error(
                    $workSheetResponse->response->error->message,
                    400
                );
            }
            if (!empty($response['tokenDetails']) && !empty($queryParams->id)) {
                GoogleSheetHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response['$spreadsheets']);
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
     * Process ajax request for refesh crm layouts
     *
     * @return JSON crm layout data
     */
    public static function refreshWorksheetsAjaxHelper()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $queryParams = json_decode($inputJSON);

            // wp_send_json_success( $queryParams, 200 );

            if (
                empty($queryParams->clientId)
                || empty($queryParams->clientSecret)
                || empty($queryParams->spreadsheetId)
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
                $response['tokenDetails'] = GoogleSheetHandler::_refreshAccessToken($queryParams);
            }

            $worksheetsMetaApiEndpoint = "https://sheets.googleapis.com/v4/spreadsheets/$queryParams->spreadsheetId?&fields=sheets.properties";

            $authorizationHeader["Authorization"] = "Bearer {$queryParams->tokenDetails->access_token}";
            $worksheetsMetaResponse = HttpHelper::get($worksheetsMetaApiEndpoint, null, $authorizationHeader);

            if (!is_wp_error($worksheetsMetaResponse)) {
                $worksheets = $worksheetsMetaResponse->sheets;
                $response['worksheets'] = $worksheets;
                // wp_send_json_success($response, 200);
            } else {
                wp_send_json_error(
                    $worksheetsMetaResponse->status === 'error' ? $worksheetsMetaResponse->message : 'Unknown',
                    400
                );
            }
            if (!empty($response['tokenDetails']) && $response['tokenDetails'] && !empty($queryParams->id)) {
                $response["queryWorkbook"] = $queryParams->workbook;
                GoogleSheetHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response);
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
     * Process ajax request for refesh crm layouts
     *
     * @return JSON crm layout data
     */
    public static function refreshWorksheetHeadersAjaxHelper()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $queryParams = json_decode($inputJSON);
            // wp_send_json_success($queryParams);

            if (

                empty($queryParams->worksheetName)
                || empty($queryParams->tokenDetails)
                || empty($queryParams->clientId)
                || empty($queryParams->clientSecret)
                || empty($queryParams->header)
                || empty($queryParams->headerRow)
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
                $response['tokenDetails'] = GoogleSheetHandler::_refreshAccessToken($queryParams);
            }
            $headerRow = $queryParams->headerRow;
            if ($queryParams->header === 'ROWS') {
                $rangeNumber = preg_replace('/[^0-9]/', '', $headerRow);
                $range = "{$headerRow}:ZZ{$rangeNumber}";
            } else {
                $columnLetter = preg_replace('/\d/', '', $headerRow);
                $range = "{$headerRow}:{$columnLetter}1005";
            }

            $worksheetHeadersMetaApiEndpoint = "https://sheets.googleapis.com/v4/spreadsheets/{$queryParams->spreadsheetId}/values/{$queryParams->worksheetName}!{$range}?majorDimension={$queryParams->header}";

            $authorizationHeader["Authorization"] = "Bearer {$queryParams->tokenDetails->access_token}";
            $worksheetHeadersMetaResponse = HttpHelper::get($worksheetHeadersMetaApiEndpoint, null, $authorizationHeader);

            // wp_send_json_success($worksheetHeadersMetaResponse, 200);


            if (!is_wp_error($worksheetHeadersMetaResponse)) {

                $allHeaders = $worksheetHeadersMetaResponse->values[0];

                if( $allHeaders === null ) {
                    $response['worksheet_headers'] = [];
                } else {
                    $response['worksheet_headers'] = $allHeaders;
                }

            } else {
                wp_send_json_error(
                    $worksheetHeadersMetaResponse->status === 'error' ? $worksheetHeadersMetaResponse->message : 'Unknown',
                    400
                );
            }
            if (!empty($response['tokenDetails']) && $response['tokenDetails'] && !empty($queryParams->id)) {
                $response["queryModule"] = $queryParams->module;
                GoogleSheetHandler::_saveRefreshedToken($queryParams->formID, $queryParams->id, $response['tokenDetails'], $response);
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
     * Helps to refresh zoho crm access_token
     *
     * @param  Array $apiData Contains required data for refresh access token
     * @return JSON  $tokenDetails API token details
     */
    protected static function _refreshAccessToken($apiData)
    {
        if (
            empty($apiData->clientId)
            || empty($apiData->clientSecret)
            || empty($apiData->tokenDetails)
        ) {
            return false;
        }
        $tokenDetails = $apiData->tokenDetails;

        $apiEndpoint = "https://oauth2.googleapis.com/token";
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
     * @param Integer $integrationID ID of Google Sheet Integration
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
        $gsheetDetails = $integrationHandler->getAIntegration($integrationID);

        if (is_wp_error($gsheetDetails)) {
            return;
        }
        $newDetails = json_decode($gsheetDetails[0]->integration_details);

        $newDetails->tokenDetails = $tokenDetails;
        if (!empty($others['spreadsheets'])) {
            $newDetails->default->workbooks = $others['spreadsheets'];
        }
        if (!empty($others['worksheets'])) {
            $newDetails->default->worksheets = $others['worksheets'];
        }
        if (!empty($others['worksheet_headers'])) {
            $newDetails->default->worksheets->headers->{$others['worksheet']} = $others['worksheet_headers'];
        }

        $integrationHandler->updateIntegration($integrationID, $gsheetDetails[0]->integration_name, 'Google Sheet', \json_encode($newDetails), 'form');
    }

    public function execute(IntegrationHandler $integrationHandler, $integrationData, $fieldValues, $entryID, $logID)
    {

        $integrationDetails = is_string($integrationData->integration_details) ? json_decode($integrationData->integration_details) : $integrationData->integration_details;

        //    wp_send_json_success($integrationDetails);

        $tokenDetails = $integrationDetails->tokenDetails;
        $spreadsheetId = $integrationDetails->spreadsheetId;
        $worksheetName = $integrationDetails->worksheetName;
        $headerRow = $integrationDetails->headerRow;
        $header = $integrationDetails->header;
        $fieldMap = $integrationDetails->field_map;
        $actions = $integrationDetails->actions;
        $defaultDataConf = $integrationDetails->default;
        // wp_send_json_success($fieldMap);
        if (
            empty($tokenDetails)
            || empty($spreadsheetId)
            || empty($worksheetName)
            || empty($fieldMap)
        ) {
            return new WP_Error('REQ_FIELD_EMPTY', __('module, fields are required for Google sheet api', 'bitform'));
        }

        if ((intval($tokenDetails->generates_on) + (55 * 60)) < time()) {
            $requiredParams['clientId'] = $integrationDetails->clientId;
            $requiredParams['clientSecret'] = $integrationDetails->clientSecret;
            $requiredParams['tokenDetails'] = $tokenDetails;
            $newTokenDetails = GoogleSheetHandler::_refreshAccessToken((object)$requiredParams);
            if ($newTokenDetails) {
                GoogleSheetHandler::_saveRefreshedToken($this->_formID, $this->_integrationID, $newTokenDetails);
                $tokenDetails = $newTokenDetails;
            }
        }

        // $actions = $integrationDetails->actions;
        $recordApiHelper = new RecordApiHelper($tokenDetails, $this->_integrationID, $logID, $entryID);

        $gsheetApiResponse = $recordApiHelper->executeRecordApi(
            $spreadsheetId,
            $worksheetName,
            $headerRow,
            $header,
            $actions,
            $defaultDataConf,
            $fieldValues,
            $fieldMap
        );

        if (is_wp_error($gsheetApiResponse)) {
            return $gsheetApiResponse;
        }
        return $gsheetApiResponse;
    }
}
