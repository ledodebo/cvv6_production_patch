<div class="bg-white product-details-sticky product-details-sticky-section pt-4 pt-md-3 pb-3 {{ $productDetails->variation && count(json_decode($productDetails->variation)) > 0 ? 'multi-variation-product' : '' }}">
    <div class="btn-circle product-details-sticky-collapse-btn d-md-none transition cursor-pointer shadow-sm position-absolute translate-middle top-0 left-50 justify-content-center align-items-center {{ $productDetails->variation && count(json_decode($productDetails->variation)) > 0 ? 'd-flex' : 'd-none' }}" style="--size: 34px">
        <i class="czi-arrow-up"></i>
    </div>

    <div class="container product-cart-option-container">
        <form class="add-to-cart-sticky-form addToCartDynamicForm">
            @csrf
            <input type="hidden" name="id" value="{{ $productDetails->id }}">
            <input type="hidden" name="position" value="bottom">
            <div class="product-details-sticky-top">
                <div class="border-bottom d-flex flex-column gap-3 mb-3 pb-3">
                    @if (count(json_decode($productDetails->colors)) > 0)
                    <div class="position-relative">
                        <h6 class="fs-14 mb-2 pb-2">
                            {{ translate('color')}}
                            <span class="text-muted font-weight-light product-details-sticky-color-name"></span>
                        </h6>
                        <div>
                            <ul class="list-inline checkbox-color m-0 px-0 flex-start gap-2">
                                @foreach (json_decode($productDetails->colors) as $key => $color)
                                    <li class="user-select-none">
                                        <input type="radio"
                                               id="sticky-{{ str_replace(' ', '', ($productDetails->id. '-color-'. str_replace('#','',$color))) }}"
                                               name="color" value="{{ $color }}"
                                               @if($key == 0) checked @endif>
                                        <label style="background: {{ $color }};"
                                               class="focus-preview-image-by-color shadow-border m-0"
                                               for="sticky-{{ str_replace(' ', '', ($productDetails->id. '-color-'. str_replace('#','',$color))) }}"
                                               data-toggle="tooltip"
                                               data-key="{{ str_replace('#','',$color) }}"
                                               data-colorid="preview-box-{{ str_replace('#','',$color) }}" data-title="{{ getColorNameByCode(code: $color) }}">
                                            <span class="outline"></span></label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif

                    <div id="sticky-digital-product-extensions-container">
                        @php
                            $stickyResolvedData = function_exists('resolveDigitalProductExtensionsAndSiblings')
                                ? resolveDigitalProductExtensionsAndSiblings($productDetails)
                                : [
                                    'extensions' => is_string($productDetails->digital_product_extensions) ? (json_decode($productDetails->digital_product_extensions, true) ?? []) : ($productDetails->digital_product_extensions ?? []),
                                    'variations' => $productDetails->digitalVariation ? $productDetails->digitalVariation->keyBy('variant_key') : collect(),
                                    'siblings_by_country' => collect(),
                                    'is_sibling_grouped' => false,
                                ];
                            $stickyExts = $stickyResolvedData['extensions'];
                            $stickyDigitalVariationsKeyed = $stickyResolvedData['variations'];
                            $stickySiblingsByCountry = $stickyResolvedData['siblings_by_country'];

                            $hasStickyCountryVars = is_array($stickyExts) && count($stickyExts) > 1;
                            if (is_array($stickyExts) && count($stickyExts) === 1) {
                                $firstK = array_key_first($stickyExts);
                                if ($firstK && !in_array(strtolower(trim($firstK)), ['package', 'default', 'options', 'variation', 'variations'])) {
                                    $hasStickyCountryVars = true;
                                }
                            }
                            $firstStickyRegion = is_array($stickyExts) ? array_key_first($stickyExts) : null;
                        @endphp
                        @if($productDetails['product_type'] == 'digital' && is_array($stickyExts) && count($stickyExts) > 0)
                            @if($hasStickyCountryVars)
                                <div class="sticky-country-selector-group mb-2">
                                    <label for="sticky-product-country-select" class="fs-14 mb-1 fw-semibold d-block">
                                        <span class="me-1">🌐</span>{{ translate('Country_/_Region') }}
                                    </label>
                                    <select id="sticky-product-country-select" class="form-control form-select product-country-select product-region-select-box mb-2">
                                        @foreach($stickyExts as $countryName => $extensionGroup)
                                            @php
                                                $countrySlug = Str::slug($countryName);
                                                $flag = function_exists('getCountryFlagEmoji') ? getCountryFlagEmoji($countryName) : '🌐';
                                                $count = is_array($extensionGroup) ? count($extensionGroup) : 0;
                                                $isFirstCountry = ($countryName === $firstStickyRegion);
                                                $siblingObj = $stickySiblingsByCountry->get($countryName);
                                            @endphp
                                            <option value="{{ $countrySlug }}"
                                                    data-target-region="{{ $countrySlug }}"
                                                    data-region-name="{{ $countryName }}"
                                                    @if($siblingObj)
                                                        data-product-id="{{ $siblingObj->id }}"
                                                        data-product-name="{{ $siblingObj->name }}"
                                                        data-slug="{{ $siblingObj->slug }}"
                                                        data-route="{{ route('product', $siblingObj->slug) }}"
                                                    @endif
                                                    {{ $isFirstCountry ? 'selected' : '' }}>
                                                {{ $flag }} {{ $countryName }} ({{ $count }} {{ $count === 1 ? translate('Package') : translate('Packages') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="sticky-packages-group mb-2">
                                <h6 class="fs-14 mb-2 text-capitalize">
                                    {{ translate('Package') }}
                                </h6>
                                @foreach($stickyExts as $countryName => $extensionGroup)
                                    @php
                                        $countrySlug = Str::slug($countryName);
                                        $isFirstCountry = ($countryName === $firstStickyRegion);
                                        $currSibling = $stickySiblingsByCountry->get($countryName) ?? $productDetails;
                                        $currProductId = $currSibling->id;
                                        $currSlug = $currSibling->slug;
                                        $currRoute = route('product', $currSlug);
                                    @endphp
                                    <div class="country-packages-wrapper {{ $isFirstCountry ? '' : 'd-none' }}"
                                         data-region="{{ $countrySlug }}"
                                         data-region-name="{{ $countryName }}">
                                        @if(is_array($extensionGroup) && count($extensionGroup) > 0)
                                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2 package-pills-list">
                                                @foreach($extensionGroup as $index => $extension)
                                                    @php
                                                        $currCleanOpt = preg_replace('/\s+/', '-', $extension);
                                                        $currVariantKey = 'Package-' . $currCleanOpt;
                                                        $currVar = $stickyDigitalVariationsKeyed->get($currVariantKey)
                                                            ?? $stickyDigitalVariationsKeyed->get($countryName . '-' . $currCleanOpt)
                                                            ?? $stickyDigitalVariationsKeyed->get($currProductId . '_' . $currVariantKey)
                                                            ?? null;
                                                        if (!$currVar && isset($stickyDigitalVariationsKeyed[$currCleanOpt])) {
                                                            $currVar = $stickyDigitalVariationsKeyed[$currCleanOpt];
                                                        }
                                                        if ($currVar && !empty($currVar->variant_key)) {
                                                            $currVariantKey = $currVar->variant_key;
                                                        }
                                                        $currPrice = $currVar ? $currVar->price : null;
                                                        $currPriceFormatted = $currPrice !== null ? webCurrencyConverter($currPrice) : null;
                                                        $uniqueStickyRadioId = 'sticky-ext_' . Str::slug($countryName) . '_' . Str::slug($currCleanOpt) . '_' . $index;
                                                        $isChecked = ($isFirstCountry && $loop->first);
                                                    @endphp
                                                    <div class="package-pill-wrapper d-inline-block">
                                                        <input type="radio"
                                                               class="package-variant-radio d-none"
                                                               id="{{ $uniqueStickyRadioId }}"
                                                               name="variant_key"
                                                               value="{{ $currVariantKey }}"
                                                               data-product-id="{{ $currProductId }}"
                                                               data-product-name="{{ $currSibling->name }}"
                                                               data-route="{{ $currRoute }}"
                                                               data-price="{{ $currPriceFormatted }}"
                                                               data-raw-price="{{ $currPrice }}"
                                                               data-title="{{ $extension }}"
                                                               data-country="{{ $countryName }}"
                                                               {{ $isChecked ? 'checked' : '' }}
                                                               {{ $isFirstCountry ? '' : 'disabled' }}>
                                                        <label for="{{ $uniqueStickyRadioId }}"
                                                               class="__text-12px variant-pill-btn m-0">
                                                            <span class="text-nowrap max-w-180 line--limit-1 fw-bold">{{ $extension }}</span>
                                                            @if($currPriceFormatted)
                                                                <span class="variant-price-tag">{{ $currPriceFormatted }}</span>
                                                            @endif
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if($productDetails['product_type'] != 'digital' || empty($productDetails['digital_product_extensions']))
                        @foreach (json_decode($productDetails->choice_options) as $key => $choice)
                        <div>
                            <h6 class="fs-14 mb-2 text-capitalize">
                                {{ $choice->title }}
                            </h6>
                            <div class="list-inline checkbox-alphanumeric checkbox-alphanumeric--style-1 mb-0 flex-start ps-0 overflow-x-auto flex-nowrap overflow-y-hidden scrollbar-none">
                                @foreach ($choice->options as $index => $option)
                                    <div class="user-select-none">
                                        <div class="for-mobile-capacity">
                                            <input type="radio"
                                                   id="sticky-{{ str_replace(' ', '', ($choice->name. '-'. $option)) }}"
                                                   name="{{ $choice->name }}" value="{{ $option }}"
                                                   @if($index == 0) checked @endif >
                                            <label class="__text-12px"
                                                   for="sticky-{{ str_replace(' ', '', ($choice->name. '-'. $option)) }}">
                                                   <span class="text-nowrap max-w-180 line--limit-1">{{ $option }}</span>
                                                </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 product-details-sticky-bottom">
                <div class="media gap-sm-3 d-flex flex-column flex-sm-row">
                    <img width="48" class="rounded d-none d-sm-block aspect-1 object-cover"
                         src="{{ getStorageImages(path: $productDetails->thumbnail_full_url, type: 'product') }}"
                         alt=""
                    >
                        @php
                            $stickyDisplayTitle = ($stickyResolvedData['is_sibling_grouped'] ?? false) ? trim(preg_replace('/\s*\([^)]*\)?\s*$/', '', $productDetails->name)) : $productDetails->name;
                        @endphp
                        <h6 class="mb-1 fs-14 line--limit-1 product-sticky-main-title">
                            {{ $stickyDisplayTitle }}
                        </h6>
                        <div>
                            <input type="hidden" class="product-generated-variation-code" name="product_variation_code" data-product-id="{{ $productDetails['id'] }}">
                            <input type="hidden" value="" class="product-exist-in-cart-list form-control w-50" name="key">
                        </div>
                        <div class="d-flex flex-wrap align-items-center mb-2 pro">
                            <span class="fs-12 text-muted line--limit-1 text-capitalize product-generated-variation-text"></span>
                            <div class="d-none d-sm-flex flex-wrap align-items-center">
                                <span class="{{ count(json_decode($productDetails->variation, true)) > 0 ? '__inline-25' : '' }} {{ count(json_decode($productDetails->variation, true)) > 0 ? 'mx-2' : '' }} mt-0"></span>

                                <span class="fs-12">
                                    <span class="d-flex flex-wrap gap-8 align-items-center row-gap-0">
                                        {!! getPriceRangeWithDiscount(product: $productDetails) !!}
                                    </span>
                                </span>
                                <span class="for-discount-value position-static p-1 px-2 font-bold fs-13 mx-2 discounted-badge-element">
                                    <span class="direction-ltr d-block discounted_badge">
                                        {{ $initialProductConfig['discount']  }}
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="d-sm-none d-flex gap-1 fs-12">
                        {!! getPriceRangeWithDiscount(product: $productDetails) !!}
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 gap-sm-3 gap-xl-4">
                    <div class="d-flex justify-content-between align-items-center quantity-box border rounded border-base web-text-primary w-130px h-40px overflow-hidden">
                        <span class="input-group-btn h-100">
                            <button class="btn btn-number __p-10 web-text-primary bg-ECF1F6 rounded-0 h-100 w-32px d-flex align-items-center" type="button" data-type="minus" data-field="quantity" disabled="disabled">-</button>
                        </span>
                        <input type="number" name="quantity"
                        class="form-control input-number text-center product-details-cart-qty __inline-29 border-0 w-100 fs-12"
                        placeholder="{{ translate('1') }}"
                        value="{{ $initialProductConfig['quantity'] ?? 1 }}"
                        data-producttype="{{ $productDetails->product_type }}"
                        min="{{ $productDetails->minimum_order_qty ?? 1 }}"
                        max="{{$productDetails['product_type'] == 'physical' ? $productDetails->current_stock : 100}}">

                        <span class="input-group-btn h-100">
                            <button class="btn btn-number __p-10 web-text-primary bg-ECF1F6 rounded-0 h-100 w-32px d-flex align-items-center" type="button" data-producttype="physical" data-type="plus" data-field="quantity">+</button>
                        </span>
                    </div>

                    <div class="font-weight-normal text-accent align-items-end gap-2 d-none d-lg-flex">
                        <span class="product-bottom-section-price fs-24 font-bold user-select-none text-nowrap">{{$initialProductConfig['price']}}</span>
                    </div>


                    @if(($product->added_by == 'admin' && (checkVendorAbility(type: 'inhouse', status: 'temporary_close') || checkVendorAbility(type: 'inhouse', status: 'vacation_status'))) || ($product->added_by == 'seller' && (checkVendorAbility(type: 'vendor', status: 'temporary_close', vendor: $product->seller->shop) || checkVendorAbility(type: 'vendor', status: 'vacation_status', vendor: $product->seller->shop))))
                        <div class="alert alert-danger m-0 font-semi-bold fs-12 ms-2" role="alert">
                            {{translate('you_cannot_add_product_to_cart_from_this_shop_for_now')}}
                        </div>
                    @else
                        <div class="product-add-and-buy-section d-flex gap-2">
                            <button type="button" class="btn btn-secondary element-center btn-gap-right product-buy-now-button"
                                    data-form=".add-to-cart-sticky-form"
                                    data-auth="{{( getWebConfig(name: 'guest_checkout') == 1 || Auth::guard('customer')->check() ? 'true':'false')}}"
                                    data-route="{{ route('shop-cart') }}"
                            >
                                <span class="string-limit">{{ translate('buy_now') }}</span>
                            </button>

                            <button class="btn btn--primary element-center product-add-to-cart-button"
                                    type="button"
                                    data-form=".add-to-cart-sticky-form"
                                    data-update="{{ translate('update_cart') }}"
                                    data-add="{{ translate('add_to_cart') }}"
                            >
                                {{ translate('add_to_cart') }}
                            </button>
                        </div>

                        @if(($productDetails['product_type'] == 'physical'))
                            <div class="product-restock-request-section collapse" {!! $firstVariationQuantity <= 0 ? 'style="display: block;"' : '' !!}>
                                <button type="button"
                                        class="btn request-restock-btn btn-outline-primary fw-semibold product-restock-request-button"
                                        data-auth="{{ auth('customer')->check() }}"
                                        data-form=".add-to-cart-sticky-form"
                                        data-default="{{ translate('Request_Restock') }}"
                                        data-requested="{{ translate('Request_Sent') }}"
                                >
                                    {{ translate('Request_Restock')}}
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
