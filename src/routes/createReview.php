<?php

$app->post('/api/Goodreads/createReview', function ($request, $response) {

    $settings = $this->settings;
    $checkRequest = $this->validation;
    $validateRes = $checkRequest->validate($request, ['apiKey','apiSecret','accessToken','accessTokenSecret','bookId']);

    if(!empty($validateRes) && isset($validateRes['callback']) && $validateRes['callback']=='error') {
        return $response->withHeader('Content-type', 'application/json')->withStatus(200)->withJson($validateRes);
    } else {
        $post_data = $validateRes;
    }

    $requiredParams = ['apiKey'=>'key','apiSecret'=>'secret','accessToken'=>'token','accessTokenSecret'=>'tokenSecret','bookId'=>'book_id'];
    $optionalParams = ['textReview'=>'review[review]','reviewRating'=>'review[rating]','reviewDate'=>'review[read_at]','shelf'=>'shelf'];
    $bodyParams = [
       'query' => ['book_id','review[review]','review[rating]','review[read_at]','shelf']
    ];

    $data = \Models\Params::createParams($requiredParams, $optionalParams, $post_data['args']);

    


    $query_str = "https://www.goodreads.com/review.xml";

    

    $requestParams = \Models\Params::createRequestBody($data, $bodyParams);


    $stack = GuzzleHttp\HandlerStack::create();
    $middleware = new GuzzleHttp\Subscriber\Oauth\Oauth1([
        'consumer_key'    => $data['key'],
        'consumer_secret' => $data['secret'],
        'token'           => $data['token'],
        'token_secret'    => $data['tokenSecret']
    ]);
    $stack->push($middleware);
    $client = new GuzzleHttp\Client([
        'handler' => $stack,
        'auth' => 'oauth'
    ]);



    try {
        $resp = $client->post($query_str, $requestParams);
        $responseBody = $resp->getBody()->getContents();


        if(in_array($resp->getStatusCode(), ['200', '201', '202', '203', '204'])) {
            $result['callback'] = 'success';
            libxml_use_internal_errors(true);
            $responseBody =  simplexml_load_string($responseBody);
            if($responseBody)
            {
                $responseBody = json_decode(json_encode((array) $responseBody), 1);
            }


            $result['contextWrites']['to'] = is_array($responseBody) ? $responseBody : json_decode($responseBody);
            if(empty($result['contextWrites']['to'])) {
                $result['callback'] = 'error';
                $result['contextWrites']['to']['status_code'] = 'API_ERROR';
                $result['contextWrites']['to']['status_msg'] = "Wrong params.";
            }
        } else {
            $result['callback'] = 'error';
            $result['contextWrites']['to']['status_code'] = 'API_ERROR';
            $result['contextWrites']['to']['status_msg'] = json_decode($responseBody);
        }

    } catch (\GuzzleHttp\Exception\ClientException $exception) {

        $responseBody = $exception->getResponse()->getBody()->getContents();


        if(empty(json_decode($responseBody))) {
            $out = $responseBody;
        } else {
            $out = json_decode($responseBody);
        }
        $result['callback'] = 'error';
        $result['contextWrites']['to']['status_code'] = 'API_ERROR';
        $out = \Models\normilizeJson::normalizeJsonErrorResponse($responseBody);
        $result['contextWrites']['to']['status_msg'] = $out;

    } catch (GuzzleHttp\Exception\ServerException $exception) {

        $responseBody = $exception->getResponse()->getBody()->getContents();
        if(empty(json_decode($responseBody))) {
            $out = $responseBody;
        } else {
            $out = json_decode($responseBody);
        }
        $result['callback'] = 'error';
        $result['contextWrites']['to']['status_code'] = 'API_ERROR';
        $result['contextWrites']['to']['status_msg'] = $out;

    } catch (GuzzleHttp\Exception\ConnectException $exception) {
        $result['callback'] = 'error';
        $result['contextWrites']['to']['status_code'] = 'INTERNAL_PACKAGE_ERROR';
        $result['contextWrites']['to']['status_msg'] = 'Something went wrong inside the package.';

    }



    return $response->withHeader('Content-type', 'application/json')->withStatus(200)->withJson($result);

});