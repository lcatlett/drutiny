<?php

namespace Drutiny\Target\Exception;

/**
 * Use when a target identifier is invalid.
 * 
 * This exception should be thrown before a target is attempted to be loaded.
 * Use TargetLoadingException if a problem occurs while loading the target instead.
 */
class InvalidTargetException extends \Exception
{

}
