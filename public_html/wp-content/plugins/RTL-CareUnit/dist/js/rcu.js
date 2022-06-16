/**
 * RCU Admin Page Scripts
 *
 * @category   Scripts
 * @version    1.0.3
 * @since      1.0.0
 */

jQuery(document).ready(function ($) {
    $.ajaxInProgress = false;

    /**
     * increase jQuery Ajax Timeout
     */
    $.ajaxSetup({
        timeout: 120000
    });

    /**
     * Handle Accordion
     */
    $('.RCU-Accordion').on('click', '.RCU-Accordion-Trigger', function () {
        let isExpanded = ('true' === $(this).attr('aria-expanded'));

        if (isExpanded) {
            $(this).attr('aria-expanded', 'false');
            $('#' + $(this).attr('aria-controls')).attr('hidden', true);
        } else {
            $(this).attr('aria-expanded', 'true');
            $('#' + $(this).attr('aria-controls')).attr('hidden', false);
        }
    });

    /**
     * Show Loading On Tab Click
     */
    $('.RCU-Header-Tabs a.RCU-Header-Tab').click(function () {
        if ($(this).attr('href') === '#') {
            return;
        }

        rcuPageLoading('در حال بارگذاری صفحه', true);
    });

    /**
     * Show or Hide Page Loading
     *
     * @param show
     * @param text
     */
    function rcuPageLoading(text = 'لطفا منتظر بمانید', show = true) {
        if (show) {
            $('.RCU-Page-Loading').fadeIn();
            $('.RCU-Page-Loading .RCU-Fader').text(text + ' ...');
        } else {
            $('.RCU-Page-Loading').fadeOut();
            $('.RCU-Page-Loading .RCU-Fader').text(text + ' ...');
        }
    }

    /**
     * Return Only JSON Object from Specified String
     *
     * @param str
     *
     * @returns {null|any}
     */
    function extractJSON(str) {
        let firstOpen, firstClose, candidate;

        firstOpen = str.indexOf('{', 1);

        do {
            firstClose = str.lastIndexOf('}');

            if (firstClose <= firstOpen) {
                return null;
            }

            do {
                candidate = str.substring(firstOpen, firstClose + 1);

                try {
                    return JSON.parse(candidate);
                } catch (e) {
                }

                firstClose = str.substr(0, firstClose).lastIndexOf('}');
            } while (firstClose > firstOpen);

            firstOpen = str.indexOf('{', firstOpen + 1);
        } while (firstOpen !== -1);
    }

    /**
     * Handle Ping Button Ajax
     */
    $(document).on('click', '.RCU-Ping-BTN', function () {
        if ($.ajaxInProgress === true || typeof RCU === undefined) {
            return;
        }

        $.ajaxInProgress = true;
        $.pingNotify     = $('.PingNotify');

        $.pingNotify.slideUp('slow');

        rcuPageLoading('در حال همگام سازی با سرور', true);

        $.ajax({
            method: 'POST',
            url   : RCU.URL,
            data  : {
                'action': 'rcuAjaxPing',
                'nonce' : RCU.Nonce
            }
        }).done(function (response) {
            if (typeof response === 'string') {
                response = extractJSON(response);
            }

            if (typeof response?.success === 'undefined' || typeof response?.data === 'undefined') {
                return;
            }

            $.pingNotify.removeClass(['notice-success', 'notice-error']);
            $.pingNotify.addClass(response.success ? 'notice-success' : 'notice-error');

            $.pingNotify.text(response?.data?.message);
            $.pingNotify.slideDown('slow');

            if (response.success === true) {
                $('.LastPing').attr('title', response?.data?.title).text(response?.data?.elapsed);
            }

        }).always(function () {
            $.ajaxInProgress = false;
            rcuPageLoading('', false);
        });
    });

    /**
     * Handle Setting Area Button Click
     */
    $(document).on('submit', '#rcuAutoUpdateForm, #rcuHidePluginForm', function () {
        rcuPageLoading('در حال اعمال تنظیمات', true);
    });

    /**
     * Handle Diagnose Button Ajax
     */
    $(document).on('click', '.Diagnose-Wrapper .RCU-Diagnose-BTN', async function () {
        if ($.ajaxInProgress === true || typeof RCU === undefined) {
            return;
        }

        let DiagnoseBTN   = $(".RCU-Diagnose-BTN");
        let DiagnoseBadge = $('.RCU-Accordion-Trigger .badge');

        $.ajaxInProgress = true;
        DiagnoseBTN.prop("disabled", true);

        DiagnoseBadge.attr('class', 'badge orange');
        DiagnoseBadge.text('تست نشده');

        await DiagnoseConTest();
        await DiagnoseHandShake();
        await DiagnosePing();
        await DiagnoseFileAccess();

        $.ajaxInProgress = false;
        DiagnoseBTN.prop("disabled", false);
    });

    /**
     * Connection Test
     */
    async function DiagnoseConTest() {
        let DiagnoseBadge = $('.RCU-Accordion-Trigger[aria-controls="RCU-Accordion-ConTest"] .badge');

        DiagnoseBadge.attr('class', 'badge spinner');
        DiagnoseBadge.text('');

        await $.ajax({
            method: 'POST',
            url   : RCU.URL,
            data  : {
                'action': 'rcuAjaxDiagnoseConTest',
                'nonce' : RCU.Nonce
            }
        }).done((response) => {
            if (typeof response === 'string') {
                response = extractJSON(response);
            }

            if (typeof response?.success === 'undefined' || typeof response?.data === 'undefined') {
                return;
            }

            $('textarea#ConTestResult').text(response.data.message);

            if (response.success !== true) {
                DiagnoseBadge.attr('class', 'badge red');
                DiagnoseBadge.text('خطا');
            } else {
                DiagnoseBadge.attr('class', 'badge green');
                DiagnoseBadge.text('موفق');
            }
        }).fail(function () {
            DiagnoseBadge.attr('class', 'badge red');
            DiagnoseBadge.text('خطا');
        });
    }

    /**
     * Handshake Test
     */
    async function DiagnoseHandShake() {
        let DiagnoseBadge = $('.RCU-Accordion-Trigger[aria-controls="RCU-Accordion-HandShake"] .badge');

        DiagnoseBadge.attr('class', 'badge spinner');
        DiagnoseBadge.text('');

        await $.ajax({
            method: 'POST',
            url   : RCU.URL,
            data  : {
                'action': 'rcuAjaxDiagnoseHandShake',
                'nonce' : RCU.Nonce
            }
        }).done((response) => {
            if (typeof response === 'string') {
                response = extractJSON(response);
            }

            if (typeof response?.success === 'undefined' || typeof response?.data === 'undefined') {
                return;
            }

            $('textarea#HandShakeResult').text(response.data.message);

            if (response.success !== true) {
                DiagnoseBadge.attr('class', 'badge red');
                DiagnoseBadge.text('خطا');
            } else {
                DiagnoseBadge.attr('class', 'badge green');
                DiagnoseBadge.text('موفق');
            }
        }).fail(function () {
            DiagnoseBadge.attr('class', 'badge red');
            DiagnoseBadge.text('خطا');
        });
    }

    /**
     * Ping Test
     */
    async function DiagnosePing() {
        let DiagnoseBadge = $('.RCU-Accordion-Trigger[aria-controls="RCU-Accordion-Ping"] .badge');

        DiagnoseBadge.attr('class', 'badge spinner');
        DiagnoseBadge.text('');

        await $.ajax({
            method: 'POST',
            url   : RCU.URL,
            data  : {
                'action': 'rcuAjaxDiagnosePing',
                'nonce' : RCU.Nonce
            }
        }).done((response) => {
            if (typeof response === 'string') {
                response = extractJSON(response);
            }

            if (typeof response?.success === 'undefined' || typeof response?.data === 'undefined') {
                return;
            }

            $('textarea#PingResult').text(response.data.message);

            if (response.success !== true) {
                DiagnoseBadge.attr('class', 'badge red');
                DiagnoseBadge.text('خطا');
            } else {
                DiagnoseBadge.attr('class', 'badge green');
                DiagnoseBadge.text('موفق');
            }
        }).fail(function () {
            DiagnoseBadge.attr('class', 'badge red');
            DiagnoseBadge.text('خطا');
        });
    }

    /**
     * File Access Test
     */
    async function DiagnoseFileAccess() {
        let DiagnoseBadge = $('.RCU-Accordion-Trigger[aria-controls="RCU-Accordion-FileAccess"] .badge');

        DiagnoseBadge.attr('class', 'badge spinner');
        DiagnoseBadge.text('');

        await $.ajax({
            method: 'POST',
            url   : RCU.URL,
            data  : {
                'action': 'rcuAjaxDiagnoseFileAccess',
                'nonce' : RCU.Nonce
            }
        }).done((response) => {
            if (typeof response === 'string') {
                response = extractJSON(response);
            }

            if (typeof response?.success === 'undefined' || typeof response?.data === 'undefined') {
                return;
            }

            $('textarea#FileAccessResult').text(response.data.message);

            if (response.success !== true) {
                DiagnoseBadge.attr('class', 'badge red');
                DiagnoseBadge.text('خطا');
            } else {
                DiagnoseBadge.attr('class', 'badge green');
                DiagnoseBadge.text('موفق');
            }
        }).fail(function () {
            DiagnoseBadge.attr('class', 'badge red');
            DiagnoseBadge.text('خطا');
        });
    }
});