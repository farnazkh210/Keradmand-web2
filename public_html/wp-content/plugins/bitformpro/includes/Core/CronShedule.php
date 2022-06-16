<?php

namespace BitCode\BitFormPro\Core;

use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitFormPro\Integration\Gclid\ApiHelper;
use BitCode\BitFormPro\Core\Database\GclidInfoModel;
use BitCode\BitFormPro\Integration\Gclid\GclidHandler;

/**
 * Class handling plugin activation.
 *
 * @since 1.0.0
 */
final class CronShedule
{
    public function cron_schedule()
    {
        add_action('gclid_cron_event', array($this, 'get_gclid_info'));
    }

    public function get_gclid_info()
    {
        $license = get_option('bitformpro_integrate_key_data');
        $integrationHandler = new IntegrationHandler(0, IpTool::getUserDetail());
        $googleDetails = $integrationHandler->getAllIntegration('app', 'Google');
        $gclidModel = new GclidInfoModel();
        if (!isset($googleDetails->errors['result_empty'])) {
            $newDetails =  json_decode($googleDetails[0]->integration_details);
            $requiredParams['clientId'] = $newDetails->clientId;
            $requiredParams['clientSecret'] = $newDetails->clientSecret;
            $requiredParams['tokenDetails'] = $newDetails->tokenDetails;
            $newTokenDetails = GclidHandler::_refreshAccessToken((object)$requiredParams);
            $apiHelper = new ApiHelper($newTokenDetails, $newDetails->clientCustomerId, $license['key']);
            $response = $apiHelper->getGclidInfo();
            $xml_data = simplexml_load_string($response->data);
            $json = json_encode($xml_data);
            $gclidReports = json_decode($json, TRUE);
            if (isset($gclidReports['ApiError'])) {
                $apiType = "error";
                $responseObj = $gclidReports['ApiError']['type'];
                $gclidModel->gclidApiLogInsert(['log_type' => 'gclid', 'response_type' => $apiType, 'response_obj' => $responseObj]);
            } else {
                $gclidModel->gclidApiLogInsert(['log_type' => 'gclid', 'response_type' => 'success', 'response_obj' => 'success']);
                foreach ($gclidReports['table']['row'] as $report) {
                    $gclidModel->gclidResponseInsert($report['@attributes']['googleClickID'], json_encode($report));
                }
            }
        }
    }
}
