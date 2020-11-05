<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Champions extends Model
{
    /**
     * Search record by riot summoner id, add it if not found. 
     *
     * @param  int  $riotId
     * @param  string  $name
     * @return \App\Summoners|null
     */
    public static function findOrAddByRiotId (int $riotId, string $name = null) : ?Champions {
        $champ = Champions::where('riot_id', $riot_id)->first();
        if (!$champ) {
            if ($name) {
                $champ = $this;
                $champ->riotId = $riotId;
                $champ->name = $name;
                $champ->save();
            }
        }
        return $champ;
    }
    
}
