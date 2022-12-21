<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Display</title>
    <link rel="stylesheet" type="text/css" href="https://img.echosting.cafe24.com/css/suio.css">
    <script type="text/javascript" src="https://img.echosting.cafe24.com/js/suio.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <style>
        .shop-loader {
            /*background: #000000;*/
            /*opacity: .7;*/
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 10;
        }
        .shop-loader > img {
            width: 100px;
            height: 100px;
            margin-bottom: 10px;
        }
        .shop-toggle-button {
            margin: auto;
            text-align: center;
            background-color: #1b87d4;
            color: white;
            height: 20px
        }
    </style>
</head>
<body data-new-gr-c-s-check-loaded="8.869.0" data-gr-ext-installed="">

<div class="mBox " style="margin: auto; width: 45% !important;">
    <div class="typeEmpty">
        <h1>PG Company Name</h1>
    </div>
    <div class="section fixed" id="QA_XXX3">
        <div class="mBox typeBg">
            <strong class="txtEng"><h2>Store ID</h2></strong>
            <strong class="txtEng"><h3>{{ $aAdminSettings['mall_name'] }}</h3></strong><br>
            <div>
                <strong class="txtMore">Step 1: Create a merchant account on the pg company.</strong>
            </div><br>
            <div>
                <strong class="txtMore">Step 2: Enter your Public Key and Secret Key.</strong>
                <form id="paymayaConnect" style="padding-left: 10px;  padding-top: 5px;">
                    <div class="loader" style="display:none"></div>
                    <div><strong class="txtEng">Public Key</strong></div>

                    <input type="text" class="fText" id="publicKey" style="width: 100%; height: 35px; border-color: black;" value="{{ $aAdminSettings['pg_connected'] === true ? $aAdminSettings['pg_public_key'] : '' }}"  autocomplete="off" {{ $aAdminSettings['pg_connected'] === true  ? 'disabled' : ''}}>

                    <label id="publicKeyError" style="color: red; float: right; display: none">* Public Key is required</label><br> <br>

                    <div><strong class="txtEng">Secret Key</strong></div>

                    <input type="text" class="fText" id="secretKey" style="width: 100%; height: 35px;" value="{{ $aAdminSettings['pg_connected'] === true ? $aAdminSettings['pg_secret_key'] : '' }}"  autocomplete="off" {{ $aAdminSettings['pg_connected'] === true  ? 'disabled' : ''}}>
                    <label id="secretKeyError" style="color: red; float: right; display: none">* Secret Key is required</label><br><br><br>

                    <div>
                        <span class="gRight gSmall">
                            <a id="btnConnect" class="btnSubmit" data-action="{{ $aAdminSettings['pg_connected'] === true ? 'disconnect' : 'connect' }}"style="margin: auto; text-align: center"><span style="margin: auto; text-align: center" >{{ $aAdminSettings['pg_connected'] === false ? 'Link Account' : 'Unlink Account'}}</span></a>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="section fixed" id="QA_XXX3">
        <div class="mBox typeBg">
            <div class="mTitle">
                <h2 style="font-weight: bold;">Shops settings
                </h2>
            </div><br>

            <div class="mGridTable">
                <table id="shop-table" class="eChkColor" border="1">
                    <caption>Shop tables</caption>
                    <thead>
                    <tr>
                        <th scope="col" style="width:50px; font-size: 15px;">No.</th>
                        <th scope="col" style="width:160px; font-size: 15px;">Shop</th>
                        <th scope="col" style="width:160px; font-size: 15px;">Currency</th>
                        @if( $aAdminSettings['pg_connected'] === true)
                        <th scope="col" style="width:200px; font-size: 15px;">Display payment method</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody class="center">
                    @foreach($aAdminSettings['shops'] as $iShopIndex => $aShop)
                        <tr>
                                <td>
                                    <div class="shop-loader">
                                        <p>{{$aShop['shop_no']}}</p>
                                    </div>
                                </td>
                                <td>
                                    <div class="shop-loader">
                                        <p>{{$aShop['shop_name']}}</p>
                                    </div>
                                </td>
                                <td>
                                    <div class="shop-loader">
                                        <p>{{$aShop['currency_code']}}</p>
                                    </div>
                                </td>
                            @if( $aAdminSettings['pg_connected'] === true)
                                <td>
                                    <div class="shop-loader" style="text-align: center;">
                                        <span class="gSmall" style="display: inline-block">
                                            <a id="shop-{{$iShopIndex}}-anchor" data-action="{{ $aShop['pg_enabled'] === true ? 'disable' : 'enable' }}" data-shop="{{$iShopIndex}}" class="shop-toggle-button"><span style="margin: auto; text-align: center;" >{{ $aShop['pg_enabled'] === false ? 'Enable PG' : 'Disable PG'}}</span></a>
                                        </span>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>

            </div>
            </div>

        </div>
    </div>
</div>
<script>
    const MALL_ID = '{{ $aAdminSettings['mall_name'] }}'
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $(document).ready(() => {
        $('#btnConnect').on('click', function () {
            let sAction = $(this).data('action');
            if (sAction === 'connect') {
                let oData = {
                    mall_id: MALL_ID,
                    public_key: $('#publicKey').val(),
                    secret_key: $('#secretKey').val(),
                }
                $.ajax({
                    url: '{{route('link-pg-account')}}',
                    type: 'POST',
                    dataType: 'json',
                    async: false,
                    data: oData,
                    success: function (oResponse) {
                        alert(oResponse.message)
                        location.reload()
                    },
                    error: function (oResponse) {
                        alert(oResponse.responseJSON.message);
                        console.error(oResponse);
                    }
                });
            }

            if (sAction === 'disconnect') {
                let oData = {
                    mall_id: MALL_ID,
                }
                $.ajax({
                    url: '{{route('unlink-pg-account')}}',
                    type: 'POST',
                    dataType: 'json',
                    async: false,
                    data: oData,
                    success: function (oResponse) {
                        alert(oResponse.message)
                        location.reload()
                    },
                    error: function (oResponse) {
                        alert(oResponse.responseJSON.message);
                        console.error(oResponse);
                    }
                });
            }
        });

        $('.shop-toggle-button').on('click', function () {
            let sAction = $(this).data('action');
            let iShopIndex = $(this).data('shop');

            let oData = {
                mall_id: MALL_ID,
                action: sAction,
                shop_index: iShopIndex,
            }

            $.ajax({
                url: '{{route('toggle-shop')}}',
                type: 'POST',
                dataType: 'json',
                async: false,
                data: oData,
                success: function (oResponse) {
                    alert(oResponse.message)
                    location.reload()
                },
                error: function (oResponse) {
                    alert(oResponse.responseJSON.message);
                    console.error(oResponse);
                }
            });
        });
    });
</script>
</body>
</html>
