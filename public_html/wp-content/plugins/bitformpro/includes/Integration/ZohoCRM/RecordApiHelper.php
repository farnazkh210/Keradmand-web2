<?php

/**
 * ZohoCrm Record Api
 *
 */

namespace BitCode\BitFormPro\Integration\ZohoCRM;

use WP_Error;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitFormPro\Integration\ZohoCRM\TagApiHelper;
use BitCode\BitFormPro\Integration\ZohoCRM\FilesApiHelper;
use BitCode\BitForm\Core\Integration\ZohoCRM\RecordApiHelper as RecordApi;

/**
 * Provide functionality for Record insert,upsert
 */
class RecordApiHelper extends RecordApi
{
    public function serachRecord($module, $searchCriteria)
    {
        $searchRecordEndpoint = "{$this->_apiDomain}/crm/v2/{$module}/search";
        return HttpHelper::get($searchRecordEndpoint, ["criteria" => "({$searchCriteria})"], $this->_defaultHeader);
    }

    public function executeRecordApi($formID, $entryID, $integId, $logID, $defaultConf, $module, $layout, $fieldValues, $fieldMap, $actions, $required, $fileMap = [], $isRelated = false)
    {
        $fieldData = [];
        $filesApiHelper = new FilesApiHelper($this->_tokenDetails, $formID, $entryID, $integId, $logID);
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
                    $error = new WP_Error('REQ_FIELD_EMPTY', wp_sprintf(__('%s is required for zoho crm, %s module', 'bitform'), $fieldPair->zohoFormField, $module));
                    $this->_logResponse->apiResponse($logID, $integId, ['type' => 'record', 'type_name' => 'field'], 'validation', $error);
                    return $error;
                }
                if (!empty($fieldData[$fieldPair->zohoFormField])) {
                    $requiredLength = $defaultConf->layouts->{$module}->{$layout}->fields->{$fieldPair->zohoFormField}->length;
                    $currentLength = is_array($fieldData[$fieldPair->zohoFormField]) || is_object($fieldData[$fieldPair->zohoFormField]) ?
                        @count($fieldData[$fieldPair->zohoFormField])
                        : strlen($fieldData[$fieldPair->zohoFormField]);
                    if ($currentLength > $requiredLength) {
                        $error = new WP_Error('REQ_FIELD_LENGTH_EXCEEDED', wp_sprintf(__('zoho crm field %s\'s maximum length is %s, Given %s', 'bitform'), $fieldPair->zohoFormField, $module));
                        $this->_logResponse->apiResponse($logID, $integId, ['type' => 'length', 'type_name' => 'field'], 'validation', $error);
                        return $error;
                    }
                }
            }
        }

        if (count($fileMap)) {
            foreach ($fileMap as $fileKey => $filePair) {
                if (!empty($filePair->zohoFormField)) {
                    if ($defaultConf->layouts->{$module}->{$layout}->fileUploadFields->{$filePair->zohoFormField}->data_type === 'fileupload' && !empty($fieldValues[$filePair->formField])) {
                        $files = $fieldValues[$filePair->formField];
                        $fileLength = $defaultConf->layouts->{$module}->{$layout}->fileUploadFields->{$filePair->zohoFormField}->length;
                        if (\is_array($files) && count($files) !== $fileLength) {
                            $files = array_slice($fieldValues[$filePair->formField], 0, $fileLength);
                        }
                        $uploadsIDs = $filesApiHelper->uploadFiles($files);
                        if ($uploadsIDs) {
                            $fieldData[$filePair->zohoFormField] = $uploadsIDs;
                        }
                    }
                }
            }
        }
        if (!empty($defaultConf->layouts->{$module}->{$layout}->id)) {
            $fieldData['Layout']['id'] = $defaultConf->layouts->{$module}->{$layout}->id;
        }
        if (!empty($actions->gclid) && isset($fieldValues['GCLID'])) {
            $fieldData['$gclid'] = $fieldValues['GCLID'];
        }
        if (!empty($actions->rec_owner)) {
            $fieldData['Owner']['id'] = $actions->rec_owner;
        }
        $requestParams['data'][] = (object) $fieldData;
        $requestParams['trigger'] = [];
        if (!empty($actions->workflow)) {
            $requestParams['trigger'][] = 'workflow';
        }
        if (!empty($actions->approval)) {
            $requestParams['trigger'][] = 'approval';
        }
        if (!empty($actions->blueprint)) {
            $requestParams['trigger'][] = 'blueprint';
        }
        if (!empty($actions->assignment_rules)) {
            $requestParams['lar_id'] = $actions->assignment_rules;
        }
        $recordApiResponse = '';
        if (!empty($actions->upsert) && !empty($actions->upsert->crmField)) {
            $requestParams['duplicate_check_fields'] = [];
            if (!empty($actions->upsert)) {
                $duplicateCheckFields = [];
                $searchCriteria = '';
                foreach ($actions->upsert->crmField as $fieldInfo) {
                    if (!empty($fieldInfo->name) && $fieldData[$fieldInfo->name]) {
                        $duplicateCheckFields[] = $fieldInfo->name;
                        if (empty($searchCriteria)) {
                            $searchCriteria .= "({$fieldInfo->name}:equals:{$fieldData[$fieldInfo->name]})";
                        } else {
                            $searchCriteria .= "and({$fieldInfo->name}:equals:{$fieldData[$fieldInfo->name]})";
                        }
                    }
                }
                if (isset($actions->upsert->overwrite) && !$actions->upsert->overwrite && !empty($searchCriteria)) {
                    $searchRecordApiResponse = $this->serachRecord($module, $searchCriteria);
                    if (!empty($searchRecordApiResponse) && !empty($searchRecordApiResponse->data)) {
                        $previousData = $searchRecordApiResponse->data[0];
                        foreach ($fieldData as $apiName => $currentValue) {
                            if (!empty($previousData->{$apiName})) {
                                $fieldData[$apiName] = $previousData->{$apiName};
                            }
                        }
                        $requestParams['data'][] = (object) $fieldData;
                    }
                }
                $requestParams['duplicate_check_fields'] = $duplicateCheckFields;
            }
            $recordApiResponse = $this->upsertRecord($module, (object) $requestParams);
        } elseif ($isRelated) {
            $recordApiResponse = $this->insertRecord($module, (object) $requestParams);
        } else {
            $recordApiResponse = $this->upsertRecord($module, (object) $requestParams);
        }
        if (isset($recordApiResponse->status) &&  $recordApiResponse->status === 'error') {
            $this->_logResponse->apiResponse($logID, $integId, ['type' => 'record', 'type_name' => $module], 'error', $recordApiResponse);
        } else {
            $this->_logResponse->apiResponse($logID, $integId, ['type' => 'record', 'type_name' => $module], 'success', $recordApiResponse);
        }
        if (
            !empty($recordApiResponse->data)
            && !empty($recordApiResponse->data[0]->code)
            && $recordApiResponse->data[0]->code === 'SUCCESS'
            && !empty($recordApiResponse->data[0]->details->id)
        ) {
            if (!empty($actions->tag_rec) && class_exists('BitCode\BitFormPro\Integration\ZohoCRM\TagApiHelper')) {
                $tags = '';
                $tag_rec = \explode(",", $actions->tag_rec);
                foreach ($tag_rec as $tag) {
                    if (is_string($tag) && substr($tag, 0, 2) === '${' && $tag[strlen($tag) - 1] === '}') {
                        $tags .= (!empty($tags) ? ',' : '') . $fieldValues[substr($tag, 2, strlen($tag) - 3)];
                    } else {
                        $tags .= (!empty($tags) ? ',' : '') . $tag;
                    }
                }
                $tagApiHelper = new TagApiHelper($this->_tokenDetails, $module);
                $addTagResponse = $tagApiHelper->addTagsSingleRecord($recordApiResponse->data[0]->details->id, $tags);
                if (isset($addTagResponse->status) &&  $addTagResponse->status === 'error') {
                    $this->_logResponse->apiResponse($logID, $integId, ['type' => 'tag', 'type_name' => $module], 'error', $addTagResponse);
                } else {
                    $this->_logResponse->apiResponse($logID, $integId, ['type' => 'tag', 'type_name' => $module], 'success', $addTagResponse);
                }
            }
            if (!empty($actions->attachment)) {
                $validAttachments = array();
                $fileFound = 0;
                $responseType = 'success';
                $attachmentApiResponses = [];
                $attachment = explode(",", $actions->attachment);
                foreach ($attachment as $fileField) {
                    if (isset($fieldValues[$fileField]) && !empty($fieldValues[$fileField])) {
                        $fileFound = 1;
                        if (is_array($fieldValues[$fileField])) {
                            foreach ($fieldValues[$fileField] as $singleFile) {
                                $attachmentApiResponse = $filesApiHelper->uploadFiles($singleFile, true, $module, $recordApiResponse->data[0]->details->id);
                                if (isset($attachmentApiResponse->status) &&  $attachmentApiResponse->status === 'error') {
                                    $responseType = 'error';
                                }
                            }
                        } else {
                            $attachmentApiResponse = $filesApiHelper->uploadFiles($fieldValues[$fileField], true, $module, $recordApiResponse->data[0]->details->id);
                            if (isset($attachmentApiResponse->status) &&  $attachmentApiResponse->status === 'error') {
                                $responseType = 'error';
                            }
                        }
                    }
                }
                if ($fileFound) {
                    $this->_logResponse->apiResponse($logID, $integId, ['type' => 'attachment', 'type_name' => $module], $responseType, $attachmentApiResponses);
                }
            }
        }

        return $recordApiResponse;
    }
}
