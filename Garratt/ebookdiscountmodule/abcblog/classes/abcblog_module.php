<?php

	class AbcBlog_Module extends Core_ModuleBase
	{

	/**
	 * Creates the module information object
	 * @return Core_ModuleInfo
	 */
	 
	protected function createModuleInfo() {
		return new Core_ModuleInfo("eBook Shipping Cost Exemption", "Applies shipping cost exemption rule for ebooks", "Summit Creative");
	}

	public function subscribeEvents() {
		Backend::$events -> addEvent('shop:onUpdateShippingQuote', $this, 'update_shipping_quote');
	}
	

	public function update_shipping_quote($shipping_option, $params) {
		
		/*
		//get original subtotal of all items
	
		$original_subtotal = $params['total_price'];
	
		//get all items in the cart
		
		$cart_items = Shop_Cart::list_active_items($cart_name);

		foreach ($cart_items as $item) {
			$product = $item -> product;
			if ($product -> product_type -> code == 'ebook') {
				$ebook_price_subtotal = $ebook_price_subtotal + ($product -> get_sale_price(1));
			}
		}
		
		$non_ebook_subtotal = $original_subtotal - $ebook_price_subtotal;
		var_dump ($non_ebook_subtotal);
	
		$table_shipping_rate = new Shop_TableRateShipping;
		$table_shipping_rate->$total_price = $non_ebook_subtotal;
		return $table_shipping_rate->$total_price;		
 
		echo "It's working!";
	  */
	}


/*
 * original subtotal - ebooksubtotal = non_ebook_subtotal X
 * calculation for shipping cost function using table rates in backend <- replace subtotal with non_ebook_subtotal
 * comes up with shipping rate
 * make subtotal use original subtotal to show subtotal price
 * 
 * 
 * if non-ebook subtotal is less than or equal to 20, charge 7
   if 20 < x > 50, charge 14
 */


	}
?>