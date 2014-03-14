<?php

class EBookDiscountModule extends Core_ModuleBase {

	protected function createModuleInfo() {
		return new Core_ModuleInfo("Garratt's Online Bookshop", "Applies shipping cost exemption for ebooks", "Summit Creative");
	}

	public function get_action_type() {
		/*
		 * This action applies discounts to the cart items
		 */
		return self::type_cart;
	}

	public function get_name() {
		return "Discount each cart item unit price by fixed amount";
	}

	public function is_per_product_action()// determines whether the action can apply discounts only to specific items in the cart. If this method returns TRUE, the Product Filter section is displayed on the action configuration form.
	{
		/*
		 * This action allows the product filter
		 */
		return true;
	}

	public function build_config_form($host_obj) {
		/*
		 * The action has a single required field - the discount amount. The code
		 * below adds this field to the action configuration form.
		 */
		$host_obj -> add_field('discount_amount', 'Discount amount', 'full', db_float) -> comment('Please specify an amount to subtract from cart item unit price. ', 'above') -> validation() -> required('Please specify discount amount');
	}

	public function eval_discount(&$params, $host_obj, &$item_discount_map, &$item_discount_tax_incl_map, $product_conditions) {
		/*
		 * Extract the cart items from the $params array and initialize discount variables
		 */

		$cart_items = $params['cart_items'];
		$total_discount = 0;
		$total_discount_incl_tax = 0;

		/*
		 * Determine whether the tax should be included to discounts
		 */
		$include_tax = Shop_CheckoutData::display_prices_incl_tax();

		/*
		 * Loop through the cart items
		 */
		foreach ($cart_items as $item) {

			/*
			 * Calculate the current product price as a difference between the original product price and discount
			 * applied to it by other discount actions (if any)
			 */
			$original_product_price = $item -> total_single_price();
			$current_product_price = max($original_product_price - $item_discount_map[$item -> key], 0);

			/*
			 * The following array ($rule_params) is needed for the product filter, to determine whether
			 * the discount should be applied to the current item. Usually the parameters initialization
			 * is similar for all discount actions.
			 */
			$rule_params = array();
			$rule_params['product'] = $item -> product;
			$rule_params['item'] = $item;
			$rule_params['current_price'] = $item -> single_price_no_tax(false) - $item -> discount(false);
			$rule_params['quantity_in_cart'] = $item -> quantity;
			$rule_params['row_total'] = $item -> total_price_no_tax();
			$rule_params['item_discount'] = isset($item_discount_map[$item -> key]) ? $item_discount_map[$item -> key] : 0;
			$rule_params['product_type'] = $item -> product_type;
			//product_type => get ebook

			/*
			 * Apply the product filter. The $this->is_active_for_product() method checks whether the product is allowed
			 * by the filter configuration.
			 */
			if ($this -> is_active_for_product($item -> product, $product_conditions, $current_product_price, $rule_params, $item -> product_type)) {
				/*
				 * Initialize the discount amount variables - just load the value from the configuration form.
				 */
				$total_discount_incl_tax = $discount_value = $host_obj -> discount_amount;

				if ($include_tax) {
					/*
					 * If the tax inclusive mode is enabled, we need to extract the real discount value from the discount total,
					 * because the discount engine works in the tax exclusive mode, but discounts are specified in tax inclusive mode.
					 */
					$discount_value = Shop_TaxClass::get_subtotal($item -> product -> tax_class_id, $discount_value);
				}

				/*
				 * Do not allow the discount to exceed the current product price
				 */
				if ($discount_value > $current_product_price)
					$total_discount_incl_tax = $discount_value = $current_product_price;

				/*
				 * Calculate the total discount with tax included value - apply the tax to the tax value and add it to the tax value
				 */
				if ($include_tax)
					$total_discount_incl_tax = Shop_TaxClass::get_total_tax($item -> product -> tax_class_id, $discount_value) + $discount_value;

				/*
				 * Increase the total discount (the method result)
				 * and update the item discount maps for the tax exclusive and tax inclusive discounts.
				 */
				$total_discount += $discount_value * $item -> quantity;
				$item_discount_map[$item -> key] += $discount_value;
				$item_discount_tax_incl_map[$item -> key] += $total_discount_incl_tax;
			}
		}

		/*
		 * Return the discount amount
		 */
		return $total_discount;
	}

}
?>