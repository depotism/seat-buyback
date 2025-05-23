<?php

/*
This file is part of SeAT

Copyright (C) 2015 to 2020  Leon Jacobs

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

namespace Depotism\Seat\SeatBuyback\Services;

use Depotism\Seat\SeatBuyback\Exceptions\ItemParserBadFormatException;
use Depotism\Seat\SeatBuyback\Exceptions\SettingsServiceException;
use Depotism\Seat\SeatBuyback\Helpers\PriceCalculationHelper;
// use Depotism\Seat\SeatBuyback\Provider\IPriceProvider;
use Depotism\Seat\SeatBuyback\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Seat\Eveapi\Models\Sde\InvType;
use RecursiveTree\Seat\PricesCore\Facades\PriceProviderSystem;
use RecursiveTree\Seat\PricesCore\Models\PriceProviderInstance;
use RecursiveTree\Seat\TreeLib\Parser\ItemListParser;
use RecursiveTree\Seat\TreeLib\Parser\Parser;
use Depotism\Seat\SeatBuyback\Parser\AssetWindowParser;
use Depotism\Seat\SeatBuyback\Parser\PriceableEveItem;
use RecursiveTree\Seat\TreeLib\Items\EveItem;
use Depotism\Seat\SeatBuyback\Models\BuybackPriceData;
use Depotism\Seat\SeatBuyback\Models\BuybackMarketConfig;
use Depotism\Seat\SeatBuyback\Models\BuybackMarketConfigGroups;


/**
 * Class ItemService
 */
class ItemService
{
    // private $priceProvider;
    private $settingsService;

    /**
     * @throws SettingsServiceException
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    protected const BIG_NUMBER_REGEXP = "(\d*\.?\d*)";//"(?:\d+(?:[’\s+,]\d\d\d)*(?:[\.,]\d\d)?)";

    /**
     * @return array|null
     * @throws ItemParserBadFormatException
     */
    public function parseEveItemData(string $item_string): ?array
    {
        if (empty($item_string)) {
            return null;
        }

        Parser::registerParser(AssetWindowParser::class);
        $parser_result = Parser::parseItems($item_string, PriceableEveItem::class);

        // i know it is not nice. but we need to split the results because we have different price providers now. 
        $sorted = [];
        $repro = [];
        $repro_arr = [];

        // dd($parser_result->items);
        foreach ($parser_result->items as $item) {
            $marketConfig = BuybackMarketConfig::where('typeId', $item->getTypeID())->first();
            
            if ($marketConfig == null) {
                $invType = InvType::where('typeID', $item->getTypeID())->first();
                $marketConfig = BuybackMarketConfigGroups::where('groupId', $invType->groupID)->first();
            }

            $provider = PriceProviderInstance::orderBy('name', 'asc')->first()->id;
            if ($marketConfig != null) {
                $provider = $marketConfig->provider;
                $item->repro = $marketConfig->repro;
            }
            $item->provider = $provider;
            
            if (array_key_exists($provider, $sorted)) {
                $sorted[$provider]->push($item);
            } else {
                $sorted[$provider] = collect([$item]);
            }


            // if we dont need to repro we will just skip
            if (! $item->repro) continue;

            //dd($marketConfig);
            // check for the materials
            $result = DB::table('invTypes as it')
                ->join('invTypeMaterials as iam', 'iam.typeID', '=', 'it.typeID')
                ->join('invTypes as it2', 'it2.typeID', '=', 'iam.materialTypeID')
                ->join('invGroups as ig', 'ig.groupID', '=', 'it.groupID')
                ->select(
                    'it2.typeID',
                    'it.typeName',
                    'it.groupID',
                    'ig.groupName',
                    'iam.materialTypeID',
                    'iam.quantity',
                    'it2.typeName',
                )
                ->where('it.typeID', '=', $item->getTypeID())
                ->get();            
            
            // add temporary item array, to collect all
            foreach ($result as $db_item) {
                if (array_key_exists($db_item->typeID, $repro_arr)) {
                    $repro_arr[$db_item->typeID] += (int)(($item->amount / $item->typeModel->portionSize)* $db_item->quantity) * (1-($marketConfig->percentage / 100)); 
                } else { // added  * (1-($marketConfig->percentage / 100)) to reduce the amount of reprocessed materials
                    $repro_arr[$db_item->typeID] = (int)(($item->amount / $item->typeModel->portionSize)* $db_item->quantity) * (1-($marketConfig->percentage / 100));
                }                   
                
            }
        }
        // dd($repro_arr);

        // move the items to the new array
        foreach ($repro_arr as $id => $db_item) {
            // create instance of invType
            // give invType to the priceable item
            $repro_item = new PriceableEveItem(InvType::where('typeID', $id)->first());
            $repro_item->amount = (int)$db_item; //  
            $repro_item->provider = $provider;
            $repro_item->isReproResult = true;


            if (array_key_exists($provider, $repro)) {
                $repro[$provider]->push($repro_item);
            } else {
                $repro[$provider] = collect([$repro_item]);
            }            
        }
        // dd($repro); //

        $parsedRawData = [];

        // loop through all repro stuff
        foreach ($repro as $provider => $items) {
            try {
                
                PriceProviderSystem::getPrices((int)$provider, $items);
            } catch (PriceProviderException $e){
                // dd($e);
                return redirect()->back()->with('error',$e->getMessage());
            }


            foreach ($items as $item) {
                $key = $item->getTypeID();

                $parsedRawData[$key]["name"] = $item->getTypeName();
                $parsedRawData[$key]["quantity"] = $item->amount;
                $parsedRawData[$key]["typeID"] = $item->getTypeID();
                $parsedRawData[$key]["priceProvider"] = $provider;
                $parsedRawData[$key]["repro"] = $item->repro;
                $parsedRawData[$key]["isReproResult"] = $item->isReproResult;                
                $parsedRawData[$key]["price"] = $item->price;//$priceData->getItemPrice();
                $parsedRawData[$key]["sum"] = $item->price;//PriceCalculationHelper::calculateItemPrice($item->getTypeID(), $item->amount, new BuybackPriceData($item->getTypeID(), $item->price));
            }        
        }
        //dd($repro); 
        // dd('too far');

        // loop through all price providers
        foreach ($sorted as $provider => $items) {
            try {
                PriceProviderSystem::getPrices((int)$provider, $items);
            } catch (PriceProviderException $e){
                // dd($e);
                return redirect()->back()->with('error',$e->getMessage());
            }
            
            foreach ($items as $item) {
                $key = $item->getTypeID();

                // if we have to calculate the repro stuff we need to go through here and set the item->price.
                // if ($item->repro) {
                //     $item->price = 0;

                //     foreach ($repro[$item->provider] as $repro_itm) {    
                //         if ($repro_item->parent != $item) continue; // skip if it does not belong to us.

                //         $item->price += $repro_itm->price;
                //     }
                //     // dd($item->price);
                // }

                $parsedRawData[$key]["name"] = $item->getTypeName();
                $parsedRawData[$key]["quantity"] = $item->amount;
                $parsedRawData[$key]["typeID"] = $item->getTypeID();
                $parsedRawData[$key]["priceProvider"] = $provider;
                $parsedRawData[$key]["repro"] = $item->repro;
                $parsedRawData[$key]["isReproResult"] = $item->isReproResult;                
                $parsedRawData[$key]["price"] = 0;
                $parsedRawData[$key]["sum"] = 0;
                // if the item is not valued at the reprocessed value then ignore that here.
                if (!$item->repro) {
                    $parsedRawData[$key]["price"] = $item->price;//$priceData->getItemPrice();
                    $parsedRawData[$key]["sum"] = PriceCalculationHelper::calculateItemPrice($item->getTypeID(), $item->amount, new BuybackPriceData($item->getTypeID(), $item->price));
                }
            }
        }

        //dd($parsedRawData);
        return $this->categorizeItems($parsedRawData);
    }

    /**
     * @return array|null
     */
    private function categorizeItems(array $itemData): ?array
    {
        $parsedItems = [];
        foreach ($itemData as $key => $item) {
            // handle repro data... 
            if ($item["isReproResult"]) {
                if (!array_key_exists($key, $parsedItems)) {
                    $parsedItems["reprocessed"][$key]["typeId"] = $item["typeID"];
                    $parsedItems["reprocessed"][$key]["typeName"] = $item["name"];
                    $parsedItems["reprocessed"][$key]["typeQuantity"] = $item["quantity"];
                    $parsedItems["reprocessed"][$key]["typeSum"] = $item["sum"];
                    $parsedItems["reprocessed"][$key]["provider"] = $item["priceProvider"];
                    // $parsedItems["reprocessed"][$key]["groupId"] = $result->groupID;
                    // $parsedItems["reprocessed"][$key]["repro"] = (bool)$result->repro;
                    // $parsedItems["reprocessed"][$key]["marketGroupName"] = $result->groupName;

                    // $parsedItems["parsed"][$key]["marketConfig"] = [
                    //     'percentage' => $result->percentage != null ? $result->percentage : 0,
                    //     'marketOperationType' => $result->marketOperationType != null ? $result->marketOperationType : 0
                    // ];
                }     
                //dd($parsedItems);
                continue;       
            }


            $result = DB::table('invTypes as it')
                ->join('invGroups as ig', 'it.groupID', '=', 'ig.GroupID')
                ->rightJoin('depo_buyback_market_config as bmc', 'it.typeID', '=', 'bmc.typeId')
                ->select(
                    'it.typeID as typeID',
                    'it.typeName as typeName',
                    'it.description as description',
                    'ig.GroupName as groupName',
                    'ig.GroupID as groupID',
                    'bmc.percentage',
                    'bmc.marketOperationType',
                    'bmc.provider',
                    'bmc.repro'
                )
                ->where('it.typeID', '=', $key)
                ->first();

            if (empty($result)) {
                // now we check again if it's a group thingi.
                $invType = InvType::where('typeID', $item["typeID"])->first();
                $result = BuybackMarketConfigGroups::where('groupId', $invType->groupID)->first(); 

                if ($result == null) {
                    $parsedItems["ignored"][] = [
                        'ItemId' => $key,
                        'ItemName' => $item["name"],
                        'ItemQuantity' => $item["quantity"]
                    ];
                    continue;
                }
            }

            if (!array_key_exists($result->groupID, $parsedItems)) {
                $parsedItems["parsed"][$key]["typeId"] = $item["typeID"];
                $parsedItems["parsed"][$key]["typeName"] = $item["name"];
                $parsedItems["parsed"][$key]["typeQuantity"] = $item["quantity"];
                $parsedItems["parsed"][$key]["typeSum"] = $item["sum"];
                $parsedItems["parsed"][$key]["provider"] = $result->provider;
                $parsedItems["parsed"][$key]["groupId"] = $result->groupID;
                $parsedItems["parsed"][$key]["repro"] = (bool)$result->repro;
                $parsedItems["parsed"][$key]["marketGroupName"] = $result->groupName;

                $parsedItems["parsed"][$key]["marketConfig"] = [
                    'percentage' => $result->percentage != null ? $result->percentage : 0,
                    'marketOperationType' => $result->marketOperationType != null ? $result->marketOperationType : 0
                ];
            }
        }
        //dd($parsedItems);
        return $parsedItems;
    }

    /**
     * @return array|null
     * @throws ItemParserBadFormatException
     */
    private function parseRawData(string $item_string): ?array
    {

        $sorted_item_data = [];

        foreach (preg_split('/\r\n|\r|\n/', $item_string) as $item) {

            if (strlen($item) < 2) {
                throw new ItemParserBadFormatException();
            }

            if(stripos($item, "    ")) {
                $item_data_details = explode("    ", $item);
            } elseif (stripos($item, "\t") ) {
                $item_data_details = explode("\t", $item);
            } else {
                throw new ItemParserBadFormatException();
            }

            $item_name = $item_data_details[0];
            $item_quantity = null;

            foreach ($item_data_details as $item_data_detail) {
                if (is_numeric(trim($item_data_detail))) {
                    $item_quantity = (int)str_replace('.', '', $item_data_detail);
                }
            }

            if ($item_quantity == null) {
                throw new ItemParserBadFormatException();
            }

            $inv_type = InvType::where('typeName', '=', $item_name)->first();

            if (!array_key_exists($item_name, $sorted_item_data)) {
                $sorted_item_data[$item_name]["name"] = $item_name;
                $sorted_item_data[$item_name]["typeID"] = $inv_type->typeID;
                $sorted_item_data[$item_name]["type_id"] = $inv_type->typeID;
                $sorted_item_data[$item_name]["quantity"] = 0;
                $sorted_item_data[$item_name]["price"] = 0;
                $sorted_item_data[$item_name]["sum"] = 0;
            }

            $sorted_item_data[$item_name]["quantity"] += $item_quantity;
        }

        return $sorted_item_data;
    }
}
