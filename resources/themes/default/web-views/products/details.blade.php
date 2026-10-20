@extends('layouts.front-end.app')

@section('title', $product['name'])

@push('css_or_js')
    @include(VIEW_FILE_NAMES['product_seo_meta_content_partials'], ['metaContentData' => $product?->seoInfo, 'productDetails' => $product])
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/product-details.css') }}"/>
    <style>
        .package-pills-list {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
        }
        .package-pill-wrapper {
            display: inline-block !important;
            margin: 0 !important;
        }
        .variant-pill-btn {
            display: inline-flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 10px !important;
            padding: 8px 14px !important;
            border-radius: 8px !important;
            border: 1.5px solid #e2e8f0 !important;
            background: #ffffff !important;
            transition: all 0.2s ease-in-out !important;
            cursor: pointer !important;
            min-width: 100px !important;
            text-align: center !important;
            line-height: 1.25 !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            font-size: 13px !important;
            user-select: none !important;
        }
        .variant-pill-btn:hover {
            border-color: var(--web-primary) !important;
            background-color: #f8fafc !important;
        }
        .package-variant-radio:checked + label.variant-pill-btn,
        input[name="variant_key"]:checked + label.variant-pill-btn,
        .checkbox-alphanumeric input:checked ~ label.variant-pill-btn {
            border-color: var(--web-primary) !important;
            background-color: rgba(var(--web-primary-rgb), 0.08) !important;
            color: var(--web-primary) !important;
            box-shadow: 0 0 0 1.5px var(--web-primary) !important;
        }
        .variant-price-tag {
            font-size: 12px !important;
            color: #475569 !important;
            margin-top: 0 !important;
            font-weight: 700 !important;
            background: #f1f5f9;
            padding: 2px 7px;
            border-radius: 4px;
            white-space: nowrap !important;
        }
        .package-variant-radio:checked + label.variant-pill-btn .variant-price-tag,
        input[name="variant_key"]:checked + label.variant-pill-btn .variant-price-tag,
        .checkbox-alphanumeric input:checked ~ label.variant-pill-btn .variant-price-tag {
            color: #ffffff !important;
            background: var(--web-primary) !important;
        }
        .product-region-select-box {
            background-color: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            cursor: pointer;
            width: 100%;
        }
        .product-region-select-box:focus {
            border-color: var(--web-primary);
            box-shadow: 0 0 0 3px rgba(var(--web-primary-rgb), 0.15);
        }
        .product-country-pills-list {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
        }
        .product-country-pill-btn {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 7px 14px !important;
            border-radius: 8px !important;
            border: 1.5px solid #cbd5e1 !important;
            background: #ffffff !important;
            color: #334155 !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            transition: all 0.2s ease-in-out !important;
            cursor: pointer !important;
            user-select: none !important;
            line-height: 1.3 !important;
            text-decoration: none !important;
        }
        .product-country-pill-btn:hover {
            border-color: var(--web-primary) !important;
            color: var(--web-primary) !important;
            background: #f8fafc !important;
        }
        .product-country-pill-btn.active {
            border-color: var(--web-primary) !important;
            background: var(--web-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(var(--web-primary-rgb), 0.3) !important;
        }
        .product-country-pill-btn.active .country-count-badge {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
        }
        .country-count-badge {
            font-size: 11px !important;
            font-weight: 700 !important;
            background: #e2e8f0;
            color: #475569;
            padding: 2px 6px;
            border-radius: 10px;
        }
    </style>
@endpush

@section('content')
    <div class="__inline-23">
        <div class="container my-4 rtl text-align-direction">
            <div class="row g-3 {{Session::get('direction') === "rtl" ? '__dir-rtl' : ''}}">
                <div class="col-lg-9">
                    <div class="h-100 d-flex flex-column gap-4 pb-2">
                        <div class="card card-body flex-grow-0">
                            <div class="row g-3">
                                <div class="col-lg-5 col-md-4">
                                    <div class="pd-img-wrap position-relative">
                                        <div class="swiper-container quickviewSlider2 border rounded aspect-1">
                                            <div class="swiper-wrapper">
                                                @php
                                                    $imageSources = ($product->product_type === 'physical' && !empty($product->color_image) && count($product->color_images_full_url) > 0)
                                                        ? $product->color_images_full_url
                                                        : $product->images_full_url;
                                                @endphp

                                                @foreach ($imageSources as $key => $photo)
                                                    @php
                                                        $imagePath = isset($photo['image_name'])
                                                            ? getStorageImages(path: $photo['image_name'], type: 'backend-product')
                                                            : getStorageImages(path: $photo, type: 'backend-product');

                                                        $colorCode = $photo['color'] ?? '';
                                                    @endphp
                                                    <div class="swiper-slide position-relative" data-color="{{ $colorCode }}">
                                                        <div class="easyzoom easyzoom--overlay is-ready">
                                                            <a href="{{ $imagePath }}">
                                                                <img class="rounded h-100 aspect-1" alt="" src="{{ $imagePath }}">
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endforeach

                                                @if($product?->preview_file_full_url['path'])
                                                    <div class="product-preview-modal-text"
                                                         data-toggle="modal"
                                                         data-target="#product-preview-modal">
                                                        <span class="text-primary fw-bold py-2 user-select-none fs-14">
                                                            {{ translate('See_Preview') }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        @if (getProductPriceByType(product: $product, type: 'discount', result: 'value') > 0)
                                        <div class="discount-badge-wrapper">
                                            <span class="fs-13 text-white bg-primary text-nowrap fw-bold d-block discount-badge">
                                                <span class="direction-ltr d-block">
                                                 -{{ getProductPriceByType(product: $product, type: 'discount', result: 'string') }}
                                                </span>
                                            </span>
                                        </div>
                                        @endif
                                        <div class="cz-product-gallery-icons">
                                            <div class="d-flex flex-column gap-12px pt-3">
                                                @if($product->product_type == "physical")
                                                <div class="bg-white btn-circle border" style="--size: 35px" data-toggle="tooltip" title="{{ translate('Physical_Product') }}" data-placement="left">
                                                    <img class="h-16px aspect-1 svg" src="{{theme_asset(path: "public/assets/front-end/img/icons/physical-product.svg")}}" alt="{{ translate('Physical_Product') }}">
                                                </div>
                                                @else
                                                    <div class="bg-white btn-circle border" style="--size: 35px" data-toggle="tooltip" title="{{ translate('Digital_Product') }}" data-placement="left">
                                                        <img class="h-16px aspect-1 svg" src="{{theme_asset(path: "public/assets/front-end/img/icons/digital-product.svg")}}" alt="{{ translate('Digital_Product') }}">
                                                    </div>
                                                @endif
                                                <button type="button" data-product-id="{{ $product['id'] }}"
                                                    class="btn __text-18px border position-static rounded-circle wishList-pos-btn d-sm-none product-action-add-wishlist">
                                                    <i class="fa {{($wishlistStatus == 1?'fa-heart':'fa-heart-o') }} wishlist_icon_{{ $product['id'] }} web-text-primary"
                                                    aria-hidden="true"></i>
                                                    <div class="wishlist-tooltip" x-placement="top">
                                                        <div class="arrow"></div>
                                                        <div class="inner">
                                                            <span class="add">{{ translate('added_to_wishlist') }}</span>
                                                            <span class="remove">{{ translate('removed_from_wishlist') }}</span>
                                                        </div>
                                                    </div>
                                                </button>

                                                <div class="share_dropdown_wrapper">
                                                    <button type="button" class="btn btn-outline-primary btn-circle p-0 share_btn" style="--size: 35px" tabindex="0">
                                                        <i class="fa fa-share-alt"></i>
                                                    </button>
                                                    <div class="share_dropdown bg-white d-flex gap-3 align-items-center flex-column">
                                                        <a href="#"  class="flex-shrink-0 btn btn-circle p-0 bg-facebook text-white share-on-social-media share_btn facebook" style="--size: 20px"   data-action="{{route('product',$product->slug)}}"    data-social-media-name="facebook.com/sharer/sharer.php?u=">
                                                            <i class="czi-facebook lh-1 fs-10"></i>
                                                        </a>
                                                        <a href="#" class="flex-shrink-0 btn btn-circle p-0 bg-twitter text-white share-on-social-media share_btn twitter" style="--size: 20px"    data-action="{{route('product',$product->slug)}}" data-social-media-name="twitter.com/intent/tweet?text=">
                                                            <i class="czi-twitter lh-1 fs-10"></i>
                                                        </a>
                                                        <a href="#" class="flex-shrink-0 btn btn-circle p-0 bg-linkedin text-white share-on-social-media share_btn linkedin" style="--size: 20px"  data-action="{{route('product',$product->slug)}}" data-social-media-name="linkedin.com/shareArticle?mini=true&url=">
                                                            <i class="czi-linkedin lh-1 fs-10"></i>
                                                        </a>
                                                        <a href="#" class="flex-shrink-0 btn btn-circle p-0 bg-whatsapp text-white share-on-social-media share_btn whatsapp" style="--size: 20px"  data-action="{{route('product',$product->slug)}}" data-social-media-name="api.whatsapp.com/send?text=">
                                                            <i class="fa fa-whatsapp lh-1 fs-10"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 user-select-none">
                                            <div class="quickviewSliderThumb2 swiper-container position-relative active-border">
                                                <div class="swiper-wrapper auto-item-width justify-content-start">
                                                    @foreach ($imageSources as $key => $photo)
                                                        @php
                                                            $imagePath = isset($photo['image_name'])
                                                                ? getStorageImages(path: $photo['image_name'], type: 'backend-product')
                                                                : getStorageImages(path: $photo, type: 'backend-product');
                                                        @endphp
                                                        <div class="swiper-slide position-relative rounded border" role="group">
                                                            <img class="aspect-1" alt="" src="{{ $imagePath }}">
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <div class="swiper-button-next swiper-quickview-button-next"></div>
                                                <div class="swiper-button-prev swiper-quickview-button-prev"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-7 col-md-8 mt-md-0 mt-sm-3 web-direction">
                                    <div class="details __h-100 product-cart-option-container p-0">
                                        @php
                                            $resolvedData = function_exists('resolveDigitalProductExtensionsAndSiblings')
                                                ? resolveDigitalProductExtensionsAndSiblings($product)
                                                : [
                                                    'extensions' => is_string($product->digital_product_extensions) ? (json_decode($product->digital_product_extensions, true) ?? []) : ($product->digital_product_extensions ?? []),
                                                    'variations' => $product->digitalVariation ? $product->digitalVariation->keyBy('variant_key') : collect(),
                                                    'siblings_by_country' => collect(),
                                                    'is_sibling_grouped' => false,
                                                ];

                                            $resolvedExtensions = $resolvedData['extensions'];
                                            $activeDigitalVariations = $resolvedData['variations'];
                                            $siblingsByCountry = $resolvedData['siblings_by_country'];
                                            $isSiblingGrouped = $resolvedData['is_sibling_grouped'] ?? false;
                                            $displayTitle = $isSiblingGrouped ? trim(preg_replace('/\s*\([^)]*\)?\s*$/', '', $product->name)) : $product->name;

                                            // Check if product has regional / country variations
                                            $hasCountryVariations = is_array($resolvedExtensions) && count($resolvedExtensions) > 1;
                                            if (is_array($resolvedExtensions) && count($resolvedExtensions) === 1) {
                                                $firstGroupKey = array_key_first($resolvedExtensions);
                                                if ($firstGroupKey && !in_array(strtolower(trim($firstGroupKey)), ['package', 'default', 'options', 'variation', 'variations'])) {
                                                    $hasCountryVariations = true;
                                                }
                                            }
                                            $firstRegionKey = is_array($resolvedExtensions) ? array_key_first($resolvedExtensions) : null;
                                        @endphp
                                        <h2 class="mb-4 __inline-24 product-main-title">{{ $displayTitle }}</h2>
                                        <div class="d-flex flex-wrap align-items-center gap-3 mb-4 pro">
                                            @if($overallRating[0] !=0)
                                            <div class="star-rating me-2">
                                                @for($inc=1;$inc<=5;$inc++)
                                                    @if ($inc <= (int)$overallRating[0])
                                                        <i class="tio-star text-warning"></i>
                                                    @elseif ($overallRating[0] != 0 && $inc <= (int)$overallRating[0] + 1.1 && $overallRating[0] > ((int)$overallRating[0]))
                                                        <i class="tio-star-half text-warning"></i>
                                                    @else
                                                        <i class="tio-star-outlined text-warning"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                                <span
                                                    class="d-inline-block  align-middle fs-14 text-muted">({{ $overallRating[0] }})</span>
                                                <span
                                                    class="font-weight-normal fs-14 font-for-tab d-inline-block font-size-sm text-body align-middle">
                                                   <a href="#reviews"
                                                      class="font-weight-normal fs-14 font-for-tab d-inline-block font-size-sm text-body align-middle"
                                                      data-toggle="tab"
                                                      role="tab">
                                                        <span class="web-text-primary fw-semibold">{{ $overallRating[1] }}</span> {{ translate('reviews') }}
                                                    </a>
                                                </span>
                                                <span class="border-middle-14px"></span>
                                               @endif
                                            <span
                                                class="font-weight-normal fs-14 font-for-tab d-inline-block font-size-sm text-body align-middle">
                                                <span class="web-text-primary fw-semibold fw-semibold">{{ $countOrder}}</span>
                                                    {{ translate('orders') }}
                                                </span>
                                            <span class="border-middle-14px"></span>
                                            <span
                                                class="font-weight-normal fs-14 font-for-tab d-inline-block font-size-sm text-body align-middle text-capitalize">
                                                <span class="web-text-primary fw-semibold countWishlist-{{ $product->id }}"> {{ $countWishlist}}</span> {{ translate('wish_listed') }}
                                            </span>
                                        </div>

                                        @if($product['product_type'] == 'digital')
                                            <div class="digital-product-authors mb-2">
                                                @if(count($productPublishingHouseInfo['data']) > 0)
                                                    <div class="d-flex align-items-center g-2 me-2">
                                                        <span class="text-capitalize digital-product-author-title">{{ translate('Publishing_House') }} :</span>
                                                        <div class="item-list">
                                                            @foreach($productPublishingHouseInfo['data'] as $publishingHouseName)
                                                                <a href="{{ route('products', ['publishing_house_id' => $publishingHouseName['id'], 'product_type' => 'digital', 'page'=>1]) }}"
                                                                   class="text-base">
                                                                    {{ $publishingHouseName['name'] }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                @if(count($productAuthorsInfo['data']) > 0)
                                                    <div class="d-flex align-items-center g-2 me-2">
                                                        <span class="text-capitalize digital-product-author-title">{{ translate('Author') }} :</span>
                                                        <div class="item-list">
                                                            @foreach($productAuthorsInfo['data'] as $productAuthor)
                                                                <a href="{{ route('products',['author_id' => $productAuthor['id'], 'product_type' => 'digital', 'page' => 1]) }}"
                                                                   class="text-base">
                                                                    {{ $productAuthor['name'] }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <form class="addToCartDynamicForm add-to-cart-details-form d-flex flex-column gap-4">

                                            <div class="">
                                                <h3 class="font-weight-normal text-accent d-flex align-items-center gap-2 mb-0 flex-wrap">
                                                    @if(getProductPriceByType(product: $product, type: 'discount', result: 'value') > 0)
                                                        <del
                                                            class="product-total-unit-price align-middle text-muted fs-18 font-semibold">
                                                            {{ webCurrencyConverter(amount: $product->unit_price) }}
                                                        </del>
                                                    @endif
                                                    <span class="discounted-unit-price fs-24 font-bold">
                                                        {{ getProductPriceByType(product: $product, type: 'discounted_unit_price', result: 'string') }}
                                                    </span>
                                                    @php
                                                        $initialSubscriptionName = '';
                                                        if ($product['product_type'] == 'digital' && is_array($resolvedExtensions) && !empty($resolvedExtensions)) {
                                                            $firstGroup = reset($resolvedExtensions);
                                                            if (is_array($firstGroup) && !empty($firstGroup)) {
                                                                $initialSubscriptionName = reset($firstGroup);
                                                            } elseif (is_string($firstGroup)) {
                                                                $initialSubscriptionName = $firstGroup;
                                                            }
                                                        }
                                                    @endphp
                                                    <span class="selected-subscription-title fs-20 font-bold text-dark ms-1" style="{{ empty($initialSubscriptionName) ? 'display: none;' : '' }}">
                                                        - {{ $initialSubscriptionName }}
                                                    </span>
                                                </h3>
                                            </div>

                                            @csrf
                                            <input type="hidden" name="id" value="{{ $product->id }}">
                                            <div
                                                class="position-relative">
                                                @if (count(json_decode($product->colors)) > 0)
                                                    <div class="d-flex align-items-center gap-3 my-0">
                                                        <div class="product-description-label m-0 __color-9B9B9B fs-14">
                                                            {{ translate('color') }}
                                                        </div>
                                                        <div>
                                                            <ul class="list-inline checkbox-color mb-0 p-0 flex-start gap-2">
                                                                @foreach (json_decode($product->colors) as $key => $color)
                                                                    <li>
                                                                        <input type="radio"
                                                                               id="{{ str_replace(' ', '', ($product->id. '-color-'. str_replace('#','',$color))) }}"
                                                                               name="color" value="{{ $color }}"
                                                                               @if($key == 0) checked @endif>
                                                                        <label style="background: {{ $color }};"
                                                                               class="focus-preview-image-by-color shadow-border"
                                                                               for="{{ str_replace(' ', '', ($product->id. '-color-'. str_replace('#','',$color))) }}"
                                                                               data-toggle="tooltip"
                                                                               data-key="{{ str_replace('#','',$color) }}"
                                                                               data-colorid="preview-box-{{ str_replace('#','',$color) }}"
                                                                               data-title="{{ getColorNameByCode(code: $color) }}">
                                                                            <span class="outline"></span></label>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    </div>
                                                @endif
                                                @php
                                                    $qty = 0;
                                                    if(!empty($product->variation)){
                                                    foreach (json_decode($product->variation) as $key => $variation) {
                                                            $qty += $variation->qty;
                                                        }
                                                    }
                                                @endphp
                                            </div>

                                            <div id="digital-product-extensions-container">
                                                @if($product['product_type'] == 'digital' && is_array($resolvedExtensions) && count($resolvedExtensions) > 0)
                                                    {{-- Country Filter on the Product Page --}}
                                                    @if($hasCountryVariations)
                                                        <div class="product-country-selector-group mb-3 pb-2 border-bottom">
                                                            <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
                                                                <label for="product-country-select" class="product-description-label __color-9B9B9B fs-14 text-capitalize flex-shrink-0 mb-0">
                                                                    <span class="me-1">🌐</span>{{ translate('Country_/_Region') }}:
                                                                </label>
                                                                <div class="flex-grow-1" style="max-width: 360px; width: 100%;">
                                                                    <select id="product-country-select" class="form-control form-select product-country-select product-region-select-box">
                                                                        @foreach($resolvedExtensions as $countryName => $extensionGroup)
                                                                            @php
                                                                                $countrySlug = Str::slug($countryName);
                                                                                $flag = function_exists('getCountryFlagEmoji') ? getCountryFlagEmoji($countryName) : '🌐';
                                                                                $count = is_array($extensionGroup) ? count($extensionGroup) : 0;
                                                                                $isFirstCountry = ($countryName === $firstRegionKey);
                                                                                $siblingObj = $siblingsByCountry->get($countryName);
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
                                                            </div>
                                                        </div>
                                                    @endif

                                                    {{-- Subscription / Package Variations for the Selected Country --}}
                                                    <div class="product-package-variations-group mb-3">
                                                        <div class="d-flex flex-column flex-md-row align-items-start gap-2">
                                                            <div class="product-description-label __color-9B9B9B fs-14 text-capitalize flex-shrink-0 pt-1">
                                                                {{ translate('Package') }}:
                                                            </div>
                                                            <div class="flex-grow-1 w-100">
                                                                @foreach($resolvedExtensions as $countryName => $extensionGroup)
                                                                    @php
                                                                        $countrySlug = Str::slug($countryName);
                                                                        $isFirstCountry = ($countryName === $firstRegionKey);
                                                                        $currSibling = $siblingsByCountry->get($countryName) ?? $product;
                                                                        $currProductId = $currSibling->id;
                                                                        $currSlug = $currSibling->slug;
                                                                        $currRoute = route('product', $currSlug);
                                                                    @endphp
                                                                    <div class="country-packages-wrapper {{ $isFirstCountry ? '' : 'd-none' }}"
                                                                         data-region="{{ $countrySlug }}"
                                                                         data-region-name="{{ $countryName }}">
                                                                        @if(is_array($extensionGroup) && count($extensionGroup) > 0)
                                                                            <div class="d-flex flex-wrap gap-2 align-items-center package-pills-list">
                                                                                @foreach($extensionGroup as $index => $extension)
                                                                                    @php
                                                                                        $currCleanOpt = preg_replace('/\s+/', '-', $extension);
                                                                                        $currVariantKey = 'Package-' . $currCleanOpt;
                                                                                        $currVar = $activeDigitalVariations->get($currVariantKey)
                                                                                            ?? $activeDigitalVariations->get($countryName . '-' . $currCleanOpt)
                                                                                            ?? $activeDigitalVariations->get($currProductId . '_' . $currVariantKey)
                                                                                            ?? null;
                                                                                        if (!$currVar && isset($activeDigitalVariations[$currCleanOpt])) {
                                                                                            $currVar = $activeDigitalVariations[$currCleanOpt];
                                                                                        }
                                                                                        if ($currVar && !empty($currVar->variant_key)) {
                                                                                            $currVariantKey = $currVar->variant_key;
                                                                                        }
                                                                                        $currPrice = $currVar ? $currVar->price : null;
                                                                                        $currPriceFormatted = $currPrice !== null ? webCurrencyConverter($currPrice) : null;
                                                                                        $uniqueRadioId = 'ext_' . Str::slug($countryName) . '_' . Str::slug($currCleanOpt) . '_' . $index;
                                                                                        $isChecked = ($isFirstCountry && $loop->first);
                                                                                    @endphp
                                                                                    <div class="package-pill-wrapper d-inline-block">
                                                                                        <input type="radio"
                                                                                               class="package-variant-radio d-none"
                                                                                               id="{{ $uniqueRadioId }}"
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
                                                                                        <label for="{{ $uniqueRadioId }}"
                                                                                               class="variant-pill-btn m-0">
                                                                                            <span class="text-truncate fw-semibold">{{ $extension }}</span>
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
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            @if($product['product_type'] != 'digital' || empty($product['digital_product_extensions']))
                                                @foreach (json_decode($product->choice_options) as $key => $choice)
                                                    <div class="row flex-start mx-0  gap-3 flex-nowrap">
                                                        <div
                                                            class="product-description-label fs-14 __color-9B9B9B text-capitalize flex-shrink-0">{{ $choice->title }}
                                                        </div>
                                                        <div>
                                                            <div
                                                                class="list-inline checkbox-alphanumeric checkbox-alphanumeric--style-1 mb-0 mx-1 flex-start row ps-0">
                                                                @foreach ($choice->options as $index => $option)
                                                                    <div class="user-select-none">
                                                                        <div class="for-mobile-capacity">
                                                                            <input type="radio"
                                                                                   id="{{ str_replace(' ', '', ($choice->name. '-'. $option)) }}"
                                                                                   name="{{ $choice->name }}" value="{{ $option }}"
                                                                                   @if($index == 0) checked @endif >
                                                                            <label class="__text-12px"
                                                                                   for="{{ str_replace(' ', '', ($choice->name. '-'. $option)) }}">
                                                                                    <span class="text-nowrap max-w-180 line--limit-1">{{ $option }}</span>
                                                                                </label>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif

                                            <div class="">
                                                <div class="product-quantity d-flex flex-column __gap-15">
                                                    <div class="d-flex align-items-center gap-4">
                                                        <div class="product-description-label __color-9B9B9B fs-14">
                                                            {{ translate('qty') }}
                                                        </div>
                                                        <div
                                                            class="d-flex justify-content-between align-items-center quantity-box border rounded border-base web-text-primary w-130px h-40px overflow-hidden">
                                                            <span class="input-group-btn h-100">
                                                                <button class="btn btn-number __p-10 web-text-primary bg-ECF1F6 rounded-0 h-100 w-32px"
                                                                        type="button"
                                                                        data-type="minus" data-field="quantity"
                                                                        disabled="disabled">
                                                                    -
                                                                </button>
                                                            </span>
                                                            <input type="text" name="quantity"
                                                                   class="form-control input-number text-center product-details-cart-qty __inline-29 border-0 w-100 fs-12"
                                                                   placeholder="{{ translate('1') }}"
                                                                   value="{{ $initialProductConfig['quantity'] ?? 1 }}"
                                                                   data-producttype="{{ $product->product_type }}"
                                                                   min="{{ $product->minimum_order_qty ?? 1 }}"
                                                                   max="{{ $product['product_type'] == 'physical' ? $product->current_stock : 100}}">
                                                            <span class="input-group-btn h-100">
                                                                <button class="btn btn-number __p-10 web-text-primary bg-ECF1F6 rounded-0 h-100 w-32px"
                                                                        type="button"
                                                                        data-producttype="{{ $product->product_type }}"
                                                                        data-type="plus" data-field="quantity">
                                                                        +
                                                                </button>
                                                            </span>
                                                        </div>
                                                        <input type="hidden" class="product-generated-variation-code"
                                                               name="product_variation_code"
                                                               data-product-id="{{ $product['id'] }}">
                                                        <input type="hidden" value=""
                                                               class="product-exist-in-cart-list form-control w-50" name="key">
                                                    </div>
                                                    <div class="product-details-chosen-price-section">
                                                        <div
                                                            class="d-none d-sm-flex justify-content-start align-items-center me-2">
                                                            <div
                                                                class="product-description-label fs-20 text-dark font-bold text-capitalize">
                                                                <strong>{{ translate('total_price') }}</strong>:
                                                            </div>
                                                            &nbsp; <strong
                                                                class="text-base product-details-chosen-price-amount fs-20">
                                                                {{ webCurrencyConverter(amount: $initialProductConfig['total_quantity_price']) }}
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="__btn-grp product-add-and-buy-section-parent">

                                                <?php


                                                $isTemporaryClose = false;
                                                $isVacationMode = false;
                                                $authorType = $product->added_by == 'admin' ? 'inhouse' : ($product->added_by == 'seller' ? 'vendor' : '');

                                                if ($authorType === 'vendor') {
                                                    $isTemporaryClose = checkVendorAbility(type: $authorType, status: 'temporary_close', vendor: $product->seller->shop);
                                                    $isVacationMode = !$isTemporaryClose ? checkVendorAbility(type: $authorType, status: 'vacation_status', vendor: $product->seller->shop) : false;
                                                } else {
                                                    $isTemporaryClose = checkVendorAbility(type: $authorType, status: 'temporary_close');
                                                    $isVacationMode = !$isTemporaryClose ? checkVendorAbility(type: $authorType, status: 'vacation_status') : false;
                                                }
                                                ?>

                                                <div
                                                    class="product-add-and-buy-section gap-2 {!! $firstVariationQuantity <= 0 ? '' : 'd-flex' !!}"
                                                    {!! $firstVariationQuantity <= 0 ? 'style="display: none;"' : '' !!}>

                                                    @if($isTemporaryClose || $isVacationMode)
                                                        <button class="btn btn-secondary" type="button" disabled>
                                                            {{ translate('buy_now') }}
                                                        </button>
                                                        <button class="btn btn--primary string-limit" type="button" disabled>
                                                            {{ translate('add_to_cart') }}
                                                        </button>
                                                    @else
                                                        <button type="button"
                                                                class="btn btn-secondary element-center btn-gap-{{ Session::get('direction') === 'rtl' ? 'left' : 'right' }} product-buy-now-button"
                                                                data-form=".add-to-cart-details-form"
                                                                data-auth="{{ (getWebConfig(name: 'guest_checkout') == 1 || Auth::guard('customer')->check()) ? 'true' : 'false' }}"
                                                                data-route="{{ route('shop-cart') }}"
                                                        >
                                                            <span class="string-limit">{{ translate('buy_now') }}</span>
                                                        </button>
                                                        <button
                                                            class="btn btn--primary element-center product-add-to-cart-button"
                                                            type="button"
                                                            data-form=".add-to-cart-details-form"
                                                            data-update="{{ translate('update_cart') }}"
                                                            data-add="{{ translate('add_to_cart') }}"
                                                        >
                                                            <span class="string-limit">
                                                                {{ $initialProductConfig['first_variant_in_cart'] ? translate('update_cart') : translate('add_to_cart') }}
                                                            </span>
                                                        </button>
                                                    @endif
                                                </div>

                                                @if(($product['product_type'] == 'physical'))
                                                    <div
                                                        class="product-restock-request-section collapse" {!! $firstVariationQuantity <= 0 ? 'style="display: block;"' : '' !!}>
                                                        <button type="button"
                                                                class="btn request-restock-btn btn-outline-primary fw-semibold product-restock-request-button"
                                                                data-auth="{{ auth('customer')->check() }}"
                                                                data-form=".addToCartDynamicForm"
                                                                data-default="{{ translate('Request_Restock') }}"
                                                                data-requested="{{ translate('Request_Sent') }}"
                                                        >
                                                            {{ translate('Request_Restock') }}
                                                        </button>
                                                    </div>
                                                @endif
                                                <button type="button" data-product-id="{{ $product['id'] }}"
                                                        class="btn __text-18px border product-action-add-wishlist">
                                                    <i class="fa {{($wishlistStatus == 1?'fa-heart':'fa-heart-o') }} wishlist_icon_{{ $product['id'] }} web-text-primary"
                                                       aria-hidden="true"></i>
                                                    <span
                                                        class="fs-14 text-muted align-bottom countWishlist-{{ $product['id'] }}">{{ $countWishlist}}</span>
                                                    <div class="wishlist-tooltip" x-placement="top">
                                                        <div class="arrow"></div>
                                                        <div class="inner">
                                                            <span class="add">{{ translate('added_to_wishlist') }}</span>
                                                            <span class="remove">{{ translate('removed_from_wishlist') }}</span>
                                                        </div>
                                                    </div>
                                                </button>

                                                @if($product->added_by == 'admin')
                                                    @if(checkVendorAbility(type: 'inhouse', status: 'temporary_close') || checkVendorAbility(type: 'inhouse', status: 'vacation_status'))
                                                        <div class="alert alert-danger mt-2" role="alert">
                                                            {{ translate('this_shop_is_temporary_closed_or_on_vacation._You_cannot_add_product_to_cart_from_this_shop_for_now') }}
                                                        </div>
                                                    @endif
                                                @elseif($product->added_by == 'seller')
                                                    @if(checkVendorAbility(type: 'vendor', status: 'temporary_close', vendor: $product->seller->shop) || checkVendorAbility(type: 'vendor', status: 'vacation_status', vendor: $product->seller->shop))
                                                        <div class="alert alert-danger mt-2" role="alert">
                                                            {{ translate('this_shop_is_temporary_closed_or_on_vacation._You_cannot_add_product_to_cart_from_this_shop_for_now') }}
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="rtl text-align-direction flex-grow-1">
                            <div class="__review-overview card card-body h-100">
                               <ul class="nav nav-tabs nav--tabs d-flex justify-content-center gap-2" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link __inline-27 mb-0 tab_link active"
                                        data-toggle="tab"
                                        href="#overview"
                                        role="tab">
                                            {{ translate('overview') }}
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link __inline-27 mb-0 tab_link"
                                        data-toggle="tab"
                                        href="#reviews"
                                        role="tab">
                                            {{ translate('reviews') }}
                                        </a>
                                    </li>
                                </ul>


                                <div class="tab-content px-lg-3">
                                    <div class="tab-pane fade show active text-justify" id="overview" role="tabpanel">
                                        <div class="details-content-wrap ov-hidden show-more--content">
                                            <div class="row pt-2 specification">

                                                @if($product->video_url != null && (str_contains($product->video_url, "youtube.com/embed/")))
                                                    <div class="col-12 mb-4">
                                                        <iframe width="420" height="315" src="{{ $product->video_url }}"
                                                                title="YouTube video player" frameborder="0"
                                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                                                referrerpolicy="strict-origin-when-cross-origin"
                                                                allowfullscreen></iframe>
                                                    </div>
                                                @endif
                                                @if ($product['details'])
                                                    <h4 class="fs-16 fw-bold mb-3 px-1">{{ translate('Detail_Description') }}</h4>
                                                    <div
                                                        class="p-details-description text-body col-lg-12 col-md-12 fs-14 text-justify details-text-justify rich-editor-html-content" style="max-height: 525px">
                                                        {!! $product['details'] !!}
                                                    </div>
                                                @endif
                                            </div>
                                            @if (!$product['details'] && ($product->video_url == null || !(str_contains($product->video_url, "youtube.com/embed/"))))
                                                <div>
                                                    <div class="text-center text-capitalize py-5">
                                                        <img class="mw-90"
                                                             src="{{ theme_asset(path: 'public/assets/front-end/img/icons/nodata.svg') }}"
                                                             alt="">
                                                        <p class="text-capitalize mt-2">
                                                            <small>
                                                                {{ translate('product_details_not_found').'!' }}
                                                            </small>
                                                        </p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="d-flex justify-content-center mt-2">
                                            <button class="btn btn-outline-primary text-capitalize px-4 py-2 see-more-details">{{ translate('see_more') }}</button>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                                        <div class="">
                                            @if(count($product->reviews)==0 && $productReviews->total() == 0)
                                                <div>
                                                    <div class="text-center text-capitalize">
                                                        <img class="mw-100"
                                                             src="{{ theme_asset(path: 'public/assets/front-end/img/icons/empty-review.svg') }}"
                                                             alt="">
                                                        <p class="text-capitalize">
                                                            <small>{{ translate('No_review_given_yet') }}!</small>
                                                        </p>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="row pt-2 pb-3">
                                                    <div class="col-lg-4 col-md-5 ">
                                                        <div
                                                            class=" row d-flex justify-content-center align-items-center">
                                                            <div
                                                                class="col-12 d-flex justify-content-center align-items-center">
                                                                <h2 class="overall_review mb-2 __inline-28">
                                                                    {{ $overallRating[0] }}
                                                                </h2>
                                                            </div>
                                                            <div
                                                                class="d-flex justify-content-center align-items-center star-rating ">
                                                                @for($inc=1;$inc<=5;$inc++)
                                                                    @if ($inc <= (int)$overallRating[0])
                                                                        <i class="tio-star text-warning"></i>
                                                                    @elseif ($overallRating[0] != 0 && $inc <= (int)$overallRating[0] + 1.1 && $overallRating[0] > ((int)$overallRating[0]))
                                                                        <i class="tio-star-half text-warning"></i>
                                                                    @else
                                                                        <i class="tio-star-outlined text-warning"></i>
                                                                    @endif
                                                                @endfor
                                                            </div>
                                                            <div
                                                                class="col-12 d-flex justify-content-center align-items-center mt-2">
                                                                <span class="text-center">
                                                                    {{ $productReviews->total() }} {{ translate('ratings') }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-8 col-md-7 pt-sm-3 pt-md-0">
                                                        <div
                                                            class="d-flex align-items-center mb-2 font-size-sm">
                                                            <div
                                                                class="__rev-txt"><span
                                                                    class="d-inline-block align-middle text-body">{{ translate('excellent') }}</span>
                                                            </div>
                                                            <div class="w-0 flex-grow">
                                                                <div class="progress text-body __h-5px">
                                                                    <div class="progress-bar web--bg-primary"
                                                                         role="progressbar"
                                                                         style="width: <?php echo $widthRating = ($rating[0] != 0) ? ($rating[0] / $overallRating[1]) * 100 : (0); ?>%;"
                                                                         aria-valuenow="60" aria-valuemin="0"
                                                                         aria-valuemax="100"></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-1 text-body">
                                                                <span
                                                                    class=" {{Session::get('direction') === "rtl" ? 'me-3 float-left' : 'ml-3 float-right'}} ">
                                                                    {{ $rating[0] }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div
                                                            class="d-flex align-items-center mb-2 text-body font-size-sm">
                                                            <div
                                                                class="__rev-txt"><span
                                                                    class="d-inline-block align-middle ">{{ translate('good') }}</span>
                                                            </div>
                                                            <div class="w-0 flex-grow">
                                                                <div class="progress __h-5px">
                                                                    <div class="progress-bar web--bg-primary" role="progressbar"
                                                                         style="width: <?php echo $widthRating = ($rating[1] != 0) ? ($rating[1] / $overallRating[1]) * 100 : (0); ?>%; background-color: #a7e453;"
                                                                         aria-valuenow="27" aria-valuemin="0"
                                                                         aria-valuemax="100"></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-1">
                                                                <span
                                                                    class="{{Session::get('direction') === "rtl" ? 'me-3 float-left' : 'ml-3 float-right'}}">
                                                                        {{ $rating[1] }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div
                                                            class="d-flex align-items-center mb-2 text-body font-size-sm">
                                                            <div
                                                                class="__rev-txt"><span
                                                                    class="d-inline-block align-middle ">{{ translate('average') }}</span>
                                                            </div>
                                                            <div class="w-0 flex-grow">
                                                                <div class="progress __h-5px">
                                                                    <div class="progress-bar web--bg-primary" role="progressbar"
                                                                         style="width: <?php echo $widthRating = ($rating[2] != 0) ? ($rating[2] / $overallRating[1]) * 100 : (0); ?>%; background-color: #ffda75;"
                                                                         aria-valuenow="17" aria-valuemin="0"
                                                                         aria-valuemax="100"></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-1">
                                                                <span
                                                                    class="{{Session::get('direction') === "rtl" ? 'me-3 float-left' : 'ml-3 float-right'}}">
                                                                    {{ $rating[2] }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div
                                                            class="d-flex align-items-center mb-2 text-body font-size-sm">
                                                            <div
                                                                class="__rev-txt "><span
                                                                    class="d-inline-block align-middle">{{ translate('below_Average') }}</span>
                                                            </div>
                                                            <div class="w-0 flex-grow">
                                                                <div class="progress __h-5px">
                                                                    <div class="progress-bar web--bg-primary" role="progressbar"
                                                                         style="width: <?php echo $widthRating = ($rating[3] != 0) ? ($rating[3] / $overallRating[1]) * 100 : (0); ?>%; background-color: #fea569;"
                                                                         aria-valuenow="9" aria-valuemin="0"
                                                                         aria-valuemax="100"></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-1">
                                                                <span
                                                                    class="{{Session::get('direction') === "rtl" ? 'me-3 float-left' : 'ml-3 float-right'}}">
                                                                    {{ $rating[3] }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div
                                                            class="d-flex align-items-center text-body font-size-sm">
                                                            <div
                                                                class="__rev-txt"><span
                                                                    class="d-inline-block align-middle ">{{ translate('poor') }}</span>
                                                            </div>
                                                            <div class="w-0 flex-grow">
                                                                <div class="progress __h-5px">
                                                                    <div class="progress-bar web--bg-primary" role="progressbar"
                                                                         style="width: <?php echo $widthRating = ($rating[4] != 0) ? ($rating[4] / $overallRating[1]) * 100 : (0); ?>%;"
                                                                         aria-valuenow="4" aria-valuemin="0"
                                                                         aria-valuemax="100"></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-1">
                                                                <span
                                                                    class="{{Session::get('direction') === "rtl" ? 'me-3 float-left' : 'ml-3 float-right'}}">
                                                                        {{ $rating[4] }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row pb-4 mb-3">
                                                    <div class="__inline-30">
                                                        <span
                                                            class="text-capitalize">{{ translate('Product_review') }}</span>
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="row pb-4">
                                                <div class="col-12" id="product-review-list">
                                                    @include('web-views.partials._product-reviews')
                                                </div>

                                                @if(count($product->reviews) > 2)
                                                    <div class="col-12">
                                                        <div
                                                            class="card-footer d-flex justify-content-center align-items-center">
                                                            <button class="btn text-white view_more_button web--bg-primary">
                                                                {{ translate('view_more') }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    @php($companyReliability = getWebConfig('company_reliability'))
                    @if($companyReliability != null)
                        <div class="product-details-shipping-details">
                            @foreach ($companyReliability as $key=>$value)
                                @if ($value['status'] == 1 && !empty($value['title']))
                                    <div class="shipping-details-bottom-border">
                                        <div class="px-3 py-3">
                                            <img
                                                class="{{Session::get('direction') === "rtl" ? 'float-right ml-2' : 'mr-2'}} __img-20"
                                                src="{{ getStorageImages(path: imagePathProcessing(imageData: $value['image'],path: 'company-reliability'), type: 'source', source: 'public/assets/front-end/img'.'/'.$value['item'].'.png') }}"
                                                alt="">
                                            <span>{{ translate($value['title']) }}</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @if(getWebConfig(name: 'business_mode')=='multi')
                        <div class="__inline-31">

                            @if($product->added_by=='seller')
                                @if(isset($product->seller->shop))
                                    <div class="row position-relative">
                                        <div class="col-12 position-relative">
                                            <a href="{{route('vendor-shop',['slug'=> $product?->seller?->shop->slug]) }}"
                                               class="d-block">
                                                <div class="d-flex __seller-author align-items-center">
                                                    <div>
                                                        <img class="__img-60 img-circle" alt=""
                                                             src="{{ getStorageImages(path: $product?->seller?->shop->image_full_url, type: 'shop') }}">
                                                    </div>
                                                    <div
                                                        class="ms-2 w-0 flex-grow">
                                                        <h2 class="fs-15 mb-2">
                                                            {{ $product->seller->shop->name }}
                                                        </h2>
                                                        @if(checkVendorAbility(type: 'vendor', status: 'temporary_close', vendor: $product->seller->shop))
                                                            <span class="badge-danger fs-12 px-3 rounded-full small">
                                                                <span>{{ translate('Temporary_OFF') }}</span>
                                                            </span>
                                                        @elseif(checkVendorAbility(type: 'vendor', status: 'vacation_status', vendor: $product->seller->shop))
                                                            <span class="badge-danger fs-12 px-3 rounded-full small">
                                                                <span>{{ translate('closed_Now') }}</span>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <div class="row d-flex justify-content-between">
                                                <div class="col-6 ">
                                                    <div
                                                        class="d-flex justify-content-center align-items-center rounded __h-79px hr-right-before">
                                                        <div class="text-center">
                                                            <img
                                                                src="{{ theme_asset(path: 'public/assets/front-end/img/rating.svg') }}"
                                                                class="mb-2" alt="">
                                                            <div class="__text-12px text-base">
                                                                <strong>{{ $totalReviews }}</strong>
                                                                {{ translate('reviews') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div
                                                        class="d-flex justify-content-center align-items-center rounded __h-79px">
                                                        <div class="text-center">
                                                            <img
                                                                src="{{ theme_asset(path: 'public/assets/front-end/img/products.svg') }}"
                                                                class="mb-2" alt="">
                                                            <div class="__text-12px text-base">
                                                                <strong>{{ $productsForReview->total() }}</strong>
                                                                {{ translate('products') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 position-static mt-3">
                                            <div class="chat_with_seller-buttons">
                                                @if (auth('customer')->id())
                                                    <button
                                                        class="btn w-100 d-block text-center web--bg-primary text-white"
                                                        data-toggle="modal"
                                                        data-target="#chatting_modal"
                                                        @if(checkVendorAbility(type: 'vendor', status: 'temporary_close', vendor: $product->seller->shop))
                                                            disabled
                                                        @endif
                                                    >
                                                        <img class="mb-1" alt=""
                                                             src="{{ theme_asset(path: 'public/assets/front-end/img/chat-16-filled-icon.png') }}">
                                                        <span class="d-none d-sm-inline-block text-capitalize">
                                                        {{ translate('chat_with_vendor') }}
                                                    </span>
                                                    </button>
                                                @else
                                                    <a href="{{ route('customer.auth.login') }}"
                                                       class="btn w-100 d-block text-center web--bg-primary text-white">
                                                        <img
                                                            src="{{ theme_asset(path: 'public/assets/front-end/img/chat-16-filled-icon.png') }}"
                                                            class="mb-1" alt="">
                                                        <span class="d-none d-sm-inline-block text-capitalize">
                                                        {{ translate('chat_with_vendor') }}
                                                    </span>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="row position-relative d-flex justify-content-between">
                                    <div class="col-9">
                                        <a href="{{route('vendor-shop',['slug' => getInHouseShopConfig(key:'slug')]) }}" class="row d-flex align-items-center">
                                            <div>
                                                <img class="__inline-32" alt=""
                                                     src="{{ getStorageImages(path: getInHouseShopConfig(key:'image_full_url'), type: 'shop') }}">
                                            </div>
                                            <div>
                                                <div
                                                    class="{{ session('direction') === "rtl" ? 'right' : 'ml-2' }} get-view-by-onclick"
                                                    data-link="{{ route('vendor-shop',['slug' => getInHouseShopConfig(key:'slug')]) }}">
                                                    <h2 class="font-bold __text-16px mb-1">
                                                        {{ getInHouseShopConfig(key: 'name') }}
                                                    </h2>
                                                    @if(checkVendorAbility(type: 'inhouse', status: 'temporary_close'))
                                                        <span class="badge-danger fs-12 px-3 rounded-full small">
                                                            <span>{{ translate('Temporary_OFF') }}</span>
                                                        </span>
                                                    @elseif(checkVendorAbility(type: 'inhouse', status: 'vacation_status'))
                                                        <span class="badge-danger fs-12 px-3 rounded-full small">
                                                            <span>{{ translate('closed_Now') }}</span>
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="fs-12 {{ session('direction') === "rtl" ? 'right' : 'ml-2' }}">{{ translate('Seller_info') }}</div>
                                            </div>
                                        </a>
                                    </div>

                                    <div class="col-12 mt-2">
                                        <div class="row d-flex justify-content-between">
                                            <div class="col-6 ">
                                                <div
                                                    class="d-flex justify-content-center align-items-center rounded __h-79px hr-right-before">
                                                    <div class="text-center">
                                                        <img
                                                            src="{{ theme_asset(path: 'public/assets/front-end/img/rating.svg') }}"
                                                            class="mb-2" alt="">
                                                        <div class="__text-12px text-base">
                                                            <strong>{{ $totalReviews}}</strong>
                                                            {{ translate('reviews') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div
                                                    class="d-flex justify-content-center align-items-center rounded __h-79px">
                                                    <div class="text-center">
                                                        <img
                                                            src="{{ theme_asset(path: 'public/assets/front-end/img/products.svg') }}"
                                                            class="mb-2" alt="">
                                                        <div class="__text-12px text-base">
                                                            <strong>{{ $productsForReview->total() }}</strong>
                                                            {{ translate('products') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 position-static mt-3">
                                        <div class="chat_with_seller-buttons">
                                            @if (auth('customer')->id())
                                                <button class="btn w-100 d-block text-center web--bg-primary text-white"
                                                        data-toggle="modal" data-target="#chatting_modal"
                                                    {{ checkVendorAbility(type: 'inhouse', status: 'temporary_close') ? 'disabled' : '' }}
                                                >
                                                    <img class="mb-1" alt=""
                                                         src="{{ theme_asset(path: 'public/assets/front-end/img/chat-16-filled-icon.png') }}">
                                                    <span class="d-none d-sm-inline-block text-capitalize">
                                                        {{ translate('chat_with_vendor') }}
                                                </span>
                                                </button>
                                            @else
                                                <a href="{{ route('vendor-shop', ['slug' => getInHouseShopConfig(key:'slug')]) }}"
                                                   class="btn w-100 d-block text-center web--bg-primary text-white">
                                                    <img class="mb-1" alt=""
                                                         src="{{ theme_asset(path: 'public/assets/front-end/img/chat-16-filled-icon.png') }}">
                                                    <span class="d-none d-sm-inline-block text-capitalize">
                                                        {{ translate('chat_with_vendor') }}
                                                    </span>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="pt-4 pb-3 d-flex justify-content-between align-items-center">
                        <h2 class="__text-16px font-bold text-capitalize mb-0">
                            @if(getWebConfig(name: 'business_mode') == 'multi')
                                {{ translate('more_from_the_store') }}
                            @else
                                {{ translate('you_may_also_like') }}
                            @endif
                        </h2>
                        @if($product->added_by == 'seller' && isset($product->seller->shop))
                        <a class="text-capitalize view-all-btn-text" href="{{ route('vendor-shop',['slug'=> $product->seller->shop->slug]) }}">
                            <span class="view-btn-text">{{ translate('View_All') }}</span>
                            <i class="czi-arrow-{{ session('direction') === "rtl" ? 'left' : 'right' }}"></i>
                        </a>
                        @else
                            <a class="text-capitalize view-all-btn-text" href="{{route('vendor-shop', ['slug'=> getInHouseShopConfig(key: 'slug')]) }}">
                                <span class="view-btn-text">{{ translate('View_All') }}</span>
                                <i class="czi-arrow-{{ session('direction') === "rtl" ? 'left' : 'right' }}"></i>
                            </a>
                        @endif
                    </div>
                    <div>
                        @foreach($moreProductFromSeller as $item)
                            @include('web-views.partials._seller-products-product-details',['product' => $item, 'decimal_point_settings' => $decimalPointSettings])
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if (count($relatedProducts)>0)
            <div class="container rtl text-align-direction">
                <div class="card __card border-0 mb-4">
                    <div class="card-body">
                        <div class="row flex-between align-items-center mb-2">
                            <div class="ms-1">
                                <h2 class="text-capitalize font-bold fs-16 mb-0">{{ translate('similar_products') }}</h2>
                            </div>
                            <div class="view_all d-flex justify-content-center align-items-center">
                                <div>
                                    @if(!empty($product?->category?->slug))
                                        <a class="text-capitalize view-all-btn-text me-1"
                                           href="{{ route('category-products', ['slug' => $product?->category?->slug]) }}">
                                            <span class="view-btn-text">{{ translate('View_All') }}</span>
                                            <i class="czi-arrow-{{ session('direction') === "rtl" ? 'left' : 'right' }}"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            @foreach($relatedProducts as $key => $relatedProduct)
                                <div class="col-xl-2 col-sm-3 col-6">
                                    @include('web-views.partials._inline-single-product-without-eye',['product'=>$relatedProduct,'decimal_point_settings'=>$decimalPointSettings])
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="modal fade rtl text-align-direction" id="show-modal-view" tabindex="-1" role="dialog"
             aria-labelledby="show-modal-image"
             aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-body flex justify-content-center">
                        <button class="btn btn-default __inline-33 dir-end-minus-7px"
                                data-dismiss="modal">
                            <i class="fa fa-close"></i>
                        </button>
                        <img class="element-center" id="attachment-view" src="" alt="">
                    </div>
                </div>
            </div>
        </div>

    </div>

    @include("web-views.products._product-details-sticky", ['productDetails' => $product])

    @if($product?->preview_file_full_url['path'])
        @include('web-views.partials._product-preview-modal', ['previewFileInfo' => $previewFileInfo])
    @endif

    @include('layouts.front-end.partials.modal._chatting',['seller'=>$product->seller, 'user_type'=>$product->added_by])

    <span id="route-review-list-product" data-url="{{ route('review-list-product') }}"></span>
    <span id="products-details-page-data" data-id="{{ $product['id'] }}"></span>
    <span id="all-msg-container" data-afterextend="{{translate('see_less')}}" data-seemore="{{translate('see_more')}}"></span>
@endsection

@push('script')
    <script>
        $(document).on('change', 'input[name="color"]', function() {
            const selectedColor = $(this).val();
            const colors = @json(json_decode($product->colors));
            console.log(colors);
            const colorIndex = colors.indexOf(selectedColor);
            if (colorIndex !== -1) {
                const mainSwiper = document.querySelector('.quickviewSlider2').swiper;
                const thumbSwiper = document.querySelector('.quickviewSliderThumb2').swiper;
                mainSwiper.slideTo(colorIndex);
                thumbSwiper.slideTo(colorIndex);
            }
        });
    </script>
    <script src="{{ theme_asset(path: 'public/assets/front-end/js/product-details.js') }}"></script>
    <script type="text/javascript" async="async"
            src="https://platform-api.sharethis.com/js/sharethis.js#property=5f55f75bde227f0012147049&product=sticky-share-buttons"></script>

    <script>
        $(document).ready(function () {

            function applyShowMoreLogic() {
                $(".tab-pane.active").each(function () {
                    let content = $(this).find(".details-content-wrap");
                    let button  = $(this).find(".see-more-details");

                    if (!content.length || !button.length) return;

                    if (content[0].scrollHeight > 280) {
                        content.addClass(" active");
                        button.show();
                    } else {
                        content.removeClass(" active");
                        button.hide();
                    }
                });
            }

            applyShowMoreLogic();

            $(document).on("shown.bs.tab", ".tab_link", function () {
                applyShowMoreLogic();
            });

            $(document).on("click", ".see-more-details", function () {
                let tabPane = $(this).closest(".tab-pane");
                let content = tabPane.find(".details-content-wrap");
                let productDetailsDescription = tabPane.find(".p-details-description");

                content.toggleClass(" active");

                if (content.hasClass("active")) {
                    $(this).html($("#all-msg-container").data("seemore"));
                    productDetailsDescription.removeClass('overflow-y-auto');
                } else {
                    $(this).html($("#all-msg-container").data("afterextend"));
                    productDetailsDescription.addClass('overflow-y-auto');
                }
            });

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

        // Initial digital variant setup on page ready
        $(document).ready(function() {
            let activeRadio = $('input[type="radio"][name="variant_key"]:checked');
            if (activeRadio.length) {
                activeRadio.first().trigger('change');
            }
        });
    </script>
@endpush
