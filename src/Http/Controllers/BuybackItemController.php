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

namespace Depotism\Seat\SeatBuyback\Http\Controllers;


use Depotism\Seat\SeatBuyback\Models\BuybackMarketConfig;
use Depotism\Seat\SeatBuyback\Models\BuybackMarketConfigGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Eveapi\Models\Sde\InvGroup;
use Seat\Web\Http\Controllers\Controller;
use Depotism\Seat\SeatBuyback\Services\ItemService;
use Depotism\Seat\SeatBuyback\Services\SettingsService;

/**
 * Class BuybackController.
 *
 * @package Depotism\Seat\SeatBuyback\Http\Controllers
 */
class BuybackItemController extends Controller {
    /**
     * @var ItemService
     */
    public $itemService;

    /**
     * @var SettingsService
     */
    public $settingsService;


    /**
     * Constructor
     */
    public function __construct(ItemService $itemService, SettingsService $settingsService)
    {
        $this->itemService = $itemService;
        $this->settingsService = $settingsService;
    }

    /**
     * @return View
     */
    public function getHome()
    {
        return view('buyback::buyback_item', [
            'marketConfigs' => BuybackMarketConfig::orderBy('typeName', 'asc')->get(),
            'marketConfigsGroups' => BuybackMarketConfigGroups::orderBy('groupName', 'asc')->get(),
        ]);
    }

    /**
     * @return mixed
     */
    public function addMarketConfig(Request $request)
    {
        
        $request->validate([
            // 'admin-market-typeId'       => 'required_if:items,null',
            'admin-market-operation'    => 'required',
            'admin-market-percentage'   => 'required|numeric|between:0,99.99',
            'admin-market-price'        => 'required|numeric',
            'defaultPriceProvider'      => 'required|numeric',
            // 'items'                     => 'required_if:admin-market-typeId,""'
        ]);

        if (($request->get('items') == null) && ($request->get('admin-market-typeId') == null) && ($request->get('admin-market-groupId') == null)) {
            return redirect()->route('buyback.item')->with('error', trans('Error, at least try to select one fucking field.')); 
        }
        
        
        $multiLine = false;
        $parsedItems = [];
        if ($request->get('items') != null) {
            $parsedItems = $this->itemService->parseEveItemData($request->get('items'));
            $multiLine = true;
        }
        // dd($request);
        
        if (!$multiLine) {

            // single typeID
            if ($request->get('admin-market-typeId') != null) {
                $res = $this->addItemToMarket(
                    (int)$request->get('admin-market-typeId'),
                    (int)$request->get('admin-market-operation'),
                    (int)$request->get('admin-market-percentage'),
                    (int)$request->get("admin-market-price"),
                    (int)$request->get("defaultPriceProvider")
                );

                if (!$res) {
                    return redirect()->route('buyback.item')
                    ->with(['error' => trans('buyback::global.admin_error_config') . $item->typeId]);
                }
            }

            // single groupID
            if ($request->get('admin-market-groupId') != null) {
                $res = $this->addGroupToMarket(
                    (int)$request->get('admin-market-groupId'),
                    (int)$request->get('admin-market-operation'),
                    (int)$request->get('admin-market-percentage'),
                    (int)$request->get("admin-market-price"),
                    (int)$request->get("defaultPriceProvider")
                );

                if (!$res) {
                    return redirect()->route('buyback.item')
                    ->with(['error' => trans('buyback::global.admin_error_config') . $item->typeId]);
                }
            }

        } else {
            // dd($parsedItems);
            // deleting previous configs
            foreach ($parsedItems['parsed'] as $typeId => $item) {
                BuybackMarketConfig::destroy($typeId);

                // and adding the new config...
                $res = $this->addItemToMarket(
                    (int)$typeId,
                    (int)$request->get('admin-market-operation'),
                    (int)$request->get('admin-market-percentage'),
                    (int)$request->get("admin-market-price"),
                    (int)$request->get("defaultPriceProvider")
                );                
            }

            foreach ($parsedItems['ignored'] as $item) {
                $res = $this->addItemToMarket(
                    (int)$item['ItemId'],
                    (int)$request->get('admin-market-operation'),
                    (int)$request->get('admin-market-percentage'),
                    (int)$request->get("admin-market-price"),
                    (int)$request->get("defaultPriceProvider")
                );    
            }

        }
        

        return redirect()->route('buyback.item')
            ->with('success', trans('buyback::global.admin_success_market_add'));
    }


    private function addItemToMarket(int $typeId, int $marketOperation, int $marketPercentage, int $marketFixedPrice, int $priceProvider) {
        $item = BuybackMarketConfig::where('typeId', $typeId)->first();

        if ($item != null) {
            return false;
        }

        $invType = InvType::where('typeID', $typeId)->first();

        BuybackMarketConfig::insert([
            'typeId' => $typeId,
            'typeName' => (string)$invType->typeName,
            'marketOperationType' => $marketOperation,
            'groupId' => (int)$invType->groupID,
            'groupName' => (string)$invType->group->groupName,
            'percentage' => $marketPercentage,
            'price' => $marketFixedPrice,
            'provider' => $priceProvider
        ]);

        return true;
    }

    private function addGroupToMarket(int $groupId, int $marketOperation, int $marketPercentage, int $marketFixedPrice, int $priceProvider) {
        $item = BuybackMarketConfigGroups::where('groupId', $groupId)->first();

        if ($item != null) {
            return false;
        }

        $invGroup = InvGroup::where('groupId', $groupId)->first();
        BuybackMarketConfigGroups::insert([
            'groupId' => $groupId,
            'groupName' => (string)$invGroup->groupName,            
            'marketOperationType' => $marketOperation,
            'percentage' => $marketPercentage,
            'provider' => $priceProvider
        ]);

        return true;
    }    


    /**
     * @return mixed
     */
    public function removeMarketConfig(Request $request, int $typeId)
    {

        if (!$request->isMethod('get') || empty($typeId) || !is_numeric($typeId)) {
            return redirect()->back()
                ->with(['error' => trans('buyback::global.error')]);
        }

        BuybackMarketConfig::destroy($typeId);

        return redirect()->back()
            ->with('success', trans('buyback::global.admin_success_market_remove'));
    }

    public function removeMarketConfigGroup(Request $request, int $groupId)
    {

        if (!$request->isMethod('get') || empty($groupId) || !is_numeric($groupId)) {
            return redirect()->back()
                ->with(['error' => trans('buyback::global.error')]);
        }

        BuybackMarketConfigGroups::destroy($groupId);

        return redirect()->back()
            ->with('success', trans('buyback::global.admin_success_market_remove'));
    }    
}
