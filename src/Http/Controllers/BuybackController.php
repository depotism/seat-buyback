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

use Depotism\Seat\SeatBuyback\Exceptions\ItemParserBadFormatException;
use Depotism\Seat\SeatBuyback\Exceptions\NoMarketDataFoundException;
use Depotism\Seat\SeatBuyback\Exceptions\SettingsServiceException as SettingsServiceExceptionAlias;
use Depotism\Seat\SeatBuyback\Services\ItemService;
use Depotism\Seat\SeatBuyback\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
use Seat\Web\Http\Controllers\Controller;
use Depotism\Seat\SeatBuyback\Helpers;

/**
 * Class BuybackController.
 *
 * @package Depotism\Seat\SeatBuyback\Http\Controllers
 */
class BuybackController extends Controller
{
    private const MaxContractIdLength = 6;

    /**
     * @var ItemService
     */
    public $itemService;

    /**
     * @var SettingsService
     */
    public $settingsService;

    public int $maxAllowedItems;

    /**
     * Constructor
     */
    public function __construct(ItemService $itemService, SettingsService $settingsService)
    {
        $this->itemService = $itemService;
        $this->settingsService = $settingsService;
        $this->maxAllowedItems = $settingsService->getMaxAllowedItems();
    }

    /**
     * @return View
     */
    public function getHome() : View
    {
        return view('buyback::buyback', [
            'maxAllowedItems' => $this->maxAllowedItems
        ]);
    }

    /**
     * @throws SettingsServiceExceptionAlias
     */
    public function checkItems(Request $request)
    {
        $request->validate([
            'items' => 'required',
        ]);

        try {
            $parsedItems = $this->itemService->parseEveItemData($request->get('items'));

            if($parsedItems == null) {
                return redirect('buyback')->withErrors(
                    ['errors' => trans('buyback::global.error_price_provider_down')]);
            }

            if(!array_key_exists("parsed", $parsedItems)) {
                return redirect('buyback')->withErrors(['errors' => trans('buyback::global.error_empty_item_field')]);
            }

            $maxAllowedItems = $this->settingsService->getMaxAllowedItems();

            if ((is_countable($parsedItems["parsed"]) ? count($parsedItems["parsed"]) : 0) > $maxAllowedItems) {
                return redirect('buyback')->withErrors(
                    ['errors' => trans('buyback::global.error_too_much_items', ['count' => $maxAllowedItems])]);
            }

            $finalPrice = Helpers\PriceCalculationHelper::calculateFinalPrice($parsedItems["parsed"]);
            if (array_key_exists("reprocessed",$parsedItems))
                $finalPrice += Helpers\PriceCalculationHelper::calculateFinalPrice($parsedItems["reprocessed"]);

            return view('buyback::buyback_check', [
                'eve_item_data' => $parsedItems,
                'maxAllowedItems' => $this->maxAllowedItems,
                'finalPrice' => $finalPrice,
                'contractTo' => $this->settingsService->get("admin_contract_contract_to"),
                'contractExpiration' => $this->settingsService->get("admin_contract_expiration"),
                'contractId' => Helpers\MiscHelper::generateRandomString(self::MaxContractIdLength)
            ]);

        } catch (NoMarketDataFoundException $e) {
            return redirect('buyback')->withErrors(
                ['errors' => trans('buyback::global.error_price_provider_down')]
            );
        } catch (ItemParserBadFormatException $e) {
            return redirect('buyback')->withErrors(
                ['errors' => trans('buyback::global.error_item_parser_format')]
            );
        }
    }
}