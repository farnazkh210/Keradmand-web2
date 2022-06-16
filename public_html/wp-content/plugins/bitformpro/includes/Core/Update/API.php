<?php
namespace BitCode\BitFormPro\Core\Update;

use WP_Error;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Util\DateTimeHelper;

final class API
{
    public static function getAPiEndPoint()
    {
        return 'http://api.bitpress.pro';
    }
    public static function getUpdatedInfo()
    {
        return new WP_Error('API_ERROR', 'Please proceed to update manually');
    }

    public static function activateLicense($licenseKey)
    {
        self::setKeyData($licenseKey);
        return true;
    }

    public static function disconnectLicense()
    {
        self::removeKeyData();
        return true;
    }

    public static function setKeyData($licenseKey)
    {
        $data['key'] = $licenseKey;
        $data['status'] = 'success';
        $data['expireIn'] = '';
        return update_option('bitformpro_integrate_key_data', $data, null);
    }

    public static function getKey()
    {
        $integrateData = get_option('bitformpro_integrate_key_data');
        $licenseKey = false;
        if (!empty($integrateData) && is_array($integrateData) && $integrateData['status'] === 'success') {
            $licenseKey = $integrateData['key'];
        }
        return $licenseKey;
    }
    public static function removeKeyData()
    {
        return delete_option('bitformpro_integrate_key_data');
    }
}
