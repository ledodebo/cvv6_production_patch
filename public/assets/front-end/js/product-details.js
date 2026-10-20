"use strict";

$(document).ready(function () {
    const $stickyElement = $(".bottom-sticky");
    const $offsetElement = $(".product-details-shipping-details");

    $(window).on("scroll", function () {
        const elementOffset = $offsetElement?.offset()?.top;
        const scrollTop = $(window).scrollTop();

        if (scrollTop >= elementOffset) {
            $stickyElement.addClass("stick");
            $(".floating-btn-grp").removeClass("style-2");
        } else {
            $stickyElement.removeClass("stick");
            $(".floating-btn-grp").addClass("style-2");
        }
    });
});

$(document).ready(function () {
    // Constants
    const DESKTOP_BREAKPOINT = 767;
    const ANIMATION_DELAY = 150;

    // Cache selectors
    const $window = $(window);
    const $stickyTop = $('.product-details-sticky-top');
    const $stickySection = $('.product-details-sticky');

    function bindStickyHover() {
        if ($stickySection.hasClass('multi-variation-product')) {
            $stickySection.hover(
                function () {
                    $stickyTop.stop(true, true).delay(ANIMATION_DELAY).slideDown();
                },
                function () {
                    $stickyTop.stop(true, true).delay(ANIMATION_DELAY).slideUp();
                }
            );
        }
    }

    function unbindStickyHover() {
        $stickySection.off('mouseenter mouseleave');
        $stickyTop.stop(true, true).hide();
    }

    function handleBreakpoint() {
        const windowWidth = $window.width();

        if (windowWidth > DESKTOP_BREAKPOINT) {
            bindStickyHover();
        } else {
            unbindStickyHover();
        }
    }

    let resizeTimeout;
    $window.on('resize', function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(handleBreakpoint, 100);
    });

    handleBreakpoint();
});


// Select the element
const targetElement = document.querySelector('.product-add-and-buy-section-parent');

// Define the action to take when the element is in the viewport
function handleIntersect(entries) {
    let getHeight = $('.product-details-sticky-bottom').height();
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            $('.product-details-sticky').removeClass('active');
            $('.floating-btn-grp').removeClass('has-product-details-sticky');
            $('body').css('padding-bottom', "0px");
        } else {
            $('.product-details-sticky').addClass('active');
            $('.floating-btn-grp').addClass('has-product-details-sticky');
            $('body').css('padding-bottom', `calc(${getHeight}px + 2rem)`);
        }
    });
}

// Create an intersection observer
const observer = new IntersectionObserver(handleIntersect, {
    root: null, // Use the viewport as the root
    threshold: 0.1 // Trigger when 10% of the element is visible
});

// Start observing the target element
if (targetElement) {
    observer.observe(targetElement);
}

cartQuantityInitialize();
getVariantPrice(".add-to-cart-details-form");
getVariantPrice(".add-to-cart-sticky-form");

$(".view_more_button").on("click", function () {
    loadReviewOnDetailsPage();
});

let loadReviewCount = 1;

function loadReviewOnDetailsPage() {
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
        },
    });
    $.ajax({
        type: "post",
        url: $("#route-review-list-product").data("url"),
        data: {
            product_id: $("#products-details-page-data").data("id"),
            offset: loadReviewCount,
        },
        success: function (data) {
            $("#product-review-list").append(data.productReview);
            if (data.checkReviews == 0) {
                $(".view_more_button").removeClass("d-none").addClass("d-none");
            } else {
                $(".view_more_button").addClass("d-none").removeClass("d-none");
            }

            $(".show-instant-image").on("click", function () {
                let link = $(this).data("link");
                showInstantImage(link);
            });
        },
    });
    loadReviewCount++;
}

$("#chat-form").on("submit", function (e) {
    e.preventDefault();

    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
        },
    });

    $.ajax({
        type: "post",
        url: $("#route-messages-store").data("url"),
        data: $("#chat-form").serialize(),
        success: function (respons) {
            toastr.success($("#message-send-successfully").data("text"), {
                CloseButton: true,
                ProgressBar: true,
            });
            $("#chat-form").trigger("reset");
        },
    });
});

function renderFocusPreviewImageByColor() {
    $(".focus-preview-image-by-color").on("click", function () {
        let id = $(this).data("colorid");
        $(`.color-variants-${id}`).click();
    });
}
renderFocusPreviewImageByColor();
document.addEventListener("DOMContentLoaded", function () {
    function activateTab(tabId) {
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active', 'show'));

        const navLink = document.querySelector(`.nav-link[href="${tabId}"]`);
        if (navLink) {
            navLink.classList.add('active');
        }
        const tabPane = document.querySelector(tabId);
        if (tabPane) {
            tabPane.classList.add('active', 'show');
        }
    }
    document.querySelectorAll('a[href="#reviews"]').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            activateTab('#reviews');
            document.querySelector('#reviews').scrollIntoView({ behavior: 'smooth' });
        });
    });

    if (window.location.hash === '#reviews') {
        activateTab('#reviews');
    }
});

// Country select change handler on product details, quick view, and sticky bar
$(document).off('change.countrySelect', '.product-country-select').on('change.countrySelect', '.product-country-select', function (e) {
    let selectedOption = $(this).find('option:selected');
    let targetRegion = selectedOption.data('target-region') || $(this).val();
    let pillProductId = selectedOption.data('product-id');
    let pillProductName = selectedOption.data('product-name');
    let pillRoute = selectedOption.data('route');
    let container = $(this).closest('#digital-product-extensions-container, #sticky-digital-product-extensions-container, #quickViewModal, .quickview-content, .modal-content, form');
    if (!container.length) container = $(this).closest('form');
    if (!container.length) container = $('#digital-product-extensions-container');

    // 1. Hide all package wrappers in this container, show selected country
    container.find('.country-packages-wrapper').addClass('d-none');
    let activeWrapper = container.find('.country-packages-wrapper[data-region="' + targetRegion + '"]');
    activeWrapper.removeClass('d-none');

    // 2. Disable radios in hidden wrappers, enable in active wrapper
    container.find('.country-packages-wrapper input[name="variant_key"]').prop('disabled', true);
    activeWrapper.find('input[name="variant_key"]').prop('disabled', false);

    // 3. Update Product ID in cart form
    if (pillProductId) {
        $('form.add-to-cart-details-form, form#add-to-cart-sticky-form, form.addToCartDynamicForm').find('input[name="id"]').val(pillProductId);
    }
    if (pillRoute && window.history && window.history.replaceState) {
        window.history.replaceState(null, '', pillRoute);
    }

    // 4. Select the first radio in active wrapper and trigger price update
    let firstRadio = activeWrapper.find('input[name="variant_key"]').first();
    if (firstRadio.length) {
        firstRadio.prop('checked', true).trigger('change');
    }

    // 5. Sync with sticky bar / main page if present and not inside modal
    if (!container.closest('.modal').length) {
        let isSticky = $(this).closest('#sticky-digital-product-extensions-container, .product-details-sticky').length > 0;
        let otherContainer = isSticky ? $('#digital-product-extensions-container') : $('#sticky-digital-product-extensions-container, .product-details-sticky');
        if (otherContainer.length) {
            let otherSelect = otherContainer.find('.product-country-select');
            if (otherSelect.length && otherSelect.val() !== targetRegion) {
                otherSelect.val(targetRegion);
            }
            otherContainer.find('.country-packages-wrapper').addClass('d-none');
            let otherWrapper = otherContainer.find('.country-packages-wrapper[data-region="' + targetRegion + '"]');
            otherWrapper.removeClass('d-none');
            otherContainer.find('.country-packages-wrapper input[name="variant_key"]').prop('disabled', true);
            otherWrapper.find('input[name="variant_key"]').prop('disabled', false);
            let otherFirstRadio = otherWrapper.find('input[name="variant_key"]').first();
            if (otherFirstRadio.length) {
                otherFirstRadio.prop('checked', true);
            }
        }
    }
});

// Variant selection change handler
$(document).off('change.variantKey', 'input[type="radio"][name="variant_key"]').on('change.variantKey', 'input[type="radio"][name="variant_key"]', function () {
    let price = $(this).data('price');
    let rawPrice = $(this).data('raw-price');
    let subTitle = $(this).data('title');
    let country = $(this).data('country');
    let newProductId = $(this).data('product-id');
    let variantKey = $(this).val();

    if (newProductId) {
        $('form.add-to-cart-details-form, form#add-to-cart-sticky-form, form.addToCartDynamicForm').find('input[name="id"]').val(newProductId);
    }
    if (price) {
        $('.discounted-unit-price').html(price);
        $('.product-details-chosen-price-amount').html(price);
        $('.product-bottom-section-price').html(price);
        $('.product-price').html(price);
        $('.product-details-sticky .discounted-unit-price, .product-details-sticky .discounted_price').html(price);
    }
    if (subTitle) {
        let fullTitle = country ? (subTitle + ' (' + country + ')') : subTitle;
        $('.selected-subscription-title').text('- ' + fullTitle).show();
    }
    if (variantKey) {
        $('.product-generated-variation-code').val(variantKey);
    }

    // Sync with sticky bar counterpart
    let currentId = $(this).attr('id');
    if (currentId && !currentId.startsWith('sticky-')) {
        let stickyRadio = $('#sticky-' + currentId);
        if (stickyRadio.length && !stickyRadio.is(':checked')) {
            stickyRadio.prop('checked', true);
        }
    } else if (currentId && currentId.startsWith('sticky-')) {
        let mainId = currentId.replace('sticky-', '');
        let mainRadio = $('#' + mainId);
        if (mainRadio.length && !mainRadio.is(':checked')) {
            mainRadio.prop('checked', true);
        }
    }
});

