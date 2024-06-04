<?php

namespace App\Http\Controllers;


use App\Models\Transactions;
use App\Models\Tripay;
use App\Services\FonnteService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TransactionController extends Controller
{
    protected $url;
    protected $fonnteService;

    public function __construct(FonnteService $fonnteService)
    {
        $this->fonnteService = $fonnteService;
        if (Tripay::latest()->first()->is_production === 0){
            $this->url = "https://tripay.co.id/api-sandbox/";
        }elseif (Tripay::latest()->first()->is_production === 1){
            $this->url = "https://tripay.co.id/api/";
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaction = Transactions::where('trx_id',$id)->first();
        $tripay = Tripay::latest()->first();
        $data = [
            'code' => $transaction->payment_method,
            'paycode' => $transaction->no_va,
            'amount' => $transaction->amount,
            'allow_html' => 1
        ];
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tripay->api_key,
        ])->get($this->url.'payment/instruction',$data);

//        $paymentInstruction = null;
        if ($response->successful()){
            $paymentInstruction = $response->json();

//            return Inertia::render('DetailTransaction',[
//                'paymentInstruction'=> $paymentInstruction
//            ]);
        }

        return Inertia::render('DetailTransaction',[
            'transaction' => $transaction,
            'paymentInstruction' => $response->json(),
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    /**
     * @throws ConnectionException
     */
    public function createTransaction(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            // Log the incoming request data
            Log::info('Transaction request received', $request->all());

            $user_id = $request->get('user_id');
            $server_id = $request->get('server_id') ?? 'default_server_id';
            $amount = $request->get('price');
            $method_code = $request->get('method_code');
            $customer_name = $request->get('customer_name') ?? 'default_customer_name';
            $email_customer = $request->get('email_customer') ?? 'webranas@gmail.com';
            $phone_number = $request->get('phone_number') ?? '';
            $product_code = $request->get('product_code');
            $product_name = $request->get('product_name');
            $product_brand = $request->get('product_brand');
            $product_price = $request->get('product_price');
            $buyer_id = auth()->check() ? auth()->user()->id : null;

            $tripay = Tripay::latest()->first();

            if (!$tripay) {
                Log::error('API key not found');
                return redirect()->back()->with(['flash' => ['message' => 'Transaksi Gagal, Segera hubungi admin.']]);
            }

            $merchantCode = $tripay->merchant_code;
            $apiKey = $tripay->api_key;
            $privateKey = $tripay->private_key;
            $order_id = UuidController::generateCustomUuid($product_name);
            $signature = $this->generateSignature($order_id, $amount);

            $request_data = [
                'method' => $method_code,
                'merchant_ref' => $order_id,
                'amount' => $amount,
                'customer_name' => $customer_name,
                'customer_email' => $email_customer,
                'order_items' => [
                    [
                        'sku' => $product_code,
                        'name' => $product_name,
                        'price' => $product_price,
                        'quantity' => 1,
                    ]
                ],
                'expired_time' => (time() + (1 * 00 * 00)),
                'signature' => $signature,
            ];

            Log::info('Sending transaction create request to Tripay', ['request_data' => $request_data, 'url' => $this->url . 'transaction/create']);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tripay->api_key,
            ])->post($this->url . 'transaction/create', $request_data);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Transaction create response received', $responseData);

                $qr_url = null;
                $status = "pending";
                $qr_string = null;
                $no_va = null;
                $fee = $responseData['data']['total_fee'] ?? null;
                if (isset($responseData['data']['qr_url'])) {
                    $qr_url = $responseData['data']['qr_url'];
                } elseif (isset($responseData['data']['qr_string'])) {
                    $qr_string = $responseData['data']['qr_string'];
                } elseif (isset($responseData['data']['pay_code'])) {
                    $no_va = $responseData['data']['pay_code'];
                } elseif (isset($responseData['data']['status'])) {
                    if ($responseData['data']['status'] === "UNPAID") {
                        $status = "pending";
                    } elseif ($responseData['data']['status'] === "PAID") {
                        $status = "process";
                    }
                }

                $transaction = Transactions::create([
                    'trx_id' => $responseData['data']['merchant_ref'],
                    'user_id' => $user_id,
                    'buyer_id' => $buyer_id,
                    'server_id' => $server_id,
                    'user_name' => $request->get('username'),
                    'email' => $email_customer,
                    'phone_number' => $phone_number,
                    'buyer_sku_code' => $responseData['data']['order_items'][0]['sku'],
                    'product_brand' => $product_brand,
                    'product_name' => $responseData['data']['order_items'][0]['name'],
                    'product_price' => $responseData['data']['order_items'][0]['price'],
                    'amount' => $responseData['data']['amount'],
                    'fee' => $responseData['data']['total_fee'],
                    'status' => $status,
                    'digiflazz_status' => $status,
                    'payment_method' => $responseData['data']['payment_method'],
                    'payment_name' => $responseData['data']['payment_name'],
                    'payment_status' => $responseData['data']['status'],
                    'expired_time' => date('Y-m-d H:i:s', $responseData['data']['expired_time']),
                    'qr_url' => $qr_url,
                    'qr_string' => $qr_string,
                    'no_va' => $no_va,
                ]);

                Log::info('Transaction created in database', ['transaction_id' => $transaction->id]);

                try {
                    $this->fonnteService->sendMessage([
                        'target' => $phone_number,
                        'message' => 'Transaction successful for ' . $product_name,
                    ]);
                    Log::info('Message sent successfully to ' . $phone_number);
                } catch (\Exception $e) {
                    Log::error('Failed to send message: ' . $e->getMessage());
                }

                return redirect()->to(route('detail.transaction', $transaction->trx_id));
            } else {
                $responseContent = $response->json();
                $statusCode = $response->status();
                $responseHeaders = $response->headers();
                Log::error('Failed to create transaction', [
                    'status' => $statusCode,
                    'response' => $responseContent,
                    'headers' => $responseHeaders
                ]);

                if ($statusCode == 403) {
                    return redirect()->back()->with(['flash' => ['message' => 'Akses ditolak, silakan hubungi admin.']]);
                }

                return redirect()->back()->with(['flash' => ['message' => 'Transaksi gagal']]);
            }
        } catch (\Exception $e) {
            Log::error('An error occurred during transaction creation: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return redirect()->back()->with(['flash' => ['message' => 'Terjadi kesalahan, silakan coba lagi atau hubungi admin.']]);
        }
    }




    function generateOrderId($appName, $length = 8): string
    {
        // Memisahkan kata dalam nama aplikasi
        $words = explode(" ", $appName);
        $initials = '';

        // Mengambil inisial dari setiap kata
        foreach ($words as $word) {
            // Menghilangkan spasi dan karakter khusus dari setiap kata
            $word = preg_replace("/[^A-Za-z0-9]/", '', $word);
            // Mengambil $length karakter pertama dari setiap kata
            $initials .= substr($word, 0, $length);
        }

        // Menambahkan timestamp untuk membuat ID lebih unik
        $timestamp = time();

        // Menggabungkan inisial aplikasi dengan timestamp
        $orderId = $initials . $timestamp;

        return $orderId;
    }

    public function generateSignature($merchantRef,$amount): string
    {
        // Define the required parameters
        $latestTripay = Tripay::latest()->first();

        // Generate the signature
        $signature = hash_hmac('sha256', $latestTripay->merchant_code  . $merchantRef . $amount, $latestTripay->private_key);

        // Return the signature as a response
        return $signature;
    }
}
