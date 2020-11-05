<?php 
namespace App\Exceptions;
use Illuminate\Support\Facades\Log;

class ReportableException extends \ErrorException {
    
    private $channel = "general_error";
    private $http_code;
    
    function __construct($message = null, $channel=null, $responseCode = 503, $code = null, $severity = null, $filename = null, $lineno = null, $previous = null) {
        $this->channel = $channel ?? $this->channel;
        $this->http_code = $responseCode ?? false;
        return parent::__construct($message, $code, $severity, $filename, $lineno, $previous);
    }
    
    function report(string $message = null, $channelOverride = null, $bypassToUpperLevel = false) {
        $channel = $channelOverride ?? $this->channel;
        $message = $message ?? $e->getMessage();
        Log::channel($channel)->info($message);
        if ($bypassToUpperLevel) {
            throw $e;
        } else {
            return $this->http_code;
        }
    }
    
}