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

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDepoBuybackTables extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('depo_buyback_admin_config', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('value');
            $table->timestamps();
        });

        Schema::create('depo_buyback_market_config', function (Blueprint $table): void {
            $table->integer('typeId')->primary();
            $table->string('typeName');
            $table->tinyInteger('marketOperationType');
            $table->integer('groupId');
            $table->string('groupName');
            $table->integer('percentage');
            $table->bigInteger('price')->nullable()->default(null);
            $table->integer('provider')->nullable()->default(null);
            $table->boolean('repro')->default(false);
            $table->timestamps();
        });

        Schema::create('depo_buyback_contracts', function (Blueprint $table): void {
            $table->string('contractId')->primary();
            $table->string('contractIssuer');
            $table->text('contractData');
            $table->tinyInteger('contractStatus')->default(0);
            $table->timestamps();
            $table->index(['contractId']);
        });

        Schema::create('depo_buyback_market_config_groups', function (Blueprint $table): void {
            $table->integer('groupId')->primary();
            $table->string('groupName');
            $table->tinyInteger('marketOperationType');
            $table->integer('percentage');
            $table->integer('provider')->nullable()->default(null);
            $table->boolean('repro')->default(false);
            $table->timestamps();
        });        

        // Schema::create('depo_buyback_price_provider', function (Blueprint $table): void {
        //     $table->increments('id');
        //     $table->string('name');
        //     $table->timestamps();
        // });        

        //Init table with default config entries
        $this->init();
    }

    /**
     * @return void
     */
    private function init() : void  {

        DB::table('depo_buyback_admin_config')->insert([
           'name' => 'admin_price_cache_time',
           'value' => '3600'
        ]);

        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'admin_max_allowed_items',
            'value' => '20'
        ]);

        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'admin_contract_contract_to',
            'value' => 'EVECharacter'
        ]);

        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'admin_contract_expiration',
            'value' => '4 Weeks'
        ]);

        // DB::table('depo_buyback_price_provider')->insert([
        //     'name' => 'EveMarketer',
        // ]);

        // DB::table('depo_buyback_price_provider')->insert([
        //     'name' => 'EvePraisal',
        // ]);


        // //Adding initial price provider
        // DB::table('depo_buyback_admin_config')->insert([
        //     'name' => 'admin_price_provider',
        //     'value' => '1'
        // ]);       
        
        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'admin_discord_webhook_url',
            'value' => 'http://'
        ]);

        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'admin_discord_status',
            'value' => 0
        ]);

        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'admin_discord_webhook_color',
            'value' => '#1928f5'
        ]);

        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'admin_discord_webhook_bot_name',
            'value' => 'SeAT BuyBack Notification'
        ]);        

        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'admin_price_provider_url',
            'value' => 'http://'
        ]);        

        // DB::table('depo_buyback_price_provider')
        //     ->where('name', 'EveMarketer')
        //     ->delete();     
        
        DB::table('depo_buyback_admin_config')->insert([
            'name' => 'defaultPriceProvider',
            'value' => '1'
        ]);        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('depo_buyback_admin_config');
        Schema::dropIfExists('depo_buyback_market_config');
        Schema::dropIfExists('depo_buyback_contracts');
        Schema::dropIfExists('depo_buyback_market_config');
    }
}