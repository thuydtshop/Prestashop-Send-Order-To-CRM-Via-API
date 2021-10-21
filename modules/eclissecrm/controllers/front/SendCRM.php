<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
use PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartPresenter;

class EclissecrmSendCRMModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        $json = [ 'error' => 0, 'data' => false, 'message' => '' ];

        $id_carrier = $this->context->cart->id_carrier;
        $shipping = new Carrier($id_carrier);

        $total_including_tax = $this->context->cart->getOrderTotal(true, Cart::BOTH);
        $total_excluding_tax = $this->context->cart->getOrderTotal(false, Cart::BOTH);

        $taxAmount = 0;
        if (Configuration::get('PS_TAX_DISPLAY')) {
            $taxAmount = $total_including_tax - $total_excluding_tax;
        }
        
        //header('Content-Type: application/json');

        $cartPresenter = new CartPresenter();
        $products = $cartPresenter->addCustomizedData($this->context->cart->getProducts(), $this->context->cart);

        if ($products) {
            foreach ($products as &$product) {
                if ($product['id_product'] > 0) {
                    $attributes_full = Product::getAttributesParams($product['id_product'], $product['id_product_attribute']);
                    $product['attributes_full'] = $attributes_full ? $attributes_full : [];

                    $customizations = [];
                    if (isset($product['customizations']) && count($product['customizations'])) {
                        foreach ($product['customizations'] as $customization) {
                            if (!isset( $customizations[ $customization['id_customization'] ] )) {
                                $customizations[ $customization['id_customization'] ] = [];
                            }

                            if (isset($customization['fields']) && count($customization['fields'])) {
                                foreach ($customization['fields'] as $field) {
                                    if (isset($field['text']) && !empty($field['text']) && strpos($field['text'], '::') !== false) {
                                        $text = strip_tags($field['text']);
                                        $text = str_replace(array("\r", "\n"), '--', $text);
                                        $text = preg_replace('!\s+!', ' ', $text);
                                        $text = str_replace(':: ', '::', trim($text));
                                        $text = str_replace('::-- ', '::', $text);
                                        $texts = explode("--", $text);
                                        if ($texts) {
                                            $new_texts = [];
                                            foreach ($texts as $value) {
                                                if (!empty(trim($value)) && strpos(trim($value), '::') !== false) {
                                                    $new_texts[] = trim($value);
                                                }
                                            }
                                            $texts = $new_texts;
                                        }
                                        $customizations[ $customization['id_customization'] ][] = $texts;
                                    } else {
                                        if (isset($field['text'])) {
                                            $field['text'] = str_replace(["\r\n", "\r", "\n"], '', $field['text']);
                                            $field['text'] = preg_replace('!\s+!', ' ', $field['text']);
                                            $field['text'] = str_replace(' <', '<', $field['text']);
                                            $field['text'] = str_replace('> ', '>', $field['text']);
                                            $field['text'] = preg_replace('/\>\s+\</m', '><', $field['text']);
                                        }
                                        $customizations[ $customization['id_customization'] ][] = $field;
                                    }
                                }
                            }
                        }
                    }
                    $product['customizations'] = $customizations;
                }
            }
        }

        $data = [
            'id_cart' => $this->context->cart->id,
            'id_customer' => $this->context->cart->id_customer,
            'cart_items'=> $products,
            'total_products_price_tax_excl' => $this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS),
            'total_products_price_tax_incl' => $this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS),
            'shipping' => $shipping && isset($shipping->name) ? $shipping->name : '',
            'total_tax' => $taxAmount,
            'total_cart_price_tax_excl' => $total_excluding_tax,
            'total_cart_price_tax_incl' => $total_including_tax
        ];

        // validate endpoint api url
        // if it is not exists -> stop and show error
        $endpoint = Configuration::get('ECLISSECRM_API_ENDPOINT', '');
        if (empty($endpoint)) {
            $json['error'] = 1;
            $json['message'] = $this->module->l('The Endpoint API invalid. Please contact with Administrator for this error.');

            header('Content-Type: application/json');
            die(json_encode($json));
        }

        // send data to endpoint here...
        $response = $this->postDataToCRM($endpoint, $data);

        // for test data
        $json['data'] = $response;

        header('Content-Type: application/json');
        die(json_encode($json));
    }

    // function to post data to endpoint api
    protected function postDataToCRM($url, $data)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        //$headers = [];
        //$headers = array( 'Content-Type: application/json' );   // can be change content type follow Endpoint API

        $token = Configuration::get('ECLISSECRM_API_KEY', '');  // Should be defined API secrect key
        if (!empty($token)) {
            $headers[] = 'Authorization:' . $token;
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

        // get response
        $response = curl_exec($curl);

        // get error
        if ($response === false) {
            $json['error'] = curl_error($curl);
            $json['message'] = curl_errno($curl);
        }

        curl_close($curl);

        // return response to front-office
        return $response;
    }
}
