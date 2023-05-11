<?php

namespace Drutiny\Target\Exception;

/**
 * Used when a target has a valid identifier but cannot be found.
 * 
 * Use InvalidTargetException when the target identifier is invalid.
 * Use TargetLoadingException when the target is found but encounters errors when loading it.
 */
class TargetNotFoundException extends \Exception
{
}
