<?php

/**
 * Pod Integration
 *
 */

namespace BitCode\BitFormPro\Integration\Pods;

use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitForm\Core\Form\FormManager;
use BitCode\BitFormPro\Core\Util\WpFileHandler;

/**
 * Provide functionality for POD integration
 */
class PodsHandler
{
    private $_formID;

    public function __construct($integrationID, $fromID)
    {
        $this->_formID = $fromID;
        $this->_integrationID = $integrationID;

        global $wpdb;
        $this->_wpdb = $wpdb;
    }

    /**
     * Helps to register ajax function's with wp
     *
     * @return null
     */

    private function podMappingField($integrationDetails, $podField, $formFields, $fieldValues, $entryID, $postID)
    {
        $string_types = [
            "text",
            "textarea",
            "radio",
            "number",
            "oembed",
            "password",
            "email",
            "pick",
            "paragraph",
            "wysiwyg",
            "code",
            "check",
            "color",
            "website",
            "date",
            "time",
            "datetime",
            "datetime-local",
            "url"
        ];
        $fileUploadHandle = new WpFileHandler($this->_formID);
        $podData = [];
        foreach ($integrationDetails->pod_map as $fieldPair) {
            foreach ($podField as $pod) {
                foreach ($formFields as $field) {
                    if (!empty($fieldPair->podFormField) && !empty($fieldPair->formField)) {
                        if ($fieldPair->formField == $field['key'] && in_array($pod['type'], $string_types) && !empty($fieldValues[$fieldPair->formField])) {
                            $podData[$fieldPair->podFormField] = $fieldValues[$fieldPair->formField];
                        } else if ($fieldPair->formField == $field['key'] && $field['type'] == "decision-box" && $pod['type'] == 'boolean') {
                            if ((int) $fieldValues[$fieldPair->formField] == 1 || (int) $fieldValues[$fieldPair->formField] == 0) {
                                $podData[$fieldPair->podFormField] = $fieldValues[$fieldPair->formField];
                            }
                        } else if ($fieldPair->formField == $field['key'] && isset($field['mul'], $field)  && in_array($pod['type'], $string_types) && $field['type'] != "file-up") {
                            if ($field['mul'] == true && $pod['pick_format_type'] == "multi" && !empty($fieldValues[$fieldPair->formField])) {
                                $podData[$fieldPair->podFormField] = explode(',', $fieldValues[$fieldPair->formField]);
                            } else if ($field['mul'] == false && $pod['pick_format_type'] == "multi" && !empty($fieldValues[$fieldPair->formField])) {
                                $podData[$fieldPair->podFormField] = $fieldValues[$fieldPair->formField];
                            }
                        } else if ($fieldPair->formField == $field['key'] && $field['type'] == "file-up" && $pod['type'] == 'file') {
                            if (!empty($fieldValues[$fieldPair->formField])) {
                                if ($pod['file_format_type'] == 'multi') {
                                    $attachmentId = $fileUploadHandle->multiFileMoveWpMedia($entryID, $fieldValues[$fieldPair->formField], $postID);
                                    if (!empty($attachmentId)) {
                                        update_post_meta($postID, $fieldPair->podFormField,  $attachmentId);
                                        update_post_meta($postID, '_pods_' . $fieldPair->podFormField,  json_encode($attachmentId));
                                    }
                                } else if ($pod['file_format_type'] == 'single') {
                                    $attachmentId = $fileUploadHandle->singleFileMoveWpMedia($entryID, $fieldValues[$fieldPair->formField], $postID);
                                    if (!empty($attachmentId)) {
                                        update_post_meta($postID, $fieldPair->podFormField,  $attachmentId);
                                        update_post_meta($postID, '_pods_' . $fieldPair->podFormField,  json_encode($attachmentId));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $podData;
    }

    private function postFieldMapping($fieldData, $post_map, $formFields, $fieldValues, $postID, $entryID)
    {
        $uploadFeatureImg = new WpFileHandler($this->_formID);
        foreach ($post_map as  $fieldPair) {
            foreach ($formFields as $field) {
                if (!empty($fieldPair->postFormField) && !empty($fieldPair->formField)) {
                    if ($fieldPair->formField == $field['key'] && $fieldPair->postFormField != '_thumbnail_id') {
                        $fieldData[$fieldPair->postFormField] = $fieldValues[$fieldPair->formField];
                    } else if ($fieldPair->formField == $field['key'] && $field['type'] == "file-up" && $fieldPair->postFormField == '_thumbnail_id' && !empty($fieldValues[$field['key']])) {
                        if (!empty($fieldValues[$field['key']])) {
                            $uploadFeatureImg->uploadFeatureImg($fieldValues[$field['key']], $entryID, $postID);
                        }
                    }
                }
            }
        }
        return $fieldData;
    }

    public function execute(IntegrationHandler $integrationHandler, $integrationData, $fieldValues, $entryID, $logID)
    {
        $integrationDetails = is_string($integrationData->integration_details) ? json_decode($integrationData->integration_details) : $integrationData->integration_details;
        $fieldData = [];
        $taxonomy = new WpFileHandler($integrationData->form_id);

        $formManger = new FormManager($integrationData->form_id);
        $formFields = $formManger->getFields();
        $allFields = pods($integrationDetails->post_type);
        $podField = [];

        foreach ($allFields->fields as $key => $field) {
            $podField[$key]['type'] = $field['type'];
            $podField[$key]['pick_format_type'] = $field['options']['pick_format_type'];
            $podField[$key]['file_format_type'] = $field['options']['file_format_type'];
        }

        $fieldData['comment_status'] = isset($integrationDetails->comment_status) ? $integrationDetails->comment_status : '';
        $fieldData['post_status'] = isset($integrationDetails->post_status) ? $integrationDetails->post_status : '';
        $fieldData['post_type'] = isset($integrationDetails->post_type) ? $integrationDetails->post_type : '';
        $fieldData['post_author'] = isset($integrationDetails->post_author) ? $integrationDetails->post_author : get_current_user_id();

        $exist_id = $fieldData['post_type'] . '_' . $entryID;
        $sql =  "SELECT * FROM `{$this->_wpdb->prefix}bitforms_form_entrymeta` WHERE `meta_key`='$exist_id' ";
        $exist_post_id =  $this->_wpdb->get_results($sql);
        $taxanomyData = $taxonomy->taxonomyData($formFields, $fieldValues);

        if ($exist_post_id == []) {

            $post_id = wp_insert_post(['post_title' => 'null', 'post_content' => 'null']);
            $updateData = $this->postFieldMapping($fieldData, $integrationDetails->post_map, $formFields, $fieldValues, $post_id, $entryID);
            $updateData['ID'] = $post_id;
            unset($updateData['_thumbnail_id']);
            wp_update_post($updateData, true);

            if (!empty($taxanomyData)) {
                foreach ($taxanomyData as $taxanomy) {
                    wp_set_post_terms($post_id, $taxanomy['value'], $taxanomy['term'], false);
                }
            }

            $this->_wpdb->insert(
                "{$this->_wpdb->prefix}bitforms_form_entrymeta",
                array(
                    'meta_key' => $fieldData['post_type'] . '_' . $entryID,
                    'meta_value' => $post_id,
                    'bitforms_form_entry_id' => $entryID,
                )
            );

            $podData = $this->podMappingField($integrationDetails, $podField, $formFields, $fieldValues, $entryID, $post_id);
            foreach ($podData as $key => $data) {
                if (is_array($data)) {
                    $count = count($data);
                    for ($i = 0; $i < $count; $i++) {
                        add_post_meta($post_id, $key, $data[$i]);
                    }
                } else {
                    add_post_meta($post_id, $key, $data);
                }
            }
        } else {
            if (!empty($taxanomyData)) {
                foreach ($taxanomyData as $taxanomy) {
                    wp_set_post_terms($exist_post_id[0]->meta_value, $taxanomy['value'], $taxanomy['term'], false);
                }
            }
            $podData = $this->podMappingField($integrationDetails, $podField, $formFields, $fieldValues, $entryID, $exist_post_id[0]->meta_value);
            foreach ($podData as $key => $data) {
                if (is_array($data)) {
                    $count = count($data);
                    for ($i = 0; $i < $count; $i++) {
                        delete_post_meta($exist_post_id[0]->meta_value, $key);
                    }
                    for ($i = 0; $i < $count; $i++) {
                        add_post_meta($exist_post_id[0]->meta_value, $key, $data[$i]);
                    }
                } else {
                    update_post_meta($exist_post_id[0]->meta_value, $key, $data);
                }
            }
            $updateData = $this->postFieldMapping($fieldData, $integrationDetails->post_map, $formFields, $fieldValues, $exist_post_id[0]->meta_value, $entryID);
            $updateData['ID'] = $exist_post_id[0]->meta_value;
            wp_update_post($updateData, true);
        }
        return $exist_post_id;
    }
}
