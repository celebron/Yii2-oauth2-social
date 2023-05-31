<?php

namespace Celebron\social\interfaces;


trait ToWidgetTrait
{
    public const METHOD_REGISTER = 'register';
    public const METHOD_DELETE = 'delete';
    public const METHOD_LOGIN = 'login';
    
    public function getName (): string
    {
        return $this->_name  ?? static::socialName();
    }

    public function setName(?string $name):void
    {
        $this->_name = $name;
    }

    public function getIcon (): string
    {
        return $this->_icon;
    }

    public function setIcon(string $icon):void
    {
        $this->_icon = $icon;
    }

    public function getVisible (): bool
    {
        return $this->_visible;
    }

    public function setVisible(bool $visible):void
    {
        $this->_visible = $visible;
    }

    public static function urlLogin(?string $state = null): string
    {
        return static::url(self::METHOD_LOGIN, $state);
    }

    public static function urlRegister(?string $state= null): string
    {
        return static::url(self::METHOD_REGISTER, $state);
    }

    public static function urlDelete(?string $state= null): string
    {
        return static::url(self::METHOD_DELETE, $state);
    }

}