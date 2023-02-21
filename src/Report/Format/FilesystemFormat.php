<?php

namespace Drutiny\Report\Format;

use Drutiny\Attribute\AsFormat;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\Format;
use Exception;
use ReflectionClass;

abstract class FilesystemFormat extends Format implements FilesystemFormatInterface {

    /**
     * {@inheritdoc}
     */
    function getExtension(): string
    {
        $reflect = new ReflectionClass($this);
        $attributes = $reflect->getAttributes(AsFormat::class);

        if (empty($attributes)) {
            throw new Exception(get_class($this) . " has no format attribute.");
        }

        $format = $attributes[0]->newInstance();
        return $format->extension;
    }
}