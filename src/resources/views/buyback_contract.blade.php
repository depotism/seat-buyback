@extends('web::layouts.grids.8-4')

@section('title', trans('buyback::global.contract_browser_title'))
@section('page_header', trans('buyback::global.contract_page_title'))

@push('head')
    <link rel="stylesheet" type="text/css" href="{{ asset('web/css/buyback.css') }}"/>
@endpush

@section('left')
    <div class="card">
        <div class="card-body">
            <span>{{ trans('buyback::global.contract_introduction') }}</span>
        </div>
    </div>
    @if($contracts->isEmpty())
        <h5>{{ trans('buyback::global.contract_error_no_items') }}</h5>
    @else
    <div id="accordion">
        @foreach($contracts as $contract)
            @php
                // $contractFinalPrice = number_format(Depotism\Seat\SeatBuyback\Helpers\PriceCalculationHelper::calculateFinalPrice(json_decode($contract->contractData, true)["parsed"]),0,',', '.')
                $contractFinalPrice = number_format($contract->price,0,',', '.');
            @endphp
        <div class="card">
            <div class="card-header border-secondary" data-toggle="collapse" data-target="#collapse_{{ $contract->contractId }}"
                 aria-expanded="true" aria-controls="collapse_{{ $contract->contractId }} id="heading_{{ $contract->contractId }}">
                <h5 class="mb-0">
                    <div class="row">
                        <i class="nav-icon fas fa-eye align-middle mt-2"></i>
                        <div class="col-md-8 align-left">
                            <button class="btn">
                                <h3 class="card-title"><b>{{ $contract->contractId }}</b>
                                    ( {{ count(json_decode($contract->contractData, true)["parsed"]) }} Items )
                                    | {{ date("d.m.Y", $contract->created_at->timestamp) }}
                                    | <b>{{ $contract->contractIssuer }}</b>
                                    | <b><span class="isk-info">+{{ $contractFinalPrice }}</span> ISK</b>
                                </h3>
                            </button>
                        </div>
                        <div class="ml-auto mr-2 align-right text-center align-centered">
                            <div class="row">
                                <form action="{{ route('buyback.contracts.succeed', ['contractId' => $contract->contractId]) }}" method="get" id="contract-success" name="contract-success">
                                    <button class="btn btn-success">Finish</button>
                                </form>
                                <form class="ml-2" action="{{ route('buyback.contracts.delete', ['contractId' => $contract->contractId]) }}" method="get" id="contract-remove" name="contract-remove">
                                    <button class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </h5>
            </div>
            <div id="collapse_{{ $contract->contractId }}" class="collapse" aria-labelledby="heading_{{ $contract->contractId }}" data-parent="#accordion">
                <div class="card-body">
                    <table class="table table-borderless">
                        <tbody>
                            @foreach(json_decode($contract->contractData)->parsed as $item )
                                <tr>
                                    <td><img src="https://images.evetech.net/types/{{ $item->typeId }}/icon?size=32"/>
                                        <b>{{ $item->typeQuantity }} x {{ $item->typeName }}</b>
                                        ( {!! $item->marketConfig->marketOperationType == 0 ? '-' : '+' !!}{{$item->marketConfig->percentage }}% )
                                    </td>

                                    @if($item->repro)
                                    <td class="isk-td"><span class="isk-info">REPROCESSED</span></td>
                                    @else
                                    <td class="isk-td"><span class="isk-info">{{ number_format($item->typeSum,0,',', '.') }}</span> {{ trans('buyback::global.currency') }}</td>
                                    @endif                                    
                                </tr>
                            @endforeach
                            <tr>
                                <td class="align-centered"><b>Summary</b></td>
                                <td class="align-centered isk-td"><b><span class="isk-info">+
                                            {{ $contractFinalPrice }}</span> {{ trans('buyback::global.currency') }}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
@stop