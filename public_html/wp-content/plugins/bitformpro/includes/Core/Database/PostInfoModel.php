<?php

namespace BitCode\BitFormPro\Core\Database;

use BitCode\BitForm\Core\Database\Model;


class PostInfoModel extends Model
{
  protected static $table = 'posts';

  public function getAllPosts($postType = '', $orderBy = 'ID', $order = 'DESC', $postStatus = 'all')
  {
    $condition = [];
    if (!empty($postType)) $condition['post_type'] = $postType;
    if ($postStatus != 'all') $condition['post_status'] = $postStatus;

    $result =  $this->get(
      array(
        'ID',
        'post_author',
        'post_date',
        'post_type',
        'post_status',
        'post_name',
        'post_title',
        'post_modified',
        'comment_count'
      ),
      $condition,
      null,
      null,
      $orderBy,
      $order
    );
    if (isset($result->errors['result_empty'])) {
      return [];
    }
   return $result;
  }
}
