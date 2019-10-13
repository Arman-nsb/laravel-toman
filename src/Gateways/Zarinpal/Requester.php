<?php

namespace AmirrezaNasiri\LaravelToman\Gateways\Zarinpal;

use AmirrezaNasiri\LaravelToman\Exceptions\InvalidConfigException;
use AmirrezaNasiri\LaravelToman\Gateways\BaseRequester;
use AmirrezaNasiri\LaravelToman\Results\RequestedPayment;
use AmirrezaNasiri\LaravelToman\Tests\Gateways\Zarinpal\Status;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Arr;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\URL;
use AmirrezaNasiri\LaravelToman\Helpers\Client as ClientHelper;
use AmirrezaNasiri\LaravelToman\Helpers\Gateway as GatewayHelper;

/**
 * Class Requester
 * @package AmirrezaNasiri\LaravelToman\Gateways\Zarinpal
 */
class Requester extends BaseRequester
{
    use CommonMethods;

    /**
     * Requester constructor.
     * @param $config
     * @param Client $client
     */
    public function __construct($config, Client $client)
    {
        $this->setConfig($config);
        $this->client = $client;
    }

    /**
     * Initialize a Requester object on-the-fly
     * @param $config
     * @param Client $client
     * @return self
     */
    public static function make($config, Client $client)
    {
        return new self($config, $client);
    }

    /**
     * Set <i>CallbackURL</i> data and override config
     * @param $callbackUrl string
     * @return $this
     */
    public function callback($callbackUrl)
    {
        $this->data('CallbackURL', $callbackUrl);

        return $this;
    }

    /**
     * Set <i>Mobile</i> data
     * @param $mobile string
     * @return $this
     */
    public function mobile($mobile)
    {
        $this->data('Mobile', $mobile);

        return $this;
    }

    /**
     * Set <i>Email</i> data
     * @param $email string
     * @return $this
     */
    public function email($email)
    {
        $this->data('Email', $email);

        return $this;
    }

    /**
     * Set <i>Description</i> data and override config
     * @param $amount
     * @return $this
     */
    public function description($description)
    {
        $this->data('Description', $description);

        return $this;
    }

    /**
     * Request a new payment from gateway
     * @return RequestedPayment If new payment is created and is ready to pay
     * @throws \AmirrezaNasiri\LaravelToman\Exceptions\GatewayException If new payment was not created
     * @throws InvalidConfigException
     */
    public function request(): RequestedPayment
    {
        try {
            $response = $this->client->post(
                $this->makeRequestURL(),
                [RequestOptions::JSON => $this->makeRequestData()]
            );
        } catch (ClientException | ServerException $exception) {
            GatewayHelper::fail($exception);
        }

        $data = ClientHelper::getResponseData($response);

        $transactionId = Arr::get($data, 'Authority');

        if (Arr::get($data, 'Status') !== Status::PAYMENT_SUCCEED || ! $transactionId) {
            GatewayHelper::fail($data);
        }

        return new RequestedPayment($transactionId, $this->getPaymentUrlFor($transactionId));
    }

    /**
     * Get payable URL for user
     * @param $transactionId
     * @return string
     * @throws \AmirrezaNasiri\LaravelToman\Exceptions\InvalidConfigException
     */
    private function getPaymentUrlFor($transactionId)
    {
        return $this->getHost()."/pg/StartPay/{$transactionId}";
    }

    /**
     * Make environment-aware verification endpoint URL
     * @return string
     * @throws InvalidConfigException
     */
    private function makeRequestURL()
    {
        return $this->getHost().'/pg/rest/WebGate/PaymentRequest.json';
    }

    /**
     * Make config-aware verification endpoint required data
     * @return array
     */
    private function makeRequestData()
    {
        return array_merge($this->data, [
            'MerchantID' => $this->getMerchantId(),
            'CallbackURL' => $this->getCallbackUrl(),
            'Description' => $this->getDescription(),
        ]);
    }

    /**
     * Get 'CallbackURL' from data or default one from config if available
     * @return array|mixed|string
     */
    private function getCallbackUrl()
    {
        if ($data = $this->getData('CallbackURL')) {
            return $data;
        }

        if ($defaultRoute = config('toman.callback_route')) {
            return URL::route($defaultRoute);
        }
    }

    /**
     * Get 'Description' from data or default one from config if available
     * @return array|mixed|string
     */
    private function getDescription()
    {
        $description = $this->getData('Description');

        if (! $description) {
            $description = config('toman.description');
        }

        return str_replace(':amount', $this->getData('Amount'), $description);
    }
}
