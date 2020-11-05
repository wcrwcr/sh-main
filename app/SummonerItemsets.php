<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Items;

class SummonerItemsets extends Model
{
    /**
     * Adds missing itemsets for summoner
     *
     * @param  int  $summonerId
     * @param  int  $champId
     * @param  string  $itemRaw
     * @return \App\SummonerItemsets|null
     */
    public static function addRawData (int $summonerId, int $champId, array $itemRaw) : ?SummonerItemsets {
        
        //$itemRaw maps - карты
        //$itemRaw slots - расположение
        //$itemRaw blocks or _json.blocks - сами итемы
        
        $blocks = json_encode($itemRaw['blocks'] ?? $itemRaw['_json']['blocks'] ?? []);
        $name = $itemRaw['name'] ?? 'undefined';
        $maps = json_encode($itemRaw['maps'] ?? []);
        $slots = json_encode($itemRaw['slots'] ?? []);
        $md5 = md5("{$blocks}{$maps}{$slots}{$name}");
        
        $champions = json_encode($itemRaw['champions'] ?? []);
        
        $itemset = SummonerItemsets::where('summoner_id', $summonerId)
            ->where('champion_id', $champId)
            ->where('riot_hash_key', $itemRaw['id'])
            ->first();
        if (!$itemset) {
            $itemset = new SummonerItemsets();
        }
        $itemset->summoner_id = $summonerId;
        $itemset->champion_id = $champId;
        $itemset->name = $name;
        $itemset->md5 = $md5;
        $itemset->riot_champion_ids = $champions;
        $itemset->riot_map_ids = $maps;
        $itemset->riot_slot_map = $slots;
        $itemset->riot_blocks = $blocks;
        $itemset->riot_hash_key = $itemRaw['id'];
        
        if ($itemset->wasChanged()) {
            $itemset->save();
            foreach (json_decode($blocks) as $rows) {
                $items = $rows['items'] ?? [];
                if (!empty($rows['items']) && is_array($rows['items'])) {
                    foreach ($rows['items'] as $item) {
                        $itemDB = Items::firstOrCreate(['riot_id' => $item['id']]);
                    }
                }
            }
        }
            
        
        return $itemset;
    }
}

