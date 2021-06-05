<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\paymentLog;

class WebhookController extends Controller
{
    public function midtransHandler(Request $request)
    {
        $data = $request->all();

        $signatureKey = $data['signature_key'];

        $orderId = $data['order_id'];
        $statusCode = $data['status_code'];
        $grossAmount = $data['gross_amount'];
        $serverKey = env('MIDTRANS_SERVER_KEY');

        // CHECK APAKAH SIGNATURE BENER APA TIDAK
        $mySignatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $transactionStatus = $data['transaction_status'];
        $type = $data['payment_type'];
        $fraudStatus = $data['fraud_status'];

        // check valid signature key from midtrans
        if ($signatureKey !== $mySignatureKey) {
            return response()->json([
                'status' => 'Error',
                'message' => 'Invalid Signature'
            ], 400);
        }

        // check order id 
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json([
                'status' => 'Error',
                'message' => 'Order ID Not Found'
            ], 404);
        }

        // Jika status success maka data tidak di apa apain lagi
        if ($order->status == 'success') {
            return response()->json([
                'status' => 'Error',
                'message' => 'Operation No Permitted'
            ], 405);
        }

        // set status pembayaran
        if ($transactionStatus == 'capture'){
            if ($fraudStatus == 'challenge'){
                $order->status = 'challenge';
            } else if ($fraudStatus == 'accept'){
                $order->status = 'success';
            }
        } else if ($transactionStatus == 'settlement'){
            $order->status = 'success';
        } else if ($transactionStatus == 'cancel' ||
            $transactionStatus == 'deny' ||
            $transactionStatus == 'expire'){
                $order->status = 'failure';
        } else if ($transactionStatus == 'pending'){
            $order->status = 'pending';
        }

        $logData = [
            'status' => $transactionStatus,
            'raw_response' => json_encode($data),
            'order_id' => $orderId,
            'payment_type' => $type
        ];

        PaymentLog::create($logData);
        $order->save();

        if ($order->status === 'success') {
            createPremiumAccess([
                'user_id' => $order->user_id,
                'course_id' => $order->course_id
            ]);

            // echo "<pre>".print_r($check)."</pre>";
        }

        return response()->json('Ok');
    }
}
