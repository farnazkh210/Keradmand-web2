<?php

/**
 *POST CREATEION WITH ACF
 *
 */

namespace BitCode\BitFormPro\Integration\Acf;

use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitForm\Core\Form\FormManager;
use BitCode\BitFormPro\Core\Util\WpFileHandler;



/**
 * Provide functionality for POST CREATEION WITH ACF 
 */
class AcfHandler
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



    private function postFieldMapping($fieldData, $post_map, $formFields, $fieldValues, $postID, $entryID)
    {
        $uploadFeatureImg = new WpFileHandler($this->_formID);
        foreach ($post_map as  $fieldPair) {
            foreach ($formFields as $field) {
                if (!empty($fieldPair->postField) && !empty($fieldPair->formField)) {
                    if ($fieldPair->postField != '_thumbnail_id') {
                        if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
                            $fieldData[$fieldPair->postField] = $fieldPair->customValue;
                        } else {
                            $fieldData[$fieldPair->postField] = $fieldValues[$fieldPair->formField];
                        }
                    } else if ($fieldPair->formField == $field['key'] && $field['type'] == "file-up" && $fieldPair->postField == '_thumbnail_id') {
                        if (!empty($fieldValues[$field['key']])) {
                            $uploadFeatureImg->uploadFeatureImg($fieldValues[$field['key']], $entryID, $postID);
                        }
                    }
                }
            }
        }
        return $fieldData;
    }

    private function getAcfFields($postType)
    {
        $acfFields = [];
        $field_groups = get_posts(array('post_type' => 'acf-field-group'));

        if ($field_groups) {
            $groups = acf_get_field_groups(array('post_type' => $postType));
            foreach ($groups as  $group) {
                foreach (acf_get_fields($group['key']) as $acf) {
                    array_push(
                        $acfFields,
                        [
                            'key' => $acf['key'],
                            'name' => $acf['name'],
                            'required' => $acf['required'],
                            'type' => $acf['type'],
                            'multiple' => isset($acf['multiple']) ? $acf['multiple']  : ''
                        ]
                    );
                }
            }
        }
        return $acfFields;
    }


    private function acfFieldMapping($acfMapField, $fieldValues, $acfFields)
    {
        $acfFieldData = [];
        $string_types = ["text", "textarea", "password", "wysiwyg", "number", "radio", "color_picker", "oembed", "email", "url", "date_picker", "true_false", "date_time_picker", "time_picker", "message"];
        $selectedTypes = ['checkbox', 'select', 'user','post_object'];
        foreach ($acfMapField as  $key => $fieldPair) {
            foreach ($acfFields as $acf) {
                if (property_exists($fieldPair, "acfField")) {
                    if ($acf['key'] == $fieldPair->acfField && in_array($acf['type'], $string_types)) {
                        $acfFieldData[$key]['key'] = $fieldPair->acfField;
                        $acfFieldData[$key]['name'] = $acf['name'];
                        if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
                            $acfFieldData[$key]['value'] = $fieldPair->customValue;
                        } else if ($fieldPair->formField !== 'custom' && !empty($fieldValues[$fieldPair->formField])) {
                            $acfFieldData[$key]['value'] = $fieldValues[$fieldPair->formField];
                        }
                    } else if ($acf['key'] == $fieldPair->acfField && in_array($acf['type'], $selectedTypes) && !empty($fieldValues[$fieldPair->formField])) {
                        $acfFieldData[$key]['key'] = $fieldPair->acfField;
                        if ($acf['multiple'] == 1) {
                            $acfFieldData[$key]['value'] = explode(',', $fieldValues[$fieldPair->formField]);
                            $acfFieldData[$key]['name'] = $acf['name'];
                        } else {
                            $acfFieldData[$key]['value'] = $fieldValues[$fieldPair->formField];
                            $acfFieldData[$key]['name'] = $acf['name'];
                        }
                    }
                }
            }
        }
        return $acfFieldData;
    }

    private function acfFileMapping($acfMapField, $fieldValues, $acfFields, $entryID, $post_id)
    {
        $fileTypes = ['file', 'image'];
        $fileUploadHandle = new WpFileHandler($this->_formID);
        foreach ($acfMapField as  $key => $fieldPair) {
            foreach ($acfFields as $acf) {
                if (property_exists($fieldPair, "acfFileUpload")) {

                    if ($acf['key'] == $fieldPair->acfFileUpload && in_array($acf['type'], $fileTypes)) {
                        if (!empty($fieldValues[$fieldPair->formField])) {
                            $attachMentId = $fileUploadHandle->singleFileMoveWpMedia($entryID, $fieldValues[$fieldPair->formField], $post_id);
                            if (!empty($attachMentId)) {
                                $exists = metadata_exists('post', $post_id, '_' . $acf['name']);
                                if ($exists == false) {
                                    update_post_meta($post_id, '_' . $acf['name'], $acf['key']);
                                    update_post_meta($post_id, $acf['name'], json_encode($attachMentId));
                                } else {
                                    update_post_meta($post_id, $acf['name'], json_encode($attachMentId));
                                }
                            }
                        }

                    } else if ($acf['key'] == $fieldPair->acfFileUpload && $acf['type'] == 'gallery') {
                        
                        if (!empty($fieldValues[$fieldPair->formField])) {
                            $attachMentId = $fileUploadHandle->multiFileMoveWpMedia($entryID, $fieldValues[$fieldPair->formField], $post_id);
                            $exists = metadata_exists('post', $post_id, '_' . $acf['name']);
                            if (!empty($attachMentId)) {
                                if ($exists == false) {
                                    update_post_meta($post_id, '_' . $acf['name'], $acf['key']);
                                    update_post_meta($post_id, $acf['name'], $attachMentId);
                                } else {
                                    update_post_meta($post_id, $acf['name'], $attachMentId);
                                }
                            }
                        }

                    }
                }
            }
        }
    }

    public function execute(IntegrationHandler $integrationHandler, $integrationData, $fieldValues, $entryID, $logID)
    {
        $integrationDetails = is_string($integrationData->integration_details) ? json_decode($integrationData->integration_details) : $integrationData->integration_details;
        $taxonomy = new WpFileHandler($integrationData->form_id);
        $formManger = new FormManager($integrationData->form_id);
        $formFields = $formManger->getFields();

        $fieldData = [];

        $fieldData['comment_status'] = isset($integrationDetails->comment_status) ? $integrationDetails->comment_status : '';
        $fieldData['post_status'] = isset($integrationDetails->post_status) ? $integrationDetails->post_status : '';
        $fieldData['post_type'] = isset($integrationDetails->post_type) ? $integrationDetails->post_type : '';
        $fieldData['post_author'] = isset($integrationDetails->post_author) ? $integrationDetails->post_author : '';

        $exist_id = $fieldData['post_type'] . '_' . $entryID;
        $sql =  "SELECT * FROM `{$this->_wpdb->prefix}bitforms_form_entrymeta` WHERE `meta_key`='$exist_id' ";
        $exist_post_id =  $this->_wpdb->get_results($sql);
        $post_id = '';
        $acfFields = $this->getAcfFields($fieldData['post_type']);

        $taxanomyData = $taxonomy->taxonomyData($formFields, $fieldValues);

        if ($exist_post_id == []) {
            $post_id = wp_insert_post(['post_title' => '(no title)', 'post_content' => '']);
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


            $acfFieldMapping = $this->acfFieldMapping($integrationDetails->acf_map, $fieldValues, $acfFields);
            $this->acfFileMapping($integrationDetails->acf_file_map, $fieldValues, $acfFields, $entryID, $post_id);
            foreach ($acfFieldMapping as $data) {
                if (isset($data['key']) && isset($data['value'])) {
                    add_post_meta($post_id,  '_' . $data['name'], $data['key']);
                    add_post_meta($post_id,  $data['name'], $data['value']);
                }
            }
        } else {
            if (!empty($taxanomyData)) {
                foreach ($taxanomyData as $taxanomy) {
                    wp_set_post_terms($exist_post_id[0]->meta_value, $taxanomy['value'], $taxanomy['term'], false);
                }
            }
            $acfFieldMapping = $this->acfFieldMapping($integrationDetails->acf_map, $fieldValues, $acfFields);
            $this->acfFileMapping($integrationDetails->acf_file_map, $fieldValues, $acfFields, $entryID, $exist_post_id[0]->meta_value);
            foreach ($acfFieldMapping as  $data) {
                if (isset($data['key']) && isset($data['value'])) {
                    update_post_meta($exist_post_id[0]->meta_value, $data['name'], $data['value']);
                }
            }
            $updateData = $this->postFieldMapping($fieldData, $integrationDetails->post_map, $formFields, $fieldValues, $exist_post_id[0]->meta_value, $entryID);
            $updateData['ID'] = $exist_post_id[0]->meta_value;
            wp_update_post($updateData, true);
        }
    }
}
