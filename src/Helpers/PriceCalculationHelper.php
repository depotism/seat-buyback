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

namespace Depotism\Seat\SeatBuyback\Helpers;

use Depotism\Seat\SeatBuyback\Models\BuybackPriceData;
use Illuminate\Support\Facades\DB;
use Depotism\Seat\SeatBuyback\Models\BuybackMarketConfig;
use Depotism\Seat\SeatBuyback\Models\BuybackMarketConfigGroups;
use Seat\Eveapi\Models\Sde\InvType;
// use Depotism\Seat\SeatBuyback\Parser\PriceableEveItem;

/**
 * Class PriceCalculationHelper
 *
 * @package Depotism\Seat\SeatBuyback\Helpers
 */
class PriceCalculationHelper {

    /**
     * @return float|int|null
     */
    public static function calculateItemPrice(int $typeId, int $quantity, BuybackPriceData $buybackPriceData) : ?float {

        $marketConfig = BuybackMarketConfig::where('typeId', $typeId)->first();

        if($marketConfig == null) {
            $invType = InvType::where('typeID', $typeId)->first();
            $marketConfig = BuybackMarketConfigGroups::where('groupId', $invType->groupID)->first();
            if ($marketConfig == null) {
                return null;
            }
        }

        if($marketConfig->price > 0) {
            return $quantity * $marketConfig->price;
        }

        $priceSum = $buybackPriceData->getItemPrice(); //$quantity *  // somehow that was a bit too much xD

        $pricePercentage = $priceSum * $marketConfig->percentage / 100;

        return $marketConfig->marketOperationType ? $priceSum + $pricePercentage : $priceSum - $pricePercentage;
    }

    /**
     * @return float|null
     */
    public static function calculateFinalPrice(array $itemData) : ?float {

        $finalPrice = 0;

        foreach ($itemData as $item) {
            $finalPrice += $item["typeSum"];
        }

        return $finalPrice;

    }
}