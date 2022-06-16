<?php

/**
 * ZohoCrm Files Api
 *
 */

namespace BitCode\BitFormPro\Integration\ZohoCRM;

use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Util\ApiResponse as UtilApiResponse;

/**
 * Provide functionality for Upload files
 */
final class FilesApiHelper
{
    private $_defaultHeader;
    private $_apiDomain;
    private $_payloadBoundary;
    private $_basepath;

    /**
     *
     * @param Object  $tokenDetails Api token details
     * @param Integer $formID       ID of the form, for which integration is executing
     * @param Integer $entryID      Current submittion ID
     */
    public function __construct($tokenDetails, $formID, $entryID, $integId, $logID)
    {
        $this->_integId = $integId;
        $this->_logID = $logID;
        $this->_logResponse = new UtilApiResponse();
        $this->_payloadBoundary = wp_generate_password(24);
        $this->_defaultHeader['Authorization'] = "Zoho-oauthtoken {$tokenDetails->access_token}";
        $this->_defaultHeader['content-type'] = "multipart/form; boundary=" . $this->_payloadBoundary;
        $this->_apiDomain = \urldecode($tokenDetails->api_domain);
        $this->_basepath = BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID . DIRECTORY_SEPARATOR . $entryID . DIRECTORY_SEPARATOR;
    }

    /**
     * Helps to execute upload files api
     *
     * @param Mixed $files        Files path
     * @param Bool  $isAttachment Check upload type
     * @param Mixed $module       Attachment Module name
     * @param Mixed $recordID     Record id
     *
     * @return Array $uploadedFiles ID's of uploaded file in Zoho CRM
     */
    public function uploadFiles($files, $isAttachment = false, $module = '', $recordID = 0)
    {
        $uploadFileEndpoint = $isAttachment ?
            "{$this->_apiDomain}/crm/v2/{$module}/{$recordID}/Attachments"
            : "{$this->_apiDomain}/crm/v2/files";
        $payload = '';
        if (is_array($files)) {
            foreach ($files as $fileIndex => $fileName) {
                if (file_exists("{$this->_basepath}{$fileName}")) {
                    $payload .= '--' . $this->_payloadBoundary;
                    $payload .= "\r\n";
                    $payload .= 'Content-Disposition: form-data; name="' . 'file' .
                        '"; filename="' . basename("{$this->_basepath}{$fileName}") . '"' . "\r\n";
                    $payload .= "\r\n";
                    $payload .= file_get_contents("{$this->_basepath}{$fileName}");
                    $payload .= "\r\n";
                }
            }
        } elseif (file_exists("{$this->_basepath}{$files}")) {
            $payload .= '--' . $this->_payloadBoundary;
            $payload .= "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . 'file' .
                '"; filename="' . basename("{$this->_basepath}{$files}") . '"' . "\r\n";
            $payload .= "\r\n";
            $payload .= file_get_contents("{$this->_basepath}{$files}");
            $payload .= "\r\n";
        }
        if (empty($payload)) {
            return false;
        }
        $payload .= '--' . $this->_payloadBoundary . '--';
        $uploadResponse = HttpHelper::post($uploadFileEndpoint, $payload, $this->_defaultHeader);
        if (!$isAttachment) {
            $uploadedFiles = [];
            if (!empty($uploadResponse->data) && \is_array($uploadResponse->data)) {
                foreach ($uploadResponse->data as $singleFileResponse) {
                    if (!empty($singleFileResponse->code) && $singleFileResponse->code === 'SUCCESS') {
                        $uploadedFiles[] = $singleFileResponse->details->id;
                    }
                }
            }
            if (isset($uploadResponse->status) &&  $uploadResponse->status === 'error') {
                $this->_logResponse->apiResponse($this->_logID, $this->_integId, ['type' => 'upload', 'type_name' => 'file'], 'error', $uploadResponse);
            } else {
                $this->_logResponse->apiResponse($this->_logID, $this->_integId, ['type' => 'upload', 'type_name' => 'file'], 'success', $uploadResponse);
            }
            return $uploadedFiles;
        }
        return $uploadResponse;
    }
}
