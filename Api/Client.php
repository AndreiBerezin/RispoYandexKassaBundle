<?php

namespace Rispo\YandexKassaBundle\Api;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class Client
 * @package Rispo\YandexKassaBundle\Api
 */
class Client
{
    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var string */
    private $shopId;

    /** @var string */
    private $scid;

    /** @var string */
    private $shopPassword;

    /** @var bool */
    private $test;

    public function __construct(TokenStorageInterface $tokenStorage, $shopId, $scid, $shopPassword, $test)
    {
        $this->tokenStorage = $tokenStorage;
        $this->shopId = $shopId;
        $this->scid = $scid;
        $this->shopPassword = $shopPassword;
        $this->test = $test;
    }

    /**
     * @return string
     */
    private function getWebServerUrl()
    {
        return $this->test ? 'https://demomoney.yandex.ru/eshop.xml' : 'https://money.yandex.ru/eshop.xml';
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return string
     * @throws \Exception
     */
    public function getRedirectUrl(FinancialTransactionInterface $transaction)
    {
        /** @var PaymentInstructionInterface $instruction */
        $instruction = $transaction->getPayment()->getPaymentInstruction();

        $extendedData = $transaction->getExtendedData()->all();
        $data = [];
        foreach ($extendedData as $name => $item) {
            $data[$name] = $item[0];
        }
        $data = array_merge($data, [
            'shopId' => $this->shopId,
            'scid' => $this->scid,
            'Sum' => $transaction->getRequestedAmount(),
            'cms_name' => 'symfony3-github',
            'orderNumber' => $instruction->getId()
        ]);

        $client = new \GuzzleHttp\Client();
        $response = $client->request(
            'POST',
            $this->getWebServerUrl(),
            [
                'form_params' => $data,
                'allow_redirects' => false
            ]
        );

        if($response->getStatusCode() == 302) {
            return $response->getHeaderLine('Location');
        } else {
            throw new \Exception('Yandex.Kassa no redirect!');
        }
    }

}