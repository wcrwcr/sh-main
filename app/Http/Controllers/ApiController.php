<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

use App\Http\Requests\TrayRequest;
use App\Jobs\ProcessIncomingPacket;
use App\Exceptions\ReportableException;


class ApiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     *
      * @OA\Get(
      *      path="/api/trayData",
      *      tags={"tray"},
      *      summary="auth",
      *      description="TBD: get auth for tray app with tokens",
      *      @OA\Response(
      *          response=200,
      *          description="Successful operation"
      *       ),
      *       @OA\Response(response=404, description="Bad request"),
      *     )
      *
      * Returns list of projects
      */
    public function index()
    {
        //
        return 'dummy';
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * /
     /**
     * @OA\Post(
     *     path="/api/trayData",
     *     tags={"tray"},
     *     operationId="addPacket",
     *     summary="Add a new packet from LCU",
     *     description="TBD",
     *     @OA\RequestBody(
     *          request="Traio",
     *          description="Sample LCU data request",
     *          required=true,
     *          @OA\JsonContent(ref="../schemas/IncomingPackets.json"),
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Ok",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Invalid input",
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="General error",
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="TBD: Auth",
     *     ),
     *     
     * )
     */
    public function store(TrayRequest $request)
    {
        try{
            
            $file = \Str::random(12). ".json";
            $data = $request->all();
            
            if (empty($data) || !isset($data['summoner']) || empty($data['summoner']))
                throw new ReportableException("Empty packet recieved from {$request->ip()}", 'packets', 405);

            Storage::disk('packets')->put($file, json_encode($data));
            
            Log::channel('packets')->info('Packet recieved pr '. print_r($data, 1));
            
            ProcessIncomingPacket::dispatch($data, $file);
            $rCode = 202;
            $rMess = 'Ok';
            return response('Ok', 202)->header('Content-Type', 'text/plain');
        } catch (ReportableException $e) {
            $rCode = $e->report();
            $rMess = "Invalid input";
        } catch (\Exception $e) {
            $e2 = new ReportableException("Error on  packet recieved from {$request->ip()} [{$e->getMessage()}] ", 'packets', 503);
            $rCode = $e2->report();
            $rMess = "General Error";
            throw $e;
        }
        return response($rMess, $rCode)->header('Content-Type', 'text/plain');
        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\IncomingPackets  $incomingPackets
     * @return \Illuminate\Http\Response
     */
    public function show(IncomingPackets $incomingPackets)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\TrayRequests  $request
     * @param  \App\IncomingPackets  $incomingPackets
     * @return \Illuminate\Http\Response
     */
    public function update(TrayRequest $request, IncomingPackets $incomingPackets)
    {
        //
    }

  
}
