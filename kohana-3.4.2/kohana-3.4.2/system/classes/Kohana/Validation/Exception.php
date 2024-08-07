<?php

/**
 * @package    Kohana
 * @category   Exceptions
 * @author     Kohana Team
 * @copyright  (c) 2009-2012 Kohana Team
 * @license    https://kohana.top/license
 */
 namespace Kohana\Validation;
 
class ValidationException extends \Exception
{
    /**
     * @var  object  Validation instance
     */
    public $array;

    /**
     * @param  Validation   $array      Validation object
     * @param  string       $message    error message
     * @param  array        $values     translation variables
     * @param  int          $code       the exception code
     */
    public function __construct(Validation $array, $message = 'Failed to validate array', array $values = null, $code = 0, Exception $previous = null)
    {
        $this->array = $array;

        parent::__construct($message, $values, $code, $previous);
    }

}
