<?php

namespace App\Http\Controllers\Payment_Methods;

use App\Models\PaymentRequest;
use App\Traits\Processor;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SandboxPaymentController extends Controller
{
    use Processor;

    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Entrypoint for Sandbox payment
     */
    public function payment(Request $request): Application|JsonResponse|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        // Auto-approve or simulate gateway capture immediately for seamless local testing
        return $this->success($request);
    }

    /**
     * Mark payment as successful and trigger order generation + fulfillment hooks
     */
    public function success(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $paymentId = $request['payment_id'];
        $txnId = 'SANDBOX-' . strtoupper(Str::random(12));

        $this->payment::where(['id' => $paymentId])->update([
            'payment_method' => 'sandbox',
            'is_paid' => 1,
            'transaction_id' => $txnId,
        ]);

        $data = $this->payment::where(['id' => $paymentId])->first();

        if (isset($data) && function_exists($data->success_hook)) {
            call_user_func($data->success_hook, $data);
        }

        return $this->payment_response($data, 'success');
    }

    /**
     * Cancel sandbox payment
     */
    public function cancel(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($data) && function_exists($data->failure_hook)) {
            call_user_func($data->failure_hook, $data);
        }
        return $this->payment_response($data, 'fail');
    }
}
