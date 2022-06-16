<?php

namespace BitCode\BitFormPro\Core\Util;

class WpFileHandler
{

  private $_formID;
  private $_uploadDirInfo;
  private $_wpUploadbaseDir;
  private $_bitformsUploadBaseDir;

  public function __construct($fromID)
  {
    $this->_formID = $fromID;
    $this->_uploadDirInfo = wp_upload_dir();
    $this->_wpUploadbaseDir = $this->_uploadDirInfo['basedir'];
    $this->_bitformsUploadBaseDir = $this->_wpUploadbaseDir . DIRECTORY_SEPARATOR .  'bitforms';
  }
  public function uploadFeatureImg($file, $entryID, $postID)
  {
    require_once(ABSPATH . 'wp-load.php');
    $_upload_dir  = $this->_bitformsUploadBaseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $this->_formID . DIRECTORY_SEPARATOR . $entryID;
    $files = is_string($file) ? json_decode($file) : $file;
    $IMGFileName = $files[0];
    $IMGFilePath = $_upload_dir . DIRECTORY_SEPARATOR . $IMGFileName;
    if (file_exists($IMGFilePath)) {
      //prepare upload image to WordPress Media Library
      $upload = wp_upload_bits($IMGFileName, null, file_get_contents($IMGFilePath, FILE_USE_INCLUDE_PATH));
      // check and return file type
      $imageFile = $upload['file'];
      $wpFileType = wp_check_filetype($imageFile, null);
      // Attachment attributes for file
      $attachment = array(
        'post_mime_type' => $wpFileType['type'],
        'post_title' => sanitize_file_name($IMGFileName),  // sanitize and use image name as file name
        'post_content' => '',
        'post_status' => 'inherit'
      );
      // insert and return attachment id
      $attachmentId = wp_insert_attachment($attachment, $imageFile, $postID);
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      // insert and return attachment metadata
      $attachmentData = wp_generate_attachment_metadata($attachmentId, $imageFile);
      // update and return attachment metadata
      wp_update_attachment_metadata($attachmentId, $attachmentData);
      // finally, associate attachment id to post id
      set_post_thumbnail($postID, $attachmentId);
    }
  }

  public function  singleFileMoveWpMedia($entryID, $fileValues, $post_id)
  {
    $_upload_dir  = $this->_bitformsUploadBaseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $this->_formID . DIRECTORY_SEPARATOR . $entryID;
    require_once(ABSPATH . 'wp-load.php');
    $files = is_string($fileValues) ? json_decode($fileValues) : $fileValues;

    $IMGFileName = $files[0];
    $IMGFilePath = $_upload_dir . DIRECTORY_SEPARATOR . $IMGFileName;
    if (file_exists($IMGFilePath)) {
      //prepare upload image to WordPress Media Library
      $upload = wp_upload_bits($IMGFileName, null, file_get_contents($IMGFilePath, FILE_USE_INCLUDE_PATH));

      $imageFile = $upload['file'];
      // echo $imageFile;
      $wpFileType = wp_check_filetype($imageFile, null);
      // Attachment attributes for file
      $attachment = array(
        'post_mime_type' => $wpFileType['type'],
        'post_title' => sanitize_file_name($IMGFileName),  // sanitize and use image name as file name
        'post_content' => '',
        'post_status' => 'inherit',
        'post_parent' => $post_id
      );
      // insert and return attachment id
      $attachmentId = wp_insert_attachment($attachment, $imageFile, $post_id);
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      // insert and return attachment metadata
      $attachmentData = wp_generate_attachment_metadata($attachmentId, $imageFile);
      wp_update_attachment_metadata($attachmentId, $attachmentData);
      return $attachmentId;
    }
  }

  public function multiFileMoveWpMedia($entryID, $fileValues, $post_id)
  {
    $_upload_dir  = $this->_bitformsUploadBaseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $this->_formID . DIRECTORY_SEPARATOR . $entryID;
    require_once(ABSPATH . 'wp-load.php');
    $attachMentId = [];
    $files = is_string($fileValues) ? json_decode($fileValues) : $fileValues;
    foreach ($files as $file) {
      $IMGFileName = $file;
      $IMGFilePath = $_upload_dir . DIRECTORY_SEPARATOR . $IMGFileName;
      if (file_exists($IMGFilePath)) {
        //prepare upload image to WordPress Media Library
        $upload = wp_upload_bits($IMGFileName, null, file_get_contents($IMGFilePath, FILE_USE_INCLUDE_PATH));

        $imageFile = $upload['file'];
        // echo $imageFile;
        $wpFileType = wp_check_filetype($imageFile, null);
        // Attachment attributes for file
        $attachment = array(
          'post_mime_type' => $wpFileType['type'],
          'post_title' => sanitize_file_name($IMGFileName),  // sanitize and use image name as file name
          'post_content' => '',
          'post_status' => 'inherit',
          'post_parent' => $post_id
        );
        // insert and return attachment id
        $attachmentId = wp_insert_attachment($attachment, $imageFile, $post_id);
        // $attachMentId[]=$attachmentId;
        array_push($attachMentId, $attachmentId);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        // insert and return attachment metadata
        $attachmentData = wp_generate_attachment_metadata($attachmentId, $imageFile);
        // update and return attachment metadata
        wp_update_attachment_metadata($attachmentId, $attachmentData);
      }
    }
    return $attachMentId;
  }

  public function taxonomyData($formFields, $fieldValues)
  {
    $taxanomyData = [];
    foreach ($formFields as $fieldKey => $field) {
      if (isset($field['customType'])) {
        if (isset($field['customType']->isTaxonomy) && !empty($fieldValues[$field['key']]) && isset($field['customType']->isHierarchical)) {
          if ($field['customType']->isTaxonomy === true) {
            if ($field['customType']->isHierarchical == true) {
              $taxanomyData[$fieldKey]['value'] = $fieldValues[$field['key']];
            } else {
              $slug = "";

              if (is_array($fieldValues[$field['key']])) {

                $allSlug = [];
                foreach ($fieldValues[$field['key']] as $key => $value) {
                  $exists = get_term_by('term_id',  $value, $field['customType']->filter->taxanomy);
                  if ($exists) $allSlug[$key] = $exists->slug;
                }
                $slug = implode(",", $allSlug);
              } else {

                $exists = get_term_by('term_id',  $fieldValues[$field['key']], $field['customType']->filter->taxanomy);
                if ($exists) $slug = $exists->slug;
              }

              $taxanomyData[$fieldKey]['value'] = $slug;
            }
            $taxanomyData[$fieldKey]['term'] = $field['customType']->filter->taxanomy;
          }
        }
      }
    }
    return $taxanomyData;
  }
}
