/**
 * 1. Run initDummyPGSDK : will  render and initialize button on page (this will call the /script/data endpoint)
 * 2. On click of the button :  will call the method doInitCheckoutProcess
 * 3. Request the PG app to create an HMAC key to reserve an order via Cafe24 SDK (/script/hmac endpoint)
 * 4. Request to Cafe24 SDK to reserve an order
 * 5. Request to create an order to the PG Company (/payload endpoint)
 */
$(document).ready(function () {
    const sClientId         = 'mock_client_id'; //get the value in env
    const sAPIVersion       = '2021-06-01';
    const sBaseUrl          = 'https://pg-demo-app.local.com'; //get value in env
    const oDivTargetId      = 'DemoExternalCheckoutButton';
    const sContentTypeJson  = 'application/json';

    let sErrorAlert       = 'An unexpected error has occurred. Please try again.';

    /**
     * Set user as non guest as default
     * @type {number}
     */
    let bGuest = 0;

    /**
     * Check if layout meta path is present
     * @type {number}
     */
    let bLayout = 1;

    /**
     * layout type.
     * bLayout value equivalent: 0 = empty. 1 = product. 2 = basket
     * @type {string}
     */
    let sLayoutType = 'Cart';

    /**
     * Check if "appPaymentButtonBox" div exists.
     * @type {number}
     */
    let bDivExist = 0;

    /**
     * Check if member exists
     * @type {number}
     */
    let bMemberInfo = 0;

    /**
     * Shop currency
     * @type {string}
     */
    let sShopCurrency = '';

    /**
     * Instance of CAFE24API SDK
     * Reference: https://developers.cafe24.com/api/front/frontsdk
     */
    const oCafe24Api = CAFE24API;


    /**
     * Start initialization of the PG Company's payment button
     */
    async function initDummyPGSDK() {
        doGetMemberInfo();
        getLayout();
        createDiv();

        // Initialize CAFE24API
        oCafe24Api.init({
            client_id: sClientId,
            version: sAPIVersion
        });

        // Check if the "appPaymentButtonBox" div doesn't exist, layout is not present, or has no member/guest id
        if(bDivExist === 0 || bMemberInfo === 0 || bLayout === 0) {
            return false;
        }

        // Fetch admin settings (merchant credentials)
        let oAdminSettings = await getAdminSettings();
        if(oAdminSettings.bResult === false) {
            return false;
        }

        // Get shop currency
        sShopCurrency = oAdminSettings.mData.shop_currency;

        // Get merchant credentials
        var sPublicKey = oAdminSettings.mData.public_key;
        var sStoreId = oAdminSettings.mData.shop_name;
        var sCurrency = oAdminSettings.mData.shop_currency;

        let sDummyPGScript = 'https://sample-pg/checkout.js'; //Payment gateway's script
        let oScript = document.createElement('script');
        oScript.setAttribute('src', sDummyPGScript);
        document.head.appendChild(oScript);
        oScript.onload = () => {
            doRenderPaymentButton(sPublicKey, sCurrency, sStoreId);
        };
    }

    /**
     * Get admin settings
     * @returns {Promise<any>|boolean}
     */
    function getAdminSettings() {
        // Use the mall id and shop no from the CAFE24API
        let sMallId =  oCafe24Api.MALL_ID;
        let sShopNumber = oCafe24Api.SHOP_NO;

        const sAdminSettingsUrl = sBaseUrl +  '/api/external-checkout/script/data?mall_id=' + sMallId + '&shop_no=' + sShopNumber;

        try {
            return new Promise(function(fResolve, fReject) {
                $.ajax({
                    url : sAdminSettingsUrl,
                    type: 'GET',
                    success: function(oResponse) {
                        fResolve(oResponse);
                    }, error: function(oResponse) {
                        fReject(oResponse)
                    }
                });
            });
        } catch (e) {
            return false;
        }
    }

    /**
     * Get page layout
     * @returns {{layout: string, shape: string, color: string, size: string}, int}
     */
    function getLayout() {
        // The payment button should only be rendered in the ORDER_BASKET or PRODUCT_DETAIL pages
        let sPath = $('meta[name=path_role]').attr('content');
        if (sPath === 'ORDER_BASKET') {
            sLayoutType = 'Cart';
            return 1;
        } else if (sPath === 'PRODUCT_DETAIL') {
            sLayoutType = 'Product';
            return 1;
        }
        bLayout = 0;
    }

    /**
     * Create div to render payment button
     * @returns {boolean}
     */
    function createDiv() {
        let oAppPaymentButtonBox = document.getElementById("appPaymentButtonBox");

        if(oAppPaymentButtonBox) {
            bDivExist = 1;
        } else {
            return false;
        }

        let oButtonDiv = document.createElement("div");
        oButtonDiv.setAttribute('id', oDivTargetId);
        oButtonDiv.setAttribute('class', oDivTargetId);
        oButtonDiv.setAttribute('style', 'margin:5px auto; width:100%; padding: 0 5px;');

        oAppPaymentButtonBox.appendChild(oButtonDiv);
    }

    /**
     * Reserve order using Cafe24 precreateOrder SDK
     * @returns {Promise}
     */
    async function doCreateOrder(sMallId, sRequestTime, sMemberId) {
        let oHmacData = await doCreateHMAC(sMallId, sRequestTime, sMemberId);
        let sHMAC = oHmacData.hmac_key;

        try {
            return new Promise(function(fResolve, fReject) {
                oCafe24Api.precreateOrder(sMallId, sRequestTime, sClientId, sMemberId, sHMAC,
                    function(err, res) {
                        if (err) {
                            fResolve(false);
                        } else {
                            fResolve(res);
                        }
                    });
            });
        } catch (e) {
            return false;
        }
    }

    /**
     * Create HMAC for Cafe24 precreateOrder SDK
     * @returns {Promise<boolean|Promise<Promise<any>|boolean|undefined>>}
     */
    async function doCreateHMAC(sMallId, sRequestTime, sMemberId) {
        let sHmacUrl = sBaseUrl + 'api/asynchronous-external-checkout/script/hmac?mall_id='+sMallId+'&request_time='+sRequestTime+'&client_key='+sClientId+'&member_id='+sMemberId;
        try {
            return new Promise(function(fResolve, fReject) {
                $.ajax({
                    url : sHmacUrl,
                    type: 'GET',
                    success: function(oResponse) {
                        fResolve(oResponse);
                    }, error: function(oResponse) {
                        fReject(oResponse)
                    }
                });
            });
        } catch (e) {
            return false;
        }
    }

    /**
     * Render the PG Company's payment button
     */
    function doRenderPaymentButton(sPublicKey, sCurrency, sStoreId) {
        var externalCheckoutButton = DemoApp.renderButton('#DemoExternalCheckoutButton', {
            publicKeyId: sPublicKey,
            ledgerCurrency: sCurrency,
            placement: sLayoutType,
            buttonColor: 'Blue',
        });

        // Bind the on click callback for the button
        externalCheckoutButton.onClick(async function(){
            var sPayloadReturn = await doInitCheckoutProcess(sStoreId);
            if(sPayloadReturn === false) {
                return false;
            } else {
                externalCheckoutButton.initCheckout({createCheckoutSessionConfig: sPayloadReturn.mData});
            }
        });
    }

    /**
     * Starts the checkout process
     * @param sStoreId
     * @returns {Promise<unknown>}
     */
    async function doInitCheckoutProcess(sStoreId) {
        let sMallId =  oCafe24Api.MALL_ID;
        let sMemberId = doGetMemberInfo();
        let sRequestTime = Math.round((new Date()).getTime() / 1000);

        // Reserve order in Cafe24
        let aPreCreateOrderData = await doCreateOrder(sMallId, sRequestTime, sMemberId);
        aPreCreateOrderData = await formatArrayRequest(aPreCreateOrderData ,sMallId, sRequestTime, sMemberId, sStoreId);

        if(aPreCreateOrderData === false) {
            return false;
        }

        // Send the payload request to the app's backend
        const sPayloadUrl = sBaseUrl + '/api/external-checkout/payload';
        try {
            return new Promise(function(fResolve, fReject) {
                $.ajax({
                    url: sPayloadUrl,
                    type: 'POST',
                    data:  JSON.stringify(aPreCreateOrderData),
                    contentType: sContentTypeJson,
                    success:function (oResponse) {
                        fResolve(oResponse);
                    }, error: function(oResponse) {
                        alert(sErrorAlert);
                        fResolve(false);
                    }
                });
            });
        } catch (e) {
            return false;
        }
    }

    /**
     * Format precreateOrder's return
     * @param aOrderList
     * @param sMallId
     * @param sRequestTime
     * @param sMemberId
     * @param sStoreId
     * @returns {*}
     */
    async function formatArrayRequest(aOrderList, sMallId, sRequestTime, sMemberId, sStoreId) {
        if(aOrderList === false ) {
            return false;
        }

        let oProducts = aOrderList.order.products || aOrderList.order;

        return {
            order                   : oProducts,
            mall_id                 : sMallId,
            store_id                : sStoreId,
            member_id               : sMemberId,
            currency                : sShopCurrency,
            order_id                : aOrderList.order.order_id,
            return_notification_url : aOrderList.order.return_notification_url,
            return_url_base         : getReturnUrlBase(),
            bGuest                  : bGuest,
            response_time           : aOrderList.order.response_time,
            hmac                    : aOrderList.order.hmac
        }
    }

    /**
     * Create return_url_base to know where the buyer will be redirected to after the external callback process
     * @return {string}
     */
    function getReturnUrlBase(){
        let sReturnUrlBase = window.location.origin;
        let oShopPattern = new RegExp('^shop\\d+$');
        let sPath = window.location.pathname;
        let aPath = sPath.split('/');
        if (oShopPattern.test(aPath[1]) === true || aPath[1] === 'm') {
            if (oShopPattern.test(aPath[1]) === true) {
                let sShopNumber = aPath[1];
                sReturnUrlBase = sReturnUrlBase + '/' + sShopNumber;
            }
            if (aPath[2] === 'm' || aPath[1] === 'm') {
                sReturnUrlBase = sReturnUrlBase + '/m'
            }
        }
        return sReturnUrlBase;
    }

    /**
     * Get member info
     * @returns {string}
     */
    function doGetMemberInfo(){
        let sMemberId = '';
        oCafe24Api.getCustomerIDInfo(function(err, memberID){
            if(memberID.id.member_id !== null) {
                bMemberInfo = 1;
                sMemberId = memberID.id.member_id.toString();
            } else if(memberID.id.guest_id !== null){
                bMemberInfo = 1;
                sMemberId = memberID.id.guest_id.toString();
                bGuest = 1;
            } else {
                return false;
            }
        });

        return sMemberId;
    }

    if (window.location !== window.parent.location) {
        return false;
    }

    return initDummyPGSDK();
});
