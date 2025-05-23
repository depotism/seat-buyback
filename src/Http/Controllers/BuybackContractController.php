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

use Cassandra\Set;
use Depotism\Seat\SeatBuyback\Exceptions\DiscordServiceCurlException;
use Depotism\Seat\SeatBuyback\Services\DiscordService;
use Depotism\Seat\SeatBuyback\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Seat\Web\Http\Controllers\Controller;
use Depotism\Seat\SeatBuyback\Models\BuybackContract;

/**
 * Class BuybackContractController
 *
 * @package Depotism\Seat\SeatBuyback\Http\Controllers
 */
class BuybackContractController extends Controller {

    private SettingsService $settingsService;

    private DiscordService $discordService;

    public function __construct(DiscordService $discordService, SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        $this->discordService = $discordService;
    }

    /**
     * @return \Illuminate\View\View
     */
    public function getHome()
    {
        $contracts = BuybackContract::where('contractStatus', '=', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('buyback::buyback_contract', [
            'contracts' => $contracts
        ]);
    }

    /**
     * @return mixed
     */
    public function getCharacterContracts () {

        //Todo Refactor contractIssuer to userID
        $openContracts = BuybackContract::where('contractStatus', '=', 0)
            ->where('contractIssuer', '=', Auth::user()->name)
            ->orderBy('created_at', 'DESC')
            ->get();

        $closedContracts = BuybackContract::where('contractStatus', '=', 1)
            ->where('contractIssuer', '=', Auth::user()->name)
            ->orderBy('created_at', 'DESC')
            ->get();

        return view('buyback::buyback_my_contracts',  [
            'openContracts' => $openContracts,
            'closedContracts' => $closedContracts
        ]);
    }

    /**
     * @return mixed
     */
    public function insertContract(Request $request) {

        $request->validate([
            'contractId' => 'required',
            'contractData' => 'required',
            'contractFinalPrice' => 'required'
        ]);
        $contractFinalPrice = (int)$request->get('contractFinalPrice');

        $contract = new BuybackContract();
        $contract->contractId = $request->get('contractId');
        $contract->contractIssuer = Auth::user()->name;
        $contract->contractData = $request->get('contractData');
        $contract->price = $contractFinalPrice;
        $contract->save();

        if((bool)$this->settingsService->get("admin_discord_status")) {
            try {
                $this->discordService->sendMessage
                (
                    Auth::user()->name,
                    Auth::user()->main_character_id,
                    $contract->contractId,
                    $contractFinalPrice,
                    is_countable(json_decode((string) $contract->contractData, true, 512, JSON_THROW_ON_ERROR)['parsed']) ? count(json_decode((string) $contract->contractData, true, 512, JSON_THROW_ON_ERROR)['parsed']) : 0
                );
            } catch (DiscordServiceCurlException $e) {
                return redirect()->back()
                    ->with(['error' => trans('buyback::global.admin_discord_error_curl')]);
            }
        }

        return redirect()->route('buyback.character.contracts')
            ->with('success', trans('buyback::global.contract_success_submit', ['id' => $request->get('contractId')]));
    }

    /**
     * @return mixed
     */
    public function deleteContract (Request $request, string $contractId)
    {
        if(!$request->isMethod('get') || $contractId === '')
        {
            return redirect()->back()
                ->with(['error' => trans('buyback::global.error')]);
        }

        BuybackContract::destroy($contractId);

        return redirect()->back()
            ->with('success', trans('buyback::global.contract_success_deleted', ['id' => $contractId]));
    }

    /**
     * @return mixed
     */
    public function succeedContract (Request $request, string $contractId)
    {
        if(!$request->isMethod('get') || $contractId === '')
        {
            return redirect()->back()
                ->with(['error' => trans('buyback::global.error')]);
        }

        $contract = BuybackContract::where('contractId', '=', $contractId)->first();
        if(!$contract->contractStatus) {

            $contract->contractStatus = 1;
            $contract->save();

            return redirect()->back()
                ->with('success', trans('buyback::global.contract_success_succeeded', ['id' => $contractId]));
        }

        return redirect()->back()
            ->with(['error' => trans('buyback::global.error')]);
    }
}
