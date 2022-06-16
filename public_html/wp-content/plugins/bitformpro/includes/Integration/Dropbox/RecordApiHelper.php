<?php

namespace BitCode\BitFormPro\Integration\Dropbox;

use BitCode\BitForm\Core\Util\ApiResponse;
use BitCode\BitForm\Core\Util\HttpHelper;
use WP_Error;

class RecordApiHelper
{
    protected $token;
    protected $formId;
    protected $entryId;
    protected $apiBaseUri = 'https://api.dropboxapi.com';
    protected $contentBaseUri = 'https://content.dropboxapi.com';

    public function __construct($token, $formId, $entryId)
    {
        $this->token = $token;
        $this->formId = $formId;
        $this->entryId = $entryId;
        $this->logResponse = new ApiResponse();
    }

    public function uploadFile($folder, $filePath)
    {
        if ($filePath === '') return;

        $filePath = $this->makeFilePath($filePath);
        $filesize = filesize($filePath);
        $fp =  fopen($filePath, "rb");
        $body = fread($fp, $filesize);
        if (!$body) {
            return new WP_Error(423, 'Can\'t open file!');
        }

        $apiEndPoint = $this->contentBaseUri . '/2/files/upload';
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/octet-stream',
            'Dropbox-API-Arg' => json_encode([
                'path' => $folder . '/' . $this->fileName($filePath),
                'mode' => 'add',
                'autorename' => true,
                'mute' => true,
                'strict_conflict' => false
            ]),
        ];

        $response = HttpHelper::post($apiEndPoint, $body, $headers);
        if (isset($response->id)) return 'uploaded';
        return $response;
    }

    public function fileName($filePath)
    {
        $filePathArray = explode('/', $filePath);
        $key = count($filePathArray) - 1;
        if (key_exists($key, $filePathArray)) {
            $fileName = $filePathArray[$key];
        }
        return $fileName;
    }

    public function handleAllFiles($folderWithFile, $actions)
    {
        $responses = [];
        foreach ($folderWithFile as $folder => $filePath) {
            if ($filePath == '') continue;
            if (is_array($filePath)) {
                foreach ($filePath as $singleFilePath) {
                    if ($singleFilePath == '') continue;
                    $response = $this->uploadFile($folder, $singleFilePath);
                    if ($response !== 'uploaded') $responses[] = $response;
                    $this->deleteFile($singleFilePath, $actions);
                }
            } else {
                $response = $this->uploadFile($folder, $filePath);
                if ($response !== 'uploaded') $responses[] = $response;
                $this->deleteFile($filePath, $actions);
            }
        }
        return $responses;
    }

    public function deleteFile($filePath, $actions)
    {
        if (isset($actions->delete_from_wp) && $actions->delete_from_wp) {
            $filePath = $this->makeFilePath($filePath);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function makeFilePath($filePath)
    {
        $upDir = wp_upload_dir();
        return $upDir['basedir'] . '/bitforms/uploads/' . $this->formId . '/' . $this->entryId . '/' . $filePath;
    }

    public function executeRecordApi($integrationId, $logID, $fieldValues, $fieldMap, $actions)
    {
        $folderWithFile = [];
        foreach ($fieldMap as $value) {
            if (!is_null($fieldValues[$value->formField])) {
                $folderWithFile[$value->dropboxFormField] = $fieldValues[$value->formField];
            }
        }
        $apiResponse = $this->handleAllFiles($folderWithFile, $actions);

        if (count($apiResponse)) {
            $this->logResponse->apiResponse($logID, $integrationId, ['type' =>  'record', 'type_name' => 'insert'], 'success', 'Some Files Can\'t Upload For Some Reason.');
        } else {
            $this->logResponse->apiResponse($logID, $integrationId, ['type' =>  'record', 'type_name' => 'insert'], 'success', 'All Files Uploaded.');
        }
        return;
    }
}
