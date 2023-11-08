<?php
/**
 * eService
 *
 * @author    eService
 * @copyright Copyright (c) 2018 eService
 * @license   http://opensource.org/licenses/LGPL-3.0  Open Software License (LGPL 3.0)
 *
 */

class EServiceSuccessModuleFrontController extends ModuleFrontController
{

    private $order;
    private $eservice;

    public function initContent()
    {
        parent::initContent();
        $eservice = new EService();
        $this->eservice = $eservice;
        $id_order = Tools::getValue('order');
        $merchantId = Tools::getValue('merchantTxId');

        if(!$id_order){
            $id_order = $eservice->getIdOrderByToken($merchantId)['0']['id_order'];
        }
        $orderPayment = $eservice->getOrdersByIdOrder($id_order);

        if (!$orderPayment) {
            Tools::redirect('index.php?controller=history', __PS_BASE_URI__, null, 'HTTP/1.1 301 Moved Permanently');
        }

        $eservice->id_order = $orderPayment['0']['id_order'];
        $eservice->id_cart = $orderPayment['0']['id_cart'];
        $eservice->token = $orderPayment['0']['token'];

        $eservice->updateOrder();

        $this->order = new Order($eservice->id_order);
        $currentState = $this->order->getCurrentStateFull($this->context->language->id);

        $evoStatus =  $orderPayment['0']['status'];
        $statusDesc = $this->eservice->getStatusDescByEvoStatus($evoStatus,  $this->context->language->iso_code);
        
        $this->context->smarty->assign([
            'evoLogo' => $eservice->getEVOLogo(),
            'orderPublicId' => $this->order->getUniqReference(),
            'redirectUrl' => $this->getRedirectLink($eservice->id_cart, $eservice->id_order),
            'orderStatus' => $statusDesc,
            'orderEvoStatus' => $evoStatus,
            'orderId' => $eservice->id_order,
            'token' => $eservice->token,
            'HOOK_ORDER_CONFIRMATION' => $this->displayOrderConfirmation(),
            'HOOK_PAYMENT_RETURN' => $this->displayPaymentReturn()
        ]);

        $this->setTemplate($eservice->buildTemplatePath('success'));
    }

    
    
    
    private function getRedirectLink($id_cart, $id_order)
    {
        if (Cart::isGuestCartByCartId($id_cart)) {
            $customer = new Customer((int)$this->order->id_customer);

            return $this->context->link->getPageLink(
                'guest-tracking',
                null,
                $this->context->language->id,
                ['id_order' => $this->order->reference, 'email' => urlencode($customer->email)]
            );
        }

        return $this->context->link->getPageLink(
            'order-detail',
            null,
            $this->context->language->id,
            ['id_order' => $id_order]
        );
    }

    /**
     * Execute the hook displayPaymentReturn
     */
    private function displayPaymentReturn()
    {
        $params = $this->displayHook();
        if ($params && is_array($params)) {
            return Hook::exec('displayPaymentReturn', $params, $this->module->id);
        }
        return false;
    }

    /**
     * Execute the hook displayOrderConfirmation
     */
    private function displayOrderConfirmation()
    {
        $params = $this->displayHook();
        if ($params && is_array($params)) {
            return Hook::exec('displayOrderConfirmation', $params);
        }
        return false;
    }

    private function displayHook()
    {
        if (Validate::isLoadedObject($this->order)) {
            $currency = new Currency((int) $this->order->id_currency);
            $params = [];
            $params['objOrder'] = $this->order;
            $params['currencyObj'] = $currency;
            $params['currency'] = $currency->sign;
            $params['total_to_pay'] = $this->order->getOrdersTotalPaid();
			$params['order'] = $this->order;

            return $params;
        }
        return false;
    }
}