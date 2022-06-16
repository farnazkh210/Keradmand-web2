<?php
namespace BitCode\BitForm\Core\Capability;

use BitCode\BitForm\Core\Util\FileDownloadProvider;

final class Request
{
    public static function Check($type)
    {
        switch ($type) {
        case 'admin':
            return is_admin();
    
        case 'ajax':
            return defined('DOING_AJAX');
    
        case 'cron':
            return defined('DOING_CRON');
    
        case 'api':
            return defined('REST_REQUEST');
    
        case 'frontend':
            return (! is_admin() || defined('DOING_AJAX')) && ! defined('DOING_CRON');
        }
    }

    public static function isPluginPage()
    {
        $queryToRemove = array('formID','entryID','fileID','download');
        $parsed_url = parse_url(home_url());
        $site_url = $parsed_url['scheme'] . "://" . $parsed_url['host'];
        
        $reqUrl = $site_url . remove_query_arg($queryToRemove, $_SERVER['REQUEST_URI']);
        $downloadUrl = FileDownloadProvider::getBaseDownloadURL();
        $reqUrl = \substr($reqUrl, -1) !== '/' ? $reqUrl.'/' : $reqUrl;
        $downloadUrl = \substr($downloadUrl, -1) !== '/' ? $downloadUrl.'/' : $downloadUrl;
        switch ($reqUrl) {
        case $downloadUrl:{
            return true;
        }
        default:
            return false;
        }
    }
}
