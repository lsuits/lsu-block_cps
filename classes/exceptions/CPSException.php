<?php

class CPSException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null) {
        // $message = 'CPS Exception: ' . $message;

        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}