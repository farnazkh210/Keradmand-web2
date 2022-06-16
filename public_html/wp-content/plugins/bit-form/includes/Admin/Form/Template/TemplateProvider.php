<?php

namespace BitCode\BitForm\Admin\Form\Template;

use FilesystemIterator;

final class TemplateProvider
{
    /**
     * Undocumented function
     *
     * @return all
     */
    public function getAllTemplates()
    {
        return $this->allTemplates();
    }
    /**
     * Undocumented function
     *
     * @return void
     */
    protected function allTemplates()
    {
        $dirs = new FilesystemIterator(__DIR__);
        foreach ($dirs as $dirInfo) {
            if ($dirInfo->isFile()) {
                $integartionBaseName = basename($dirInfo);
                if (substr($integartionBaseName, -strlen('Template.php')) === 'Template.php') {
                    $className = substr($integartionBaseName, 0, strpos($integartionBaseName, '.php'));
                    $className = __NAMESPACE__ . '\\' . $className;
                    $templateDetail = new $className;
                    $Templates[] =  array(
                        'title' => $templateDetail->getTitle(),
                        'description' =>  $templateDetail->getDescription(),
                        'status' => $templateDetail->getStatus(),
                        'thumbnail' => $templateDetail->getThumbnail(),
                        'category' => $templateDetail->getCategory()
                    );
                }
            }
        }
        return json_encode($Templates);
    }
    /**
     * Undocumented function
     *
     * @param String $name
     * @return void
     */
    protected function setTemplate($name = 'Contact Form', $newFormId)
    {
        $name = is_null($name) ? 'Contact Form' : $name;
        $name = str_replace(' ', null, $name);
        $file = strpos($name, 'Template') !== false ? $name . '.php' : $name . 'Template.php';
        if (file_exists(__DIR__ . '/' . $file)) {
            $className = __NAMESPACE__ . '\\' . $name . 'Template';
            $templateDetail = new $className;
            return array(
                'fields' => json_decode($templateDetail->getFields($newFormId)),
                'layout' => json_decode($templateDetail->getLayout($newFormId)),
                'form_name' => $templateDetail->getTitle()
            );
        } else {
            return false;
        }
    }
    /**
     * This function helps to get TEMPLATE
     *
     * @return bool setTemplate()
     */
    public function getTemplate($name, $newFormId)
    {
        return $this->setTemplate($name, $newFormId);
    }
}
