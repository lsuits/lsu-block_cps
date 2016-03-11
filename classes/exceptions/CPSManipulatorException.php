<?php

class CPSManipulatorException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null) {
        // $message = 'UES Manipulator: ' . $message;

        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}