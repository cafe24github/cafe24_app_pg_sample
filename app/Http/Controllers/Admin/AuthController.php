<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenModel;
use App\Utilities\Cafe24Utility;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

/**
 * Handles the authentication process when a user (admin) opens the PG App's settings page.
 * For the Cafe24 API authentication & authorization process, read here https://developers.cafe24.com/app/front/develop/oauth/process
 */
class AuthController extends Controller
{
    /**
     * @var Request
     */
    private $oRequest;

    public function __construct(Request $oRequest)
    {
        $this->oRequest = $oRequest;
    }

    /**
     * Checks the query parameters in the endpoint provided by the Cafe24 oAuth and redirect the admin/merchant to the Cafe24 Authorization endpoint.
     * @return RedirectResponse|Redirector
     */
    public function checkAuth()
    {
        $aRequest = $this->oRequest->all();

        // Required query parameters for authentication
        $sMallId = $aRequest['mall_id'] ?? null;
        $sUserId = $aRequest['user_id'] ?? null;
        $sUserType = $aRequest['user_type'] ?? null;
        $sNation = $aRequest['nation'] ?? null;

        // Check if required parameters are in the request; if parameters are not complete, redirect to the Forbidden Page
        if ($sMallId === null || $sUserId === null || $sUserType === null || $sNation === null) {
            session()->flush();
            session()->save();
            abort(403);
        }

        // Check if user type is valid; if admin user type is not valid, redirect to the Forbidden Page
        // P: Representative Operator
        // A: Sub-operator
        if (in_array(strtoupper($sUserType), ['P', 'A']) === false) {
            session()->flush();
            session()->save();
            abort(403);
        }

        // Check if session already exist, redirect to admin display if true
        $bMallSessionExist = session()->has($sMallId) || (
                session()->has($sMallId . '.mall_id') &&
                session()->has($sMallId . '.user_id') &&
                session()->has($sMallId . '.user_type') &&
                session()->has($sMallId . '.nation')
            );
        if ($bMallSessionExist === true) {
            return redirect('/admin/display?mall_id=' . $sMallId);
        }

        // Save session
        $sKey = $aRequest['mall_id'];
        $aSessionInfo = [
            'mall_id'   => $sMallId,
            'user_id'   => $aRequest['user_id'],
            'user_type' => $aRequest['user_type'],
            'nation'    => $aRequest['nation']
        ];
        session()->put($sKey, $aSessionInfo);
        session()->save();

        // Create auth url to get the authentication code from Cafe24
        // Read more: https://developers.cafe24.com/docs/en/api/admin/#get-authentication-code
        $sAuthUrl = sprintf('https://%s.cafe24api.com/api/v2/oauth/authorize?', $sMallId);
        $aCallData = [
            'response_type' => 'code',
            'client_id'     => env('APP_CLIENT_ID'),
            // The state is used when the redirect_uri is accessed by the oAuth process
            'state'         => base64_encode(json_encode(['mall_id' => $sMallId])),
            'redirect_uri'  => env('APP_URL') . '/auth/token',
            'scope'         => env('APP_SCOPE')
        ];

        // Redirect to the authorization endpoint to request for an authorization code from Cafe24.
        // return redirect($sAuthUrl . http_build_query($aCallData));

        // For the sake of the Postman demo request, the auth URL is mocked.
        return 'https://demomall.cafe24api.com/api/v2/oauth/authorize?response_type=code&client_id=mock_client_id&state=eyJtYWxsX2lkIjoiZWN0bXRqcHEwMDEifQ%3D%3D&redirect_uri=https%3A%2F%2Fpg-demo-app.local.com%2Fauth%2Ftoken&scope=mall.read_application%2Cmall.write_application%2Cmall.read_store%2Cmall.write_store';
    }

    /**
     * Handles the process after the admin/merchant is given authorization and requests for an access token from Cafe24.
     * @return RedirectResponse|Redirector
     */
    public function handleCallback()
    {
        $aRequest = $this->oRequest->all();

        // Validate request parameters to make sure the "state" the app provided is present and an authorization code is issued by Cafe24
        if (array_key_exists('code', $aRequest) && array_key_exists('state', $aRequest)) {
            $oState = json_decode(base64_decode($aRequest['state']));
            $sMallId = $oState->mall_id;
            $sSessionKey = $sMallId;
        } else {
            session()->flush();
            session()->save();
            abort(403);
        }

        try {
            // Request for an access token from Cafe24 using the issued authorization code.
            $aTokenResult = Cafe24Utility::getAccessToken($aRequest['code'], $sMallId);
            // If the request failed, return a 403 result
            if (array_key_exists('mData', $aTokenResult) === false) {
                session()->forget($sSessionKey);
                session()->save();
                abort(403);
            }

            // Validate and store the issued access token
            $aToken = json_decode($aTokenResult['mData'], true);
            if ($sMallId !== $aToken['mall_id']) {
                session()->forget($sSessionKey);
                session()->save();
                abort(403);
            }
            TokenModel::storeAccessToken($sMallId, $aToken);

            // Once the process is successful, the admin/merchant is redirected to the admin settings page of the app
            return redirect('/admin/display?mall_id=' . $sMallId);
        } catch (Exception $oException)  {
            // If any exception occurs in the request, the request is aborted
            abort (500, 'Something went wrong.');
        }
    }
}
