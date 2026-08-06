<?php
/**
 * Copyright (c) 2014 Team TamedBitches.
 * Written by Chuck JS. Oh <jinseokoh@hotmail.com>
 * http://facebook.com/chuckoh
 *
 * Date: 11 10, 2014
 * Time: 01:51 AM
 *
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://www.wtfpl.net/txt/copying/ for more details. 
 *
 */

//https://github.com/jinseokoh/additional-providers
class Hybrid_Providers_Kakao extends Hybrid_Provider_Model_OAuth2
{
    /**
    * initialization
    */
    function initialize()
    {
        parent::initialize();

        // Provider API end-points
        $this->api->api_base_url  = "https://kapi.kakao.com/v2/";
        $this->api->authorize_url = "https://kauth.kakao.com/oauth/authorize";
        $this->api->token_url     = "https://kauth.kakao.com/oauth/token";

		// redirect uri mismatches when authenticating with Kakao.
		if (isset($this->config['redirect_uri']) && !empty($this->config['redirect_uri'])) {
			$this->api->redirect_uri = $this->config['redirect_uri'];
		}
    }

    /**
    * finish login step
    */
    function loginFinish()
    {
        $this->validateOAuthState();

        $error = (array_key_exists('error', $_REQUEST)) ? $_REQUEST['error'] : "";
        // check for errors
        if ( $error ){
            throw new Exception( "Authentication failed! {$this->providerId} returned an error: $error", 5 );
        }
        // try to authenicate user
        $code = (array_key_exists('code', $_REQUEST)) ? $_REQUEST['code'] : "";
        try{
            $this->authenticate( $code );
        }
        catch( Exception $e ){
            error_log('[Kakao token] ' . $e->getMessage());
            throw new Exception(
                "Kakao token request failed: " . $e->getMessage(),
                5,
                $e
            );
        }
        // check if authenticated
        if ( ! $this->api->access_token ){
            throw new Exception( "Authentication failed! {$this->providerId} returned an invalid access token.", 5 );
        }
        // store tokens
        $this->token("access_token",  $this->api->access_token);
        $this->token("refresh_token", $this->api->refresh_token);
        $this->token("expires_in",    $this->api->access_token_expires_in);
        $this->token("expires_at",    $this->api->access_token_expires_at);
        // set user connected locally
        $this->setUserConnected();
    }

    /**
    * load the user profile
    */
    function getUserProfile()
    {
        // OAuth2Client::api() always adds access_token to the request parameters.
        // Kakao's current API expects it in the Authorization header, so call the
        // endpoint directly to avoid sending the token twice.
        $this->api->curl_header = array(
            'Authorization: Bearer ' . $this->api->access_token,
            'Accept: application/json'
        );

        $response = $this->request($this->api->api_base_url . 'user/me');
        $data = is_string($response) ? json_decode($response) : null;

        if ( ! is_object($data) || ! isset($data->id) ) {
            $kakao_error = is_object($data) ? $data : new StdClass();
            $error_code = isset($kakao_error->code) ? $kakao_error->code : 'invalid_json';
            $error_message = isset($kakao_error->msg) ? $kakao_error->msg : 'Invalid response';
            $kakao_debug = 'HTTP ' . (int)$this->http_code . ', code ' . $error_code . ': ' . $error_message;
            error_log('[Kakao user/me] ' . $kakao_debug);
            if (class_exists('Hybrid_Logger')) Hybrid_Logger::error('Kakao user/me ' . $kakao_debug);
            throw new Exception("Kakao user profile request failed: " . $kakao_debug, 6);
        }

        $account = (isset($data->kakao_account) && is_object($data->kakao_account))
            ? $data->kakao_account : new StdClass();
        $profile = (isset($account->profile) && is_object($account->profile))
            ? $account->profile : new StdClass();
        $properties = (isset($data->properties) && is_object($data->properties))
            ? $data->properties : new StdClass();

        # store the user profile.
        $this->user->profile->identifier = (string)$data->id;
        $this->user->profile->displayName = !empty($profile->nickname)
            ? $profile->nickname
            : (!empty($properties->nickname) ? $properties->nickname : 'Kakao User');
        $this->user->profile->photoURL = !empty($profile->thumbnail_image_url)
            ? $profile->thumbnail_image_url
            : (!empty($properties->thumbnail_image) ? $properties->thumbnail_image : '');

        $email = isset($account->email) ? $account->email : '';

        if( $email ){
            $this->user->profile->email = $email;
            if ( !empty($account->is_email_verified) ) {
                $this->user->profile->emailVerified = $email;
            }
        }

        $this->user->profile->sid         = get_social_convert_id( $this->user->profile->identifier, $this->providerId );

        return $this->user->profile;
    }

    private function authenticate($code)
    {
        $params = array(
            "grant_type"    => "authorization_code",
            "client_id"     => $this->api->client_id,
            "redirect_uri"  => $this->api->redirect_uri,
            "code"          => $code
        );

        if( $this->api->client_secret && ($this->api->client_secret !== $this->api->client_id) ){
            $params['client_secret'] = $this->api->client_secret;
        }

        $response = $this->request($this->api->token_url, $params, $this->api->curl_authenticate_method);
        $response = $this->parseRequestResult($response);
        if ( ! $response || ! isset($response->access_token) ) {
            // $response 가 객체가 아니거나 error 필드가 없을 수 있으므로 방어적으로 메시지를 구성한다.
            $err = 'unknown_error';
            if ( is_object($response) ) {
                if ( isset($response->error_description) )      $err = $response->error_description;
                elseif ( isset($response->error) )             $err = is_scalar($response->error) ? $response->error : json_encode($response->error);
            } elseif ( is_string($response) && $response !== '' ) {
                $err = $response;
            }
            throw new Exception("The Authorization Service has return: " . $err);
        }
        if ( isset($response->access_token) )  $this->api->access_token            = $response->access_token;
        if ( isset($response->refresh_token) ) $this->api->refresh_token           = $response->refresh_token;
        if ( isset($response->expires_in) )    $this->api->access_token_expires_in = $response->expires_in;

        // calculate when the access token expire
        if ( isset($response->expires_in) ) {
            $this->api->access_token_expires_at = time() + $response->expires_in;
        }

        return $response;
    }

    private function request($url, $params=false, $type="GET")
    {
        if(Class_exists('Hybrid_Logger')){
            Hybrid_Logger::info("Enter OAuth2Client::request( $url )");
        }
        $this->http_info = array();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL           , $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT       , $this->api->curl_time_out);
        curl_setopt($ch, CURLOPT_USERAGENT     , $this->api->curl_useragent);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->api->curl_connect_time_out);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->api->curl_ssl_verifypeer);
        curl_setopt($ch, CURLOPT_HTTPHEADER    , $this->api->curl_header);

        if ( $this->api->curl_proxy ) {
            curl_setopt( $ch, CURLOPT_PROXY, $this->api->curl_proxy);
        }
        if ( $type == "POST" ) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($params) curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($params) );
        }

        $response = curl_exec($ch);
        $curl_error = ($response === false) ? curl_error($ch) : '';
        if(Class_exists('Hybrid_Logger')){
            Hybrid_Logger::debug( "OAuth2Client::request(). dump request info: ", serialize(curl_getinfo($ch)) );
        }
        $this->http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->http_info = array_merge($this->http_info, curl_getinfo($ch));
        curl_close ($ch);

        if ( $response === false ) {
            error_log('[Kakao HTTP] cURL error: ' . $curl_error);
            if (Class_exists('Hybrid_Logger')) {
                Hybrid_Logger::error('Kakao HTTP cURL error: ' . $curl_error);
            }
        }

        return $response;
    }

    private function parseRequestResult($result)
    {
        if ( json_decode($result) ) return json_decode($result);
        parse_str( $result, $ouput );
        $result = new StdClass();
        foreach( $ouput as $k => $v )
            $result->$k = $v;

        return $result;
    }
}
