<script>
    "use strict";

    window.digitalCardCodeMemoryCache = window.digitalCardCodeMemoryCache || {};

    function getDigitalCsrfToken() {
        return $('meta[name="_token"]').attr('content') ||
               $('meta[name="csrf-token"]').attr('content') ||
               $('input[name="_token"]').val() ||
               '{{ csrf_token() }}';
    }

    window.revealDigitalCardCode = function(btnElement, codeId, revealUrl) {
        const $btn = $(btnElement);
        const $voucherCard = $btn.closest('.digital-voucher-card');
        const $codeDisplay = $voucherCard.find('.voucher-code-display');
        const $pinDisplay = $voucherCard.find('.voucher-pin-display');
        const maskedCode = $codeDisplay.data('masked') || '••••-••••-••••';

        // Toggle back to masked if currently revealed
        if ($btn.data('revealed') === true) {
            $codeDisplay.text(maskedCode);
            if ($pinDisplay.length) {
                $pinDisplay.text('••••');
            }
            $btn.data('revealed', false);
            $btn.html('<i class="tio-invisible me-1"></i> ' + ($btn.data('reveal-text') || 'Reveal'));
            return;
        }

        // Check memory cache first (prevents redundant network requests)
        if (window.digitalCardCodeMemoryCache[codeId]) {
            const cached = window.digitalCardCodeMemoryCache[codeId];
            $codeDisplay.text(cached.code);
            if ($pinDisplay.length && cached.pin) {
                $pinDisplay.text(cached.pin);
            }
            $btn.data('revealed', true);
            $btn.html('<i class="tio-hidden me-1"></i> ' + ($btn.data('hide-text') || 'Hide'));
            return;
        }

        // Fetch via authenticated reveal endpoint
        $btn.prop('disabled', true);
        $.ajax({
            url: revealUrl,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': getDigitalCsrfToken(),
                'Accept': 'application/json'
            },
            data: {
                _token: getDigitalCsrfToken(),
            },
            success: function (res) {
                if (res.status === 1 && res.code) {
                    window.digitalCardCodeMemoryCache[codeId] = {
                        code: res.code,
                        pin: res.pin || null,
                    };
                    $codeDisplay.text(res.code);
                    if ($pinDisplay.length && res.pin) {
                        $pinDisplay.text(res.pin);
                    }
                    $btn.data('revealed', true);
                    $btn.html('<i class="tio-hidden me-1"></i> ' + ($btn.data('hide-text') || 'Hide'));
                } else {
                    const msg = res.message || 'Unable to reveal card code.';
                    if (typeof toastr !== 'undefined') {
                        toastr.error(msg);
                    } else {
                        alert(msg);
                    }
                }
            },
            error: function (xhr) {
                let msg = 'Failed to reveal digital code. Please verify you are logged in.';
                if (xhr.status === 419) {
                    msg = 'Session expired. Please refresh the page.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (typeof toastr !== 'undefined') {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    };

    window.copyDigitalCardCode = function(btnElement, codeId, revealUrl) {
        const $btn = $(btnElement);
        const originalHtml = $btn.html();

        function showCopySuccess() {
            $btn.html('<i class="tio-checkmark-circle-outlined me-1"></i> ' + ($btn.data('copied-text') || 'Copied!'));
            $btn.addClass('btn-success').removeClass('btn-outline-primary btn-primary');
            if (typeof toastr !== 'undefined') {
                toastr.success('Card code copied to clipboard!');
            }
            setTimeout(() => {
                $btn.html(originalHtml);
                $btn.removeClass('btn-success');
            }, 2000);
        }

        function doClipboardCopy(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopySuccess();
                }).catch(() => {
                    fallbackExecCopy(text);
                });
                return;
            }
            fallbackExecCopy(text);
        }

        function fallbackExecCopy(text) {
            try {
                const tempInput = document.createElement('textarea');
                tempInput.value = text;
                tempInput.style.position = 'fixed';
                tempInput.style.left = '-9999px';
                tempInput.style.top = '0';
                tempInput.style.opacity = '0';
                document.body.appendChild(tempInput);
                tempInput.focus();
                tempInput.select();
                tempInput.setSelectionRange(0, 99999);
                const successful = document.execCommand('copy');
                document.body.removeChild(tempInput);
                if (successful) {
                    showCopySuccess();
                    return;
                }
            } catch (e) {}

            window.prompt("Press Ctrl+C to copy your card code:", text);
            showCopySuccess();
        }

        // If code is already cached in memory, copy immediately
        if (window.digitalCardCodeMemoryCache[codeId]) {
            doClipboardCopy(window.digitalCardCodeMemoryCache[codeId].code);
            return;
        }

        // Fetch via authenticated reveal endpoint, reveal it, and copy
        $btn.prop('disabled', true);
        $.ajax({
            url: revealUrl,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': getDigitalCsrfToken(),
                'Accept': 'application/json'
            },
            data: {
                _token: getDigitalCsrfToken(),
            },
            success: function (res) {
                if (res.status === 1 && res.code) {
                    window.digitalCardCodeMemoryCache[codeId] = {
                        code: res.code,
                        pin: res.pin || null,
                    };
                    const $voucherCard = $btn.closest('.digital-voucher-card');
                    const $codeDisplay = $voucherCard.find('.voucher-code-display');
                    const $pinDisplay = $voucherCard.find('.voucher-pin-display');
                    const $revealBtn = $voucherCard.find('.btn-reveal-code');
                    if ($codeDisplay.length) {
                        $codeDisplay.text(res.code);
                    }
                    if ($pinDisplay.length && res.pin) {
                        $pinDisplay.text(res.pin);
                    }
                    if ($revealBtn.length) {
                        $revealBtn.data('revealed', true);
                        $revealBtn.html('<i class="tio-hidden me-1"></i> ' + ($revealBtn.data('hide-text') || 'Hide'));
                    }
                    doClipboardCopy(res.code);
                } else {
                    const msg = res.message || 'Unable to copy card code.';
                    if (typeof toastr !== 'undefined') {
                        toastr.error(msg);
                    } else {
                        alert(msg);
                    }
                }
            },
            error: function (xhr) {
                let msg = 'Failed to fetch card code for copying.';
                if (xhr.status === 419) msg = 'Session expired. Please refresh the page.';
                if (typeof toastr !== 'undefined') toastr.error(msg);
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    };

    window.printDigitalVouchers = function() {
        const fetchPromises = [];
        $('.digital-voucher-card').each(function () {
            const $card = $(this);
            const codeId = $card.data('code-id');
            const revealUrl = $card.data('reveal-url');
            if (!codeId || !revealUrl) return;

            if (window.digitalCardCodeMemoryCache[codeId]) {
                const cached = window.digitalCardCodeMemoryCache[codeId];
                $card.find('.voucher-code-display').text(cached.code);
                if (cached.pin) {
                    $card.find('.voucher-pin-display').text(cached.pin);
                }
                $card.find('.btn-reveal-code').data('revealed', true).html('<i class="tio-hidden me-1"></i> Hide');
                return;
            }

            const p = $.ajax({
                url: revealUrl,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getDigitalCsrfToken(),
                    'Accept': 'application/json'
                },
                data: { _token: getDigitalCsrfToken() }
            }).then(function (res) {
                if (res.status === 1 && res.code) {
                    window.digitalCardCodeMemoryCache[codeId] = { code: res.code, pin: res.pin || null };
                    $card.find('.voucher-code-display').text(res.code);
                    if (res.pin) {
                        $card.find('.voucher-pin-display').text(res.pin);
                    }
                    $card.find('.btn-reveal-code').data('revealed', true).html('<i class="tio-hidden me-1"></i> Hide');
                }
            });
            fetchPromises.push(p);
        });

        if (fetchPromises.length > 0) {
            $.when.apply($, fetchPromises).always(function () {
                setTimeout(() => window.print(), 300);
            });
        } else {
            window.print();
        }
    };
</script>
