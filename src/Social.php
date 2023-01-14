<?php

namespace Celebron\social;

use Celebron\social\interfaces\RequestIdInterface;
use Celebron\social\RequestCode;
use Celebron\social\RequestToken;
use Celebron\social\eventArgs\ErrorEventArgs;
use Celebron\social\eventArgs\FindUserEventArgs;
use Celebron\social\eventArgs\SuccessEventArgs;
use Celebron\social\interfaces\GetUrlsInterface;
use Exception;
use ReflectionClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\di\Instance;
use yii\di\NotInstantiableException;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;
use yii\httpclient\Request;
use yii\httpclient\Response;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\IdentityInterface;
use yii\web\NotFoundHttpException;

/**
 * Базовый класс авторизации соц.сетей.
 * @property-read Client $client - (для чтения) Http Client
 */
abstract class Social extends Model
{
    public const EVENT_REGISTER_SUCCESS = "registerSuccess";
    public const EVENT_LOGIN_SUCCESS = 'loginSuccess';
    public const EVENT_ERROR = "error";
    public const EVENT_DELETE_SUCCESS = 'deleteSuccess';
    public const EVENT_FIND_USER = "findUser";

    public const SCENARIO_REQUEST = 'request';
    public const SCENARIO_RESPONSE = 'response';

    ////В config

    /** @var string - поле в базе данных для идентификации  */
    public string $field;
    /** @var bool - разрешить использование данной социальной сети  */
    public bool $active = true;
    /** @var bool - использование сессии для сохранения data */
    public bool $useSession = false;
    /** @var string - ид от соц.сети */
    public string $clientId;
    /** @var string - секрет от соц.сети  */
    public string $clientSecret;



    ///В Controllers

    /** @var null|string - oAuth2 state */
    public ?string $state;
    /** @var string|null - oAuth2 code */
    public ?string $code;
    /** @var string - oAuth redirectUrl */
    public string $redirectUrl;

    /** @var array - Данные от социальных сетей */
    public array $data = [];
    /** @var mixed|null - Id от соцеальных сетей */
    public mixed $id = null;


    protected readonly Client $client;

    public function __construct ($config = [])
    {
        parent::__construct($config);
        $this->client = new Client();
        $this->client->transport = CurlTransport::class;
        if($this instanceof GetUrlsInterface) {
            $this->client->baseUrl = $this->getBaseUrl();
        }

        $name = static::socialName();
        //Генерация констант под каждую соц.сеть
        $contName = 'SOCIAL_' . strtoupper($name);
        if(!defined($contName)) {
            define($contName, strtolower($name));
        }
    }


    /**
     * Правила проверки данных
     * @return array
     */
    public function rules (): array
    {
        return [
            ['redirectUrl', 'url', 'on' => self::SCENARIO_REQUEST ],
            [['clientId', 'clientSecret'], 'string', 'on' => self::SCENARIO_REQUEST],
            [['clientId', 'clientSecret'], 'required', 'on' => self::SCENARIO_REQUEST],
            ['field', 'fieldValidator','on' => [self::SCENARIO_RESPONSE, self::SCENARIO_REQUEST]],
            ['code', 'codeValidator', 'skipOnEmpty' => false, 'on' => self::SCENARIO_REQUEST ],
        ];
    }

    /**
     * Валидация поля аврторизации
     * @param $a
     * @return void
     * @throws InvalidConfigException
     */
    final public function fieldValidator($a) : void
    {
        $class = Yii::createObject(Yii::$app->user->identityClass);
        if(!($class instanceof ActiveRecord)) {
            throw new NotInstantiableException(ActiveRecord::class, code: 0);
        }
        if(!ArrayHelper::isIn($this->$a, $class->attributes())) {
            throw new InvalidConfigException('Field ' . $this->$a . ' not supported to class ' .$class::class, code: 1);
        }
    }

    /**
     * Валидация кода
     * @param $a
     * @throws NotFoundHttpException
     */
    final public function codeValidator($a): void
    {
        if ($this->$a === null) {
            $request = new RequestCode();
            $request->uri = ($this instanceof GetUrlsInterface) ? $this->getUriCode():'';
            $request->client_id = $this->clientId;
            $request->redirect_uri = $this->redirectUrl;
            $request->state = $this->state;
            $this->requestCode($request);
            if($request->enable) {
                $request->toClient($this->client);
            } else { exit(0); }
        }

        $request = new RequestToken($this->code);
        $request->uri = ($this instanceof GetUrlsInterface) ? $this->getUriToken():'';
        $request->client_id = $this->clientId;
        $request->redirect_uri = $this->redirectUrl;
        $request->client_secret = $this->clientSecret;
        $this->requestToken($request);

        if(($this instanceof RequestIdInterface) && $request->enable) {
            $response = $this->send($request, 'token');
            $requestId = new RequestId($response, $this->client);
            $requestId->uri = $this->getUriInfo();
            $this->id = $this->requestId($requestId);
        }

        \Yii::debug("User id: {$this->id}", static::class);

        if ($this->id === null) {
            throw new NotFoundHttpException("User not found", code: 2);
        }
    }



    abstract protected function requestCode(RequestCode $request): void;
    abstract protected function requestToken(RequestToken $request): void;


    /**
     * Поиск по полю в бд
     * @return IdentityInterface|ActiveRecord
     * @throws InvalidConfigException
     */
    protected function findUser(): ?IdentityInterface
    {
        $class = Instance::ensure(\Yii::$app->user->identityClass, ActiveRecord::class);
        $query = $class::find()->andWhere([$this->field => $this->id]);
        $findUserEventArgs = new FindUserEventArgs($query);
        $this->trigger(self::EVENT_FIND_USER, $findUserEventArgs);
        \Yii::debug($findUserEventArgs->user?->toArray(), static::class);
        return $findUserEventArgs->user;
    }

    /**
     * @return mixed
     * @throws NotSupportedException
     */
    public function getSocialId(): mixed
    {
        $this->scenario = self::SCENARIO_RESPONSE;
        if($this->validate()) {
            return \Yii::$app->user->identity->{$this->field};
        }
        throw new NotSupportedException('Not validate Social class');
    }

    /**
     * Регистрация пользователя из социальной сети
     * @return bool
     */
    final public function register() : bool
    {
        $this->scenario =  self::SCENARIO_REQUEST;
        return $this->validate() && $this->modifiedUser($this->id);
    }

    /**
     * Удаление записи соц УЗ.
     * @return bool
     */
    final public function delete() : bool
    {
        $this->scenario = self::SCENARIO_RESPONSE;
        return $this->validate() && $this->modifiedUser(null);
    }

    /**
     * Авторизация в системе
     * @param int $duration
     * @return bool
     * @throws InvalidConfigException
     */
    final public function login(int $duration = 0) : bool
    {
        $this->scenario = self::SCENARIO_REQUEST;
        if($this->validate() && ( ($user = $this->findUser()) !== null )) {
            $login = Yii::$app->user->login($user, $duration);
            self::debug("User login ($this->id) " . $login ? "succeeded": "failed");
            return $login;
        }
        return false;
    }

    public function deleteSuccess(SocialController $action)
    {
        $eventArgs = new SuccessEventArgs($action);
        $eventArgs->useSession = $this->useSession;
        $this->trigger(self::EVENT_DELETE_SUCCESS, $eventArgs);
        if($eventArgs->useSession) {
            if(!\Yii::$app->session->isActive) {
                \Yii::$app->session->open();
            }
            $session = \Yii::$app->session;
            \Yii::debug('Used session to save token', static::class);
            $session[static::socialName() . '.token'] = null;
        }
        return $eventArgs->result ?? $action->goBack();
    }

    /**
     * Событие положительной авторизации
     * @param SocialController $action
     * @return \yii\web\Response
     */
    public function loginSuccess(SocialController $action): mixed
    {
        $eventArgs = new SuccessEventArgs($action);
        $eventArgs->useSession = $this->useSession;
        $this->trigger(self::EVENT_LOGIN_SUCCESS, $eventArgs);
        if($eventArgs->useSession) {
            if(!\Yii::$app->session->isActive) {
                \Yii::$app->session->open();
            }
            $session = \Yii::$app->session;
            \Yii::debug('Used session to save token', static::class);
            $session[static::socialName() . '.token'] = $this->data['token'];
        }
        return $eventArgs->result ?? $action->goBack();
    }

    /**
     * Событие положительной регистрации
     * @param SocialController $action
     * @return mixed
     */
    public function registerSuccess(SocialController $action): mixed
    {
        $eventArgs = new SuccessEventArgs($action);
        $this->trigger(self::EVENT_REGISTER_SUCCESS, $eventArgs);
        return $eventArgs->result ?? $action->goBack();
    }

    /**
     * Событие на ошибку
     * @param SocialController $action
     * @param Exception|null $ex
     * @return mixed
     * @throws ForbiddenHttpException|NotFoundHttpException
     * @throws Exception
     */
    public function error(SocialController $action, ?Exception $ex): mixed
    {
        $eventArgs = new ErrorEventArgs($action, $ex);
        $this->trigger(self::EVENT_ERROR, $eventArgs);

        if($eventArgs->result === null) {
            throw $ex;
        }

        return $eventArgs->result;
    }

    /**
     * Модификация данных пользователя
     * @param mixed $data - Значение поля field в пользовательской модели
     * @return bool
     */
    protected function modifiedUser(mixed $data) : bool
    {
        /** @var ActiveRecord|IdentityInterface $user */
        $user = Yii::$app->user->identity;
        $field = $this->field;
        $user->$field = $data;

        if ($user->save()) {
            \Yii::debug("Save field ['{$field}' = {$data}] to user {$user->getId()}", static::class);
            return true;
        }
        \Yii::warning($user->getErrorSummary(true), static::class);
        return false;
    }


    /**
     * Выполнение отправки сообщения
     * @param Request|RequestToken $sender - Запрос
     * @param string $theme - Тема
     * @return Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    protected function send(Request|RequestToken $sender, string $theme = 'info') : Response
    {
        if($sender instanceof  RequestToken) {
            $sender = $sender->toRequest($this->client);
        }

        $response = $this->client->send($sender);
        if ($response->isOk && !isset($response->data['error'])) {

            $this->data[$theme] = $response->getData();
            \Yii::debug($this->data[$theme],static::class);
            return $response;
        }

        $this->getException($response);
    }

    /**
     * Отслеживание ошибки
     * @param Response $response
     * @throws BadRequestHttpException
     * @throws \yii\httpclient\Exception
     */
    protected function getException (Response $response): void
    {
        $data = $response->getData();
        \Yii::warning($this->data, static::class);
        if (isset($data['error'], $data['error_description'])) {
            throw new BadRequestHttpException('[' . static::socialName() . "]Error {$data['error']} (E{$response->getStatusCode()}). {$data['error_description']}");
        }
        throw new BadRequestHttpException('[' . static::socialName() . "]Response not correct. Code E{$response->getStatusCode()}");
    }

    /**
     * Название класса
     * @return string
     */
    final public static function socialName(): string
    {
        $reflect = new ReflectionClass(static::class);
        $attributes = $reflect->getAttributes(SocialName::class);
        $socialName = $reflect->getShortName();
        if(count($attributes) > 0) {
            $socialName = $attributes[0]->getArguments()[0];
        }

        return $socialName;
    }

    /**
     * Ссылка на oauth авторизацию
     * @param bool|string|null $state
     * @return string
     */
    final public static function url(bool|string|null $state = false) : string
    {
        return SocialConfiguration::url(static::socialName(), $state);
    }
}