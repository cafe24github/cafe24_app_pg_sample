$(document).ready(function () {

    const sBaseUrl          = 'https://pg-demo-app.local.com';
    const sContentTypeJson  = 'application/json';

    var sSessionId = $('#session_id').text();
    var sMallId = $('#mall_id').text();
    var sOrderNumber = $('#order_no').text();
    var sBackButton =  $('#back_button').attr('href');

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $('#confirmPayment').on('click', function () {
        doInitPayProcess();
    });


    function doInitPayProcess() {
        var aPayOrderData = {
            'session_id'    : sSessionId,
            'mall_id'       : sMallId,
            'order_no'      : sOrderNumber
        };

        const sPayEndpoint = sBaseUrl + '/internal/checkout/external/pay';
        $.ajax({
            url: sPayEndpoint,
            type: 'POST',
            data:  JSON.stringify(aPayOrderData),
            contentType: sContentTypeJson,
            success:function (oResponse) {
                if(oResponse.bResult === 200) {
                    window.location.replace(oResponse.url);
                } else {
                    alert('Something went wrong. Please try again.');
                }
            }, error: function(oResponse) {
                alert('Something went wrong. Please try again.');
                window.location.replace(sBackButton);
            }
        });
    }
});
