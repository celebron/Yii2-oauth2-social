<?php

namespace Celebron\src_old;

use Celebron\social\old\eventArgs\RequestArgs;
use Celebron\social\old\interfaces\GetUrlsInterface;
use Celebron\social\old\interfaces\AuthRequestInterface;
use yii\base\InvalidConfigException;
use yii\httpclient\{Client, CurlTransport, Exception, Request, Response};
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;


abstract class OAuth2 extends AuthBase implements AuthRequestInterface
{
    public string $clientId;
    public string $clientSecret;
    public string $redirectUrl;


    public readonly Client $client;
    public ?Token $token = null;

    protected array $data = [];
    public mixed $id = null;


    /**
     * @param RequestCode $request
     * @return void
     */
    abstract public function requestCode(RequestCode $request):void;

    /**
     * @param RequestToken $request
     * @return void
     */
    abstract public function requestToken(RequestToken $request): void;


    public function __construct ($config = [])
    {
        parent::__construct($config);
        $this->client = new Client();
        $this->client->transport = CurlTransport::class;
        if($this instanceof GetUrlsInterface) {
            $this->client->baseUrl = $this->getBaseUrl();
        }
    }

    /**
     * @throws InvalidConfigException
     * @throws Exception
     * @throws \yii\base\Exception
     * @throws BadRequestHttpException
     */
    public function Request(\ReflectionMethod $method, RequestArgs $args):void
    {
        $attributes = $method->getAttributes(OAuth2Request::class);
        if (isset($attributes[0])) {
            /** @var OAuth2Request $attr */
            $attr = $attributes[0]->newInstance();
            $attr->request($this, $args);
        }
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    final protected function send(Request $sender, string $theme = 'info') : Response
    {
        $response = $this->client->send($sender);
        if ($response->isOk && !isset($response->data['error'])) {
            $this->data[$theme] = $response->getData();
            \Yii::debug($this->data[$theme],static::class);
            return $response;
        }

        $this->getException($response);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    final protected function sendToken(RequestToken $sender) : Token
    {
        //Получаем данные
        $data = $this
            ->send($sender->sender(), 'token')
            ->getData();
        return new Token($data);
    }

    /**
     * @param Request|RequestToken $sender
     * @param string|\Closure|array $field
     * @return mixed
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws Exception
     */
    protected function sendToField(Request|RequestToken $sender, string|\Closure|array $field) : mixed
    {
        if($sender instanceof  RequestToken) {
            $sender->send = false;
            $sender = $sender->sender();
        }
        $response = $this->send($sender);
        return ArrayHelper::getValue($response->getData(), $field);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    protected function getException(Response $response): void
    {
        $data = $response->getData();
        if (isset($data['error'], $data['error_description'])) {
            throw new BadRequestHttpException('[' . static::socialName() . "]Error {$data['error']} (E{$response->getStatusCode()}). {$data['error_description']}");
        }
        throw new BadRequestHttpException('[' . static::socialName() . "]Response not correct. Code E{$response->getStatusCode()}");
    }


}