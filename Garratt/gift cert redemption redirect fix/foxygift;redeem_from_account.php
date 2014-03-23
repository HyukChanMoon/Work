<? 
	//Below 3 lines were added to fix the webpage from going to 404 page when a gift-certificate redemption is successful
	$location=root_url('/redeem-gift-certificate');
	$search="/garratt";
	$trimmed = str_replace($search, '', $location);
	
	if ( isset($this->customer) ): ?>
	
		<? $config = FoxyGift_Configuration::create(); ?>
		<?=open_form() ?>
			<?= flash_message() ?>
			
			<div class="control-group">
				<label for="gift-code">Enter Code</label>
				<input type="text" id="gift-code" name="gift_code" class="form-control" autofocus />
			</div>
			
			<br/><br/>
			<input type="hidden" name="customer_id" value="<?=$this->customer->id?>" />
			<!--<input type="hidden" name="redirect" value="<?= root_url( $config->foxygift_profile_url ? $config->foxygift_profile_url : '/profile' ) ?>" /> -->
	
			<input type="hidden" name="redirect" value="<?= $trimmed?>" />
	
			<input class="btn btn-primary" type="submit" name="submit" value="Save &amp; Exit" title="Save &amp; Exit" />
			<!--<input class="btn" type="button" value="Cancel" onclick="location='<?= root_url( $config->foxygift_profile_url ? $config->foxygift_profile_url : '/profile' ) ?>'" />-->
	
			<input class="btn" type="button" value="Cancel" onclick="location='<?= root_url('/orders') ?>'" />
	
		</form>
	
	<? else: ?>
	
		<p>You have to be logged in to access that page.</p>
	
	<? endif 
?>