<?php

namespace App;

use Illuminate\Support\Facades\Storage;

class JSSDK
{
    private $appId;
    private $appSecret;

    public function __construct($appId, $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
    }

    /**
     * 获取微信Access_Token
     * @return access_token字符串
     */

    public function getAccessToken()
    {
        $file = 'weixin/access_token.json';
        $exists = '0';
        if (Storage::exists($file) && Storage::size($file) > 0) {
            $exists = '1';
            $data = \GuzzleHttp\json_decode(Storage::get($file));
        }
        if ($exists == '0' || $data->expire_time < time()) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
            $res = json_decode($this->httpGet($url));
            $access_token = $res->access_token;
            if ($access_token) {
                $data = array();
                $data['expire_time'] = time() + 7000;
                $data['access_token'] = $access_token;
                Storage::put($file, json_encode($data));
            }
        } else {
            $access_token = $data->access_token;
        }
        return $access_token;
    }

    private function httpGet($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    /**
     * 获取CODE
     * @param $redirect_uri
     * @param $scope (snsapi_base不弹出授权页面，只能获得OpenId;snsapi_userinfo弹出授权页面)
     * @param $state
     * @return 授权地址
    */

    public function getCode($redirect_uri, $scope = 'snsapi_base', $state = 1)
    {
        $redirect_uri = urlencode($redirect_uri);
        //返回类型，请填写code
        $response_type = 'code';
        //构造请求微信接口的URL
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->appId . '&redirect_uri=' . $redirect_uri . '&response_type=' . $response_type . '&scope=' . $scope . '&state=' . $state . '#wechat_redirect';
        return $url;
    }

    /**
     * 通过code换取网页授权access_token
     * @param $code
     * @return Array(access_token, expires_in, refresh_token, openid, scope)
    */

    public function getAccessTokenAndOpenId($code){
        //填写为authorization_code
        $grant_type = 'authorization_code';
        //构造请求微信接口的URL
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this->appId.'&secret='.$this->appSecret.'&code='.$code.'&grant_type='.$grant_type.'';
        //请求微信接口, Array(access_token, expires_in, refresh_token, openid, scope)
        return json_decode($this->httpGet($url));
    }

    /**
     * 刷新access_token
     * 由于access_token拥有较短的有效期，当access_token超时后，可以使用refresh_token进行刷新，refresh_token拥有较长的有效期（7天、30天、60天、90天），当refresh_token失效的后，需要用户重新授权。
     * @param $refreshToken
     * @return array(
                    "access_token"=>"网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同",
                    "expires_in"=>access_token接口调用凭证超时时间，单位（秒）,
                    "refresh_token"=>"用户刷新access_token",
                    "openid"=>"用户唯一标识",
                    "scope"=>"用户授权的作用域，使用逗号（,）分隔")
     */
    public function refreshToken($refreshToken){
        $url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.$this->appId.'&grant_type=refresh_token&refresh_token='.$refreshToken;
        return json_decode($this->httpGet($url));
    }


    /**
     * 检验授权凭证（access_token）是否有效
     * @param $accessToken
     * @param $openId
     * @return array("errcode"=>0,"errmsg"=>"ok")
     */
    public function checkAccessToken($accessToken, $openId){
        $url = 'https://api.weixin.qq.com/sns/auth?access_token='.$accessToken.'&openid='.$openId;
        return json_decode($this->httpGet($url));
    }

    /**
     * 拉取用户信息(需scope为 snsapi_userinfo)
     * @param $accessToken
     * @param $openId
     * @return array("openid"=>"用户的唯一标识",
                    "nickname"=>'用户昵称',
                    "sex"=>"1是男，2是女，0是未知",
                    "province"=>"用户个人资料填写的省份"
                    "city"=>"普通用户个人资料填写的城市",
                    "country"=>"国家，如中国为CN",
                    //户头像，最后一个数值代表正方形头像大小（有0、46、64、96、132数值可选，0代表640*640正方形头像），用户没有头像时该项为空
                    "headimgurl"=>"http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/46",
                    //用户特权信息，json 数组，如微信沃卡用户为chinaunicom
                    "privilege"=>array("PRIVILEGE1", "PRIVILEGE2"),
    );
    */
    public function getUserInfo($accessToken, $openId){
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='. $accessToken . '&openid='. $openId .'&lang=zh_CN';
        return json_decode($this->httpGet($url));
    }


}
