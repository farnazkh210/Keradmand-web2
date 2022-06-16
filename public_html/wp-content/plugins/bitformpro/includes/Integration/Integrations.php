<?php
/**
 *
 * @package BitFormPro
 */
namespace BitCode\BitFormPro\Integration;

/**
 * Provides details of available integration and helps to
 * execute available integrations
 */

 use FilesystemIterator;
 use WP_Error;

final class Integrations
{
    public function registerAjax()
    {
        $dirs = new FilesystemIterator(__DIR__);
        foreach ($dirs as $dirInfo) {
            if ($dirInfo->isDir()) {
                $integartionBaseName = basename($dirInfo);
                if (file_exists(__DIR__.'/'.$integartionBaseName)
                    && file_exists(__DIR__.'/'.$integartionBaseName.'/'.$integartionBaseName.'Handler.php')
                ) {
                    $integration = __NAMESPACE__. "\\{$integartionBaseName}\\{$integartionBaseName}Handler";
                    if (method_exists($integration, 'registerAjax')) {
                        $integration::registerAjax();
                    }
                }
            }
        }
    }

    public function registerHooks()
    {
        $dirs = new FilesystemIterator(__DIR__);
        foreach ($dirs as $dirInfo) {
            if ($dirInfo->isDir()) {
                $integartionBaseName = basename($dirInfo);
                if (file_exists(__DIR__.'/'.$integartionBaseName)
                    && file_exists(__DIR__.'/'.$integartionBaseName.'/'.$integartionBaseName.'Handler.php')
                ) {
                    $integration = __NAMESPACE__. "\\{$integartionBaseName}\\{$integartionBaseName}Handler";
                    if (method_exists($integration, 'registerHooks')) {
                        $integration::registerHooks();
                    }
                }
            }
        }
    }
}
