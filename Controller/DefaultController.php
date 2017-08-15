<?php

namespace Rispo\YandexKassaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;

/**
 * Class DefaultController
 * @package Rispo\YandexKassaBundle\Controller
 */
class DefaultController extends Controller
{
    /**
     * @param Request $request
     * @return array
     */
    public function checkOrderAction(Request $request)
    {
        $code = $this->getCodeFromRequest($request);

        return $this->render(
            'RispoYandexKassaBundle:Default:checkOrder.xml.twig',
            [
                'requestDatetime' => $request->get('requestDatetime'),
                'code' => $code,
                'invoiceId' => $request->get('invoiceId'),
                'shopId' => $request->get('shopId')
            ]
        );
    }

    /**
     * @param Request $request
     * @return array
     */
    public function paymentAvisoAction(Request $request)
    {
        $code = $this->getCodeFromRequest($request);

        if ($code == 0) {
            $instruction = $this->getInstruction($request->get('orderNumber'));

            /** @var FinancialTransactionInterface $transaction */
            if (null === $transaction = $instruction->getPendingTransaction()) {
                return new Response('FAIL (null === $transaction = $instruction->getPendingTransaction())', 500);
            }

            try {
                $this->get('payment.plugin_controller')->approveAndDeposit(
                    $transaction->getPayment()->getId(),
                    $request->get('orderSumAmount')
                );
            } catch (\Exception $e) {
                return new Response('FAIL (approveAndDeposit)', 500);
            }

            $this->get('doctrine.orm.entity_manager')->flush();
        }

        return $this->render(
            'RispoYandexKassaBundle:Default:paymentAviso.xml.twig',
            [
                'requestDatetime' => $request->get('requestDatetime'),
                'code' => $code,
                'invoiceId' => $request->get('invoiceId'),
                'shopId' => $request->get('shopId')
            ]
        );
    }

    /**
     * @param Request $request
     * @return array
     */
    public function successAction(Request $request)
    {
        $orderNumber = $request->get('orderNumber');
        $instruction = $this->getInstruction($orderNumber);
        $data = $instruction->getExtendedData();

        return $this->redirect($data->get('return_url'));
    }

    /**
     * @param Request $request
     * @return array
     */
    public function failAction(Request $request)
    {
        $orderNumber = $request->get('orderNumber');
        $instruction = $this->getInstruction($orderNumber);
        $data = $instruction->getExtendedData();

        return $this->redirect($data->get('cancel_url'));
    }

    private function getInstruction($id)
    {
        $instruction = $this->get('doctrine.orm.entity_manager')
            ->getRepository('JMSPaymentCoreBundle:PaymentInstruction')
            ->find($id);
        if (empty($instruction)) {
            throw new \Exception('Cannot find instruction id=' . $id);
        }

        return $instruction;
    }

    /**
     * @param Request $request
     * @return int
     */
    private function getCodeFromRequest(Request $request)
    {
        $hash = md5(
            implode(
                ';',
                [
                    $request->get('action'),
                    $request->get('orderSumAmount'),
                    $request->get('orderSumCurrencyPaycash'),
                    $request->get('orderSumBankPaycash'),
                    $this->getParameter('rispo_yandexkassa_shopId'),
                    $request->get('invoiceId'),
                    $request->get('customerNumber'),
                    $this->getParameter('rispo_yandexkassa_ShopPassword')
                ]
            )
        );

        return (int)(strtolower($hash) !== strtolower($request->get('md5')));
    }
}