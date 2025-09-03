<?php
defined('BASEPATH') OR exit('No direct script access allowed');
Class Order_model extends CI_Model{

   public function __construct(){
      parent::__construct();
      $this->load->database();
      $this->load->model('user_auth_model');
      $this->load->model('cart_model');
   }

   public function get_ordered_items($data){

		return $this->db->select('`master_checkout_charge_perc`,`sale_ex_tax_perc`,SUM(`is_invalid`) AS is_invalid,SUM(`sub_total`) AS cart_sub_total,SUM(`courier_fee`) AS total_courier_charge,SUM(`master_checkout_charge`) AS total_master_checkout_charge,SUM(`sales_ex_tax`) AS total_sales_ex_tax,SUM(`sub_total`) + SUM(`sales_ex_tax`) + SUM(`master_checkout_charge`) AS total_amount')
			->from('(SELECT

			 		      `configuration`.master_checkout_charge_perc
			 		    , `configuration`.sale_ex_tax_perc
			 		    , CASE WHEN `tbl_carts`.`qnt` > `tbl_items`.stock THEN 1 ELSE 0 END AS \'is_invalid\'
			 		    ,  `tbl_items`.`discount` AS discount
			 		    ,  `tbl_courier`.`courier_fee`
			 		    , (`tbl_items`.`unit_price` + (`tbl_items`.`unit_price` * `tbl_items`.`discount`)) * `tbl_carts`.`qnt` AS sub_total
			 		    , ((`tbl_items`.`unit_price` + (`tbl_items`.`unit_price` * `tbl_items`.`discount`)) * `tbl_carts`.`qnt`) * `configuration`.master_checkout_charge_perc AS master_checkout_charge
			 		    , ((`tbl_items`.`unit_price` + (`tbl_items`.`unit_price` * `tbl_items`.`discount`)) * `tbl_carts`.`qnt`) * `configuration`.sale_ex_tax_perc AS sales_ex_tax
			 		FROM
			 		    `tbl_carts`
			 		    INNER JOIN `tbl_items` 
			 			ON (`tbl_carts`.`upc` = `tbl_items`.`upc`)
			 		    INNER JOIN configuration
			 			ON (`configuration`.ID = 1)
			 		    INNER JOIN `tbl_courier`
			 			ON (`tbl_courier`.`id` = `tbl_items`.`courier_id`)
			 		WHERE (`tbl_carts`.`cart_id` IN ('.implode(',', $data).'))) AS A')
			->group_by(array('`master_checkout_charge_perc`', '`sale_ex_tax_perc`'))
			->get();

   }

   public function place_order($data){

   			
		try {

		    //Check if stock is still available per items
			$this->db->trans_start();	
	   		$order_data = $this->get_ordered_items($data->csv_cart_items);
	   		$order_data = ((object) $order_data)->result()[0];
	   		$cur_date = date('Y-m-d h-i-s');
	   		$order_id = 0;


		    if ($order_data->is_invalid == 0) {
				$tbl_order_data = 	array(		'shipment_id' 			=> $data->shipment_id, 
												'date_posted' 		=> $cur_date, 
												'date_due' 			=> date('Y-m-d H:i:s', strtotime('+1 day')), 
												'ex_tax_rate' 		=> $order_data->sale_ex_tax_perc, 
												'master_charge_rate' 	=> $order_data->master_checkout_charge_perc, 
												'ex_tax_fee' 			=> $order_data->total_sales_ex_tax, 
												'status_id' 			=> 1, 
												'master_charge_fee' 	=> $order_data->total_master_checkout_charge, 
												'shipment_fee' 		=> $order_data->total_courier_charge, 
												'Note' 				=> $data->message);
				

		    	// INSERT ORDER DETAILS (EXCEPT PAYMENT_ID)
				$this->db->insert('`tbl_orders`', $tbl_order_data);
				$order_id = $this->db->insert_id();
				if ($order_id > 0) {
					
					// UPDATE CART AS ORDERED (fill order_id)
					$this->db->where_in('`cart_id`', $data->csv_cart_items)
					->set('`order_id`', $order_id)
					->update('`tbl_carts`');
					
					// SUBTRACT QTY FROM ITEMS
					$this->db->query('
					UPDATE `tbl_carts`  , `tbl_items` 
					 	INNER JOIN `tbl_carts` t1 ON (`tbl_items`.`upc` = t1.`upc`)
					SET `tbl_items`.`stock` = `tbl_items`.`stock` - t1.`qnt`
					WHERE (t1.`order_id` = ? );
					',[$order_id]);
				}

		    }else{
		    	echo json_encode(array('result' => 'error','error_message' => 'Unidentified error occured!'));	
		    	$this->db->trans_rollback();
		    }
			 $this->db->trans_complete();
			 // $this->db->trans_rollback();
			 echo json_encode(array('result' => 'success','order_id' => $order_id));	

		} catch (Exception $e) {
			$this->db->trans_rollback();
			echo json_encode(array('result' => 'error','error_message' => 'Unidentified error occured!'));	
		}	//try	

	} 

	// ---------------------------------------------------------------------------------------

	public function get_order_detail($order_id){
			return $this->db->select('tbl_carts.order_id
				,tbl_carts.upc
				,tbl_items.item_caption
				,tbl_items.item_desc
				,tbl_carts.qnt 
				,tbl_items.unit_price
				,tbl_items.discount
				,configuration.sale_ex_tax_perc
				,tbl_orders.date_posted
				,tbl_orders.shipment_fee as courier_fee
				,tbl_orders.date_due
				,tbl_items.unit_price - (tbl_items.unit_price * tbl_items.discount) AS discounted_unit_price
				,tbl_carts.qnt * (tbl_items.unit_price - (tbl_items.unit_price * tbl_items.discount)) AS NET_AMOUNT
				,ROUND((tbl_orders.shipment_fee + tbl_carts.qnt * (tbl_items.unit_price - (tbl_items.unit_price * tbl_items.discount))),2) AS sub_total')
				->from('tbl_orders')
				->join('tbl_carts', 'tbl_carts.order_id = tbl_orders.id')
				->join('tbl_items', 'tbl_items.upc = tbl_carts.upc')
				->join('configuration', 'configuration.id = 1')
				->where('tbl_orders.payment_id', 0)
				->where('tbl_orders.id', $order_id)
				->order_by('date_posted ASC')
				->get()->result();
			// $this->db->last_query();
	}

	public function get_order_listing($login_oauth_uid){
			return $this->db->select('
				tbl_carts.order_id,
				lib_order_status.status,
				tbl_orders.status_id,
				SUM(tbl_carts.qnt) AS ITEM_COUNT
				,tbl_orders.date_posted,tbl_orders.date_due,
				SUM(tbl_carts.qnt * (tbl_items.unit_price - (tbl_items.unit_price * tbl_items.discount))) AS NET_AMOUNT
				,ROUND(SUM((tbl_carts.qnt * (tbl_items.unit_price - (tbl_items.unit_price * tbl_items.discount))) * (configuration.sale_ex_tax_perc)),2) AS EX_TAX
				,ROUND(SUM((tbl_carts.qnt * (tbl_items.unit_price - (tbl_items.unit_price * tbl_items.discount))) * (configuration.sale_ex_tax_perc + 1)),2) AS SUB_TOTAL')
				->from('tbl_orders')
				->join('tbl_carts', 'tbl_carts.order_id = tbl_orders.id')
				->join('tbl_items', 'tbl_items.upc = tbl_carts.upc')
				->join('configuration', 'configuration.id = 1')
				->join('lib_order_status', 'lib_order_status.id = tbl_orders.status_id')
				->where('tbl_orders.payment_id', 0)
				->where('tbl_carts.login_oauth_uid', $login_oauth_uid)
				->where('tbl_items.is_bidding', 0)
				->group_by('tbl_carts.order_id')
				->order_by('date_posted ASC, tbl_orders.date_posted ASC, tbl_orders.date_due ASC')
				->get();
	}

	public function get_order_by_id($id) {
         $login_oauth_uid = $this->user_auth_model->get_user_id();
         return $this->db->get_where('`tbl_orders`', array('`id`' => $id))->result();
   	}
	
    public function update_order_status($order_id, $status) {
        $status_id = 0;
        switch ($status) {
            case 'Paid':
                $status_id = 2; // Assuming 2 is the status ID for 'Paid'
                break;
            // Add other cases as needed
        }

        if ($status_id > 0) {
            $this->db->where('id', $order_id);
            $this->db->update('tbl_orders', array('status_id' => $status_id));
        }
    }

}

?>