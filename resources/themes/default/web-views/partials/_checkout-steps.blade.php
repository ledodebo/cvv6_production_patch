@php($currentStep = $step ?? 1)
<div class="checkout-steps-custom">
    <div class="step {{ $currentStep >= 1 ? ($currentStep > 1 ? 'completed' : 'active') : '' }}">
        <div class="step-circle fs-13">
            @if($currentStep > 1)
                <i class="czi-check"></i>
            @else
                <span>1</span>
            @endif
        </div>
        <p class="m-0 fs-16 text-dark fw-medium">{{ translate('cart_review') }}</p>
    </div>

    <div class="step {{ $currentStep >= 2 ? ($currentStep > 2 ? 'completed' : 'active') : '' }}">
        <div class="step-circle fs-13">
            @if($currentStep > 2)
                <i class="czi-check"></i>
            @else
                <span>2</span>
            @endif
        </div>
        <p class="m-0 fs-16 text-dark fw-medium">{{ translate('payment_and_confirmation') }}</p>
    </div>
</div>
