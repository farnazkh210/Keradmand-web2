<?php

/**
 * ZohoRecruit Record Api
 *
 */

namespace BitCode\BitFormPro\Integration\ZohoSheet;

use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Util\FieldValueHandler;
use BitCode\BitForm\Core\Util\ApiResponse as UtilApiResponse;


/**
 * Provide functionality for Record insert,upsert
 */
class RecordApiHelper
{
    private $_defaultHeader;
    private $_apiDomain;
    private $_tokenDetails;

    public function __construct($tokenDetails, $integId, $logID)
    {
        $this->_defaultHeader['Authorization'] = "Zoho-oauthtoken {$tokenDetails->access_token}";
        $this->_defaultHeader['Content-Type'] = "application/json";
        $this->_apiDomain = \urldecode($tokenDetails->api_domain);
        $this->_tokenDetails = $tokenDetails;
        $this->_integrationID = $integId;
        $this->_logID = $logID;
        $this->_logResponse = new UtilApiResponse();
    }

    public function insertRecord($workbook, $worksheet, $headerRow, $dataCenter, $data)
    {
        $insertRecordEndpoint = "https://sheet.zoho.{$dataCenter}/api/v2/{$workbook}?method=worksheet.records.add&worksheet_name={$worksheet}&header_row={$headerRow}&json_data={$data}";

        return HttpHelper::post($insertRecordEndpoint, null, $this->_defaultHeader);
    }

    public function updateRecord($workbook, $worksheet, $headerRow, $dataCenter, $criteria, $firstMatch, $data)
    {
        $updateRecordEndpoint = "https://sheet.zoho.{$dataCenter}/api/v2/{$workbook}?method=worksheet.records.update&worksheet_name={$worksheet}&header_row={$headerRow}&first_match_only=" . json_encode($firstMatch) . "&data={$data}&criteria={$criteria}";

        return HttpHelper::post($updateRecordEndpoint, $data, $this->_defaultHeader);
    }

    public function shareWorkbook($workbook,  $dataCenter, $data)
    {
        $insertRecordEndpoint = "https://sheet.zoho.{$dataCenter}/api/v2/share?method=workbook.share&resource_id={$workbook}&share_json={$data}";

        return HttpHelper::post($insertRecordEndpoint, null, $this->_defaultHeader);
    }

    public function executeRecordApi($workbook, $worksheet, $headerRow, $dataCenter, $actions, $defaultConf, $fieldValues, $fieldMap)
    {
        $fieldData = [];
        foreach ($fieldMap as $fieldKey => $fieldPair) {
            if (!empty($fieldPair->zohoFormField)) {
                if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
                    $fieldData[$fieldPair->zohoFormField] = $fieldPair->customValue;
                } else {
                    $fieldData[$fieldPair->zohoFormField] = $fieldValues[$fieldPair->formField];
                }
            }
        }

        $fieldData = wp_json_encode($fieldData);
        $newFieldData = "[" . $fieldData . "]";

        if (isset($actions->update->criteria)) {
            $recordApiResponse = $this->updateRecord($workbook, $worksheet, $headerRow, $dataCenter, $actions->update->criteria, $actions->update->firstMatch, $fieldData);
            if (isset($recordApiResponse->error_message) || $recordApiResponse->status === 'failure' || $recordApiResponse->status === 'error') {
                $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => 'update'], 'error', $recordApiResponse);
            } else {
                $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => 'update'], 'success', $recordApiResponse);
            }

            if ($actions->update->insert && $recordApiResponse->no_of_affected_rows === 0) {
                $recordApiResponse = $this->insertRecord($workbook, $worksheet, $headerRow, $dataCenter, $newFieldData);
                if (isset($recordApiResponse->error_message) || $recordApiResponse->status === 'failure' || $recordApiResponse->status === 'error') {
                    $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => 'insert'], 'error', $recordApiResponse);
                } else {
                    $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => 'insert'], 'success', $recordApiResponse);
                }
            }
        } else {
            $recordApiResponse = $this->insertRecord($workbook, $worksheet, $headerRow, $dataCenter, $newFieldData);
            if (isset($recordApiResponse->error_message) || $recordApiResponse->status === 'failure' || $recordApiResponse->status === 'error') {
                $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => 'insert'], 'error', $recordApiResponse);
            } else {
                $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'record', 'type_name' => 'insert'], 'success', $recordApiResponse);
            }
        }

        if (isset($actions->share)) {
            $share_json = [];
            foreach ($actions->share as $user_share) {
                if (!empty($user_share->email)) {
                    $emails = explode(",", $user_share->email);
                    foreach ($emails as $email) {
                        $email = FieldValueHandler::replaceFieldWithValue($email, $fieldValues);
                        if (is_email($email)) {
                            $share_json[] = [
                                "user_email" => $email,
                                "access_level" => $user_share->access
                            ];
                        }
                    }
                }
            }
            $shareApiResponse = $this->shareWorkbook($workbook, $dataCenter, wp_json_encode($share_json));
            if (isset($shareApiResponse->error_message) || $shareApiResponse->status === 'failure' || $shareApiResponse->status === 'error') {
                $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'share', 'type_name' => 'workbook'], 'error', $shareApiResponse);
            } else {
                $this->_logResponse->apiResponse($this->_logID, $this->_integrationID, ['type' =>  'share', 'type_name' => 'workbook'], 'success', $shareApiResponse);
            }
        }

        return $recordApiResponse;
    }
}
