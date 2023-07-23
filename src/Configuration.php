<?php

namespace Celebron\social;

use Celebron\social\args\EventRegister;
use Celebron\social\attrs\SocialName;
use Celebron\social\interfaces\CustomInterfaceSocial;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\UnknownMethodException;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\Application;


/**
 *
 * @property-write array $socials
 *
 * @method socialYandex
 * @method socialVk
 * @method socialTelegram
 * @method
 *
 */
class Configuration extends Component implements BootstrapInterface
{
    public const EVENT_BEFORE_REGISTER = 'beforeRegister';
    public const EVENT_AFTER_REGISTER = 'afterRegister';
    public const EVENT_REGISTER = 'register';
    public string $route = "social";
    public ?string $paramsGroup = null;
    public ?\Closure $onSuccess = null;
    public ?\Closure $onFailed = null;
    public ?\Closure $onError = null;



    public static self $config;
    private array $_socials = [];

    public function __construct ($cfg = [])
    {
        self::$config = $this;
        parent::__construct($cfg);
    }

    /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
    public function bootstrap ($app)
    {
        $app->urlManager->addRules([
            "{$this->route}/<social>" => "{$this->route}/handler",
        ]);

        $app->controllerMap[$this->route] = [
            'class' => SocialController::class,
            'config' => $this,
        ];

    }

    /**
     * @throws InvalidConfigException
     * @throws \ReflectionException
     */
    public function add(array $socialClassConfig, mixed $socialName = 0): void
    {
        if(is_numeric($socialName)) {
            $classRef = new \ReflectionClass($socialClassConfig['class']);
            if($classRef->isSubclassOf(CustomInterfaceSocial::class)) {
                throw new InvalidConfigException('An explicit definition of the key is required (not numeric).');
            }
            $socialName = $classRef->getShortName();
            $attrs = $classRef->getAttributes(SocialName::class);
            if(isset($attrs[0])) {
                /** @var SocialName $attr */
                $attr = $attrs[0]->newInstance();
                $socialName = $attr->name;
            }
            $socialName = strtolower(trim($socialName));
        }
        if(ArrayHelper::keyExists($socialName, $this->_socials)) {
            throw new InvalidConfigException("Key $socialName not exists");
        }
        $registerEventArgs = new EventRegister();
        $object = \Yii::createObject($socialClassConfig, [ $socialName, $this ]);
        $registerEventArgs->support = false;
        if($object instanceof SocialAuthBase) {
            $registerEventArgs->social = $object;
            if($this->onSuccess !== null) {
                $object->on(SocialAuthBase::EVENT_SUCCESS, $this->onSuccess);
            }
            if($this->onFailed !== null) {
                $object->on(SocialAuthBase::EVENT_FAILED, $this->onFailed);
            }
            if($this->onError !== null) {
                $object->on(SocialAuthBase::EVENT_ERROR, $this->onError);
            }
            $registerEventArgs->support = true;
        }
        $this->trigger(self::EVENT_REGISTER, $registerEventArgs);
        if(!$registerEventArgs->support) {
            \Yii::warning($object::class . ' not support',static::class);
        } else {
            \Yii::info("$socialName registered...", static::class);
            $this->_socials[$socialName] = $object;
        }
    }

    /**
     * @throws InvalidConfigException
     * @throws \ReflectionException
     */
    public function setSocials(array $socials):void
    {
        $this->trigger(self::EVENT_BEFORE_REGISTER);
        foreach ($socials as $key => $class) {
            $this->add($class, $key);
        }
        $this->trigger(self::EVENT_AFTER_REGISTER);
    }

    /**
     * @throws \ReflectionException
     */
    public function getSocials (...$interfaces): array
    {
        if(count($interfaces) > 0) {
            $result = [];
            foreach ($this->_socials as $social) {
                $classRef = new \ReflectionClass($social);
                if(count(array_intersect($classRef->getInterfaceNames(), $interfaces)) > 0) {
                    $result[] = $social;
                }
            }
            return $result;
        }
        return $this->_socials;
    }

    /**
     * @throws \Exception
     */
    public function get(string $social, ...$interface): ?SocialAuthBase
    {
        $social =  strtolower(trim(strip_tags($social)));
        /** @var SocialAuthBase $object */
        $object = ArrayHelper::getValue($this->getSocials(...$interface), $social);

        if($object === null) {
            return null;
        }

        if($object instanceof OAuth2 && \Yii::$app instanceof Application) {
            $object->redirectUrl = Url::toRoute([
                "{$this->route}/handler",
                'social' => $social,
            ], true);
        }

        return $object;
    }

    public static function url (string $socialName, string $method, ?string $state=null): string
    {
        $url[0] = self::$config->route . '/handler';
        $url['social'] = strtolower(trim($socialName));
        $url['state'] = (string)State::create($method, $state);
        return Url::toRoute($url, true);
    }

    /**
     * Выводит Social класс по имени класса (static)
     * @param string $socialName
     * @param mixed ...$interfaces
     * @return null|SocialAuthBase
     * @throws \Exception
     */
    public static function social(string $socialName, ...$interfaces) : ?SocialAuthBase
    {
        return  static::$config->get($socialName, ...$interfaces);
    }

    /**
     * Вывод Socials[] (static)
     * @return SocialAuthBase[]
     * @throws \ReflectionException
     */
    public static function socials(...$interfaces): array
    {
        return static::$config->getSocials(...$interfaces);
    }

    /**
     * @throws \Exception
     */
    public static function __callStatic ($methodName, $arguments)
    {
        if(str_starts_with($methodName, 'social')) {
            return static::social(substr($methodName, 6), ...$arguments);
        }
        if(str_starts_with($methodName, 'url')) {
            return static::url(substr($methodName, 3), $arguments[0], $arguments[1] ?? null);
        }
        throw new UnknownMethodException('Calling unknown method: ' . static::class . "::$methodName()");
    }
}