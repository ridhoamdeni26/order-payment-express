<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{

    public function index(Request $request)
    {
        $userId = $request->input('user_id');
        $orders = Order::query();

        // filter user id check user id
        $orders->when($userId, function($query) use ($userId) {
            return $query->where('user_id', '=', $userId);
        });

        return response()->json([
            'status' => 'Success',
            'data' => $orders->get()
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->input('user');
        $course = $request->input('course');

        // simpan di db
        $order = Order::create([
            'user_id' => $user['id'],
            'course_id' => $course['id']
        ]);

        $transactionDetails = [
            'order_id' => $order->id,
            'gross_amount' => $course['price']
        ];

        $itemDetails = [
            [
                'id' => $course['id'],
                'price' => $course['price'],
                'quantity' => 1,
                'name' => $course['name'],
                'brand' => 'Test Course This Obtional',
                'category' => 'Online Corse'
            ]
        ];

        $costomerDetails = [
            'first_name' => $user['name'],
            'email' => $user['email']
        ];

        // snap url to midtrans
        $midtransParams = [
            'transaction_details' => $transactionDetails,
            'item_details' => $itemDetails,
            'customer_details' => $costomerDetails
        ];

        $midtransSnapUrl = $this->getMidtransSnapUrl($midtransParams);

        // update snap_url untuk direct halaman
        $order->snap_url = $midtransSnapUrl;

        // update untuk metadata
        $order->metadata = [
            'course_id' => $course['id'],
            'course_price' => $course['price'],
            'course_name' => $course['name'],
            'course_thumbnail' => $course['thumbnail'],
            'course_level' => $course['level']
        ];

        $order->save();

        return response()->json([
            'status' => 'Success',
            'data' => $order
        ]);

    }

    private function getMidtransSnapUrl($params)
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');
        \Midtrans\Config::$is3ds = (bool) env('MIDTRANS_3DS');

        // get front to redirect url
        $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
        return $snapUrl;
        
        // $params = array(
        //     'transaction_details' => array(
        //         'order_id' => rand(),
        //         'gross_amount' => 10000,
        //     ),
        //     'customer_details' => array(
        //         'first_name' => 'budi',
        //         'last_name' => 'pratama',
        //         'email' => 'budi.pra@example.com',
        //         'phone' => '08111222333',
        //     ),
        // );
        
        // $snapToken = \Midtrans\Snap::getSnapToken($params);
    }
}
