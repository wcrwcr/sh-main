<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Http\Requests\TrayRequest;
use App\Exception\{DBException, ReportableException};

use App\{IncomingPackets, Summoners, Champions, SummonerChampions, SummonerSkins, SummonerItemsets};


class ProcessIncomingPacket implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $packet;
    protected $fileName;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($requestData, $fileId)
    {
        $this->packet = $requestData;
        $this->fileName = $fileId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if (!$this->fullStorage()) {
                throw new ReportableException("Failed to store whole packet");
            }
            if (!$summonerId = $this->summonerStorage()) {
                throw new ReportableException("Failed to store summoner");
            }
            if (!$this->championStorage($summonerId)) {
                throw new ReportableException("Failed to store champions");
            }
            if (!$this->missionStorage($summonerId)) {
                throw new ReportableException("Failed to store missions");
            }
            return true;
        }catch (ReportableException $e){
            return $e->report();
        }
        return false;
    }
    
    private function fullStorage() {
        $fullStore = new IncomingPackets;
        $fullStore->packet = json_encode($this->packet);
        $fullStore->storage_file =  $this->fileName;
        $this->recieved_at = \Carbon\Carbon::now();
        return $fullStore->save();
    }
    
    private function summonerStorage() : ?int {
        try {
            $riotId = $this->packet['summoner']['id'] ?? null;
            $riotPuuid = $this->packet['summoner']['puuid'] ?? null;
            if(!$riotId || !$puuid) {
                throw new DBException('got empty summoner data in packet');
            }
            
            
            if (!($summoner = Summoners::locateByRiotId($riotId, $riotPuuid))) {
                $summoner = new Summoners;
                $summoner->riot_id = $riotId;
                $summoner->riot_puuid = $riotPuuid;
                $summoner->riot_icon_id = $this->packet['summoner']['icon'] ?? 0;
                $summoner->name = $this->packet['summoner']['name'] ?? 'undefined';
                if (!$summoner->save()) {
                    throw new DBException("Failed to save summoner [{$summoner->name} : {$summoner->riot_icon_id} #{$riotId}:{$riotPuuid}]");
                }
            }
            return $summoner->id;
        } catch (DBException $e) {
            $e->report();
        }
        return null;
    }
    
    private function championStorage(int $summonerId) : bool {
        try {
            foreach ($this->packet['champions'] as $champRaw) {
                try {
                    if (!$champRaw['id'] || !$champRaw['name']) {
                        throw new ReportableException("Got broken champion with #{$champRaw['id']} ('{$champRaw['name']}')");
                    }

                    $champ = (new Champions())->findOrAddByRiotId($champRaw['id'], $champRaw['name']);
                    if (! $sumChamp = SummonerChampions::firstOrCreate(['summoner_id' => $summonerId, 'champion_id' => $champ->id])) {
                        throw new DBException("Cant store champ#{$championId} for summ#{$summonerId}");
                    }
                    
                    if (!empty($champRaw['skins'])) {
                        foreach ($champRaw['skins'] as $skinRaw) {
                            try {
                                if (! $skin = SummonerSkins::firstOrCreate([
                                    'summoner_id' => $summonerId,
                                    'champion_id' => $champ->id,
                                    'riot_skin_id' => $skinRaw['id']
                                    
                                ], ['name' => $skinRaw['name']])) {
                                    throw new DBException("Cant store skin#{$skinRaw['id']}:{$skinRaw['name']} champ#{$champ->id} for summ#{$summonerId}");
                                }
                            } catch (ReportableException $e) {
                                return $e->report();
                            }
                        }
                    }
                    
                    if (!empty($champRaw['items'])) {
                        foreach ($champRaw['items'] as $itemRaw) {
                            try {
                                if (! $itemset = SummonerItemsets::addRawData($summonerId, $champ->id, $itemRaw)) {
                                    throw new DBException("Cant store itemset for champ#{$champ->id} for summ#{$summonerId}");
                                }
                            } catch (ReportableException $e) {
                                return $e->report();
                            }
                        }
                    }
                    
                    
                } catch (ReportableException $e) {
                    return $e->report();
                }
            }
            
            return true;
        } catch (DBException $e) {
            $e->report();
        }
        return false;
    }
    
    private function missionStorage(int $summonerId) : bool {
        try {
            if (empty($this->packet['missions'] ?? [])) {
                return true;
            }
            foreach ($this->packet['missions'] as $series) {
                if (empty($series ?? [])) {
                    continue;
                }
                foreach ($series as $mission) {
                    if (empty($mission ?? [])) {
                        continue;
                    }
                    try {
                        $expirationTimestamp = \Carbon\Carbon::createFromTimestampMs($mission['endTime']);
                        $nowTimestamp = \Carbon\Carbon::now();
                        
                        if (($mission['seriesName'] ?? null)) {
                            $seriesDB = MissionSeries::firstOrCreate(['riot_series_name' => $mission['seriesName']], ['expired_at' => $expirationTimestamp]);
                        }
                        
                        //diffInMinutes
                        
                        if (!$mission = Missions::firstOrCreate(['riot_id' => $mission['id']], 
                            [
                                'name' => $mission['name'] ?? $mission['_json']['internalName'] ?? $mission['seriesName'],
                                'description' => $mission['description'],
                                'series_id' => $seriesDB->id,
                                'expired_at' => $expirationTimestamp
                            ]
                        )) {
                            throw new DBException("Cant store mission#{$mission['id']}");
                        }
                        //TODO: below
                        
                        $champ = (new Champions())->findOrAddByRiotId($champRaw['id'], $champRaw['name']);
                        if (! $sumChamp = SummonerChampions::firstOrCreate(['summoner_id' => $summonerId, 'champion_id' => $champ->id])) {
                            throw new DBException("Cant store champ#{$championId} for summ#{$summonerId}");
                        }
                        
                        if (!empty($champRaw['skins'])) {
                            foreach ($champRaw['skins'] as $skinRaw) {
                                try {
                                    if (! $skin = SummonerSkins::firstOrCreate([
                                        'summoner_id' => $summonerId,
                                        'champion_id' => $champ->id,
                                        'riot_skin_id' => $skinRaw['id']
                                        
                                    ], ['name' => $skinRaw['name']])) {
                                        throw new DBException("Cant store skin#{$skinRaw['id']}:{$skinRaw['name']} champ#{$champ->id} for summ#{$summonerId}");
                                    }
                                } catch (ReportableException $e) {
                                    return $e->report();
                                }
                            }
                        }
                        
                        if (!empty($champRaw['items'])) {
                            foreach ($champRaw['items'] as $itemRaw) {
                                try {
                                    if (! $itemset = SummonerItemsets::addRawData($summonerId, $champ->id, $itemRaw)) {
                                        throw new DBException("Cant store itemset for champ#{$champ->id} for summ#{$summonerId}");
                                    }
                                } catch (ReportableException $e) {
                                    return $e->report();
                                }
                            }
                        }
                        
                        
                    } catch (ReportableException $e) {
                        return $e->report();
                    }
                }
            }
            
            return true;
        } catch (DBException $e) {
            $e->report();
        }
        return false;
    }
    
}
