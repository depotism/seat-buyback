<?php

namespace Depotism\Seat\SeatBuyback\Parser;

use Seat\Eveapi\Models\Sde\InvType;
use RecursiveTree\Seat\TreeLib\Parser\Parser;
use RecursiveTree\Seat\TreeLib\Parser\UnparsedLine;
use RecursiveTree\Seat\TreeLib\Parser\ParseResult;

class AssetWindowParser extends Parser
{
    protected static function parse(string $text, string $EveItemClass): ?ParseResult
    {
        // include translation star
        //$expr = "^(?<name>[^\t*]+)\*?\t(?<amount>(\d*\.?\d*)?)";   
        $expr = "^(?<name>[^\t*]+)\*?\t(?<amount>(\d*\.?\d*\.?\d*\.?\d*\.?\d*)?)"; // fixed to add higher numbers....

        $lines = self::matchLines($expr, $text);

        dd($lines);           

        //check if there are any matches
        if($lines->whereNotNull("match")->isEmpty()) return null;

        $items = [];
        $unparsed = [];

        $warning = false;

        foreach ($lines as $line){
            if($line->match === null) {
                $warning = true;
                continue;
            }

            // dd($line);
            $inv_model = InvType::where('typeName', $line->match->name)->first();
            //dd($inv_model);
            if($inv_model==null){
                $warning = true;
                $unparsed[] = new UnparsedLine($line->line,[
                    'name' => $line->match->name,
                    'amount' => 1
                ]);
                continue;
            }

            $item = new $EveItemClass($inv_model);            
            $item->amount = intval(str_replace([".", " ", ","], "", $line->match->amount));
            //dd($item);
            $items[] = $item;
        }

        //if there are no items, ignore
        if(count($items)<1) return null;

        $result = new ParseResult(collect($items), collect($unparsed));
        $result->warning = $warning;
        return $result;
    }
}