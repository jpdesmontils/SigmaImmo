<?php
class APIException extends RuntimeException
{
    private $status;
    private $details;
    public function __construct($status, $code, $message, $details = null)
    {
        parent::__construct($message, 0);
        $this->status = (int)$status;
        $this->details = $details;
        $this->apiCode = $code;
    }
    private $apiCode;
    public function status() { return $this->status; }
    public function apiCode() { return $this->apiCode; }
    public function details() { return $this->details; }
}
