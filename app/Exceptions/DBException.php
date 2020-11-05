<?php 
namespace App\Exceptions;
use Illuminate\Support\Facades\Log;

class  DBException extends ReportableException {
    private $channel = "db_error";
}