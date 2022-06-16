<?php

/**
 * ZohoCrm Record Api
 *
 */

namespace BitCode\BitForm\Core\Integration\ZohoCRM;

use WP_Error;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Util\DateTimeHelper;
use BitCode\BitForm\Core\Util\ApiResponse as UtilApiResponse;
use BitCode\BitFormPro\Integration\ZohoCRM\TagApiHelper;

/**
 * Provide functionality for Record insert,upsert
 */
class RecordApiHelper
{
    protected $_defaultHeader;
    protected $_apiDomain;
    protected $_tokenDetails;

    public function __construct($tokenDetails)
    {
        $this->_defaultHeader['Authorization'] = "Zoho-oauthtoken {$tokenDetails->access_token}";
        $this->_apiDomain = \urldecode($tokenDetails->api_domain);
        $this->_tokenDetails = $tokenDetails;
        $this->_logResponse = new UtilApiResponse();
    }

    public function upsertRecord($module, $data)
    {
        $insertRecordEndpoint = "{$this->_apiDomain}/crm/v2/{$module}/upsert";
        $data = \is_string($data) ? $data : \json_encode($data);
        return HttpHelper::post($insertRecordEndpoint, $data, $this->_defaultHeader);
    }

    public function insertRecord($module, $data)
    {
        $insertRecordEndpoint = "{$this->_apiDomain}/crm/v2/{$module}";
        $data = \is_string($data) ? $data : \json_encode($data);
        return HttpHelper::post($insertRecordEndpoint, $data, $this->_defaultHeader);
    }

    public function executeRecordApi($formID, $entryID, $integId, $logID, $defaultConf, $module, $layout, $fieldValues, $fieldMap, $actions, $required, $fileMap = [], $isRelated = false)
    {

        foreach ($fieldMap as $fieldKey => $fieldPair) {
            if (!empty($fieldPair->zohoFormField)) {
                if (empty($defaultConf->layouts->{$module}->{$layout}->fields->{$fieldPair->zohoFormField})) {
                    continue;
                }
                if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
                    $fieldData[$fieldPair->zohoFormField] = $this->formatFieldValue($fieldPair->customValue, $defaultConf->layouts->{$module}->{$layout}->fields->{$fieldPair->zohoFormField});
                } else {
                    $fieldData[$fieldPair->zohoFormField] = $this->formatFieldValue($fieldValues[$fieldPair->formField], $defaultConf->layouts->{$module}->{$layout}->fields->{$fieldPair->zohoFormField});
                }
                if (empty($fieldData[$fieldPair->zohoFormField]) && \in_array($fieldPair->zohoFormField, $required)) {
                    $error = new WP_Error('REQ_FIELD_EMPTY', wp_sprintf(__('%s is required for zoho crm, %s module', 'bit-form'), $fieldPair->zohoFormField, $module));
                    $this->_logResponse->apiResponse($logID, $integId, ['type' => 'record', 'type_name' => 'field'], 'validation', $error);
                    return $error;
                }

                if (!empty($fieldData[$fieldPair->zohoFormField])) {
                    $requiredLength = $defaultConf->layouts->{$module}->{$layout}->fields->{$fieldPair->zohoFormField}->length;
                    $currentLength = is_array($fieldData[$fieldPair->zohoFormField]) || is_object($fieldData[$fieldPair->zohoFormField]) ?
                        @count($fieldData[$fieldPair->zohoFormField])
                        : strlen($fieldData[$fieldPair->zohoFormField]);
                    if ($currentLength > $requiredLength) {
                        $error = new WP_Error('REQ_FIELD_LENGTH_EXCEEDED', wp_sprintf(__('zoho crm field %s\'s maximum length is %s, Given %s', 'bit-form'), $fieldPair->zohoFormField, $module));
                        $this->_logResponse->apiResponse($logID, $integId, ['type' => 'record', 'type_name' => 'field'], 'validation', $error);
                        return $error;
                    }
                }
            }
        }
        if (!empty($defaultConf->layouts->{$module}->{$layout}->id)) {
            $fieldData['Layout']['id'] = $defaultConf->layouts->{$module}->{$layout}->id;
        }
        $requestParams['data'][] = (object) $fieldData;
        $requestParams['trigger'] = [];
        $recordApiResponse = $this->upsertRecord($module, (object) $requestParams);
        if ($recordApiResponse->status === 'error') {
            $this->_logResponse->apiResponse($logID, $integId, 'record', 'error', $recordApiResponse);
        } else {
            $this->_logResponse->apiResponse($logID, $integId, 'record', 'success', $recordApiResponse);
        }
        return $recordApiResponse;
    }

    public function formatFieldValue($value, $formatSpecs)
    {
        if (empty($value)) {
            return '';
        }

        switch ($formatSpecs->json_type) {
            case 'jsonarray':
                $apiFormat = 'array';
                break;
            case 'jsonobject':
                $apiFormat = 'object';
                break;

            default:
                $apiFormat = $formatSpecs->json_type;
                break;
        }

        $formatedValue = '';
        $fieldFormat = gettype($value);
        if ($fieldFormat === $apiFormat && $formatSpecs->data_type !== 'datetime') {
            $formatedValue = $value;
        } else {
            if ($apiFormat === 'array' || $apiFormat === 'object') {
                if ($fieldFormat === 'string') {
                    if (strpos($value, ',') === -1) {
                        $formatedValue = json_decode($value);
                    } else {
                        $formatedValue = explode(',', $value);
                    }
                    $formatedValue = is_null($formatedValue) && !is_null($value) ? [$value] : $formatedValue;
                } else {
                    $formatedValue = $value;
                }

                if ($apiFormat === 'object') {
                    $formatedValue = (object) $formatedValue;
                }
            } elseif ($apiFormat === 'string' && $formatSpecs->data_type !== 'datetime') {
                $formatedValue = !is_string($value) ? json_encode($value) : $value;
            } elseif ($formatSpecs->data_type === 'datetime') {
                $dateTimeHelper = new DateTimeHelper();
                $formatedValue = $dateTimeHelper->getFormated($value, 'Y-m-d\TH:i', wp_timezone(), 'Y-m-d\TH:i:sP', null);
            } else {
                $stringyfiedValue = !is_string($value) ? json_encode($value) : $value;

                switch ($apiFormat) {
                    case 'double':
                        $formatedValue = (float) $stringyfiedValue;
                        break;

                    case 'boolean':
                        $formatedValue = (bool) $stringyfiedValue;
                        break;

                    case 'integer':
                        $formatedValue = (int) $stringyfiedValue;
                        break;

                    default:
                        $formatedValue = $stringyfiedValue;
                        break;
                }
            }
        }
        $formatedValueLenght = $apiFormat === 'array' || $apiFormat === 'object' ? (is_countable($formatedValue) ? \count($formatedValue) : @count($formatedValue)) : \strlen($formatedValue);
        if ($formatedValueLenght > $formatSpecs->length) {
            $formatedValue = $apiFormat === 'array' || $apiFormat === 'object' ? array_slice($formatedValue, 0, $formatSpecs->length) : substr($formatedValue, 0, $formatSpecs->length);
        }

        return $formatedValue;
    }
}
