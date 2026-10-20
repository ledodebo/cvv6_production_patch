@php
    $overallRating = getOverallRating($product?->reviews);
    $rating = getRating($product->reviews);
    $productReviews = \App\Utils\ProductManager::get_product_review($product->id);
@endphp


<style>
    .product-country-pills-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .product-country-pill-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        padding: 6px 12px !important;
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
        border-color: var(--web-primary, #045cff) !important;
        color: var(--web-primary, #045cff) !important;
        background: #f8fafc !important;
    }
    .product-country-pill-btn.active {
        border-color: var(--web-primary, #045cff) !important;
        background: var(--web-primary, #045cff) !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(4, 92, 255, 0.3) !important;
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
        border-color: var(--web-primary, #045cff);
        box-shadow: 0 0 0 3px rgba(4, 92, 255, 0.15);
    }
    .package-pill-wrapper .variant-pill-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 8px;
        border: 1.5px solid #e2e8f0;
        background: #f8fafc;
        color: #334155;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .package-pill-wrapper input[type="radio"]:checked + .variant-pill-btn {
        border-color: var(--web-primary, #045cff);
        background: rgba(4, 92, 255, 0.08);
        color: var(--web-primary, #045cff);
        font-weight: 600;
    }
    .variant-price-tag {
        font-size: 11px;
        background: #e2e8f0;
        color: #1e293b;
        padding: 1px 6px;
        border-radius: 6px;
        font-weight: 700;
    }
    .package-pill-wrapper input[type="radio"]:checked + .variant-pill-btn .variant-price-tag {
        background: var(--web-primary, #045cff);
        color: #fff;
    }
</style>

<div class="modal-body rtl">
    <div class="d-flex justify-content-end pb-2 mt-2">
        <button class="close close-quick-view-modal z-index-99 p-0 pe-0 ps-0 w-24 h-24px rounded-circle bg-light" type="button" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <div class="row g-3">
        <div class="col-lg-5 col-md-4 col-12">

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
                                class="btn __text-18px border wishList-pos-btn d-sm-none product-action-add-wishlist position-static rounded-circle">
                            <i class="fa {{($wishlist_status == 1?'fa-heart':'fa-heart-o')}} wishlist_icon_{{$product['id']}} web-text-primary"
                               id="wishlist_icon_{{$product['id']}}" aria-hidden="true"></i>
                            <div class="wishlist-tooltip" x-placement="top">
                                <div class="arrow"></div>
                                <div class="inner">
                                    <span class="add">{{translate('added_to_wishlist')}}</span>
                                    <span class="remove">{{translate('removed_from_wishlist')}}</span>
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

        <div class="col-lg-7 col-md-8 col-12 mt-md-0 mt-sm-3 web-direction">
            <div class="details __h-100 product-cart-option-container border-0 py-0">
                @if (getWebConfig(name: 'business_mode') == 'multi')
                    @if($product->added_by =="admin")
                        <a href="{{route('vendor-shop',['slug'=> getInHouseShopConfig(key:'slug')])}}" class="d-block pb-2 text-truncate">{{getInHouseShopConfig('name')?? ""}}</a>
                    @else
                        <a href="{{route('vendor-shop',['slug'=> $product->seller?->shop?->slug])}}" class="d-block pb-2 text-truncate">{{$product->seller?->shop?->name ?? ""}}</a>
                    @endif
                @endif

                @php
                    $qvResolvedData = function_exists('resolveDigitalProductExtensionsAndSiblings')
                        ? resolveDigitalProductExtensionsAndSiblings($product)
                        : [
                            'extensions' => is_string($product->digital_product_extensions) ? (json_decode($product->digital_product_extensions, true) ?? []) : ($product->digital_product_extensions ?? []),
                            'variations' => $product->digitalVariation ? $product->digitalVariation->keyBy('variant_key') : collect(),
                            'siblings_by_country' => collect(),
                            'is_sibling_grouped' => false,
                        ];
                    $qvDisplayTitle = ($qvResolvedData['is_sibling_grouped'] ?? false) ? trim(preg_replace('/\s*\([^)]*\)?\s*$/', '', $product->name)) : $product->name;
                @endphp
                <a href="{{route('product',$product->slug)}}" class="fs-18 fw-bold text-title mb-3">{{$qvDisplayTitle}}</a>
                <div class="d-flex flex-wrap align-items-baseline gap-3 mb-3 pro">
                    @if($overallRating[0] != 0)
                        <div class="d-flex gap-1 align-items-baseline">
                            <div class="star-rating">
                                @for($inc=0;$inc<5;$inc++)
                                    @if($inc<$overallRating[0])
                                        <i class="sr-star czi-star-filled m-0 active"></i>
                                    @else
                                        <i class="sr-star czi-star m-0"></i>
                                    @endif
                                @endfor
                            </div>
                            <span class="d-inline-block  align-middle mt-1 fs-14 text-muted">({{$overallRating[0]}})</span>
                        </div>
                        <span class="font-wreight-normal fs-14 font-for-tab d-inline-block font-size-sm text-body align-middle">
                            <span class="web-text-primary fw-semibold">{{$overallRating[1]}}</span> {{translate('reviews')}}</span>
                        <span class="border-middle-14px"></span>
                    @endif
                    <span
                        class="font-wreight-normal fs-14 font-for-tab d-inline-block font-size-sm text-body align-middle">
                        <span class="web-text-primary fw-semibold">
                            {{$countOrder}}
                        </span> {{translate('orders')}}   </span>
                    <span class="border-middle-14px"></span>
                    <span
                        class="font-wreight-normal fs-14 font-for-tab d-inline-block font-size-sm text-body align-middle text-capitalize">
                        <span class="web-text-primary fw-semibold countWishlist-{{ $product->id }}"> {{$countWishlist}}</span> {{translate('wish_listed')}}
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
                                <span
                                    class="text-capitalize digital-product-author-title">{{ translate('Author') }} :</span>
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
                <form class="addToCartDynamicForm add-to-cart-details-form d-flex flex-column gap-3">
                    @csrf

                    <div>
                        <span class="font-weight-normal text-accent d-flex align-items-center gap-2">
                            {!! getPriceRangeWithDiscount(product: $product) !!}
                        </span>
                    </div>

                    <input type="hidden" name="id" value="{{ $product->id }}">
                    <div class="position-relative {{count(json_decode($product->colors)) > 0 ? '' : 'd-none'}}">
                        @if (count(json_decode($product->colors)) > 0)
                            <div class="flex-start align-items-center gap-3 mt-1 mb-2">
                                <div class="product-description-label __color-9B9B9B fs-14 text-nowrap">
                                    {{translate('color')}}:
                                </div>
                                <div class="">
                                    <ul class="flex-start checkbox-color mb-0 p-0 list-inline gap-2">
                                        @foreach (json_decode($product->colors) as $key => $color)
                                            <li>
                                                <input type="radio"
                                                       id="{{ $product->id }}-color-{{ str_replace('#','',$color) }}"
                                                       name="color" value="{{ $color }}"
                                                       @if($key == 0) checked @endif>
                                                <label style="background: {{ $color }};"
                                                       class="quick-view-preview-image-by-color shadow-border"
                                                       for="{{ $product->id }}-color-{{ str_replace('#','',$color) }}"
                                                       data-toggle="tooltip"
                                                       data-key="{{ str_replace('#','',$color) }}"
                                                       data-title="{{ getColorNameByCode(code: $color) }}">
                                                    <span class="outline"></span>
                                                </label>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        @php
                            $qty = 0;
                            foreach (json_decode($product->variation) as $key => $variation) {
                                $qty += $variation->qty;
                            }
                        @endphp

                    </div>

                    @if($product['product_type'] != 'digital' || empty($product['digital_product_extensions']))
                        @foreach (json_decode($product->choice_options) as $key => $choice)
                            <div class="flex-start gap-3">
                                <div class="product-description-label __color-9B9B9B fs-14 mt-1 text-capitalize text-nowrap">
                                    {{ $choice->title }}:
                                </div>
                                <div>
                                    <ul class="checkbox-alphanumeric checkbox-alphanumeric--style-1 p-0 mt-1">
                                        @foreach ($choice->options as $index => $option)
                                            <span>
                                                <input type="radio" id="{{ $choice->name }}-{{ $option }}"
                                                       name="{{ $choice->name }}"
                                                       value="{{ $option }}" @if($index==0) checked @endif>
                                                <label class="user-select-none"
                                                       for="{{ $choice->name }}-{{ $option }}">
                                                        <span class="text-nowrap max-w-180 line--limit-1">{{ $option }}</span>
                                                    </label>
                                            </span>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    @php
                        $qvExtensions = $qvResolvedData['extensions'];
                        $quickViewDigitalVariationsKeyed = $qvResolvedData['variations'];
                        $qvSiblingsByCountry = $qvResolvedData['siblings_by_country'];

                        $hasQvCountryVars = is_array($qvExtensions) && count($qvExtensions) > 1;
                        if (is_array($qvExtensions) && count($qvExtensions) === 1) {
                            $firstQvK = array_key_first($qvExtensions);
                            if ($firstQvK && !in_array(strtolower(trim($firstQvK)), ['package', 'default', 'options', 'variation', 'variations'])) {
                                $hasQvCountryVars = true;
                            }
                        }
                        $firstQvRegion = is_array($qvExtensions) ? array_key_first($qvExtensions) : null;
                    @endphp

                    @if($product['product_type'] == 'digital' && is_array($qvExtensions) && count($qvExtensions) > 0)
                        <div id="digital-product-extensions-container">
                            @if($hasQvCountryVars)
                                <div class="qv-country-selector-group mb-3">
                                    <label for="qv-product-country-select" class="product-description-label text-body fs-14 text-capitalize text-nowrap mb-1 fw-semibold d-block">
                                        <span class="me-1">🌐</span>{{ translate('Country_/_Region') }}:
                                    </label>
                                    <select id="qv-product-country-select" class="form-control form-select product-country-select product-region-select-box">
                                        @foreach($qvExtensions as $countryName => $extensionGroup)
                                            @php
                                                $countrySlug = Str::slug($countryName);
                                                $flag = function_exists('getCountryFlagEmoji') ? getCountryFlagEmoji($countryName) : '🌐';
                                                $count = is_array($extensionGroup) ? count($extensionGroup) : 0;
                                                $isFirstCountry = ($countryName === $firstQvRegion);
                                                $siblingObj = $qvSiblingsByCountry->get($countryName);
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

                            <div class="qv-package-variations-group mb-2">
                                <div class="product-description-label text-body fs-14 text-capitalize text-nowrap mb-1">
                                    {{ translate('Package') }}:
                                </div>
                                @foreach($qvExtensions as $countryName => $extensionGroup)
                                    @php
                                        $countrySlug = Str::slug($countryName);
                                        $isFirstCountry = ($countryName === $firstQvRegion);
                                        $currSibling = $qvSiblingsByCountry->get($countryName) ?? $product;
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
                                                        $currVar = $quickViewDigitalVariationsKeyed->get($currVariantKey)
                                                            ?? $quickViewDigitalVariationsKeyed->get($countryName . '-' . $currCleanOpt)
                                                            ?? $quickViewDigitalVariationsKeyed->get($currProductId . '_' . $currVariantKey)
                                                            ?? null;
                                                        if (!$currVar && isset($quickViewDigitalVariationsKeyed[$currCleanOpt])) {
                                                            $currVar = $quickViewDigitalVariationsKeyed[$currCleanOpt];
                                                        }
                                                        if ($currVar && !empty($currVar->variant_key)) {
                                                            $currVariantKey = $currVar->variant_key;
                                                        }
                                                        $currPrice = $currVar ? $currVar->price : null;
                                                        $currPriceFormatted = $currPrice !== null ? webCurrencyConverter($currPrice) : null;
                                                        $uniqueQvRadioId = 'qv_ext_' . Str::slug($countryName) . '_' . Str::slug($currCleanOpt) . '_' . $index;
                                                        $isChecked = ($isFirstCountry && $loop->first);
                                                    @endphp
                                                    <div class="package-pill-wrapper d-inline-block">
                                                        <input type="radio"
                                                               class="package-variant-radio d-none"
                                                               id="{{ $uniqueQvRadioId }}"
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
                                                        <label for="{{ $uniqueQvRadioId }}"
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
                        </div>
                    @endif

                    <div class="mb-3">
                        <div class="product-quantity d-flex flex-column">
                            <div class="d-flex align-items-center gap-3 mb-30">
                                <div class="product-description-label __color-9B9B9B fs-14 mt-0 text-nowrap">
                                    {{ translate('Qty') }} :
                                </div>
                                <div
                                    class="d-flex justify-content-between align-items-center quantity-box w-130px h-40px overflow-hidden rounded border border-base web-text-primary">
                                    <span class="input-group-btn h-100">
                                        <button class="btn btn-number __p-10 web-text-primary bg-ECF1F6 rounded-0 w-32px" type="button"
                                                data-type="minus"
                                                data-field="quantity"
                                                disabled="disabled">
                                            -
                                        </button>
                                    </span>
                                    <input type="text" name="quantity"
                                           class="form-control input-number text-center product-details-cart-qty __inline-29 border-0 w-100"
                                           placeholder="{{ translate('1') }}"
                                           value="{{ $initialProductConfig['quantity'] ?? 1 }}"
                                           data-producttype="{{ $product->product_type }}"
                                           min="{{ $product->minimum_order_qty ?? 1 }}"
                                           max="{{$product['product_type'] == 'physical' ? $product->current_stock : 100}}">
                                    <span class="input-group-btn h-100">
                                        <button class="btn btn-number __p-10 web-text-primary bg-ECF1F6 rounded-0 w-32px" type="button"
                                                data-producttype="{{ $product->product_type }}"
                                                data-type="plus" data-field="quantity">
                                            +
                                        </button>
                                    </span>
                                </div>
                                <input type="hidden" class="product-generated-variation-code"
                                       name="product_variation_code" data-product-id="{{ $product['id'] }}">
                                <input type="hidden" value="" class="product-exist-in-cart-list form-control w-50"
                                       name="key">
                            </div>
                            <div class="product-details-chosen-price-section">
                                <div class="d-flex justify-content-start align-items-center me-2">
                                    <div class="product-description-label text-dark font-bold text-capitalize">
                                        <strong>{{translate('total_price')}}</strong> :
                                    </div>
                                    &nbsp; <strong class="text-base product-details-chosen-price-amount">
                                        {{ webCurrencyConverter(amount: $initialProductConfig['total_quantity_price']) }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php($guestCheckout = getWebConfig(name: 'guest_checkout'))

                    <div
                        class="__btn-grp align-items-center product-add-and-buy-section" {!! $firstVariationQuantity <= 0 ? 'style="display: none;"' : '' !!}>
                        @if(($product->added_by == 'admin' && (checkVendorAbility(type: 'inhouse', status: 'temporary_close') || checkVendorAbility(type: 'inhouse', status: 'vacation_status'))) || ($product->added_by == 'seller' && (checkVendorAbility(type: 'vendor', status: 'temporary_close', vendor: $product->seller->shop) || checkVendorAbility(type: 'vendor', status: 'vacation_status', vendor: $product->seller->shop))))
                            <button class="btn btn-secondary" type="button" disabled>
                                {{translate('buy_now')}}
                            </button>

                            <button class="btn btn--primary string-limit" type="button" disabled>
                                {{translate('add_to_cart')}}
                            </button>
                        @else
                            <button class="btn btn-secondary product-buy-now-button"
                                    type="button"
                                    data-form=".add-to-cart-details-form"
                                    data-auth="{{( getWebConfig(name: 'guest_checkout') == 1 || Auth::guard('customer')->check() ? 'true':'false')}}"
                                    data-route="{{ route('shop-cart') }}"
                            >
                                {{translate('buy_now')}}
                            </button>
                            <button class="btn btn--primary string-limit product-add-to-cart-button"
                                    type="button"
                                    data-form=".add-to-cart-details-form"
                                    data-update="{{ translate('update_cart') }}"
                                    data-add="{{ translate('add_to_cart') }}"
                            >
                                {{ $initialProductConfig['first_variant_in_cart'] ? translate('update_cart') : translate('add_to_cart') }}
                            </button>
                        @endif

                        <button type="button" data-product-id="{{$product['id']}}"
                                class="btn __text-18px border product-action-add-wishlist">
                            <i class="fa {{($wishlist_status == 1?'fa-heart':'fa-heart-o')}} wishlist_icon_{{$product['id']}} web-text-primary"
                               id="wishlist_icon_{{$product['id']}}" aria-hidden="true"></i>
                            <span class="fs-14 text-muted align-bottom countWishlist-{{$product['id']}}">
                                {{$countWishlist}}
                            </span>
                            <div class="wishlist-tooltip" x-placement="top">
                                <div class="arrow"></div>
                                <div class="inner">
                                    <span class="add">{{translate('added_to_wishlist')}}</span>
                                    <span class="remove">{{translate('removed_from_wishlist')}}</span>
                                </div>
                            </div>
                        </button>
                    </div>

                    @if(($product['product_type'] == 'physical'))
                        <div
                            class="product-restock-request-section collapse" {!! $firstVariationQuantity <= 0 ? 'style="display: block;"' : '' !!}>
                            <button type="button"
                                    class="btn request-restock-btn btn-outline-primary fw-semibold product-restock-request-button me-2"
                                    data-auth="{{ auth('customer')->check() }}"
                                    data-form=".addToCartDynamicForm"
                                    data-default="{{ translate('Request_Restock') }}"
                                    data-requested="{{ translate('Request_Sent') }}"
                            >
                                {{ translate('Request_Restock') }}
                            </button>
                            <button type="button" data-product-id="{{$product['id']}}"
                                    class="btn __text-18px border product-action-add-wishlist">
                                <i class="fa {{($wishlist_status == 1?'fa-heart':'fa-heart-o')}} wishlist_icon_{{$product['id']}} web-text-primary"
                                   id="wishlist_icon_{{$product['id']}}" aria-hidden="true"></i>
                                <span class="fs-14 text-muted align-bottom countWishlist-{{$product['id']}}">
                                {{$countWishlist}}
                            </span>
                                <div class="wishlist-tooltip" x-placement="top">
                                    <div class="arrow"></div>
                                    <div class="inner">
                                        <span class="add">{{translate('added_to_wishlist')}}</span>
                                        <span class="remove">{{translate('removed_from_wishlist')}}</span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    @endif

                   <div>
                       @if($product->added_by == 'admin')
                           @if(checkVendorAbility(type: 'inhouse', status: 'temporary_close') || checkVendorAbility(type: 'inhouse', status: 'vacation_status'))
                               <div class="alert alert-danger" role="alert">
                                   {{ translate('this_shop_is_temporary_closed_or_on_vacation._You_cannot_add_product_to_cart_from_this_shop_for_now') }}
                               </div>
                           @endif
                       @elseif($product->added_by == 'seller')
                           @if(checkVendorAbility(type: 'vendor', status: 'temporary_close', vendor: $product->seller->shop) || checkVendorAbility(type: 'vendor', status: 'vacation_status', vendor: $product->seller->shop))
                               <div class="alert alert-danger" role="alert">
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

<script type="text/javascript">
    "use strict";
    productQuickViewFunctionalityInitialize();
</script>

<script type="text/javascript" async="async"
        src="https://platform-api.sharethis.com/js/sharethis.js#property=5f55f75bde227f0012147049&product=sticky-share-buttons"></script>

<script>
    function initSliderWithZoom() {
        $(".easyzoom").each(function () {
            $(this).easyZoom();
        });

        new Swiper(".quickviewSlider2", {
            slidesPerView: 1,
            spaceBetween: 10,
            loop: false,
            thumbs: {
                swiper: new Swiper(".quickviewSliderThumb2", {
                    spaceBetween: 10,
                    slidesPerView: 'auto',
                    watchSlidesProgress: true,
                    navigation: {
                        nextEl: ".swiper-quickview-button-next",
                        prevEl: ".swiper-quickview-button-prev",
                    },
                }),
            },
        });
    }

    initSliderWithZoom();

    $(document).on('change', 'input[name="color"]', function() {
        const selectedColor = $(this).val();
        const colors = @json(json_decode($product->colors));
        const colorIndex = colors.indexOf(selectedColor);
        if (colorIndex !== -1) {
            const mainSwiper = document.querySelector('.quickviewSlider2').swiper;
            const thumbSwiper = document.querySelector('.quickviewSliderThumb2').swiper;
            mainSwiper.slideTo(colorIndex);
            thumbSwiper.slideTo(colorIndex);
        }
    });
</script>
