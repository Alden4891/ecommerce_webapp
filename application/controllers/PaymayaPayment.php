<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Include Composer's autoloader
require_once FCPATH . 'vendor/autoload.php';

class PaymayaPayment extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->config('paymaya');
        $this->load->library('session');
        $this->load->helper('url');
        $this->load->model('Payment');

        // Initialize PayMaya SDK
        \PayMaya\PayMayaSDK::getInstance()->initCheckout(
            $this->config->item('paymaya_public_key'),
            $this->config->item('paymaya_secret_key'),
            $this->config->item('paymaya_environment')
        );
    }

    public function process_payment() {
        $order_id = $this->session->userdata('order_id');
        if (!$order_id) {
            // Handle error, redirect to cart or show an error message
            show_error('No order ID found in session.');
        }

        $this->load->model('order_model');
        $order_details = $this->order_model->get_order_by_id($order_id);
        $order_items = $this->order_model->get_order_detail($order_id);

        if (!$order_details || !$order_items) {
            show_error('Order not found.');
        }

        $total_amount = 0;
        $items = [];
        foreach ($order_items as $order_item) {
            $itemAmount = new \PayMaya\Model\Checkout\ItemAmount();
            $itemAmount->currency = "PHP";
            $itemAmount->value = $order_item->item_price;

            $item = new \PayMaya\Model\Checkout\Item();
            $item->name = $order_item->item_name;
            $item->quantity = $order_item->quantity;
            $item->amount = $itemAmount;
            $item->totalAmount = $itemAmount;

            $items[] = $item;
            $total_amount += $order_item->item_price * $order_item->quantity;
        }

        $totalAmount = new \PayMaya\Model\Checkout\ItemAmount();
        $totalAmount->currency = "PHP";
        $totalAmount->value = $total_amount;

        // Checkout
        $itemCheckout = new \PayMaya\Model\Checkout\Checkout();
        $itemCheckout->totalAmount = $totalAmount;
        $itemCheckout->requestReferenceNumber = $order_id;
        $itemCheckout->items = $items;
        $itemCheckout->redirectUrl = array(
            "success" => site_url('paymayapayment/success'),
            "failure" => site_url('paymayapayment/failure'),
            "cancel" => site_url('paymayapayment/cancel')
        );

        if ($itemCheckout->execute() === false) {
            $error = $itemCheckout->getError();
            // Handle error
            echo "Error executing checkout: " . $error['message'];
            return;
        }

        if ($itemCheckout->retrieve() === false) {
            $error = $itemCheckout->getError();
            // Handle error
            echo "Error retrieving checkout: " . $error['message'];
            return;
        }

        // Redirect to PayMaya payment page
        redirect($itemCheckout->url);
    }

    public function success() {
        $checkoutId = $this->input->get('id');

        $itemCheckout = new \PayMaya\Model\Checkout\Checkout();
        $itemCheckout->id = $checkoutId;
        if ($itemCheckout->retrieve() === false) {
            $error = $itemCheckout->getError();
            // Handle error
            echo "Error retrieving checkout: " . $error['message'];
            return;
        }

        if ($itemCheckout->paymentStatus === 'PAYMENT_SUCCESS') {
            $order_id = $this->session->userdata('order_id');

            // Update order status
            $this->load->model('order_model');
            $this->order_model->update_order_status($order_id, 'Paid');

            // Insert transaction data
            $transaction_data = array(
                'order_id' => $order_id,
                'txn_id' => $itemCheckout->transactionReferenceNumber,
                'payment_gross' => $itemCheckout->totalAmount->value,
                'currency_code' => $itemCheckout->totalAmount->currency,
                'payment_status' => $itemCheckout->paymentStatus,
            );
            $this->Payment->insertTransaction_paymaya($transaction_data);

            $data['message'] = "Payment successful!";
            $this->load->view('payment_success', $data);
        } else {
            $data['message'] = "Payment failed or was not successful. Status: " . $itemCheckout->paymentStatus;
            $this->load->view('payment_failure', $data);
        }
    }

    public function failure() {
        $data['message'] = "Payment failed!";
        $this->load->view('payment_failure', $data);
    }

    public function cancel() {
        $data['message'] = "Payment cancelled!";
        $this->load->view('payment_cancel', $data);
    }
}
