<?php

namespace App\Controllers;

use App\Models\TransactionModel;
use App\Models\TransactionDetailModel;


class TransaksiController extends BaseController
{
    protected $cart;
    protected $client;
    protected $apiKey;
    protected $transaction;
    protected $transaction_detail;

    function __construct()
    {
        helper('number');
        helper('form');
        $this->cart = \Config\Services::cart();
        $this->client = new \GuzzleHttp\Client();
        $this->apiKey = env('COST_KEY');
        $this->transaction = new TransactionModel();
        $this->transaction_detail = new TransactionDetailModel();
    }

    public function index()
    {
        $data['items'] = $this->cart->contents();
        $data['total'] = $this->cart->total();
        return view('v_keranjang', $data);
    }

    public function cart_add()
    {
        $this->cart->insert(array(
            'id'        => $this->request->getPost('id'),
            'qty'       => 1,
            'price'     => $this->request->getPost('harga'),
            'name'      => $this->request->getPost('nama'),
            'options'   => array('foto' => $this->request->getPost('foto'))
        ));
        session()->setflashdata('success', 'Produk berhasil ditambahkan ke keranjang. (<a href="' . base_url() . 'keranjang">Lihat</a>)');
        return redirect()->to(base_url('/'));
    }

    public function cart_clear()
    {
        $this->cart->destroy();
        session()->setflashdata('success', 'Keranjang Berhasil Dikosongkan');
        return redirect()->to(base_url('keranjang'));
    }

    public function cart_edit()
    {
        $i = 1;
        foreach ($this->cart->contents() as $value) {
            $this->cart->update(array(
                'rowid' => $value['rowid'],
                'qty'   => $this->request->getPost('qty' . $i++)
            ));
        }

        session()->setflashdata('success', 'Keranjang Berhasil Diedit');
        return redirect()->to(base_url('keranjang'));
    }

    public function cart_delete($rowid)
    {
        $this->cart->remove($rowid);
        session()->setflashdata('success', 'Keranjang Berhasil Dihapus');
        return redirect()->to(base_url('keranjang'));
    }

    public function checkout()
    {
        $data['items'] = $this->cart->contents();
        $data['total'] = $this->cart->total();

        // Calculate total weight: 1000 gram per item * total quantity
        $total_weight = 0;
        foreach ($this->cart->contents() as $item) {
            $total_weight += $item['qty'] * 1000; // 1000 gram per item
        }
        $data['total_weight'] = $total_weight;

        return view('v_checkout', $data);
    }

    public function getLocation()
    {
        //keyword pencarian yang dikirimkan dari halaman checkout
        $search = $this->request->getGet('search');

        $response = $this->client->request(
            'GET',
            'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination?search=' . $search . '&limit=50',
            [
                'headers' => [
                    'accept' => 'application/json',
                    'key' => $this->apiKey,
                ],
            ]
        );

        $body = json_decode($response->getBody(), true);
        return $this->response->setJSON($body['data']);
    }

    public function getCost()
    {
        //ID lokasi yang dikirimkan dari halaman checkout
        $destination = $this->request->getGet('destination');

        // Get dynamic weight from request, fallback to 1000 if not provided
        $weight = $this->request->getGet('weight') ? $this->request->getGet('weight') : 1000;

        // Get dynamic courier from request, fallback to 'jne' if not provided
        $courier = $this->request->getGet('courier') ? $this->request->getGet('courier') : 'jne';

        //parameter daerah asal pengiriman, berat produk, dan kurir 
        //valuenya => 64999 : PEDURUNGAN TENGAH , dynamic weight, dan JNE
        $response = $this->client->request(
            'POST',
            'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost',
            [
                'multipart' => [
                    [
                        'name' => 'origin',
                        'contents' => '64999'
                    ],
                    [
                        'name' => 'destination',
                        'contents' => $destination
                    ],
                    [
                        'name' => 'weight',
                        'contents' => $weight
                    ],
                    [
                        'name' => 'courier',
                        'contents' => $courier
                    ]
                ],
                'headers' => [
                    'accept' => 'application/json',
                    'key' => $this->apiKey,
                ],
            ]
        );

        $body = json_decode($response->getBody(), true);
        return $this->response->setJSON($body['data']);
    }

    public function buy()
    {
        if ($this->request->getPost()) {
            $dataForm = [
                'username' => $this->request->getPost('username'),
                'total_harga' => $this->request->getPost('total_harga'),
                'alamat' => $this->request->getPost('alamat'),
                'ongkir' => $this->request->getPost('ongkir'),
                'no_whatsapp' => $this->request->getPost('no_whatsapp'),
                'status' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s")
            ];

            $this->transaction->insert($dataForm);

            $last_insert_id = $this->transaction->getInsertID();

            foreach ($this->cart->contents() as $value) {
                $dataFormDetail = [
                    'transaction_id' => $last_insert_id,
                    'product_id' => $value['id'],
                    'jumlah' => $value['qty'],
                    'diskon' => 0,
                    'subtotal_harga' => $value['qty'] * $value['price'],
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s")
                ];

                $this->transaction_detail->insert($dataFormDetail);
            }

            // Send WhatsApp notification
            $this->sendWhatsAppNotification($dataForm, $last_insert_id);

            $this->cart->destroy();

            session()->setFlashdata('success', 'Pesanan berhasil dibuat dan notifikasi telah dikirim ke WhatsApp Anda!');
            return redirect()->to(base_url());
        }
    }

    private function sendWhatsAppNotification($orderData, $transactionId)
    {
        // Format message
        $message = "*===== DETAIL PEMBELIAN =====*\n";
        $message .= "============================\n";
        $message .= "📦 *ID Pesanan:* #{$transactionId}\n";
        $message .= "👤 *Nama:* {$orderData['username']}\n";
        $message .= "📍 *Alamat:* {$orderData['alamat']}\n";
        $message .= "📅 *Tanggal:* " . date('d/m/Y H:i:s') . "\n\n";

        $message .= "*====== DETAIL PRODUK ======*\n";
        $message .= "============================\n";

        $subtotalHarga = 0;
        foreach ($this->cart->contents() as $item) {
            $subtotal = $item['qty'] * $item['price'];
            $subtotalHarga += $subtotal;
            $message .= "• {$item['name']}\n";
            $message .= "  Qty: {$item['qty']} x " . number_to_currency($item['price'], 'IDR') . "\n";
            $message .= "  Subtotal: " . number_to_currency($subtotal, 'IDR') . "\n\n";
        }

        $message .= "*===== RINGKASAN BIAYA =====*\n";
        $message .= "============================\n";
        $message .= "Subtotal Produk: " . number_to_currency($subtotalHarga, 'IDR') . "\n";
        $message .= "Ongkir: " . number_to_currency($orderData['ongkir'], 'IDR') . "\n";
        $message .= "============================\n";
        $message .= "💰 *TOTAL: " . number_to_currency($orderData['total_harga'], 'IDR') . "*\n\n";

        $message .= "✅ *Status:* Menunggu Konfirmasi\n";
        $message .= "📞 *Info:* Kami akan segera menghubungi Anda untuk konfirmasi pesanan.\n\n";
        $message .= "Terima kasih telah berbelanja! 🙏";

        // Send to Fonnte API
        $this->sendToFonnte($orderData['no_whatsapp'], $message);
    }

    private function sendToFonnte($target, $message)
    {
        $fonteToken = env('FONTE_TOKEN'); // Add this to your .env file

        if (empty($fonteToken)) {
            log_message('error', 'Fonnte token not configured');
            return false;
        }

        try {
            $response = $this->client->request('POST', 'https://api.fonnte.com/send', [
                'form_params' => [
                    'target' => $target,
                    'message' => $message,
                    'countryCode' => '62', // Indonesia country code
                ],
                'headers' => [
                    'Authorization' => $fonteToken
                ],
                'timeout' => 30
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['status']) && $body['status'] === true) {
                log_message('info', 'WhatsApp message sent successfully to: ' . $target);
                return true;
            } else {
                log_message('error', 'Failed to send WhatsApp message: ' . json_encode($body));
                return false;
            }
        } catch (\Exception $e) {
            log_message('error', 'Error sending WhatsApp message: ' . $e->getMessage());
            return false;
        }
    }
}
