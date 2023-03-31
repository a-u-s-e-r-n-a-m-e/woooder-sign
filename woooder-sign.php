<?php

/**
 * Plugin Name: Woooder Sign (Woocommerce Signature Agreement)
 * Plugin URI: https://github.com/a-u-s-e-r-n-a-m-e/woooder-sign
 * Description: This plugin adds a signature agreement field to the WooCommerce checkout page that requires customers to sign an agreement before submitting their payment.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/a-u-s-e-r-n-a-m-e/
 */

// Add signature agreement field to checkout page
add_action('woocommerce_checkout_after_terms_and_conditions', 'add_woooder_sign_field');
function add_woooder_sign_field()
{
    $refund_agreement_page = get_option('refund_agreement_page');
    if ($refund_agreement_page) {
        $refund_agreement_content = get_post_field('post_content', $refund_agreement_page);
        woocommerce_form_field('woooder_sign', array(
            'type' => 'checkbox',
            'class' => array('form-row-wide'),
            'label_class' => array('woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'),
            'input_class' => array('woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'),
            'required' => true,
            'label' => __($refund_agreement_content, 'woocommerce'),
        ));
    } else {
        woocommerce_form_field('woooder_sign', array(
            'type' => 'checkbox',
            'class' => array('form-row-wide'),
            'label_class' => array('woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'),
            'input_class' => array('woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'),
            'required' => true,
            'label' => __('I have read and agree to the <a href="/refund-policy/">refund policy</a>, and I acknowledge that by signing this agreement I am giving up my right to a refund.', 'woocommerce'),
        ));
    }
}

// Validate signature agreement field on checkout submission
add_action('woocommerce_checkout_process', 'validate_woooder_sign_field');
function validate_woooder_sign_field()
{
    if (!isset($_POST['woooder_sign']) || empty($_POST['woooder_sign'])) {
        wc_add_notice(__('Please read and agree to the refund policy by checking the signature agreement box.', 'woocommerce'), 'error');
    }
}

// Save signature data and date to order meta data on checkout submission
add_action('woocommerce_checkout_create_order', 'save_signature_data_and_date');
function save_signature_data_and_date($order)
{
    if (isset($_POST['signature_data']) && !empty($_POST['signature_data'])) {
        $signature_data = sanitize_text_field($_POST['signature_data']);
        $signature_date = date('F j, Y');
        $order->update_meta_data('_signature_data', $signature_data);
        $order->update_meta_data('_signature_date', $signature_date);
    }
}

// Process signature data and date on thank you page
add_action('woocommerce_thankyou', 'process_signature_data_and_date');
function process_signature_data_and_date($order_id)
{
    $order = wc_get_order($order_id);
    $signature_data = $order->get_meta('_signature_data');
    $signature_date = $order->get_meta('_signature_date');
    if ($signature_data && $signature_date) {
        // Save signature image to server
        $upload_dir = wp_upload_dir();
        $image_data = str_replace('data:image/svg+xml;base64,', '', $signature_data);
        $image_data = base64_decode($image_data);
        $image_path    = $upload_dir['path'] . '/' . $order_id . '.svg';
        file_put_contents($image_path, $image_data);

        // Add signature font to invoice
        $pdf_invoice_template = apply_filters('wpo_wcpdf_templates', array());
        if (isset($pdf_invoice_template['invoice'])) {
            $pdf_invoice_template = $pdf_invoice_template['invoice'];
            $template_path = $pdf_invoice_template->get_template_path();
            $template_font_path = dirname($template_path) . '/fonts/';
            $template_font_url = dirname($pdf_invoice_template->get_template_url()) . '/fonts/';

            // Copy custom signature font to invoice template
            $custom_font_path = plugin_dir_path(__FILE__) . 'fonts/MrsSaintDelafield-Regular.ttf';
            if (file_exists($custom_font_path)) {
                $custom_font_url = plugin_dir_url(__FILE__) . 'fonts/MrsSaintDelafield-Regular.ttf';
                $custom_font_filename = basename($custom_font_path);
                copy($custom_font_path, $template_font_path . $custom_font_filename);
                $pdf_invoice_template->set_font('signature', $template_font_url . $custom_font_filename);
            }

            // Add signature to invoice
            $pdf_invoice_template->set_line_height(20);
            $pdf_invoice_template->set_font_size(16);
            $pdf_invoice_template->set_text_color(array(0, 0, 0));
            $pdf_invoice_template->text(__('Customer Signature:', 'woocommerce'), 30, 220);
            $pdf_invoice_template->set_line_height(15);
            $pdf_invoice_template->set_font_size(12);
            $pdf_invoice_template->set_font('signature', $template_font_url . 'signature-font.ttf');
            $pdf_invoice_template->text($signature_data, 30, 240);
        }

        // Display signature and date on thank you page
        echo '<h2>' . __('Signature Agreement', 'woocommerce') . '</h2>';
        echo '<p>' . __('Thank you for signing the agreement. Your digital signature has been saved and will be used to verify that you agreed to the refund policy.', 'woocommerce') . '</p>';
        echo '<h3>' . __('Signature', 'woocommerce') . '</h3>';
        echo '<img src="' . $upload_dir['url'] . '/' . $order_id . '.svg" alt="Customer Signature" />';
        echo '<h3>' . __('Date', 'woocommerce') . '</h3>';
        echo '<p>' . $signature_date . '</p>';
    }
}
