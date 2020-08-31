<?php

class Cubit {
    private $registry;

    public function __construct($registry) {
        $this->registry = $registry;
    }

    public function getPaypalAccessToken($client_id = '', $client_secret = '', $sandox = 0) {
        if ($sandox) {
            $url = 'https://api.sandbox.paypal.com/v1/oauth2/token';
        } else {
            $url = 'https://api.paypal.com/v1/oauth2/token';
        }

        $headers = array(
            ['Authorization', 'Basic ' . base64_encode($client_id . ':' . $client_secret)],
            ['Content-type', 'application/x-www-form-urlencoded'],
            ['Accept', 'application/json'],
        );

        $params = array(
            'grant_type' => 'client_credentials',
        );

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $curl_headers = array();

        foreach ($headers as $header) {
            $curl_headers[] = implode(': ', $header);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_headers);

        $response = trim(curl_exec($curl));
        $response_info = curl_getinfo($curl);
        $response_error = curl_errno($curl);

        curl_close($curl);

        $access_token = '';

        if (!$response_error) {
            if ($response_info['http_code'] == 200) {
                $response_json = json_decode($response, true);

                if (isset($response_json['access_token'])) {
                    $access_token = $response_json['access_token'];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } else {
            throw new Exception(ERROR_CURL . ': ' . $response_error);
        }

        return $access_token;
    }

    public function sendRequest($method = 'POST', $request_url = '', $request_headers = array(), $params = array()) {
        $curl = curl_init($request_url);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

        if ($method == 'POST' || $method == 'PUT' || $method == 'PATCH') {
            curl_setopt($curl, CURLOPT_POST, true);

            if ($params) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
            }
        } else {
            if ($method == 'DELETE') {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            }
        }

        $response_body = trim(curl_exec($curl));
        $response_info = curl_getinfo($curl);
        $response_error = curl_errno($curl);

        curl_close($curl);

        if (!$response_error) {
            return array(
                'status' => $response_info['http_code'],
                'body' => $response_body
            );
        } elseif (!$response_error) {
            throw new Exception(ERROR_PAYPAL);
        } else {
            throw new Exception(ERROR_CURL . ': ' . $response_error);
        }

        return false;
    }

    public function sendPaypalRequest($method = 'POST', $request_url = '', $access_token, $custom_headers = array(), $params = array()) {
        $request_headers = array(
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
            'Content-type: application/json',
            'Content-Length: ' . ($params ? strlen(json_encode($params)) : '')
        );

        foreach ($custom_headers as $header) {
            $request_headers[] = implode(': ', $header);
        }

        $response =  $this->sendRequest($method, $request_url, $request_headers, $params);

        $response['body'] = json_decode($response['body'] , true);
        
        return $response;
    }

    public function getMembership() {
        $customer_id = $this->registry->get('customer')->getId();

        $sql = "SELECT * FROM " . DB_PREFIX . "cubit_membership WHERE `customer_id`='" . $customer_id . "' and display=1 ORDER by date_added DESC LIMIT 1";

        $result = $this->registry->get('db')->query($sql);

        if ($result->rows) {
            return $result->row;
        }

        return false;
    }
}