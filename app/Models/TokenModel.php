<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The TokenModel handles the database related functionality of the oAuth data
 * Note: The methods defined in this class are for demo purposes only, please make sure your database operations are foolproof.
 */
class TokenModel
{
    private $sModule;

    public function __construct($sModule)
    {
        $this->sModule = $sModule;
    }

    public static function getAccessToken($sMallId)
    {
        $aSession = session()->get($sMallId);

        // Mock access token
        if ($aSession === null || isset($aSession['token']) === false) {
            return [
                "access_token" => "fZpRgUNlUKuFHBS1USv4cC",
                "expires_at" => "2022-07-06T14:15:56.000",
                "refresh_token" => "oKc4j9zjPNpghP2PPerJ5B",
                "refresh_token_expires_at" => "2022-07-20T12:15:56.000",
                "client_id" => "iSCr50CoPxDpsmTLgede0A",
                "mall_id" => "ectmtjpq001",
                "user_id" => "ectmtjpq001",
                "scopes" => [
                    0 => "mall.read_application",
                    1 => "mall.write_application",
                    2 => "mall.read_store",
                    3 => "mall.write_store",
                ],
                "issued_at" => "2022-07-06T12:15:57.000",
                "shop_no" => "1",
            ];
        }

        $oToken = $aSession['token'];
        return json_decode(json_encode($oToken,true),true);
    }

    public static function storeAccessToken($sMallId, $oToken)
    {
        // We suggest caching the token data in a storage like Redis instead of storing it solely in the session browser.
        session()->put($sMallId . '.token', $oToken);
        session()->save();
    }
}
