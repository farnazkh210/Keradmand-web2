<?php

namespace BitCode\BitForm\Core\Util;

use BitCode\BitForm\Core\Util\MailConfig;
use BitCode\BitForm\Core\Util\FieldValueHandler;
use BitCode\BitForm\Core\Messages\EmailTemplateHandler;

final class MailNotifier
{
    public static function notify($notifyDetails, $formID, $fieldValue, $entryID)
    {
        $emailTemplateHandler = new EmailTemplateHandler($formID);
        if (is_string($notifyDetails->id)) {
            $mailTemplateID = json_decode($notifyDetails->id)->id;
            $mailTemplate = $emailTemplateHandler->getATemplate($mailTemplateID);
            if (!is_wp_error($mailTemplate)) {
                $mailTo = FieldValueHandler::validateMailArry($notifyDetails->to, $fieldValue);
                if (!empty($mailTo)) {
                    (new MailConfig())->sendMail();
                    $mailSubject = FieldValueHandler::replaceFieldWithValue($mailTemplate[0]->sub, $fieldValue);
                    $mailBody = FieldValueHandler::replaceFieldWithValue($mailTemplate[0]->body, $fieldValue);

                    $mailHeaders = array(
                        // "Content-Type: text/html; charset=UTF-8",
                    );
                    if (!empty($notifyDetails->replyto)) {
                        $mailReplyTo = FieldValueHandler::validateMailArry($notifyDetails->replyto, $fieldValue);
                        if (is_array($mailReplyTo)) {
                            foreach ($mailReplyTo as $key => $emailAddress) {
                                $mailHeaders[] = "Reply-To: " . explode('@', $emailAddress)[0] . "<" . sanitize_email($emailAddress) . ">";
                            }
                        } else {
                            $mailHeaders[] = "Reply-To: " . explode('@', $mailReplyTo)[0] . "<" . sanitize_email($mailReplyTo) . ">";
                        }
                    }
                    if (!empty($notifyDetails->bcc)) {
                        $mailBCC = FieldValueHandler::validateMailArry($notifyDetails->bcc, $fieldValue);
                        if (is_array($mailBCC)) {
                            foreach ($mailBCC as $key => $emailAddress) {
                                $mailHeaders[] = "Bcc: " . sanitize_email($emailAddress);
                            }
                        } else {
                            $mailHeaders[] = "Bcc: " . sanitize_email($mailBCC);
                        }
                    }
                    if (!empty($notifyDetails->cc)) {
                        $mailCC = FieldValueHandler::validateMailArry($notifyDetails->cc, $fieldValue);
                        if (is_array($mailCC)) {
                            foreach ($mailCC as $key => $emailAddress) {
                                $mailHeaders[] = "Cc: " . sanitize_email($emailAddress);
                            }
                        } else {
                            $mailHeaders[] = "Cc: " . sanitize_email($mailCC);
                        }
                    }
                    if (!empty($notifyDetails->from)) {
                        $mailFrom = FieldValueHandler::validateMailArry($notifyDetails->from, $fieldValue);
                        $fromName = !empty($notifyDetails->fromName) ? $notifyDetails->fromName : explode('@', $mailFrom[0])[0];
                        $mailHeaders[] = "FROM: $fromName " . "<" . sanitize_email($mailFrom[0]) . ">";
                    }
                    $attachments = [];
                    if (!empty($notifyDetails->attachment)) {
                        $files = $notifyDetails->attachment;
                        $fileBasePath = BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . $formID . DIRECTORY_SEPARATOR . $entryID . DIRECTORY_SEPARATOR;
                        if (is_array($files)) {
                            foreach ($files as $file) {
                                if (isset($fieldValue[$file])) {
                                    if (is_array($fieldValue[$file])) {
                                        foreach ($fieldValue[$file] as $singleFile) {
                                            if (\is_readable("{$fileBasePath}{$singleFile}")) {
                                                $attachments[] = "{$fileBasePath}{$singleFile}";
                                            }
                                        }
                                    } elseif (\is_readable("{$fileBasePath}{$fieldValue[$file]}")) {
                                        $attachments[] = "{$fileBasePath}{$fieldValue[$file]}";
                                    }
                                }
                            }
                        } else if (isset($fieldValue[$files])) {
                            if (is_array($fieldValue[$files])) {
                                foreach ($fieldValue[$files] as $singleFile) {
                                    if (\is_readable("{$fileBasePath}{$singleFile}")) {
                                        $attachments[] = "{$fileBasePath}{$singleFile}";
                                    }
                                }
                            } elseif (\is_readable("{$fileBasePath}{$fieldValue[$files]}")) {
                                $attachments[] = "{$fileBasePath}{$fieldValue[$files]}";
                            }
                        }
                    }
                    $mailBody = stripcslashes($mailBody);
                    $mailSubject = stripcslashes($mailSubject);
                    add_filter('wp_mail_content_type', [self::class, 'filterMailContentType']);
                    $status = wp_mail($mailTo, $mailSubject, $mailBody, $mailHeaders, $attachments);
                    if (!$status) {
                        wp_mail($mailTo, $mailSubject, $mailBody, $mailHeaders);
                    }
                    remove_filter('wp_mail_content_type', [self::class, 'filterMailContentType']);
                }
            }
        }
    }

    public static function filterMailContentType()
    {
        return 'text/html; charset=UTF-8';
    }
}
