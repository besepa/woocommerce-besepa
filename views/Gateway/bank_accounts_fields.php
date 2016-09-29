<div id="besepa_bank_accounts_option">

	<h4><?php _e("Selecciona una cuenta bancaria", "besepa") ?></h4>

	<table id="besepa_bank_accounts">
<?php

$bank_accounts = $besepaUser->getBankAccounts();

if(count($bank_accounts)){ ?>


<?php

/**
 * @var $bank_account \Besepa\Entity\BankAccount
 */
$_count=0;
foreach ($bank_accounts as $bank_account){ ?>

	<tr id="besepa_ba_<?php echo $bank_account["id"] ?>"
		class="bank_account <?php echo 0==$_count++ ? "selected": "" ?>">

		<td><?php echo $bank_account["iban"] ?> <span class="status"><?php echo $bank_account["status"] ?></span></td>
		<td class="select">
			<a href="#"
			   data-id="<?php echo $bank_account["id"] ?>"
			   class="select_besepa_bank_account">
				<?php _e("seleccionar", "besepa") ?>
			</a>
		</td>



	</tr>


<?php } ?>



	<?php }else{ ?>

	<tr id="besepa_no_bank_accounts">
		<td colspan="2">
			<div class="alert">
				<strong><?php _e("Ninguna cuenta bancaria registrada", "besepa") ?></strong>
			</div>
		</td>
	</tr>

	<?php } ?>
</table>

	<hr>

	<div id="add_account_field">

		<h3><?php _e("Añadir cuenta bancaria", "besepa") ?></h3>

		<p id="add_account_field_error" class="error" style="display: none"></p>


		<label for="besepa_account_iban"><?php _e("IBAN de tu cuenta", "besepa") ?></label>
		<p>
			<input type="text"
				   placeholder="<?php _e("Introduce el número de IBAN de tu cuenta bancaria", "besepa") ?>"
				   id="besepa_new_iban"
				   style="width: 80%">
		</p>
		<p>
			<button type="button"
					class="button"
					id="besepa_register_bc"><?php _e("Añadir cuenta bancaria", "besepa") ?></button>
		</p>
	</div>

	<input required type="hidden" id="besepa_bank_account_id" name="besepa_selected_bank_account_id" value="<?php echo count($bank_accounts) ? $bank_accounts[0]["id"] : "" ?>">
	<input required type="hidden" id="besepa_current_customer_id" name="besepa_current_customer_id" value="<?php echo $besepaUser->getCustomerId() ?>">


	<script type="text/javascript">


	</script>


</div>