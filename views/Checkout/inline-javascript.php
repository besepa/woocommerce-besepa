
var tax_id_field_id = "besepa_taxid";

var ajax_url = "<?php echo get_permalink() ?>";

var besepa_messages  = {
    required_iban_number: "<?php _e("El campo iban es obligatorio", "besepa") ?>",
			required_company_name: "<?php _e("Por favor, introduzca el nombre de su empresa o razón social", "besepa") ?>",
			required_tax_id: "<?php _e("Por favor, introduzca el su CIF o NIF", "besepa") ?>",
			required_email: "<?php _e("Por favor, introduzca su correo electrónico", "besepa") ?>",
			required_first_name: "<?php _e("Por favor, introduzca su nombre", "besepa") ?>",
			required_: "<?php _e("El campo iban es obligatorio", "besepa") ?>",
			register_is_required: "<?php _e("Necesitas crearte una cuenta en este sitio para añadir tu cuenta bancaria", "besepa") ?>",
			select_bank_account: "<?php _e("seleccionar", "besepa") ?>",
			select_a_bank_account_required: "<?php _e("selecciona una cuenta bancaria o añade una nueva", "besepa") ?>",
			error_creating_customer_in_besepa: "<?php _e("Ocurrió un error asociando tu cuenta a besepa", "besepa") ?>",
			error_creating_bank_account_in_besepa: "<?php _e("Ocurrió un error registrando tu número de cuenta en besepa", "besepa") ?>",
			error_incorrect_iban: "<?php _e("Número de iban incorrecto", "besepa") ?>",
			adding_bank_account: "<?php _e("Añadiendo número de cuenta...", "besepa") ?>",
			add_bank_account: "<?php _e("Añadir cuenta bancaria", "besepa") ?>"
};

var besepa_user = {
    customer_id: "<?php echo $userManager->getUser()->getCustomerId()?:'' ?>",
    user_id: "<?php echo get_current_user_id()?:0 ?>"
};