<?php

namespace Celebron\social\interfaces;
/**
 * @property string $name
 * @property string $icon
 * @property bool $visible
 */
interface ToWidgetInterface
{
    public function getName():string;
    public function getIcon():string;
    public function getVisible():bool;

    public static function urlLogin(?string $state = null): string;
    public static function urlRegister(?string $state= null): string;
    public static function urlDelete(?string $state= null): string;

}