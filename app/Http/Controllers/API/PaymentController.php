<?php

namespace App\Http\Controllers\API;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\PaymentLog;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class PaymentController extends Controller
{
    private const DEFAULT_CURRENCY = 'IDR';

    private const PAYMENT_EXPIRY_HOURS = 24;

    private const ACTIVE_PAYMENT_STATUSES = ['pending', 'waiting_payment'];

    private const PAYMENT_RELATIONS = [
        'order.items',
        'order.shop',
        'order.shippingAddress',
        'logs',
    ];

    /**
     * Get payments for the authenticated user
     *
     * Returns payments for orders where the user is the buyer or the shop owner.
     * Optional filters: order_id, status.
     */
    public function getPayments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'nullable|uuid|exists:orders,id',
                'status' => 'nullable|string|in:pending,waiting_payment,paid,failed,expired,cancelled,refunded',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $userId = auth()->id();

            $query = Payment::with(self::PAYMENT_RELATIONS)
                ->whereHas('order', function ($orderQuery) use ($userId) {
                    $orderQuery->where('user_id', $userId)
                        ->orWhere('shop_id', $userId);
                })
                ->latest();

            if ($request->filled('order_id')) {
                $query->where('order_id', $request->order_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $payments = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Payments fetched successfully',
                'data' => $payments->map(fn (Payment $payment) => $this->formatPaymentPayload($payment)),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payments',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Show payment by ID
     *
     * @bodyParam id uuid required Payment ID. Example: 123e4567-e89b-12d3-a456-426614174000
     */
    #[BodyParameter('id', description: 'Payment ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function showPayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:payments,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payment = $this->findUserPayment(auth()->id(), $request->id);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            $this->expirePaymentIfNeeded($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment fetched successfully',
                'data' => $this->formatPaymentPayload($payment->fresh()->load(self::PAYMENT_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to show payment',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Show payment by payment number
     *
     * @bodyParam payment_number string required Payment number. Example: PAY-20260530120000-ABC123
     */
    #[BodyParameter('payment_number', description: 'Payment number.', type: 'string', example: 'PAY-20260530120000-ABC123')]
    public function showPaymentByNumber(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_number' => 'required|string|exists:payments,payment_number',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payment = Payment::with(self::PAYMENT_RELATIONS)
                ->where('payment_number', $request->payment_number)
                ->whereHas('order', function ($orderQuery) {
                    $userId = auth()->id();
                    $orderQuery->where('user_id', $userId)
                        ->orWhere('shop_id', $userId);
                })
                ->first();

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            $this->expirePaymentIfNeeded($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment fetched successfully',
                'data' => $this->formatPaymentPayload($payment->fresh()->load(self::PAYMENT_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to show payment',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a payment for an order
     *
     * Initiates payment for an unpaid order. Reuses an active pending payment when available.
     *
     * @bodyParam order_id uuid required Order ID. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam payment_method string optional e.g. bank_transfer, ewallet, qris. Example: bank_transfer
     * @bodyParam payment_channel string optional e.g. bca_va, gopay. Example: bca_va
     * @bodyParam provider string optional Payment gateway name. Example: manual
     * @bodyParam fee_amount numeric optional Transaction fee. Example: 0
     */
    #[BodyParameter('order_id', description: 'Order ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('payment_method', description: 'Payment method.', type: 'string', example: 'bank_transfer')]
    #[BodyParameter('payment_channel', description: 'Payment channel.', type: 'string', example: 'bca_va')]
    #[BodyParameter('provider', description: 'Payment provider.', type: 'string', example: 'manual')]
    #[BodyParameter('fee_amount', description: 'Transaction fee.', type: 'numeric', example: 0)]
    public function storePayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|uuid|exists:orders,id',
                'payment_method' => 'nullable|string|max:50',
                'payment_channel' => 'nullable|string|max:100',
                'provider' => 'nullable|string|max:100',
                'fee_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = auth()->user();
            $order = $this->findBuyerOrder($user->id, $request->order_id);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            if (in_array($order->payment_status, ['paid', 'refunded', 'partial_refunded'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order is already paid or refunded',
                ], 422);
            }

            if ((float) $order->grand_total <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order grand total must be greater than zero',
                ], 422);
            }

            DB::beginTransaction();

            $existingPayment = Payment::where('order_id', $order->id)
                ->whereIn('status', self::ACTIVE_PAYMENT_STATUSES)
                ->latest()
                ->first();

            if ($existingPayment) {
                $this->expirePaymentIfNeeded($existingPayment);

                if (in_array($existingPayment->fresh()->status, self::ACTIVE_PAYMENT_STATUSES, true)) {
                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Active payment already exists',
                        'data' => $this->formatPaymentPayload($existingPayment->load(self::PAYMENT_RELATIONS)),
                    ], 200);
                }
            }

            $feeAmount = (float) ($request->fee_amount ?? 0);
            $amount = (float) $order->grand_total;
            $netAmount = max($amount - $feeAmount, 0);

            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_number' => $this->generatePaymentNumber(),
                'payment_method' => $request->payment_method,
                'payment_channel' => $request->payment_channel,
                'provider' => $request->provider ?? 'manual',
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'net_amount' => $netAmount,
                'currency' => $order->currency ?? self::DEFAULT_CURRENCY,
                'status' => 'pending',
                'expired_at' => now()->addHours(self::PAYMENT_EXPIRY_HOURS),
                'payment_url' => null,
                'va_number' => null,
                'qr_string' => null,
            ]);

            $order->update(['payment_status' => 'pending']);

            $this->recordPaymentLog($payment, 'payment.created', null, 'pending', [
                'order_id' => $order->id,
                'payment_number' => $payment->payment_number,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => $this->formatPaymentPayload($payment->load(self::PAYMENT_RELATIONS)),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update payment instructions (URL, VA, QR)
     *
     * Used after the payment gateway responds, or for manual payment setup.
     *
     * @bodyParam id uuid required Payment ID.
     * @bodyParam status string optional pending|waiting_payment|paid|failed|expired|cancelled|refunded
     * @bodyParam payment_url string optional Redirect URL for payment page.
     * @bodyParam va_number string optional Virtual account number.
     * @bodyParam qr_string string optional QRIS string.
     * @bodyParam provider_reference string optional External reference from gateway.
     * @bodyParam raw_response object optional Raw gateway response.
     */
    #[BodyParameter('id', description: 'Payment ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('status', description: 'Payment status.', type: 'string', example: 'waiting_payment')]
    #[BodyParameter('payment_url', description: 'Payment page URL.', type: 'string', example: 'https://payment.example.com/pay/xxx')]
    #[BodyParameter('va_number', description: 'Virtual account number.', type: 'string', example: '1234567890')]
    #[BodyParameter('qr_string', description: 'QRIS payload.', type: 'string', example: '000201010212...')]
    #[BodyParameter('provider_reference', description: 'Gateway reference ID.', type: 'string', example: 'INV-123')]
    public function updatePayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:payments,id',
                'status' => 'nullable|string|in:pending,waiting_payment,paid,failed,expired,cancelled,refunded',
                'payment_url' => 'nullable|string',
                'va_number' => 'nullable|string|max:100',
                'qr_string' => 'nullable|string',
                'provider_reference' => 'nullable|string|max:255',
                'raw_response' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payment = $this->findUserPayment(auth()->id(), $request->id);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            if (in_array($payment->status, ['paid', 'cancelled', 'refunded'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment can no longer be updated',
                ], 422);
            }

            DB::beginTransaction();

            $oldStatus = $payment->status;
            $newStatus = $request->status ?? $payment->status;

            $payment->update([
                'status' => $newStatus,
                'payment_url' => $request->has('payment_url') ? $request->payment_url : $payment->payment_url,
                'va_number' => $request->has('va_number') ? $request->va_number : $payment->va_number,
                'qr_string' => $request->has('qr_string') ? $request->qr_string : $payment->qr_string,
                'provider_reference' => $request->has('provider_reference') ? $request->provider_reference : $payment->provider_reference,
                'raw_response' => $request->has('raw_response') ? $request->raw_response : $payment->raw_response,
            ]);

            if ($newStatus === 'waiting_payment' && $oldStatus === 'pending') {
                $payment->order?->update(['payment_status' => 'pending']);
            }

            $this->recordPaymentLog($payment, 'payment.updated', $oldStatus, $newStatus, $request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'data' => $this->formatPaymentPayload($payment->fresh()->load(self::PAYMENT_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm payment as paid
     *
     * Marks payment and order as paid. Intended for gateway callbacks or manual confirmation.
     *
     * @bodyParam id uuid required Payment ID.
     * @bodyParam provider_reference string optional Gateway reference.
     * @bodyParam raw_response object optional Raw gateway response.
     */
    #[BodyParameter('id', description: 'Payment ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('provider_reference', description: 'Gateway reference ID.', type: 'string', example: 'INV-123')]
    public function confirmPayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:payments,id',
                'provider_reference' => 'nullable|string|max:255',
                'raw_response' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payment = $this->findUserPayment(auth()->id(), $request->id);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            if ($payment->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment is already paid',
                    'data' => $this->formatPaymentPayload($payment->load(self::PAYMENT_RELATIONS)),
                ], 200);
            }

            if (! in_array($payment->status, ['pending', 'waiting_payment'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be confirmed in its current status',
                ], 422);
            }

            DB::beginTransaction();

            $oldStatus = $payment->status;

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_reference' => $request->provider_reference ?? $payment->provider_reference,
                'raw_response' => $request->raw_response ?? $payment->raw_response,
            ]);

            $payment->order?->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'order_status' => $payment->order->order_status === 'pending' ? 'confirmed' : $payment->order->order_status,
            ]);

            $this->recordPaymentLog($payment, 'payment.paid', $oldStatus, 'paid', $request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'data' => $this->formatPaymentPayload($payment->fresh()->load(self::PAYMENT_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a payment
     *
     * @bodyParam id uuid required Payment ID.
     */
    #[BodyParameter('id', description: 'Payment ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function cancelPayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:payments,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payment = $this->findUserPayment(auth()->id(), $request->id);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            if (in_array($payment->status, ['paid', 'cancelled', 'refunded'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be cancelled',
                ], 422);
            }

            DB::beginTransaction();

            $oldStatus = $payment->status;

            $payment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            if ($payment->order && $payment->order->payment_status !== 'paid') {
                $hasOtherActive = Payment::where('order_id', $payment->order_id)
                    ->where('id', '!=', $payment->id)
                    ->whereIn('status', self::ACTIVE_PAYMENT_STATUSES)
                    ->exists();

                if (! $hasOtherActive) {
                    $payment->order->update(['payment_status' => 'unpaid']);
                }
            }

            $this->recordPaymentLog($payment, 'payment.cancelled', $oldStatus, 'cancelled', $request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment cancelled successfully',
                'data' => $this->formatPaymentPayload($payment->fresh()->load(self::PAYMENT_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel payment',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status (for polling)
     *
     * @bodyParam id uuid required Payment ID.
     */
    #[BodyParameter('id', description: 'Payment ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function checkPaymentStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:payments,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payment = $this->findUserPayment(auth()->id(), $request->id);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            $wasExpired = $this->expirePaymentIfNeeded($payment);
            $payment->refresh()->load(self::PAYMENT_RELATIONS);

            return response()->json([
                'success' => true,
                'message' => $wasExpired ? 'Payment has expired' : 'Payment status checked',
                'data' => $this->formatPaymentPayload($payment),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    private function findUserPayment(string $userId, string $paymentId): ?Payment
    {
        return Payment::with(self::PAYMENT_RELATIONS)
            ->where('id', $paymentId)
            ->whereHas('order', function ($orderQuery) use ($userId) {
                $orderQuery->where('user_id', $userId)
                    ->orWhere('shop_id', $userId);
            })
            ->first();
    }

    private function findBuyerOrder(string $userId, string $orderId): ?Order
    {
        return Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->first();
    }

    private function generatePaymentNumber(): string
    {
        do {
            $number = 'PAY-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
        } while (Payment::where('payment_number', $number)->exists());

        return $number;
    }

    private function expirePaymentIfNeeded(Payment $payment): bool
    {
        if (! in_array($payment->status, self::ACTIVE_PAYMENT_STATUSES, true)) {
            return false;
        }

        if (! $payment->expired_at || $payment->expired_at->isFuture()) {
            return false;
        }

        $oldStatus = $payment->status;

        $payment->update(['status' => 'expired']);

        if ($payment->order && $payment->order->payment_status === 'pending') {
            $hasOtherActive = Payment::where('order_id', $payment->order_id)
                ->where('id', '!=', $payment->id)
                ->whereIn('status', self::ACTIVE_PAYMENT_STATUSES)
                ->exists();

            if (! $hasOtherActive) {
                $payment->order->update(['payment_status' => 'expired']);
            }
        }

        $this->recordPaymentLog($payment, 'payment.expired', $oldStatus, 'expired');

        return true;
    }

    private function recordPaymentLog(
        Payment $payment,
        string $eventType,
        ?string $oldStatus,
        ?string $newStatus,
        array $payload = [],
        array $response = []
    ): void {
        PaymentLog::create([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'provider' => $payment->provider,
            'event_type' => $eventType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'payload' => $payload ?: null,
            'response' => $response ?: null,
            'created_at' => now(),
        ]);
    }

    private function formatPaymentPayload(Payment $payment): array
    {
        $payment->loadMissing(self::PAYMENT_RELATIONS);

        $data = $payment->toArray();
        $data['is_expired'] = $payment->expired_at?->isPast() ?? false;
        $data['is_payable'] = in_array($payment->status, self::ACTIVE_PAYMENT_STATUSES, true)
            && ! ($payment->expired_at?->isPast() ?? false);
        $data['instructions'] = [
            'payment_url' => $payment->payment_url,
            'va_number' => $payment->va_number,
            'qr_string' => $payment->qr_string,
        ];

        return $data;
    }
}
