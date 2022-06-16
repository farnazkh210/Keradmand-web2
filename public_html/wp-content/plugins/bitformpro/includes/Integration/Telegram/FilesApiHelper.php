<?php
/**
 * Telegram Files Api
 *
 */

namespace BitCode\BitFormPro\Integration\Telegram;

/**
 * Provide functionality for Upload files
 */
final class FilesApiHelper
{
	private $_defaultHeader;
	private $_payloadBoundary;
	private $_basepath;

	/**
	 *
	 * @param Integer $formID  ID of the form, for which integration is executing
	 * @param Integer $entryID Current submittion ID
	 */
	public function __construct($formID, $entryID)
	{
		$this->_payloadBoundary = wp_generate_password(24);
		$this->_defaultHeader['Content-Type'] = "multipart/form-data; boundary=" . $this->_payloadBoundary;
		$this->_basepath = BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID . DIRECTORY_SEPARATOR . $entryID . DIRECTORY_SEPARATOR;
	}

	/**
	 * Helps to execute upload files api
	 *
	 * @param String $apiEndPoint Telegram API base URL
	 * @param Array  $data        Data to pass to API
	 *
	 * @return Array $uploadResponse Telegram API response
	 */
	public function uploadFiles($apiEndPoint, $data)
	{
		$mimeType = mime_content_type("{$this->_basepath}{$data['photo']}");
		$fileType = \explode("/", $mimeType);

		switch ($fileType[0]) {
			case 'image':
				$apiMethod = '/sendPhoto';
				$param = 'photo';
				break;

			case 'audio':
				$apiMethod = '/sendAudio';
				$param = 'audio';
				break;
			case 'video':
				$apiMethod = '/sendVideo';
				$param = 'video';
				break;

			default:
				$apiMethod = '/sendDocument';
				$param = 'document';
				break;
		}
		$uploadFileEndpoint = $apiEndPoint . $apiMethod;

		$data[$param] = new \CURLFILE("{$this->_basepath}{$data['photo']}");
		if ($param != 'photo') {
			unset($data['photo']);
		}
		$curl = curl_init();
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $uploadFileEndpoint,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $data,
			)
		);

		$uploadResponse = curl_exec($curl);

		curl_close($curl);
		return $uploadResponse;
	}
}
