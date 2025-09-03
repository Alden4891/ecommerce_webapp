<?php defined('BASEPATH') OR exit('No direct script access allowed'); 
 
class Payment extends CI_Model{ 
     
    function __construct() { 
        $this->transTable = 'payments'; 
    } 
     
    /* 
     * Fetch payment data from the database 
     * @param id returns a single record if specified, otherwise all records 
     */ 
    public function getPayment($conditions = array()){ 
        $this->db->select('*'); 
        $this->db->from($this->transTable); 
         
        if(!empty($conditions)){ 
            foreach($conditions as $key=>$val){ 
                $this->db->where($key, $val); 
            } 
        } 
         
        $result = $this->db->get(); 
        return ($result->num_rows() > 0)?$result->row_array():false; 
    } 
     
    /* 
     * Insert payment data in the database 
     * @param data array 
     */ 
    public function insertTransaction_paypal($data){ 




        $insert = $this->db->insert('`epiz_32635831_db`.`tbl_payments`', 
            array(
                '`order_id`' => 'order_id', 
                '`payer_email`' => 'payer_email', 
                '`payer_id`' => 'payer_id', 
                '`payer_status`' => 'payer_status', 
                '`first_name`' => 'first_name', 
                '`last_name`' => 'last_name', 
                '`address_name`' => 'address_name', 
                '`address_street`' => 'address_street', 
                '`address_city`' => 'address_city', 
                '`address_state`' => 'address_state', 
                '`address_country_code`' => 'address_country_code', '`address_zip`' => 'address_zip', '`residence_country`' => 'residence_country', '`txn_id`' => 'txn_id', '`mc_currency`' => 'mc_currency', '`mc_fee`' => 'mc_fee', '`mc_gross`' => 'mc_gross', '`protection_eligibility`' => 'protection_eligibility', '`payment_fee`' => 'payment_fee', '`payment_gross`' => 'payment_gross', '`payment_status`' => 'payment_status', '`payment_type`' => 'payment_type', '`handling_amount`' => 'handling_amount', '`shipping`' => 'shipping', '`item_name`' => 'item_name', '`item_number`' => 'item_number', '`quantity`' => 'quantity', '`txn_type`' => 'txn_type', '`payment_date`' => 'payment_date', '`receiver_id`' => 'receiver_id', '`notify_version`' => 'notify_version', '`custom`' => 'custom', '`verify_sign`' => 'verify_sign'));

; 
        return $insert?true:false; 
    }

    public function insertTransaction_paymaya($data){
        $insert = $this->db->insert($this->transTable, $data);
        return $insert?true:false;
    }
     
}