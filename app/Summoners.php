<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Summoners extends Model
{
   /**
    * Search record by riot summoner id and riot user unique id. Required due to puuid in string so indexing it directly is complicated  
    *
    * @param  int  $id
    * @param  string  $puuid
    * @return \App\Summoners|null
    */
    public static function locateByRiotId (int $id, string $puuid) : ?Summoners {
        $sums = Summoners::where('riot_id', $id)->all();
        if ($sums) {
            foreach ($sums as $summoner) {
                if ($summoner->riot_puuid == $puuid) 
                    return $summoner;
            }
        }
        return null;
    }
}
