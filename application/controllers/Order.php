<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Order extends CI_Controller {

    public function __construct() {
       parent::__construct();
       
       $this->load->model('user_auth_model');
       $this->load->model('library_model');
       $this->load->model('shipment_model');
       $this->load->model('cart_model');
       $this->load->model('settings_model');
       $this->load->model('order_model');

       $this->load->helper('url');
       $this->load->helper('form');

       $this->load->library('paypal_lib'); 
       $this->load->helper('cookie');
       // $this->load->model('product'); 

       
    }

    public function index(){
        $this->user_auth_model->login_required();
    }

    public function checkout(){


          // echo "just a test1";
          // return;


        $this->user_auth_model->login_required();
        $data['item_categories'] = $this->library_model->get_product_categories();

        #IF CHECKOUT LOADS FROM CART PAGE
        if($this->input->post('submit') != NULL ){

            $chkout_items = $this->input->post();
            $shipment_info = $this->shipment_model->fetch_default()[0];
            $data['shipment_info'] = $shipment_info;

            //get checked-out items
            $selected_items = $chkout_items['cart_id'];
            $data['cart_entries'] = $this->cart_model->get_cart_entries($selected_items);
            $csv_cart_items = implode(',',$selected_items);
            $data['csv_cart_items'] = $csv_cart_items;
            
            //get total
            $sub_total = 0;
            $shipping_total = 0;
            foreach ($data['cart_entries'] as $item) {
               $sub_total += $item->sub_total;
               $shipping_total+=$item->courier_fee;
            }
            
            //summary
            $charges = $this->settings_model->get_tax_rate();
            $ex_tax_rate = $charges[0]->sale_ex_tax_perc;  
            $ex_tax_charge = ($sub_total * $ex_tax_rate);    
            $total_amount =  $sub_total + $ex_tax_charge + $shipping_total;
            $data['totals'] = (object) array(
                              'sub_total' =>  number_format( $sub_total,2), 
                              'shipment_cost' => number_format( $shipping_total,2), 
                              'ex_tax_charge' => number_format( $ex_tax_charge,2), 
                              'ex_tax_rate' => number_format( $ex_tax_rate*100,2) . '%', 
                              'total_amount' => number_format( $total_amount,2)
                            );

            $data['action_container'] = "
                <form action=\"" . site_url('order/payment') . "\" method=\"post\">
                    <input type=\"hidden\" name=\"order_id\" value=\"\">
                    <div class=\"radio-group row justify-content-between px-3 text-center a\">
                        <label class=\"col-auto mr-sm-2 mx-1 card-block  py-0 text-center radio selected \">
                            <input type=\"radio\" name=\"payment_method\" value=\"stripe\" checked class=\"d-none\">
                            <div class=\"flex-row\">
                                <div class=\"col\">
                                    <div class=\"pic2\">
                                        <img class=\"irc_mut img-fluid\" src=\"" . site_url('assets/images/stripe_secure.jpg') . "\" width=\"200\" height=\"200\">
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class=\"col-auto ml-sm-2 mx-1 card-block  py-0 text-center radio  \">
                            <input type=\"radio\" name=\"payment_method\" value=\"google_pay\" class=\"d-none\">
                            <div class=\"flex-row\">
                                <div class=\"col\">
                                    <div class=\"pic2\">
                                          <img class=\"irc_mut img-fluid\" src=\"" . site_url('assets/images/google_pay.jpg') . "\" width=\"200\" height=\"200\">
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class=\"col-auto ml-sm-2 mx-1 card-block  py-0 text-center radio  \">
                            <input type=\"radio\" name=\"payment_method\" value=\"paypal\" class=\"d-none\">
                            <div class=\"flex-row\">
                                <div class=\"col\">
                                    <div class=\"pic2\">
                                          <img class=\"irc_mut img-fluid\" src=\"" . site_url('assets/images/paypal.jpg') . "\" width=\"200\" height=\"200\">
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class=\"col-auto ml-sm-2 mx-1 card-block  py-0 text-center radio  \">
                            <input type=\"radio\" name=\"payment_method\" value=\"paymaya\" class=\"d-none\">
                            <div class=\"flex-row\">
                                <div class=\"col\">
                                    <div class=\"pic2\">
                                          <img class=\"irc_mut img-fluid\" src=\"https://upload.wikimedia.org/wikipedia/commons/thumb/7/79/Paymaya_logo.svg/684px-Paymaya_logo.svg.png\" width=\"200\" height=\"200\">
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div class=\"row justify-content-center\">
                        <div class=\"col\">
                            <button type=\"submit\" class=\"btn btn-primary\">Proceed to Payment</button>
                        </div>
                    </div>
                </form>
            ";


            $this->load->view('header',$data);
            $this->load->view('sidebar');
            $this->load->view('invoice');
            $this->load->view('footer');
             $this->load->view('js/place_order');

         } else {



            #IF CHECKOUT LOADS FROM MY PURCHASES PAGE
            #INDER CONSTRUCTION
            // $order_id = $this->input->post()['order_id'];

            $order_id = $this->uri->segment(3);
            $order_info =  $this->order_model->get_order_by_id($order_id);
            $data['shipment_info'] = $this->shipment_model->fetch_by_id($order_info[0]->shipment_id)[0];
            $data['cart_entries'] = $this->order_model->get_order_detail($order_id);


            // print('<pre>');
            // print_r($data['cart_entries']);
            // print('</pre>');


// =======
//             ;   

//             //Redirect of the post to prevent browser from asking for resubmission of post data.       
//             if (!empty($this->input->post())) {
//                 $data = $this->input->post();
//                 setcookie('checkout_order_id',$data['order_id']);
//                 print('redirect');
//                 redirect(site_url('order/checkout'), 'refresh');
//                 return;
//             }
//             $order_id = $this->input->cookie('checkout_order_id',TRUE);

//             //load data
//             $order_info = (object) $this->order_model->get_order_by_id($order_id)[0];
//             $data['shipment_info'] = $this->shipment_model->fetch_by_id($order_info->shipment_id)[0];
//             $data['cart_entries'] = $this->order_model->get_order_detail($order_id);

// >>>>>>> 3f0c07464beea9663730d39bb78afe693f68e49c
           //get total
            $sub_total = 0;
            $shipping_total = 0;
            foreach ($data['cart_entries'] as $item) {
               $sub_total += $item->sub_total;
               $shipping_total+=$item->courier_fee;
            }

            //summary
            $charges = $this->settings_model->get_tax_rate();
            $ex_tax_rate = $charges[0]->sale_ex_tax_perc;  
            $ex_tax_charge = ($sub_total * $ex_tax_rate);    
            $total_amount =  $sub_total + $ex_tax_charge + $shipping_total;
            $data['totals'] = (object) array(
                              'sub_total' =>  number_format( $sub_total,2), 
                              'shipment_cost' => number_format( $shipping_total,2), 
                              'ex_tax_charge' => number_format( $ex_tax_charge,2), 
                              'ex_tax_rate' => number_format( $ex_tax_rate*100,2) . '%', 
                              'total_amount' => number_format( $total_amount,2)
                            );

            $data['csv_cart_items'] = "";

            // print('place order clicked!');

            $data['action_container'] = "
                <form action=\"" . site_url('order/payment') . "\" method=\"post\">
                    <input type=\"hidden\" name=\"order_id\" value=\"" . $order_id . "\">
                    <div class=\"radio-group row justify-content-between px-3 text-center a\">
                        <label class=\"col-auto mr-sm-2 mx-1 card-block  py-0 text-center radio selected \">
                            <input type=\"radio\" name=\"payment_method\" value=\"stripe\" checked class=\"d-none\">
                            <div class=\"flex-row\">
                                <div class=\"col\">
                                    <div class=\"pic2\">
                                        <img class=\"irc_mut img-fluid\" src=\"" . site_url('assets/images/stripe_secure.jpg') . "\" width=\"200\" height=\"200\">
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class=\"col-auto ml-sm-2 mx-1 card-block  py-0 text-center radio  \">
                            <input type=\"radio\" name=\"payment_method\" value=\"google_pay\" class=\"d-none\">
                            <div class=\"flex-row\">
                                <div class=\"col\">
                                    <div class=\"pic2\">
                                          <img class=\"irc_mut img-fluid\" src=\"" . site_url('assets/images/google_pay.jpg') . "\" width=\"200\" height=\"200\">
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class=\"col-auto ml-sm-2 mx-1 card-block  py-0 text-center radio  \">
                            <input type=\"radio\" name=\"payment_method\" value=\"paypal\" class=\"d-none\">
                            <div class=\"flex-row\">
                                <div class=\"col\">
                                    <div class=\"pic2\">
                                          <img class=\"irc_mut img-fluid\" src=\"" . site_url('assets/images/paypal.jpg') . "\" width=\"200\" height=\"200\">
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class=\"col-auto ml-sm-2 mx-1 card-block  py-0 text-center radio  \">
                            <input type=\"radio\" name=\"payment_method\" value=\"paymaya\" class=\"d-none\">
                            <div class=\"flex-row\">
                                <div class=\"col\">
                                    <div class=\"pic2\">
                                          <img class=\"irc_mut img-fluid\" src=\"https://upload.wikimedia.org/wikipedia/commons/thumb/7/79/Paymaya_logo.svg/684px-Paymaya_logo.svg.png\" width=\"200\" height=\"200\">
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div class=\"row justify-content-center\">
                        <div class=\"col\">
                            <button type=\"submit\" class=\"btn btn-primary\">Proceed to Payment</button>
                        </div>
                    </div>
                </form>
            ";

            $this->load->view('header',$data);
            $this->load->view('sidebar');
            $this->load->view('invoice');
            $this->load->view('footer');
            $this->load->view('js/order_payment');


           
          
         }
    }

    //PLACE ORDER
    public function place_order(){
        $this->user_auth_model->login_required();
        if ($this->input->post('place_order')!=NULL) {
            $data = $this->input->post();
            $data['csv_cart_items'] = explode(',', $data['csv_cart_items']);
            return $this->order_model->place_order((object)$data);

        }
    }

    //LOAD LIST OF ORDERS
    public function list(){
        $this->user_auth_model->login_required();
        $login_oauth_uid = $this->user_auth_model->get_user_id();
        $data['item_categories'] = $this->library_model->get_product_categories();
        $data['order_listing'] = $this->order_model->get_order_listing($login_oauth_uid);
        // print_r($data['order_listing']->result());
        // print_r($this->db->last_query());
        $this->load->view('header',$data);
        $this->load->view('sidebar');
        $this->load->view('order_listing');
        $this->load->view('footer');
    }

    //PAYMENT
    public function payment(){
        $this->user_auth_model->login_required();
        $payment_method = $this->input->post('payment_method');
        $order_id = $this->input->post('order_id'); // You may need to pass this from the view

        // Store order_id in session to retrieve it in the PaymayaPayment controller
        $this->session->set_userdata('order_id', $order_id);

        switch ($payment_method) {
            case 'stripe':
                // The stripe controller doesn't have a proper payment flow yet
                // redirect('stripepayment/handlePayment');
                echo "Stripe payment is not yet implemented.";
                break;
            case 'paypal':
                 $this->mop_paypal($order_id);
                break;
            case 'paymaya':
                redirect('paymayapayment/process_payment');
                break;
            case 'google_pay':
                echo "Google Pay is not yet implemented.";
                break;
            default:
                // Handle error or redirect to checkout
                $this->session->set_flashdata('error_message', 'Invalid payment method selected.');
                redirect('checkout');
                break;
        }
    }

    function mop_paypal($id){ 

        # sb-uz9bd18075500@business.example.com
        # P@ssw0rd0214

        #sb-43x43px20719471@personal.example.com


        // Set variables for paypal form 
        $returnURL = site_url().'paypal/success'; //payment success url 
        $cancelURL = base_url().'paypal/cancel'; //payment cancel url 
        $notifyURL = base_url().'paypal/ipn'; //ipn url 
         
        // Get product data from the database 
        // $product = $this->product->getRows($id); 
         
        // Get current user ID from the session (optional) 
        $userID =  '8888';
         
        // Add fields to paypal form 
        $this->paypal_lib->add_field('return', $returnURL); 
        $this->paypal_lib->add_field('cancel_return', $cancelURL); 
        $this->paypal_lib->add_field('notify_url', $notifyURL); 
        $this->paypal_lib->add_field('order_number', '1'); 
        // $this->paypal_lib->add_field('custom', $userID); 
        // $this->paypal_lib->add_field('item_number',  2); 
        // $this->paypal_lib->add_field('itemName',  '3'); 

        // // $this->paypal_lib->add_field('item_name', 'suns beauty product'); 
        // $this->paypal_lib->add_field('quantity',  5); 
        // $this->paypal_lib->add_field('amount',  100); 

        $this->paypal_lib->add_field('item_name', 'order');
        $this->paypal_lib->add_field('custom', 'customer identification');
        $this->paypal_lib->add_field('quantity',  2);
        $this->paypal_lib->add_field('sample_field',  2);
        $this->paypal_lib->add_field('item_number',  '111');
        $this->paypal_lib->add_field('amount',  130);
         
        // Render paypal form 
        $this->paypal_lib->paypal_auto_form(); 
    } 

}



